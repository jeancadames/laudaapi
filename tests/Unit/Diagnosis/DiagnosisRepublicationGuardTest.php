<?php

namespace Tests\Unit\Diagnosis;

use App\Http\Requests\Diagnosis\PublishDiagnosisResultRequest;
use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisDetailedRoadmap;
use App\Models\DiagnosisExpandedReport;
use App\Models\TransformationImplementationPlan;
use App\Models\User;
use App\Services\Diagnosis\DiagnosisAssessmentCompletenessService;
use App\Services\Diagnosis\DiagnosisBusinessProfileService;
use App\Services\Diagnosis\DiagnosisDeliverableValidationService;
use App\Services\Diagnosis\DiagnosisFreeDeliverablesOrchestrator;
use App\Services\Diagnosis\DiagnosisResultPublisher;
use Closure;
use Fiber;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Throwable;

/** Real publisher/Eloquent and existing closure/profile gates; PHP rows only, no app boot/PDO. */
final class DiagnosisRepublicationGuardTest extends TestCase
{
    private array $previous;
    private array $modelState;
    private RepublicationMemory $db;
    private Container $app;

    protected function setUp(): void
    {
        $this->previous = [Container::getInstance(), Facade::getFacadeApplication(),
            (new ReflectionProperty(Facade::class, 'resolvedInstance'))->getValue(),
            Model::getConnectionResolver(), Model::getEventDispatcher()];
        foreach (['booted', 'bootedCallbacks', 'globalScopes'] as $name) {
            $this->modelState[$name] = (new ReflectionProperty(Model::class, $name))->getValue();
        }
        $this->app = new class extends Container {
            public function runningInConsole(): bool { return true; }
        };
        Container::setInstance($this->app);
        Facade::setFacadeApplication($this->app);
        Facade::clearResolvedInstances();
        $this->app->instance('config', new Repository([
            'lauda360_business_profile' => require dirname(__DIR__, 3).'/config/lauda360_business_profile.php',
            'lauda360_diagnosis' => require dirname(__DIR__, 3).'/config/lauda360_diagnosis.php',
        ]));
        $this->app->instance('validator', new Factory(new Translator(new ArrayLoader, 'es'), $this->app));
        $this->db = new RepublicationMemory;
        $resolver = new ConnectionResolver(['pure' => $this->db]);
        $resolver->setDefaultConnection('pure');
        Model::setConnectionResolver($resolver);
        Model::unsetEventDispatcher();
        Model::clearBootedModels();
        DB::swap($this->db);
        Auth::swap(new class { public function id() { return null; } });
        $this->db->rows['diagnosis_assessments'][50] = [
            'id' => 50, 'user_id' => 20, 'organization_id' => 7, 'status' => 'reviewed', 'is_active' => 1,
            'organization_name' => 'Synthetic I03 fixture', 'review_summary' => 'Conclusión histórica suficiente para revisión y publicación.',
            'methodology_version' => '1.0',
            'answers' => json_encode(array_fill_keys((new \App\Services\Diagnosis\Lauda360ScoringService)->allQuestionIds(), 3)),
            'review_priorities' => json_encode(['Prioridad histórica']), 'published_at' => '2026-11-02 12:00:00',
            'reviewed_at' => '2026-11-02 12:00:00', 'reviewed_by_user_id' => 99,
            'dimension_scores' => json_encode(['strategy' => 50]), 'maturity_score' => 50,
            'business_activity_type' => 'services', 'business_sector' => 'professional_services',
            'customer_market' => 'b2b', 'sales_channels' => json_encode(['quotations']),
            'logistics_operation_types' => '[]', 'business_activity_description' => 'Servicios profesionales recurrentes para empresas.',
        ];
        $this->db->rows['users'] = [20 => ['id' => 20, 'role' => 'subscriber'], 99 => ['id' => 99, 'role' => 'admin']];
    }

