<?php

namespace Tests\Unit\Diagnosis;

use App\Http\Requests\Diagnosis\UpdateDiagnosisDetailedRoadmapReviewRequest;
use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisDetailedRoadmap;
use App\Models\DiagnosisExpandedReport;
use App\Models\User;
use App\Services\Diagnosis\DiagnosisDetailedRoadmapGenerator;
use App\Services\Diagnosis\DiagnosisDetailedRoadmapService;
use App\Services\Diagnosis\DiagnosisExpandedReportGenerator;
use App\Services\Diagnosis\DiagnosisRoadmapDependencies;
use App\Services\Diagnosis\DiagnosisTransformationProgressService;
use App\Services\Diagnosis\Lauda360ScoringService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

/** Content, generation, review readiness and publication barriers; never PDO/app boot. */
final class DiagnosisRoadmapQualityTest extends TestCase
{
    private array $previous;
    private array $modelState;
    private Container $app;

    protected function setUp(): void
    {
        $this->previous = [Container::getInstance(), Facade::getFacadeApplication(),
            (new ReflectionProperty(Facade::class, 'resolvedInstance'))->getValue(), Model::getConnectionResolver()];
        foreach (['booted', 'bootedCallbacks', 'globalScopes'] as $name) {
            $this->modelState[$name] = (new ReflectionProperty(Model::class, $name))->getValue();
        }
        $this->app = new Container;
        Container::setInstance($this->app);
        Facade::setFacadeApplication($this->app);
        Facade::clearResolvedInstances();
        $this->app->instance('config', new Repository([
            'lauda360_diagnosis' => require dirname(__DIR__, 3).'/config/lauda360_diagnosis.php',
            'lauda360_business_profile' => require dirname(__DIR__, 3).'/config/lauda360_business_profile.php',
        ]));
        $this->app->instance('validator', new Factory(new Translator(new ArrayLoader, 'es'), $this->app));
        $this->connection(new MySqlConnection(fn () => throw new RuntimeException('SQL prohibited')));
    }

