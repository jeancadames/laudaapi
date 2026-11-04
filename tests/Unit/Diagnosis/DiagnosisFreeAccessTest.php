<?php

declare(strict_types=1);

namespace Tests\Unit\Diagnosis;

use App\Models\Company;
use App\Models\ContactRequest;
use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use App\Models\User;
use App\Policies\DiagnosisAssessmentPolicy;
use App\Services\Diagnosis\DiagnosisAccessService;
use App\Services\Diagnosis\InitialDiagnosisCommercialService;
use Closure;
use Illuminate\Auth\RequestGuard;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\MySqlConnection;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Throwable;

/** Real services and Eloquent, with scripted storage only. No Laravel boot, PDO or external SQL. */
final class DiagnosisFreeAccessTest extends TestCase
{
    private FreeDiagnosisScriptedConnection $connection;

    private array $previous;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previous = ['container' => Container::getInstance(), 'facades' => Facade::getFacadeApplication(),
            'roots' => (new ReflectionProperty(Facade::class, 'resolvedInstance'))->getValue(),
            'resolver' => Model::getConnectionResolver(), 'dispatcher' => Model::getEventDispatcher()];
        foreach (['booted', 'bootedCallbacks', 'globalScopes'] as $name) {
            $this->previous[$name] = (new ReflectionProperty(Model::class, $name))->getValue();
        }
        $app = new class extends Container
        {
            public function runningInConsole(): bool
            {
                return true;
            }
        };
        Container::setInstance($app);
        $app->instance('config', new Repository);
        $app->instance('validator', new Factory(new Translator(new ArrayLoader, 'es'), $app));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        Auth::swap(new RequestGuard(static fn () => null, Request::create('/')));
        $this->connection = new FreeDiagnosisScriptedConnection([
            'companies' => [7 => ['id' => 7, 'name' => 'QA company', 'subscriber_id' => 17, 'active' => 1]],
            'subscribers' => [17 => ['id' => 17, 'name' => 'QA tenant']],
            'users' => [20 => ['id' => 20, 'name' => 'QA owner', 'email' => 'free@example.test', 'role' => 'subscriber']],
            'contact_requests' => [30 => ['id' => 30, 'name' => 'QA owner', 'email' => 'free@example.test',
                'company' => 'QA company', 'topic' => 'Solicitud de acceso al Diagnóstico LAUDA 360',
                'metadata' => json_encode(['request_type' => 'digital_diagnosis_access_request'])]],
            'diagnosis_access_requests' => [40 => ['id' => 40, 'public_id' => '01QA', 'contact_request_id' => 30,
                'user_id' => 20, 'diagnosis_assessment_id' => null, 'status' => 'pending',
                'meta' => json_encode(['source' => InitialDiagnosisCommercialService::SOURCE, 'company_id' => 7, 'subscriber_id' => 17])]],
            'diagnosis_assessments' => [], 'audit_logs' => [],
            'invoices' => [999 => ['id' => 999, 'status' => 'voided', 'total' => '0.00']],
            'invoice_items' => [999 => ['id' => 999, 'invoice_id' => 999]],
            'payments' => [], 'subscriptions' => [],
        ]);
        $capsule = new Capsule($app);
        $capsule->addConnection(['driver' => 'mysql', 'name' => 'free_diagnosis_memory'], 'free_diagnosis_memory');
        $manager = $capsule->getDatabaseManager();
        $manager->extend('mysql', fn () => $this->connection);
        $manager->setDefaultConnection('free_diagnosis_memory');
        $app->instance('db', $manager);
        $capsule->setEventDispatcher(new Dispatcher($app));
        Model::clearBootedModels();
        $capsule->bootEloquent();
    }

    protected function tearDown(): void
    {
        try {
            self::assertFalse($this->connection->pdoAttempted);
            self::assertSame([], $this->connection->reads, 'Unconsumed scripted reads.');
            self::assertSame(0, $this->connection->transactionLevel());
            self::assertSame([999 => ['id' => 999, 'status' => 'voided', 'total' => '0.00']], $this->connection->rows['invoices']);
            self::assertSame([999 => ['id' => 999, 'invoice_id' => 999]], $this->connection->rows['invoice_items']);
            self::assertSame([], $this->connection->rows['payments']);
            self::assertSame([], $this->connection->rows['subscriptions']);
            foreach ($this->connection->mutations as $table) {
                self::assertContains($table, ['contact_requests', 'diagnosis_access_requests', 'diagnosis_assessments', 'audit_logs']);
            }
        } finally {
            Container::setInstance($this->previous['container']);
            Facade::setFacadeApplication($this->previous['facades']);
            (new ReflectionProperty(Facade::class, 'resolvedInstance'))->setValue(null, $this->previous['roots']);
            foreach (['booted', 'bootedCallbacks', 'globalScopes'] as $name) {
                (new ReflectionProperty(Model::class, $name))->setValue(null, $this->previous[$name]);
            }
            $this->previous['resolver'] === null ? Model::unsetConnectionResolver() : Model::setConnectionResolver($this->previous['resolver']);
            $this->previous['dispatcher'] === null ? Model::unsetEventDispatcher() : Model::setEventDispatcher($this->previous['dispatcher']);
            parent::tearDown();
        }
    }

    private function user(int $id = 20): User
    {
        $user = (new User)->setRawAttributes($id === 20 ? $this->connection->rows['users'][20]
            : ['id' => $id, 'role' => 'admin'], true);
        $user->exists = true;

        return $user;
    }

    private function expectRow(string $table, array $parts, array $bindings, int $id): void
    {
        $this->connection->expect($table, $parts, $bindings, fn () => [$this->connection->rows[$table][$id]]);
    }

    private function contextReads(bool $member = true): void
    {
        $this->connection->expect('subscribers', ['inner join `subscriber_user`', '`subscriber_user`.`active` = ?', '`subscriber_user`.`role` in (?, ?)'],
            [20, true, 'owner', 'admin'], $member ? fn () => [$this->connection->rows['subscribers'][17]
                + ['pivot_user_id' => 20, 'pivot_subscriber_id' => 17, 'pivot_role' => 'owner', 'pivot_active' => 1]] : []);
        if (! $member) {
            return;
        }
        $this->expectRow('companies', ['`subscriber_id` = ?', '`active` = ?'], [17, true], 7);
        $this->connection->expect('users', ['inner join `subscriber_user`', '`users`.`id` = ?'], [17, 20],
            fn () => [$this->connection->rows['users'][20] + ['pivot_user_id' => 20, 'pivot_subscriber_id' => 17, 'pivot_role' => 'owner', 'pivot_active' => 1]]);
        $this->expectRow('companies', ['for update'], [7], 7);
    }

    private function freshReads(bool $assessment = false): void
    {
        $this->expectRow('diagnosis_access_requests', ['`id` = ?'], [40], 40);
        $this->expectRow('contact_requests', [' in (30)'], [], 30);
        if ($assessment) {
            $this->expectRow('users', [' in (20)'], [], 20);
            $this->expectRow('diagnosis_assessments', [' in (50)'], [], 50);
        }
    }

    private function pendingPreparationReads(): void
    {
        $this->expectRow('diagnosis_access_requests', ['diagnosis_assessment_id` is null', 'not in'],
            [InitialDiagnosisCommercialService::SOURCE, 7, 'active', 'inactive', 'rejected'], 40);
        $this->expectRow('contact_requests', ['`id` = ?'], [30], 30);
        $this->freshReads();
    }

    private function approvalReads(bool $existing = false): void
    {
        $this->expectRow('diagnosis_access_requests', ['contact_request_id` = ?', 'for update'], [30], 40);
        $this->expectRow('companies', ['`id` = ?', '`subscriber_id` = ?', '`active` = ?'], [7, 17, true], 7);
        $this->expectRow('users', ['LOWER(email) = ?'], ['free@example.test'], 20);
        if ($existing) {
            $this->expectRow('diagnosis_assessments', ['`id` = ?'], [50], 50);
        } else {
            $this->connection->expect('diagnosis_access_requests', ['diagnosis_assessment_id` is not null'],
                [InitialDiagnosisCommercialService::SOURCE, 7], []);
        }
        $this->freshReads(true);
        $this->freshReads(true);
    }

    private function approve(): DiagnosisAccessRequest
    {
        $contact = (new ContactRequest)->setRawAttributes($this->connection->rows['contact_requests'][30], true);
        $contact->exists = true;

        return (new DiagnosisAccessService)->approve($contact, $this->user(99));
    }

    public function test_access_terms_do_not_depend_on_commercial_configuration(): void
    {
        config(['lauda360_commercial.initial_diagnosis' => ['subtotal' => 999, 'tax_rate' => 18]]);
        $offer = (new InitialDiagnosisCommercialService)->offer();
        self::assertTrue($offer['free']);
        self::assertTrue($offer['manual_confirmation_required']);
        foreach (['invoice_required', 'payment_required', 'subscription_required', 'modality_required', 'contract_required'] as $key) {
            self::assertFalse($offer[$key]);
        }
        foreach (['currency', 'subtotal', 'tax_rate', 'tax_amount', 'total', 'invoice_description'] as $key) {
            self::assertArrayNotHasKey($key, $offer);
        }
    }

    public function test_request_keeps_administrative_pending_status_without_commercial_records(): void
    {
        $this->contextReads();
        $this->pendingPreparationReads();
        $workflow = (new InitialDiagnosisCommercialService)->request($this->user());
        self::assertSame('pending', $workflow->status);
        self::assertNull($workflow->diagnosis_assessment_id);
        self::assertTrue($workflow->meta['initial_diagnosis']['free']);
        self::assertSame('pending', $workflow->meta['initial_diagnosis']['confirmation_status']);
        self::assertArrayNotHasKey('invoice_id', $workflow->meta['initial_diagnosis']);
        self::assertArrayNotHasKey('invoice_number', $workflow->meta['initial_diagnosis']);
        self::assertCount(0, $this->connection->rows['diagnosis_assessments']);
    }

    public function test_first_request_creates_only_contact_workflow_and_audit_without_a_fiscal_record(): void
    {
        $this->connection->rows['contact_requests'] = [];
        $this->connection->rows['diagnosis_access_requests'] = [];
        $this->contextReads();
        $source = InitialDiagnosisCommercialService::SOURCE;
        $this->connection->expect('diagnosis_access_requests', ['diagnosis_assessment_id` is null', 'not in'], [$source, 7, 'active', 'inactive', 'rejected'], []);
        $this->connection->expect('diagnosis_access_requests', ['exists', 'published_at` is not null'], [$source, 7, true], []);
        $this->connection->expect('diagnosis_access_requests', ['exists', 'order by'], [$source, 7, 'inactive', true], []);
        $this->connection->expect('diagnosis_access_requests', ['`user_id` = ?', 'exists'], [20, $source, 7, 7, 7, true], []);
        $this->connection->expect('company_diagnosis_settings', ['`company_id` = ?'], [7], []);
        $this->connection->expect('company_tax_profiles', ['`company_id` = ?'], [7], []);
        $this->expectRow('contact_requests', ['`id` = ?'], [30], 30);
        $this->freshReads();
        $workflow = (new InitialDiagnosisCommercialService)->request($this->user());
        self::assertSame('pending', $workflow->status);
        self::assertSame(20, $workflow->user_id);
        self::assertSame(7, $workflow->meta['company_id']);
        self::assertSame(17, $workflow->meta['subscriber_id']);
        self::assertArrayNotHasKey('invoice_id', $workflow->meta['initial_diagnosis']);
        self::assertCount(1, $this->connection->rows['contact_requests']);
        self::assertCount(1, $this->connection->rows['diagnosis_access_requests']);
        self::assertCount(0, $this->connection->rows['diagnosis_assessments']);
        self::assertCount(1, $this->connection->rows['audit_logs']);
    }

    public function test_onboarding_ensure_uses_the_same_invoice_free_preparation(): void
    {
        $this->contextReads();
        $this->pendingPreparationReads();
        $workflow = (new InitialDiagnosisCommercialService)->ensure($this->user());
        self::assertSame(40, $workflow->id);
        self::assertSame('pending', $workflow->status);
        self::assertTrue($workflow->meta['initial_diagnosis']['free']);
    }

    public function test_preparation_preserves_historical_fiscal_metadata_without_querying_it(): void
    {
        $legacy = ['invoice_id' => 999, 'invoice_number' => 'HISTORICAL', 'invoice_issued_at' => '2026-01-01',
            'currency' => 'DOP', 'total' => '0.00', 'confirmation_status' => 'pending'];
        $meta = json_decode($this->connection->rows['diagnosis_access_requests'][40]['meta'], true);
        $meta['initial_diagnosis'] = $legacy;
        $this->connection->rows['diagnosis_access_requests'][40]['meta'] = json_encode($meta);
        $this->contextReads();
        $this->pendingPreparationReads();
        $workflow = (new InitialDiagnosisCommercialService)->ensure($this->user());
        foreach ($legacy as $key => $value) {
            self::assertSame($value, $workflow->meta['initial_diagnosis'][$key]);
        }
    }

    public function test_admin_can_approve_new_access_without_any_invoice(): void
    {
        $this->approvalReads();
        $workflow = $this->approve();
        self::assertSame('active', $workflow->status);
        self::assertSame('confirmed', $workflow->meta['initial_diagnosis']['confirmation_status']);
        self::assertSame(7, $workflow->assessment->organization_id);
        self::assertSame(20, $workflow->assessment->user_id);
        self::assertTrue($workflow->assessment->is_active);
        self::assertSame('draft', $workflow->assessment->status);
        self::assertArrayNotHasKey('invoice_id', $workflow->meta['initial_diagnosis']);
    }

    public function test_approval_preserves_existing_assessment_and_historical_invoice_reference(): void
    {
        $assessment = ['id' => 50, 'user_id' => 20, 'organization_id' => 7, 'status' => 'in_progress', 'is_active' => 1,
            'answers' => json_encode(['STR-01' => 3])];
        $this->connection->rows['diagnosis_assessments'][50] = $assessment;
        $this->connection->rows['diagnosis_access_requests'][40]['diagnosis_assessment_id'] = 50;
        $meta = json_decode($this->connection->rows['diagnosis_access_requests'][40]['meta'], true);
        $meta['initial_diagnosis'] = ['invoice_id' => 999, 'invoice_number' => 'HISTORICAL'];
        $this->connection->rows['diagnosis_access_requests'][40]['meta'] = json_encode($meta);
        $this->approvalReads(true);
        $workflow = $this->approve();
        self::assertSame(50, $workflow->diagnosis_assessment_id);
        self::assertSame(999, $workflow->meta['initial_diagnosis']['invoice_id']);
        self::assertSame('HISTORICAL', $workflow->meta['initial_diagnosis']['invoice_number']);
        self::assertSame($assessment, $this->connection->rows['diagnosis_assessments'][50]);
    }

    public function test_historical_published_diagnosis_state_remains_available_without_loading_its_invoice(): void
    {
        $this->connection->rows['diagnosis_assessments'][50] = ['id' => 50, 'user_id' => 20,
            'organization_id' => 7, 'status' => 'reviewed', 'is_active' => 1, 'current_step' => 11,
            'published_at' => '2026-01-01 12:00:00'];
        $this->connection->rows['diagnosis_access_requests'][40]['status'] = 'active';
        $this->connection->rows['diagnosis_access_requests'][40]['diagnosis_assessment_id'] = 50;
        $meta = json_decode($this->connection->rows['diagnosis_access_requests'][40]['meta'], true);
        $meta['initial_diagnosis'] = ['invoice_id' => 999, 'confirmation_status' => 'confirmed'];
        $this->connection->rows['diagnosis_access_requests'][40]['meta'] = json_encode($meta);
        $before = $this->connection->rows;
        $source = InitialDiagnosisCommercialService::SOURCE;
        $this->connection->expect('company_diagnosis_settings', ['`company_id` = ?'], [7], []);
        $this->connection->expect('diagnosis_access_requests', ['diagnosis_assessment_id` is null', 'not in'], [$source, 7, 'active', 'inactive', 'rejected'], []);
        $this->expectRow('diagnosis_access_requests', ['exists', 'published_at` is not null'], [$source, 7, true], 40);
        $this->expectRow('diagnosis_assessments', [' in (50)'], [], 50);
        $this->connection->expect('diagnosis_access_requests', ['diagnosis_assessment_id` != ?', 'exists'], [$source, 7, 'inactive', 50, true], []);
        $app = Container::getInstance();
        $app->instance('url', new class
        {
            public function route($name, $model, $absolute): string
            {
                return '/diagnostico/'.$model->id;
            }
        });
        $company = (new Company)->setRawAttributes($this->connection->rows['companies'][7], true);
        $state = (new InitialDiagnosisCommercialService)->state($this->user(), $company);
        self::assertSame(50, $state['assessment']['id']);
        self::assertSame('/diagnostico/50', $state['assessment']['url']);
        self::assertSame('confirmed', $state['workflow']['confirmation_status']);
        self::assertTrue($state['assessment']['is_active']);
        self::assertArrayNotHasKey('invoice', $state);
        self::assertSame($before, $this->connection->rows);
    }

    public function test_rejected_request_cannot_be_approved(): void
    {
        $this->connection->rows['diagnosis_access_requests'][40]['status'] = 'rejected';
        $this->expectRow('diagnosis_access_requests', ['for update'], [30], 40);
        $this->expectException(ValidationException::class);
        $this->approve();
    }

    public function test_native_company_identity_is_still_required(): void
    {
        $this->connection->rows['diagnosis_access_requests'][40]['meta'] = json_encode(['source' => InitialDiagnosisCommercialService::SOURCE]);
        $this->expectRow('diagnosis_access_requests', ['for update'], [30], 40);
        $this->expectException(ValidationException::class);
        $this->approve();
    }

    public function test_company_must_belong_to_the_native_subscriber(): void
    {
        $this->expectRow('diagnosis_access_requests', ['for update'], [30], 40);
        $this->connection->expect('companies', ['`subscriber_id` = ?', '`active` = ?'], [7, 17, true], []);
        $this->expectException(ValidationException::class);
        $this->approve();
    }

    public function test_inactive_or_ordinary_members_cannot_request_access(): void
    {
        $this->contextReads(false);
        $this->expectException(ValidationException::class);
        (new InitialDiagnosisCommercialService)->request($this->user());
    }

    public function test_authorized_tenant_can_view_and_edit_but_admin_cannot_submit(): void
    {
        $assessment = (new DiagnosisAssessment)->setRawAttributes(['id' => 50, 'user_id' => 20, 'is_active' => 1, 'status' => 'draft'], true);
        $policy = new DiagnosisAssessmentPolicy;
        self::assertTrue($policy->view($this->user(), $assessment));
        self::assertTrue($policy->submit($this->user(), $assessment));
        self::assertFalse($policy->submit($this->user(99), $assessment));
        $assessment->status = 'submitted';
        self::assertFalse($policy->update($this->user(), $assessment));
    }

    public function test_storage_double_rejects_unknown_or_fiscal_operations(): void
    {
        foreach (['select * from `invoices`', 'select * from `unknown`'] as $sql) {
            try {
                $this->connection->select($sql);
                self::fail('Unscripted read accepted.');
            } catch (LogicException) {
            }
        }
        try {
            $this->connection->insert('insert into `invoice_items` (`id`) values (?)', [1]);
            self::fail('Fiscal write accepted.');
        } catch (LogicException) {
        }
    }
}