    protected function tearDown(): void
    {
        try {
            self::assertFalse($this->db->pdoAttempted);
            self::assertSame([], $this->db->locks);
            self::assertSame([], $this->db->pending);
            foreach (['invoices', 'invoice_items', 'payments', 'subscriptions'] as $table) self::assertSame([], $this->db->rows[$table]);
        } finally {
            Container::setInstance($this->previous[0]);
            Facade::setFacadeApplication($this->previous[1]);
            (new ReflectionProperty(Facade::class, 'resolvedInstance'))->setValue(null, $this->previous[2]);
            $this->previous[3] ? Model::setConnectionResolver($this->previous[3]) : Model::unsetConnectionResolver();
            $this->previous[4] ? Model::setEventDispatcher($this->previous[4]) : Model::unsetEventDispatcher();
            foreach ($this->modelState as $name => $value) (new ReflectionProperty(Model::class, $name))->setValue(null, $value);
            parent::tearDown();
        }
    }

    private function assessment(): DiagnosisAssessment
    {
        return (new DiagnosisAssessment)->setRawAttributes($this->db->rows['diagnosis_assessments'][50], true);
    }

    private function admin(): User { return (new User(['role' => 'admin', 'name' => 'Synthetic Admin']))->forceFill(['id' => 99]); }

    private function changed(): array
    {
        return ['review_summary' => 'Conclusión actualizada que requiere conservar la coherencia de entregables.', 'review_priorities' => ['Prioridad actualizada']];
    }

    private function publisher(int $calls, bool $generate = false, bool $fail = false): DiagnosisResultPublisher
    {
        $orchestrator = $this->createMock(DiagnosisFreeDeliverablesOrchestrator::class);
        $orchestrator->expects(self::exactly($calls))->method('generateAndPresent')->willReturnCallback(function () use ($generate, $fail): array {
            if ($fail) throw new RuntimeException('Synthetic generation failure');
            if ($generate) $this->db->put('diagnosis_expanded_reports', 70, $this->dependency('report', 'published', '2026-11-03 00:00:00'));
            return ['expanded_report' => (new DiagnosisExpandedReport)->forceFill(['id' => 70]),
                'roadmap' => (new DiagnosisDetailedRoadmap)->forceFill(['id' => 80]),
                'implementation_plan' => (new TransformationImplementationPlan)->forceFill(['id' => 90])];
        });

        return new DiagnosisResultPublisher($orchestrator, new DiagnosisDeliverableValidationService,
            new DiagnosisAssessmentCompletenessService(new DiagnosisBusinessProfileService));
    }

    private function dependency(string $type, string $status = 'published', ?string $date = '2026-11-02 13:00:00'): array
    {
        return ['id' => $type === 'report' ? 70 : 80, 'diagnosis_assessment_id' => 50, 'version' => 1,
            'source_expanded_report_id' => $type === 'roadmap' ? 70 : null, 'status' => $status, 'published_at' => $date,
            'source_snapshot' => json_encode(['historical' => true]), 'sections' => json_encode(['original' => true]),
            'roadmap' => json_encode(['original' => true]), 'reviewed_at' => '2026-11-02 13:00:00'];
    }

    private function assertBlocked(array $payload): void
    {
        $before = $this->db->rows;
        try { $this->publisher(0)->publish($this->assessment(), $this->admin(), $payload); self::fail('Changed conclusions must be blocked'); }
        catch (ValidationException $e) {
            self::assertStringContainsString('nuevo ciclo de diagnóstico', $e->errors()['assessment'][0]);
            self::assertStringContainsString('no se creará automáticamente', $e->errors()['assessment'][0]);
        }
        self::assertSame($before, $this->db->rows);
        self::assertSame([], $this->db->writes);
    }

    public function test_initial_publication_and_republication_without_published_dependencies_keep_existing_flow(): void
    {
        $this->db->rows['diagnosis_assessments'][50]['status'] = 'submitted';
        $this->db->rows['diagnosis_assessments'][50]['published_at'] = null;
        $result = $this->publisher(1)->publish($this->assessment(), $this->admin(), $this->changed());
        self::assertSame('reviewed', $result->status);
        self::assertNotNull($result->published_at);
        $payload = $this->changed(); $payload['review_summary'] .= ' Segunda revisión.';
        $result = $this->publisher(1)->publish($this->assessment(), $this->admin(), $payload);
        self::assertSame($payload['review_summary'], $result->review_summary);
        self::assertCount(1, $this->db->rows['diagnosis_assessments']);
    }

