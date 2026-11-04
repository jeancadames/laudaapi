<?php

namespace Tests\Feature\Diagnosis;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Company;
use App\Models\CompanyTaxProfile;
use App\Models\ContactRequest;
use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisDetailedRoadmap;
use App\Models\DiagnosisExpandedReport;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\TransformationImplementationPlan;
use App\Models\User;
use App\Services\Diagnosis\DiagnosisBusinessProfileService;
use App\Services\Diagnosis\DiagnosisDeliverableValidationService;
use App\Services\Diagnosis\DiagnosisDraftRevision;
use App\Services\Diagnosis\DiagnosisRoadmapDependencies;
use App\Services\Diagnosis\Lauda360ScoringService;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;

/** One opt-in HTTP/MySQL scenario using Laravel's existing connection; no schema/seed commands. */
#[Group('lauda360-f1-dev-rollback')]
final class Lauda360PhaseOneDevTest extends TestCase
{
    private const DATABASE = 'laudaapi_dev';
    // Trusted DEV identity recorded in F1-P2A-Q2-R4; never inferred from the connection under test.
    private const SERVER_UUID = '59fbd6ae-a5d5-11ee-9572-227890cb77b1';

    private Connection $connection;
    private ?PDO $pdo = null;
    private int $sessionId = 0;
    private array $created = [];
    private array $checkedTables = [];
    private string $csrf;