    private function connection(MySqlConnection $connection): void
    {
        $resolver = new ConnectionResolver(['pure' => $connection]);
        $resolver->setDefaultConnection('pure');
        Model::setConnectionResolver($resolver);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previous[0]);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previous[1]);
        (new ReflectionProperty(Facade::class, 'resolvedInstance'))->setValue(null, $this->previous[2]);
        if ($this->previous[3]) Model::setConnectionResolver($this->previous[3]);
        else Model::unsetConnectionResolver();
        foreach ($this->modelState as $name => $value) (new ReflectionProperty(Model::class, $name))->setValue(null, $value);
        parent::tearDown();
    }

    private function assessment(int $answer = 3): DiagnosisAssessment
    {
        $scoring = new Lauda360ScoringService;
        $answers = array_fill_keys($scoring->allQuestionIds(), $answer);

        $assessment = new DiagnosisAssessment([
            ...$scoring->calculate($answers), 'id' => 50, 'organization_name' => 'Roadmap fixture',
            'methodology_version' => '1.0', 'status' => 'reviewed', 'answers' => $answers,
            'notes' => ['STR-01' => 'Plan declarado, pendiente de contraste técnico.'],
            'business_activity_type' => 'services', 'business_sector' => 'professional_services',
        ]);
        $assessment->id = 50;

        return $assessment;
    }

    private function generated(?DiagnosisAssessment $assessment = null): array
    {
        return (new DiagnosisDetailedRoadmapGenerator)->generate($assessment ?? $this->assessment())['roadmap'];
    }

    private function assertPrerequisites(array $payload): void
    {
        (new DiagnosisRoadmapDependencies)->assertValid($payload);
        $indexed = array_column($payload['initiatives'], null, 'id');
        foreach ($payload['initiatives'] as $position => $initiative) {
            self::assertSame($position + 1, $initiative['sequence']);
            foreach ($initiative['dependencies'] as $id) {
                self::assertArrayHasKey($id, $indexed);
                self::assertLessThan($initiative['sequence'], $indexed[$id]['sequence']);
                self::assertLessThanOrEqual($initiative['phase'], $indexed[$id]['phase']);
            }
        }
    }

    public function test_generator_orders_every_prerequisite_before_critical_dependents(): void
    {
        $assessment = $this->assessment();
        $assessment->dimension_scores = [
            'strategy' => 100, 'people' => 80, 'presence' => 0, 'commercial' => 0,
            'operations' => 0, 'technology' => 80, 'data' => 0, 'governance' => 100,
        ];
        $payload = $this->generated($assessment);
        $this->assertPrerequisites($payload);
        $indexed = array_column($payload['initiatives'], null, 'id');
        self::assertSame('sustain', $indexed['STR-01']['priority']);
        self::assertSame('critical', $indexed['OPS-01']['priority']);
        self::assertSame('medium', $indexed['TEC-01']['priority']);
        self::assertSame('critical', $indexed['DAT-01']['priority']);
        self::assertLessThan($indexed['OPS-01']['sequence'], $indexed['PPL-01']['sequence']);
        self::assertLessThan($indexed['DAT-01']['sequence'], $indexed['TEC-01']['sequence']);
    }

    public function test_order_is_deterministic_and_phase_adjustments_follow_prerequisites(): void
    {
        $items = [
            ['id' => 'CHILD', 'dependencies' => ['PARENT'], 'phase' => 1, 'priority' => 'critical', 'impact' => 'high'],
            ['id' => 'PARENT', 'dependencies' => [], 'phase' => 3, 'priority' => 'sustain', 'impact' => 'low'],
            ['id' => 'READY', 'dependencies' => [], 'phase' => 1, 'priority' => 'high', 'impact' => 'high'],
        ];
        $graph = new DiagnosisRoadmapDependencies;
        $ordered = $graph->sequence($items);
        self::assertSame(['READY', 'PARENT', 'CHILD'], array_column($ordered, 'id'));
        self::assertSame(3, $ordered[2]['phase']);
        self::assertSame('91-180 días', $ordered[2]['horizon']);
        self::assertSame('critical', $ordered[2]['priority']);
        self::assertSame($ordered, $graph->sequence($items));
    }

    public function test_impact_only_breaks_ties_among_ready_initiatives_of_same_phase_and_priority(): void
    {
        $items = [];
        foreach (['LOW' => 'low', 'HIGH' => 'high', 'MEDIUM' => 'medium'] as $id => $impact) {
            $items[] = ['id' => $id, 'dependencies' => [], 'phase' => 1, 'priority' => 'high', 'impact' => $impact];
        }
        self::assertSame(['HIGH', 'MEDIUM', 'LOW'], array_column((new DiagnosisRoadmapDependencies)->sequence($items), 'id'));
    }

    #[DataProvider('invalidCases')]
    public function test_invalid_content_is_rejected_before_publication_storage(string $case): void
    {
        $payload = $this->generated();
        $first = $payload['initiatives'][0]['id'];
        switch ($case) {
            case 'empty': $payload['initiatives'] = []; break;
            case 'missing': $payload['initiatives'][1]['dependencies'][] = 'MISSING-01'; break;
            case 'duplicate_id': $payload['initiatives'][1]['id'] = $first; break;
            case 'duplicate_reference': $payload['initiatives'][1]['dependencies'] = [$first, $first]; break;
            case 'self': $payload['initiatives'][0]['dependencies'] = [$first]; break;
            case 'cycle': $payload['initiatives'][0]['dependencies'] = [$payload['initiatives'][1]['id']]; $payload['initiatives'][1]['dependencies'] = [$first]; break;
            case 'long_cycle': $payload['initiatives'][0]['dependencies'] = [$payload['initiatives'][2]['id']]; $payload['initiatives'][1]['dependencies'] = [$first]; $payload['initiatives'][2]['dependencies'] = [$payload['initiatives'][1]['id']]; break;
            case 'phase': $payload['initiatives'][1]['phase'] = 0; break;
            case 'prerequisite_later_phase': $payload['initiatives'][0]['phase'] = 4; $payload['initiatives'][0]['horizon'] = '181-365 días'; break;
            case 'order': [$payload['initiatives'][0], $payload['initiatives'][1]] = [$payload['initiatives'][1], $payload['initiatives'][0]]; break;
            case 'reverse_dependency': $payload['initiatives'][0]['dependencies'] = [$payload['initiatives'][array_key_last($payload['initiatives'])]['id']]; $payload['initiatives'][array_key_last($payload['initiatives'])]['dependencies'] = []; break;
            case 'sequence': $payload['initiatives'][1]['sequence'] = 1; break;
            case 'horizon': $payload['initiatives'][0]['horizon'] = '91-180 días'; break;
            case 'phase_duplicate': $payload['phases'][0]['initiative_ids'][] = $first; break;
            case 'phase_unknown': $payload['phases'][0]['initiative_ids'][] = 'MISSING-01'; break;
            case 'phase_missing': $payload['phases'][0]['initiative_ids'] = []; break;
            case 'phase_order': $payload['phases'] = array_reverse($payload['phases']); break;
            case 'malformed': $payload['initiatives'][0]['dependencies'] = 'STR-01'; break;
            case 'malformed_item': $payload['initiatives'][0] = 'STR-01'; break;
            case 'nonlist': $payload['initiatives'] = ['STR-01' => $payload['initiatives'][0]]; break;
        }
        $report = new DiagnosisRoadmapPublicationProbe(['status' => 'draft', 'roadmap' => $payload]);
        $before = $report->getAttributes();
        try {
            (new DiagnosisDetailedRoadmapService(new DiagnosisDetailedRoadmapGenerator))->publish($report, new User);
            self::fail('Invalid roadmap must not reach storage');
        } catch (ValidationException $e) {
            self::assertNotEmpty($e->errors()['roadmap']);
            self::assertSame($before, $report->getAttributes());
        }
    }

    public static function invalidCases(): array
    {
        return array_map(fn (string $case): array => [$case], [
            'empty', 'missing', 'duplicate_id', 'duplicate_reference', 'self', 'cycle', 'long_cycle',
            'phase', 'prerequisite_later_phase', 'order', 'reverse_dependency', 'sequence', 'horizon',
            'phase_duplicate', 'phase_unknown', 'phase_missing', 'phase_order', 'malformed', 'malformed_item', 'nonlist',
        ]);
    }

    public function test_empty_generator_input_cannot_hide_missing_prerequisites(): void
    {
        $assessment = $this->assessment();
        $assessment->dimension_scores = [];
        $this->expectException(ValidationException::class);
        $this->generated($assessment);
    }

    public function test_perfect_dimensions_generate_maintenance_and_preserve_free_conclusions(): void
    {
        $assessment = $this->assessment(5);
        $reportData = (new DiagnosisExpandedReportGenerator)->generate($assessment);
        $report = new DiagnosisExpandedReport(['id' => 70, 'version' => 2, 'status' => 'published', ...$reportData]);
        $report->id = 70;
        $before = [$assessment->getAttributes(), $report->getAttributes()];
        $generated = (new DiagnosisDetailedRoadmapGenerator)->generate($assessment, $report);
        $payload = $generated['roadmap'];
        $dimensions = array_filter($payload['initiatives'], fn (array $item): bool => $item['source_score'] !== null);
        self::assertCount(8, $dimensions);
        self::assertSame([], $reportData['sections']['critical_gaps']['items']);
        foreach ($dimensions as $item) {
            self::assertSame('strength', $item['classification']);
            self::assertSame('sustain', $item['priority']);
            self::assertSame('low', $item['effort']);
            self::assertStringStartsWith('Mantener y validar', $item['title']);
            self::assertCount(5, $item['evidence']);
        }
        self::assertNotContains('critical', array_column($payload['initiatives'], 'priority'));
        self::assertCount(51, $generated['source_snapshot']['assessment']['declared_evidence']);
        self::assertSame(2, $generated['source_snapshot']['expanded_report']['version']);
        self::assertSame(70, $generated['source_snapshot']['expanded_report']['id']);
        self::assertStringContainsString('gratuito', $payload['scope_note']['body']);
        self::assertSame($before, [$assessment->getAttributes(), $report->getAttributes()]);
        $this->assertPrerequisites($payload);
    }

    public function test_initial_gap_and_action_priority_remain_distinct_without_changing_scales(): void
    {
        $assessment = $this->assessment(1);
        $answers = $assessment->answers;
        foreach (config('lauda360_diagnosis.dimensions.strategy.questions') as $id) {
            $answers[$id] = 2;
        }
        $assessment->forceFill([
            ...(new Lauda360ScoringService)->calculate($answers),
            'answers' => $answers,
        ]);
        $payload = $this->generated($assessment);
        $indexed = array_column($payload['initiatives'], null, 'id');
        self::assertSame('gap', $indexed['STR-01']['classification']);
        self::assertSame('critical', $indexed['STR-01']['priority']);
        self::assertStringContainsString('STR-01=2/5', $indexed['STR-01']['rationale']);
        self::assertStringContainsString('no equivale a severidad', $indexed['STR-01']['rationale']);
        self::assertSame('critical', $indexed['OPS-01']['classification']);
        self::assertSame(4, $assessment->maturity_score);
    }

    public function test_urgency_affects_existing_impact_rule_without_inventing_dimension_gaps(): void
    {
        $assessment = $this->assessment(5);
        $assessment->urgency_score = 24;
        $low = array_column($this->generated($assessment)['initiatives'], null, 'id');
        $assessment->urgency_score = 75;
        $high = array_column($this->generated($assessment)['initiatives'], null, 'id');
        self::assertSame('low', $low['STR-01']['impact']);
        self::assertSame('high', $high['STR-01']['impact']);
        self::assertSame('sustain', $high['STR-01']['priority']);
        self::assertSame('strength', $high['STR-01']['classification']);
    }

    public function test_valid_content_passes_publication_barriers_without_storage(): void
    {
        $roadmap = new DiagnosisRoadmapPublicationProbe(['status' => 'under_review', 'roadmap' => $this->generated()]);
        try {
            (new DiagnosisDetailedRoadmapService(new DiagnosisDetailedRoadmapGenerator))->publish($roadmap, new User(['id' => 91]));
            self::fail('Probe must stop at storage');
        } catch (RuntimeException $e) {
            self::assertSame('Valid content; storage prohibited', $e->getMessage());
            self::assertSame('published', $roadmap->status);
        }
    }

    public function test_published_history_is_immutable_and_review_does_not_edit_initiatives(): void
    {
        $roadmap = new DiagnosisDetailedRoadmap(['status' => 'published', 'version' => 2, 'roadmap' => $this->generated()]);
        $before = $roadmap->getAttributes();
        $service = new DiagnosisDetailedRoadmapService(new DiagnosisDetailedRoadmapGenerator);
        foreach (['regenerateDraft', 'publish', 'markUnderReview'] as $method) {
            try { $service->{$method}($roadmap, new User); self::fail('Published history must remain intact'); }
            catch (ValidationException) { self::assertSame($before, $roadmap->getAttributes()); }
        }
        try { $service->saveReviewNotes($roadmap, new User, 'No alterar historia'); self::fail('Published history must remain intact'); }
        catch (ValidationException) { self::assertSame($before, $roadmap->getAttributes()); }
        $rules = (new UpdateDiagnosisDetailedRoadmapReviewRequest)->rules();
        self::assertSame(['review_notes'], array_keys($rules));
        $validated = $this->app['validator']->make(['review_notes' => 'Revisar prerrequisitos', 'initiatives' => []], $rules)->validated();
        self::assertSame(['review_notes' => 'Revisar prerrequisitos'], $validated);
    }

    public function test_admin_readiness_explains_invalid_graph_without_sql(): void
    {
        $assessment = $this->assessment();
        $assessment->published_at = '2026-11-03 00:00:00';
        $payload = $this->generated($assessment);
        $db = new DiagnosisRoadmapReadinessMemory($payload);
        $this->connection($db);
        $service = new DiagnosisTransformationProgressService;
        $ready = $service->roadmapReadiness($assessment);
        self::assertTrue($ready['publication_ready']);
        $db->payload['initiatives'][0]['dependencies'] = ['MISSING-01'];
        $blocked = $service->roadmapReadiness($assessment);
        self::assertFalse($blocked['publication_ready']);
        self::assertStringContainsString('MISSING-01', implode(' ', $blocked['publication_blockers']));
    }
}

final class DiagnosisRoadmapPublicationProbe extends DiagnosisDetailedRoadmap
{
    public function save(array $options = [])
    {
        throw new RuntimeException('Valid content; storage prohibited');
    }
}

/** Explicit technical rows for the real readiness service; never opens a connection. */
final class DiagnosisRoadmapReadinessMemory extends MySqlConnection
{
    public function __construct(public array $payload)
    {
        parent::__construct(fn () => throw new RuntimeException('SQL prohibited'));
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
        if (str_contains($query, 'from `diagnosis_detailed_roadmaps`')) return [['id' => 80, 'version' => 1, 'status' => 'draft', 'roadmap' => json_encode($this->payload)]];
        if (str_contains($query, 'from `diagnosis_expanded_reports`')) return [['id' => 70, 'version' => 1, 'status' => 'published', 'published_at' => '2026-11-03 00:00:00']];
        foreach (['diagnosis_access_requests', 'transformation_implementation_plans', 'diagnosis_deliverable_validations'] as $table) {
            if (str_contains($query, 'from `'.$table.'`')) return [];
        }
        throw new RuntimeException('Unexpected query in pure readiness QA: '.$query);
    }
}