    #[DataProvider('publishedCases')]
    public function test_every_publication_marker_and_old_version_blocks_without_partial_writes(string $type, string $status, ?string $date): void
    {
        $table = $type === 'report' ? 'diagnosis_expanded_reports' : 'diagnosis_detailed_roadmaps';
        $row = $this->dependency($type, $status, $date);
        $this->db->rows[$table][$row['id']] = $row;
        $this->db->rows[$table][$row['id'] + 1] = [...$row, 'id' => $row['id'] + 1, 'version' => 2, 'status' => 'draft', 'published_at' => null];
        $this->db->rows['diagnosis_deliverable_validations'][1] = ['id' => 1, 'deliverable_type' => $type === 'report' ? 'expanded_report' : 'detailed_roadmap', 'deliverable_id' => $row['id'], 'validated_at' => '2026-11-02 14:00:00'];
        $this->assertBlocked($this->changed());
        $locks = array_filter($this->db->reads, fn ($read) => str_contains($read[0], 'for update'));
        self::assertCount(3, $locks);
        foreach ($locks as $read) {
            self::assertStringNotContainsString('`status` = ?', $read[0]);
            self::assertStringNotContainsString('`published_at` is not null', $read[0]);
        }
    }

    public static function publishedCases(): array
    {
        return [['report', 'published', '2026-11-02 13:00:00'], ['roadmap', 'published', '2026-11-02 13:00:00'],
            ['report', 'published', null], ['roadmap', 'published', null],
            ['report', 'draft', '2026-11-02 13:00:00'], ['roadmap', 'under_review', '2026-11-02 13:00:00']];
    }

    public function test_roadmap_linked_through_report_is_protected_even_if_its_assessment_link_disagrees(): void
    {
        $this->db->rows['diagnosis_assessments'][51] = [...$this->db->rows['diagnosis_assessments'][50], 'id' => 51];
        $this->db->rows['diagnosis_expanded_reports'][70] = $this->dependency('report', 'draft', null);
        $this->db->rows['diagnosis_detailed_roadmaps'][80] = [...$this->dependency('roadmap'), 'diagnosis_assessment_id' => 51];
        $this->assertBlocked($this->changed());
        self::assertStringContainsString('source_expanded_report_id', $this->db->reads[2][0]);
    }

    public function test_other_assessment_deliverables_are_not_a_global_veto(): void
    {
        $this->db->rows['diagnosis_assessments'][51] = [...$this->db->rows['diagnosis_assessments'][50], 'id' => 51];
        $this->db->rows['diagnosis_expanded_reports'][70] = [...$this->dependency('report'), 'diagnosis_assessment_id' => 51];
        $this->db->rows['diagnosis_detailed_roadmaps'][80] = [...$this->dependency('roadmap'), 'diagnosis_assessment_id' => 51];
        $otherCycle = [$this->db->rows['diagnosis_assessments'][51], $this->db->rows['diagnosis_expanded_reports'], $this->db->rows['diagnosis_detailed_roadmaps']];
        self::assertSame($this->changed()['review_summary'], $this->publisher(1)->publish($this->assessment(), $this->admin(), $this->changed())->review_summary);
        self::assertSame($otherCycle, [$this->db->rows['diagnosis_assessments'][51], $this->db->rows['diagnosis_expanded_reports'], $this->db->rows['diagnosis_detailed_roadmaps']]);
    }