/** Rejects all unscripted reads and every mutation outside the four explicit domain/audit tables. */
final class FreeDiagnosisScriptedConnection extends MySqlConnection
{
    public array $reads = [];

    public array $mutations = [];

    public bool $pdoAttempted = false;

    public function __construct(public array $rows)
    {
        parent::__construct(function () {
            $this->pdoAttempted = true;
            throw new LogicException('PDO forbidden in pure QA.');
        },
            'free_diagnosis_memory', '', ['driver' => 'mysql', 'name' => 'free_diagnosis_memory']);
    }

    public function expect(string $table, array $parts, array $bindings, array|Closure $result): void
    {
        $this->reads[] = [$table, $parts, $bindings, $result];
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
        if (preg_match('/\b(invoices|invoice_items|payments|payment_transactions|subscriptions|subscription_items)\b/i', $query)
            || $this->reads === []) {
            throw new LogicException('Unscripted/fiscal SQL: '.$query);
        }
        [$table, $parts, $expected, $rows] = array_shift($this->reads);
        if (! str_contains($query, 'from `'.$table.'`') || $bindings !== $expected) {
            throw new LogicException('Unexpected read: '.$query.'; bindings='.json_encode($bindings));
        }
        foreach ($parts as $part) {
            if (! str_contains($query, $part)) {
                throw new LogicException('Expected SQL fragment '.$part.'; got '.$query);
            }
        }
        $result = $rows instanceof Closure ? $rows() : $rows;

        return array_map(static fn (array $row): object => (object) $row, $result);
    }