    public function createApplication()
    {
        if (getenv('LAUDA360_F1_P3_QA') !== 'rollback-only') {
            self::markTestSkipped('MYSQL_HTTP_QA=PENDING: run the explicit SSH command; no application/configuration was loaded.');
        }

        // Standard kernel/bootstrap, with DEV checks before provider boot can perform effects.
        $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $this->traitsUsedByTest = array_flip(class_uses_recursive(static::class));
        $app->booting(fn () => $this->prepareDevRuntime());
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    public function test_complete_free_diagnosis_cycle_on_dev_and_roll_every_fixture_back(): void
    {
        self::assertSame(0, $this->connection->transactionLevel());
        foreach (['show' => 'GET', 'update' => 'PATCH', 'submit' => 'POST'] as $name => $method) {
            $route = app('router')->getRoutes()->getByName('diagnosis.'.$name);
            self::assertNotNull($route);
            self::assertContains($method, $route->methods());
            self::assertContains('auth', $route->gatherMiddleware());
            self::assertContains('verified', $route->gatherMiddleware());
        }
        $this->withoutVite();
        $this->csrf = Str::random(40);
        $run = 'f1p3-'.Str::lower(Str::random(16));

        $this->connection->beginTransaction();
        try {
            $tenant = $this->newUser($run.'-owner', 'user');
            $admin = $this->newUser($run.'-admin', 'admin');
            $outsider = $this->newUser($run.'-other', 'subscriber');
            $invited = $this->newUser($run.'-invited', 'user');

            $this->requestAs($tenant, 'POST', route('app.onboarding.store', absolute: false), [
                'name' => $tenant->name, 'company_name' => $run, 'legal_name' => null, 'tax_id' => null,
                'country_code' => 'DO', 'currency' => 'DOP', 'timezone' => 'America/Santo_Domingo',
                'company_size' => '1-10', 'billing_email' => $tenant->email,
            ])->assertRedirect();
            $company = Company::query()->where('owner_user_id', $tenant->id)->sole();
            self::assertNull(CompanyTaxProfile::query()->where('company_id', $company->id)->sole()->legal_name);
            $this->assertFree($company);

            $this->requestAs($tenant, 'POST', route('app.diagnosis.request', absolute: false))->assertRedirect();
            $workflow = DiagnosisAccessRequest::query()->where('user_id', $tenant->id)->sole();
            self::assertNull($workflow->diagnosis_assessment_id);
            $contact = $workflow->contactRequest;
            $this->requestAs($admin, 'POST', route('admin.diagnosis_requests.approve', $contact, false))->assertRedirect();
            $workflow->refresh();
            $assessment = $workflow->assessment;
            self::assertNotNull($assessment);
            self::assertTrue($assessment->is_active);
            $this->assertFree($company);

            $url = route('diagnosis.show', $assessment, false);
            $this->requestAs($outsider, 'GET', $url)->assertForbidden();
            $this->requestAs($admin, 'GET', $url)->assertOk();
            $this->requestAs($tenant, 'GET', $url)->assertOk()
                ->assertJsonPath('props.interpretationBands.capacity.1.min', 40);
            $this->actingAs($tenant->fresh(), 'web')->withSession(['_token' => $this->csrf])
                ->json('PATCH', route('diagnosis.update', $assessment, false), [], ['X-CSRF-TOKEN' => 'invalid-qa-token'])
                ->assertStatus(419);
            $this->assertHistoricalInvitation($invited, $company, $run);

            $ids = app(Lauda360ScoringService::class)->allQuestionIds();
            self::assertCount(51, $ids);
            $profile = $this->businessProfile();
            $revision = app(DiagnosisDraftRevision::class)->forAssessment($assessment->fresh());
            $draft = ['answers' => array_fill_keys($ids, 1), 'notes' => ['STR-01' => 'Borrador anterior'],
                'current_step' => 2, 'expected_revision' => $revision, ...$profile];
            $this->requestAs($tenant, 'PATCH', route('diagnosis.update', $assessment, false), $draft)->assertRedirect();
            $assessment->refresh();
            self::assertSame(2, $assessment->current_step);
            self::assertSame('Borrador anterior', $assessment->notes['STR-01']);
            $saved = $assessment->getAttributes();
            $this->requestAs($tenant, 'PATCH', route('diagnosis.update', $assessment, false), [...$draft, 'current_step' => 3])
                ->assertUnprocessable()->assertJsonStructure(['errors' => ['expected_revision']]);
            self::assertSame($saved, $assessment->fresh()->getAttributes());

            DB::table('subscriber_user')->where('subscriber_id', $company->subscriber_id)->where('user_id', $tenant->id)->update(['active' => false]);
            $this->requestAs($tenant, 'GET', $url)->assertForbidden();
            DB::table('subscriber_user')->where('subscriber_id', $company->subscriber_id)->where('user_id', $tenant->id)->update(['active' => true]);

            $this->requestAs($tenant, 'POST', route('diagnosis.submit', $assessment, false), [
                'answers' => array_fill_keys($ids, 5), 'notes' => ['STR-01' => 'Declaración final de QA'],
                'expected_revision' => app(DiagnosisDraftRevision::class)->forAssessment($assessment->fresh()), ...$profile,
            ])->assertRedirect();
            $assessment->refresh();
            self::assertSame('submitted', $assessment->status);
            self::assertSame(100, $assessment->maturity_score);
            self::assertSame('Crítica', $assessment->urgency_level);
            self::assertStringContainsString('STR-01=5/5', implode(' ', $assessment->review_priorities));
            self::assertStringNotContainsString('Borrador anterior', implode(' ', $assessment->review_priorities));

            $adminView = $this->requestAs($admin, 'GET', route('admin.diagnosis_requests.show', $contact, false))->assertOk();
            foreach (DiagnosisBusinessProfileService::FIELDS as $field) {
                $adminView->assertJsonPath('props.workflow.assessment.'.$field, $assessment->{$field});
            }
            $adminView->assertJsonPath('props.workflow.assessment.business_profile_complete', true);
            $publish = ['review_summary' => $assessment->review_summary, 'review_priorities' => $assessment->review_priorities];
            $publishUrl = route('admin.diagnosis_requests.publish_result', $contact, false);
            $this->requestAs($tenant, 'POST', $publishUrl, $publish)->assertForbidden();
            $this->requestAs($admin, 'POST', $publishUrl, $publish)->assertRedirect();

            $report = DiagnosisExpandedReport::query()->where('diagnosis_assessment_id', $assessment->id)->where('status', 'published')->sole();
            $roadmap = DiagnosisDetailedRoadmap::query()->where('diagnosis_assessment_id', $assessment->id)->where('status', 'published')->sole();
            $plan = TransformationImplementationPlan::query()->where('diagnosis_assessment_id', $assessment->id)->sole();
            self::assertSame([], $report->sections['critical_gaps']['items']);
            self::assertCount(8, $report->sections['relative_strengths']['items']);
            self::assertSame('Declaración final de QA', $report->source_snapshot['declared_evidence'][0]['note']);
            app(DiagnosisRoadmapDependencies::class)->assertValid($roadmap->roadmap);
            foreach ($roadmap->roadmap['initiatives'] as $initiative) {
                if ($initiative['source_score'] !== null) self::assertSame('strength', $initiative['classification']);
            }
            $this->requestAs($tenant, 'GET', route('diagnosis.expanded_report.show', $assessment, false))->assertOk();
            $this->requestAs($tenant, 'GET', route('diagnosis.detailed_roadmap.show', $assessment, false))->assertOk();
            $this->assertFree($company);

            $validation = app(DiagnosisDeliverableValidationService::class)->validate($report, $tenant);
            $history = [$assessment->fresh()->getAttributes(), $report->getAttributes(), $roadmap->getAttributes(), $plan->getAttributes(), $validation->getAttributes()];
            $this->requestAs($admin, 'POST', $publishUrl, [...$publish, 'review_summary' => $publish['review_summary'].' Cambio bloqueado.'])
                ->assertUnprocessable()->assertJsonStructure(['errors' => ['assessment']]);
            self::assertSame($history, [$assessment->fresh()->getAttributes(), $report->fresh()->getAttributes(), $roadmap->fresh()->getAttributes(), $plan->fresh()->getAttributes(), $validation->fresh()->getAttributes()]);
            self::assertNull(CompanyTaxProfile::query()->where('company_id', $company->id)->sole()->legal_name);
            $this->assertFree($company);
            self::assertSame(1, $this->connection->transactionLevel());
        } finally {
            // Always roll back the outer transaction, including HTTP/service savepoints.
            if ($this->connection->transactionLevel() > 0) $this->connection->rollBack(0);
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            self::assertFalse($this->pdo->inTransaction());
            self::assertSame(0, $this->connection->transactionLevel());
            foreach ($this->created as $table => $ids) {
                self::assertSame(0, $this->connection->table($table)->whereIn('id', array_keys($ids))->count(), 'Rollback left synthetic rows in '.$table);
            }
        }
    }

    private function prepareDevRuntime(): void
    {
        // These checks precede PDO acquisition and every fixture mutation.
        self::assertSame('local', config('app.env'), 'QA is limited to the existing local DEV application.');
        self::assertSame('local', app()->environment());
        self::assertSame('mysql', config('database.default'));
        $this->connection = DB::connection();
        self::assertSame('mysql', $this->connection->getName());
        self::assertSame('mysql', $this->connection->getDriverName());
        self::assertSame(self::DATABASE, $this->connection->getDatabaseName());
        self::assertEmpty($this->connection->getConfig('read'), 'Use the single existing DEV session.');
        self::assertEmpty($this->connection->getConfig('write'));
        self::assertSame(0, $this->connection->transactionLevel());
        $this->installRollbackGuards();
        $this->assertDevIdentity();
        self::assertFalse($this->pdo->inTransaction());

        config(['cache.default' => 'array', 'session.driver' => 'array', 'queue.default' => 'sync',
            'mail.default' => 'array', 'telescope.enabled' => false, 'pulse.enabled' => false, 'nightwatch.enabled' => false]);
        Mail::fake(); Notification::fake(); Bus::fake(); Http::preventStrayRequests();
        foreach (DB::getConnections() as $connection) {
            self::assertSame($this->connection, $connection, 'Only the existing DEV connection is permitted.');
        }
    }

    private function assertDevIdentity(): void
    {
        self::assertSame('local', config('app.env'));
        self::assertSame('local', app()->environment());
        self::assertSame('mysql', config('database.default'));
        self::assertSame($this->connection, DB::connection());
        self::assertSame(self::DATABASE, $this->connection->getDatabaseName());
        $pdo = $this->connection->getPdo();
        $identity = $this->connection->selectOne('SELECT DATABASE() AS db, @@server_uuid AS server_uuid, CONNECTION_ID() AS session_id');
        self::assertSame(self::DATABASE, $identity->db);
        self::assertSame(self::SERVER_UUID, $identity->server_uuid);
        if ($this->pdo !== null) {
            self::assertSame($this->pdo, $pdo);
            self::assertSame($this->sessionId, (int) $identity->session_id);
        } else {
            $this->pdo = $pdo;
            $this->sessionId = (int) $identity->session_id;
        }
    }

    private function installRollbackGuards(): void
    {
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            self::assertSame($this->connection, $event->connection, 'QA cannot open another connection.');
        });
        Event::listen(TransactionCommitting::class, function (TransactionCommitting $event): void {
            if ($event->connection === $this->connection && $event->connection->transactionLevel() === 1) {
                throw new RuntimeException('QA is rollback-only: committing the outer transaction is forbidden.');
            }
        });
        Event::listen('eloquent.created: *', function (string $event, array $models): void {
            $model = $models[0];
            if ($model instanceof Model && $model->getKey() !== null) $this->created[$model->getTable()][$model->getKey()] = true;
        });
        Event::listen('eloquent.updating: *', function (string $event, array $models): void {
            $model = $models[0];
            self::assertTrue(isset($this->created[$model->getTable()][$model->getKey()]), 'QA cannot update an existing model.');
        });
        $this->connection->beforeExecuting(function (string $sql): void {
            if (preg_match('/^\s*(?:alter|create|drop|truncate|rename|grant|revoke|commit|use)\b/i', $sql)) {
                throw new RuntimeException('Schema/administrative/commit SQL is forbidden in this QA.');
            }
            if (! preg_match('/^\s*(?:insert(?:\s+ignore)?\s+into|update|delete\s+from)\s+`?([a-z0-9_]+)`?/i', $sql, $match)) return;
            self::assertNotNull($this->pdo, 'DEV identity must be verified before any mutation.');
            $this->assertDevIdentity();
            self::assertGreaterThanOrEqual(1, $this->connection->transactionLevel());
            self::assertTrue($this->pdo->inTransaction());
            $table = $match[1];
            self::assertNotContains($table, ['invoices', 'invoice_items', 'payments', 'subscriptions', 'migrations']);
            if (! isset($this->checkedTables[$table])) {
                $engine = $this->connection->selectOne('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [self::DATABASE, $table]);
                self::assertSame('InnoDB', $engine?->engine, 'Rollback requires transactional fixture tables.');
                $this->checkedTables[$table] = true;
            }
        });
    }