    public function test_summary_priority_and_priority_order_changes_are_all_protected(): void
    {
        $this->db->rows['diagnosis_assessments'][50]['review_priorities'] = json_encode(['Primera prioridad', 'Segunda prioridad']);
        $this->db->rows['diagnosis_expanded_reports'][70] = $this->dependency('report');
        $existing = ['review_summary' => $this->assessment()->review_summary, 'review_priorities' => $this->assessment()->review_priorities];
        foreach ([array_replace($existing, ['review_summary' => $this->changed()['review_summary']]),
            array_replace($existing, ['review_priorities' => ['Otra prioridad']]),
            array_replace($existing, ['review_priorities' => array_reverse($existing['review_priorities'])])] as $data) $this->assertBlocked($data);
    }

    public function test_unchanged_conclusions_remain_subject_to_existing_publication_contract(): void
    {
        $this->db->rows['diagnosis_expanded_reports'][70] = $this->dependency('report');
        $snapshot = $this->db->rows['diagnosis_expanded_reports'];
        $data = ['review_summary' => '  '.$this->assessment()->review_summary.'  ', 'review_priorities' => $this->assessment()->review_priorities];
        self::assertSame(trim($data['review_summary']), $this->publisher(1)->publish($this->assessment(), $this->admin(), $data)->review_summary);
        self::assertSame($snapshot, $this->db->rows['diagnosis_expanded_reports']);
    }

    public function test_validation_alone_is_not_publication_but_full_closure_still_applies(): void
    {
        $this->db->rows['diagnosis_expanded_reports'][70] = $this->dependency('report', 'draft', null);
        $this->db->rows['diagnosis_deliverable_validations'][1] = ['id' => 1, 'deliverable_type' => 'expanded_report', 'deliverable_id' => 70, 'validated_at' => '2026-11-02 14:00:00'];
        $this->publisher(1)->publish($this->assessment(), $this->admin(), $this->changed());
        self::assertSame('draft', $this->db->rows['diagnosis_expanded_reports'][70]['status']);
    }

    public function test_existing_full_validation_closure_still_blocks_even_without_publication_markers(): void
    {
        $this->db->rows['diagnosis_expanded_reports'][70] = $this->dependency('report', 'draft', null);
        $this->db->rows['diagnosis_detailed_roadmaps'][80] = $this->dependency('roadmap', 'draft', null);
        $this->db->rows['transformation_implementation_plans'][90] = ['id' => 90, 'diagnosis_assessment_id' => 50, 'version' => 1, 'status' => 'draft'];
        foreach (['expanded_report' => 70, 'detailed_roadmap' => 80, 'implementation_plan' => 90] as $type => $id) {
            $this->db->rows['diagnosis_deliverable_validations'][$id] = ['id' => $id, 'deliverable_type' => $type, 'deliverable_id' => $id, 'validated_at' => '2026-11-02 14:00:00'];
        }
        $before = $this->db->rows;
        try { $this->publisher(0)->publish($this->assessment(), $this->admin(), $this->changed()); self::fail('Closed cycle'); }
        catch (ValidationException $e) { self::assertStringContainsString('ciclo documental', $e->errors()['assessment'][0]); }
        self::assertSame($before, $this->db->rows);
        self::assertSame([], $this->db->writes);
    }