    public function transaction(Closure $callback, $attempts = 1)
    {
        $before = $this->rows;
        $this->transactions++;
        try {
            return $callback($this);
        } catch (Throwable $e) {
            $this->rows = $before;
            throw $e;
        } finally {
            $this->transactions--;
        }
    }

    public function insert($query, $bindings = [], $sequence = null)
    {
        if (! preg_match('/\Ainsert into `(contact_requests|diagnosis_access_requests|diagnosis_assessments|audit_logs)` \(([^)]+)\) values \([?, ]+\)\z/', $query, $m)) {
            throw new LogicException('Unapproved insert.');
        }
        preg_match_all('/`([a-z0-9_]+)`/', $m[2], $columns);
        $row = array_combine($columns[1], $bindings);
        $row['id'] ??= $this->rows[$m[1]] === []
            ? (['contact_requests' => 30, 'diagnosis_access_requests' => 40, 'diagnosis_assessments' => 50][$m[1]] ?? 1)
            : max(array_keys($this->rows[$m[1]])) + 1;
        $this->rows[$m[1]][$row['id']] = $row;
        $this->lastInsertId = $row['id'];
        $this->mutations[] = $m[1];

        return true;
    }

    public function update($query, $bindings = [])
    {
        if (! preg_match('/\Aupdate `(contact_requests|diagnosis_access_requests|diagnosis_assessments)` set (.+) where `id` = \?\z/', $query, $m)) {
            throw new LogicException('Unapproved update.');
        }
        preg_match_all('/`([a-z0-9_]+)` = \?/', $m[2], $columns);
        $id = array_pop($bindings);
        if (count($columns[1]) !== count($bindings) || ! isset($this->rows[$m[1]][$id])) {
            throw new LogicException('Unexpected update target.');
        }
        $this->rows[$m[1]][$id] = array_replace($this->rows[$m[1]][$id], array_combine($columns[1], $bindings));
        $this->mutations[] = $m[1];

        return 1;
    }
}