    private function requestAs(User $actor, string $method, string $url, array $data = []): TestResponse
    {
        $headers = ['X-CSRF-TOKEN' => $this->csrf];
        if ($method === 'GET') {
            $headers['X-Inertia'] = 'true';
            $headers['X-Inertia-Version'] = (new HandleInertiaRequests)->version(Request::create($url)) ?? '';
        }

        return $this->actingAs($actor->fresh(), 'web')->withSession(['_token' => $this->csrf])
            ->json($method, $url, ['_token' => $this->csrf, ...$data], $headers);
    }

    private function newUser(string $name, string $role): User
    {
        return User::query()->forceCreate(['name' => $name, 'email' => $name.'@qa.invalid', 'password' => Hash::make(Str::random(32)),
            'role' => $role, 'email_verified_at' => now(), 'must_change_password' => false, 'password_changed_at' => now()]);
    }

    private function businessProfile(): array
    {
        return app(DiagnosisBusinessProfileService::class)->normalize([
            'business_activity_type' => 'services', 'business_sector' => 'professional_services', 'customer_market' => 'b2b',
            'sales_channels' => ['quotations'], 'business_activity_description' => 'Servicios profesionales declarados para QA sintética.',
        ]);
    }

    private function assertHistoricalInvitation(User $invited, Company $company, string $run): void
    {
        $historical = DiagnosisAssessment::query()->create(['user_id' => $invited->id, 'organization_id' => $company->id,
            'organization_name' => $run.' invitación', 'is_active' => true, 'status' => 'draft', 'current_step' => 1,
            'methodology_version' => config('lauda360_diagnosis.version'), 'answers' => [], 'notes' => []]);
        $contact = ContactRequest::query()->create(['name' => $invited->name, 'email' => $invited->email,
            'company' => $run, 'topic' => 'Solicitud de acceso al Diagnóstico LAUDA 360', 'message' => 'Invitación sintética de QA', 'terms' => true,
            'metadata' => ['request_type' => 'digital_diagnosis_access_request']]);
        DiagnosisAccessRequest::query()->create(['public_id' => (string) Str::ulid(), 'contact_request_id' => $contact->id,
            'user_id' => $invited->id, 'diagnosis_assessment_id' => $historical->id, 'status' => 'invited', 'invitation_sent_at' => now(),
            'meta' => ['source' => 'historical_invitation_qa']]);
        $this->requestAs($invited, 'GET', route('diagnosis.show', $historical, false))->assertOk();
    }

    private function assertFree(Company $company): void
    {
        self::assertSame(0, Invoice::query()->where('company_id', $company->id)->count());
        self::assertSame(0, InvoiceItem::query()->whereHas('invoice', fn ($query) => $query->where('company_id', $company->id))->count());
        self::assertSame(0, Payment::query()->where('company_id', $company->id)->count());
        self::assertSame(0, Subscription::query()->where('subscriber_id', $company->subscriber_id)->count());
    }
}