    public function test_real_orchestrator_reuses_published_versions_without_regeneration(): void
    {
        $this->db->rows['diagnosis_expanded_reports'][70] = $this->dependency('report');
        $this->db->rows['diagnosis_detailed_roadmaps'][80] = $this->dependency('roadmap');
        $reports = $this->createMock(\App\Services\Diagnosis\DiagnosisExpandedReportService::class);
        $reports->expects(self::never())->method('createOrGetDraft');
        $reports->expects(self::never())->method('publish');
        $reports->expects(self::never())->method('regenerateDraft');
        $roadmaps = $this->createMock(\App\Services\Diagnosis\DiagnosisDetailedRoadmapService::class);
        $roadmaps->expects(self::never())->method('createOrGetDraft');
        $roadmaps->expects(self::never())->method('publish');
        $roadmaps->expects(self::never())->method('regenerateDraft');
        $plans = $this->createMock(\App\Services\Diagnosis\TransformationImplementationPlanService::class);
        $plans->expects(self::once())->method('latestForAssessment')->willReturn(
            (new TransformationImplementationPlan)->forceFill(['id' => 90, 'status' => 'presented'])
        );
        $plans->expects(self::never())->method('createDraftFromPublishedRoadmap');
        $plans->expects(self::never())->method('markPresented');
        $autogenerator = $this->createMock(\App\Services\Diagnosis\TransformationImplementationPlanAutogenerator::class);
        $autogenerator->expects(self::never())->method('generate');
        $orchestrator = new DiagnosisFreeDeliverablesOrchestrator($reports, $roadmaps, $plans, $autogenerator);
        $publisher = new DiagnosisResultPublisher($orchestrator, new DiagnosisDeliverableValidationService,
            new DiagnosisAssessmentCompletenessService(new DiagnosisBusinessProfileService));
        $before = [$this->db->rows['diagnosis_expanded_reports'], $this->db->rows['diagnosis_detailed_roadmaps']];
        $publisher->publish($this->assessment(), $this->admin(), [
            'review_summary' => $this->assessment()->review_summary,
            'review_priorities' => $this->assessment()->review_priorities,
        ]);
        self::assertSame($before, [$this->db->rows['diagnosis_expanded_reports'], $this->db->rows['diagnosis_detailed_roadmaps']]);
        self::assertCount(1, $this->db->rows['diagnosis_assessments']);
    }

    public function test_existing_profile_gate_and_transaction_rollback_are_preserved(): void
    {
        $before = $this->db->rows;
        try { $this->publisher(1, fail: true)->publish($this->assessment(), $this->admin(), $this->changed()); self::fail('Expected downstream failure'); }
        catch (RuntimeException $e) { self::assertSame('Synthetic generation failure', $e->getMessage()); }
        self::assertSame($before, $this->db->rows);
        $this->db->rows['diagnosis_assessments'][50]['business_activity_description'] = '';
        $this->expectException(ValidationException::class);
        $this->publisher(0)->publish($this->assessment(), $this->admin(), $this->changed());
    }

    public function test_unauthorized_user_is_rejected_before_any_database_access(): void
    {
        $user = new User(['role' => 'subscriber']);
        $request = new PublishDiagnosisResultRequest;
        self::assertFalse($request->authorize());
        $request->setUserResolver(fn () => $user);
        self::assertFalse($request->authorize());
        $request->setUserResolver(fn () => $this->admin());
        self::assertTrue($request->authorize());
        try { $this->publisher(0)->publish($this->assessment(), $user, $this->changed()); self::fail('Unauthorized publish'); }
        catch (AuthorizationException) { self::assertSame([], $this->db->reads); self::assertSame([], $this->db->writes); }
    }

    public function test_concurrent_publishers_reload_under_parent_lock_and_second_cannot_bypass_new_dependencies(): void
    {
        $stale = $this->assessment();
        $firstPublisher = $this->publisher(1, generate: true);
        $secondPublisher = $this->publisher(0);
        $paused = false;
        $this->db->afterRead = function ($query) use (&$paused): void {
            if (! $paused && str_contains($query, 'from `diagnosis_assessments`') && str_contains($query, 'for update')) {
                $paused = true; Fiber::suspend('first-holds-assessment');
            }
        };
        $first = new Fiber(fn () => $firstPublisher->publish($stale, $this->admin(), $this->changed()));
        // A stale browser re-sends the old conclusions after another publisher
        // commits new conclusions and dependent publication. Comparing the
        // route-bound stale model would incorrectly report "no changes".
        $data = ['review_summary' => $stale->review_summary, 'review_priorities' => $stale->review_priorities];
        $second = new Fiber(function () use ($secondPublisher, $stale, $data): string {
            try { $secondPublisher->publish($stale, $this->admin(), $data); return 'BYPASS'; }
            catch (ValidationException) { return 'BLOCKED'; }
        });
        self::assertSame('first-holds-assessment', $first->start());
        self::assertSame('wait:diagnosis_assessments:50', $second->start());
        $first->resume(); self::assertTrue($first->isTerminated());
        $committed = $this->db->rows;
        $second->resume(); self::assertSame('BLOCKED', $second->getReturn());
        self::assertSame($committed, $this->db->rows);
    }

    #[DataProvider('concurrentDependencyTypes')]
    public function test_dependency_publication_during_lock_wait_is_seen_as_current_read(string $type, string $table, int $id): void
    {
        $this->db->rows[$table][$id] = $this->dependency($type, 'draft', null);
        $writer = new Fiber(function () use ($type, $table, $id): void {
            $this->db->transaction(function () use ($type, $table, $id): void {
                $this->db->acquire($table.':'.$id);
                $this->db->put($table, $id, $this->dependency($type));
                Fiber::suspend('dependency-not-committed');
            });
        });
        $publisher = $this->publisher(0);
        $reader = new Fiber(function () use ($publisher): string {
            try { $publisher->publish($this->assessment(), $this->admin(), $this->changed()); return 'BYPASS'; }
            catch (ValidationException) { return 'BLOCKED'; }
        });
        self::assertSame('dependency-not-committed', $writer->start());
        self::assertSame('wait:'.$table.':'.$id, $reader->start());
        $writer->resume(); $committed = $this->db->rows;
        $reader->resume(); self::assertSame('BLOCKED', $reader->getReturn());
        self::assertSame($committed, $this->db->rows);
    }

    public static function concurrentDependencyTypes(): array
    {
        return [['report', 'diagnosis_expanded_reports', 70], ['roadmap', 'diagnosis_detailed_roadmaps', 80]];
    }
}

/** Transactional row/lock simulation for interleavings; no SQL is sent to any engine. */
final class RepublicationMemory extends MySqlConnection
{
    public array $rows = [];
    public array $pending = [];
    public array $locks = [];
    public array $reads = [];
    public array $writes = [];
    public bool $pdoAttempted = false;
    public ?Closure $afterRead = null;
    private array $depth = [];

    public function __construct()
    {
        parent::__construct(function () { $this->pdoAttempted = true; throw new LogicException('PDO prohibited'); });
        foreach (['diagnosis_assessments', 'diagnosis_expanded_reports', 'diagnosis_detailed_roadmaps', 'transformation_implementation_plans',
            'diagnosis_deliverable_validations', 'diagnosis_access_requests', 'users', 'audit_logs', 'invoices', 'invoice_items', 'payments', 'subscriptions'] as $table) $this->rows[$table] = [];
    }

    private function owner(): string { return Fiber::getCurrent() ? (string) spl_object_id(Fiber::getCurrent()) : 'main'; }

    public function acquire(string $key): void
    {
        $owner = $this->owner();
        if (! ($this->depth[$owner] ?? 0)) throw new LogicException('Lock outside transaction');
        while (isset($this->locks[$key]) && $this->locks[$key] !== $owner) {
            if (! Fiber::getCurrent()) throw new LogicException('Unexpected blocking main QA thread');
            Fiber::suspend('wait:'.$key);
        }
        $this->locks[$key] = $owner;
    }

    private function visible(): array
    {
        $rows = $this->rows;
        foreach ($this->pending[$this->owner()] ?? [] as $table => $updates) foreach ($updates as $id => $row) $rows[$table][$id] = $row;
        return $rows;
    }

    public function put(string $table, int $id, array $row): void
    {
        if (! in_array($table, ['diagnosis_assessments', 'diagnosis_expanded_reports', 'diagnosis_detailed_roadmaps', 'audit_logs'], true)) throw new LogicException('Unapproved mutation');
        $this->acquire($table.':'.$id);
        $this->pending[$this->owner()][$table][$id] = $row;
        $this->writes[] = [$this->owner(), $table, $id];
    }

    public function transaction(Closure $callback, $attempts = 1)
    {
        $owner = $this->owner(); $this->depth[$owner] = ($this->depth[$owner] ?? 0) + 1;
        try {
            $result = $callback($this);
            if ($this->depth[$owner] === 1) foreach ($this->pending[$owner] ?? [] as $table => $updates) foreach ($updates as $id => $row) $this->rows[$table][$id] = $row;
            return $result;
        } finally {
            if (--$this->depth[$owner] === 0) {
                unset($this->pending[$owner], $this->depth[$owner]);
                foreach ($this->locks as $key => $holder) if ($holder === $owner) unset($this->locks[$key]);
            }
        }
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
        $this->reads[] = [$query, $bindings];
        if (! preg_match('/from `([a-z_]+)`/', $query, $match)) throw new LogicException('Unexpected read: '.$query);
        $table = $match[1];
        if (! array_key_exists($table, $this->rows) || in_array($table, ['invoices', 'invoice_items', 'payments', 'subscriptions'], true)) throw new LogicException('Unapproved read');
        $filter = function () use ($table, $query, $bindings): array {
            $rows = array_values($this->visible()[$table]);
            if ($table === 'diagnosis_access_requests' || ($table === 'diagnosis_assessments' && str_contains($query, '0 = 1'))) return [];
            if ($table === 'diagnosis_assessments') $rows = array_values(array_filter($rows, fn ($row) => (int) $row['id'] === (int) ($bindings[0] ?? 0)));
            elseif ($table === 'diagnosis_deliverable_validations') $rows = array_values(array_filter($rows, fn ($row) => $row['deliverable_type'] === ($bindings[0] ?? null) && $row['deliverable_id'] === ($bindings[1] ?? null)));
            elseif ($table !== 'users') $rows = array_values(array_filter($rows, fn ($row) =>
                (int) ($row['diagnosis_assessment_id'] ?? 0) === (int) ($bindings[0] ?? 0)
                || ($table === 'diagnosis_detailed_roadmaps' && str_contains($query, 'source_expanded_report_id') && in_array($row['source_expanded_report_id'] ?? null, array_slice($bindings, 1), true))));
            if (str_contains($query, '`status` = ?')) $rows = array_values(array_filter($rows, fn ($row) => $row['status'] === end($bindings)));
            if (str_contains($query, '`published_at` is not null')) $rows = array_values(array_filter($rows, fn ($row) => ($row['published_at'] ?? null) !== null));
            if (str_contains($query, 'order by `version` desc')) usort($rows, fn ($a, $b) => $b['version'] <=> $a['version']);
            if (str_contains($query, 'order by `id` asc')) usort($rows, fn ($a, $b) => $a['id'] <=> $b['id']);
            return str_contains($query, 'limit 1') ? array_slice($rows, 0, 1) : $rows;
        };
        if (str_contains($query, 'for update')) foreach ($filter() as $row) $this->acquire($table.':'.$row['id']);
        // Re-evaluate after a simulated wait, just like a current locking read.
        $rows = $filter();
        if ($this->afterRead) ($this->afterRead)($query);
        return $rows;
    }

    public function update($query, $bindings = [])
    {
        if (! preg_match('/\Aupdate `diagnosis_assessments` set (.+) where `id` = \?\z/', $query, $match)) throw new LogicException('Unapproved update: '.$query);
        preg_match_all('/`([a-z_]+)` = \?/', $match[1], $fields); $id = array_pop($bindings);
        $this->put('diagnosis_assessments', $id, array_replace($this->visible()['diagnosis_assessments'][$id], array_combine($fields[1], $bindings)));
        return 1;
    }

    public function insert($query, $bindings = [], $sequence = null)
    {
        if (! preg_match('/\Ainsert into `audit_logs` \(([^)]+)\) values \([?, ]+\)\z/', $query, $match)) throw new LogicException('Unapproved insert: '.$query);
        preg_match_all('/`([a-z_]+)`/', $match[1], $fields);
        $id = count($this->visible()['audit_logs']) + 1;
        $this->put('audit_logs', $id, ['id' => $id, ...array_combine($fields[1], $bindings)]);
        $this->lastInsertId = $id;
        return true;
    }
}
