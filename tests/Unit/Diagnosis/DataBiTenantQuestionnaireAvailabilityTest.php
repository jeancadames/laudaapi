<?php

namespace Tests\Unit\Diagnosis;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Closure;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\BuildsDataBiTenantDeclarationHttpApplication;
use Tests\Support\BuildsDataBiTenantDeclarationPayloads;

/** HTTP y servicio reales, lecturas simuladas. No PDO, dotenv ni SQL de escritura. */
final class DataBiTenantQuestionnaireAvailabilityTest extends TestCase
{
    use BuildsDataBiTenantDeclarationHttpApplication;
    use BuildsDataBiTenantDeclarationPayloads;

    private T4eReadFixtureConnection $connection;

    private array $missing = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->startHttpApplication();
        $snapshot = $this->reviewSnapshot();
        $this->connection = new T4eReadFixtureConnection([
            'users' => ['id' => 501, 'email_verified_at' => '2026-10-31 00:00:00'],
            'companies' => ['id' => 701, 'subscriber_id' => 801, 'owner_user_id' => 501, 'active' => 1],
            'subscribers' => ['id' => 801, 'active' => 1],
            'subscriber_user' => ['user_id' => 501, 'subscriber_id' => 801, 'active' => 1, 'role' => 'admin'],
            'transformation_implementation_requests' => ['id' => 101, 'company_id' => 701, 'capability_key' => 'data_transformation_bi'],
            'data_bi_information_requests' => ['id' => 202, 'company_id' => 701, 'transformation_implementation_request_id' => 101,
                'version' => 1, 'status' => 'sent', 'sent_at' => '2026-10-31 00:00:00', 'sent_by_user_id' => 501,
                'content_sha256' => $snapshot['content_sha256'], 'review_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)],
        ]);
        $capsule = new Capsule($this->httpApp);
        $capsule->addConnection(['driver' => 'mysql', 'name' => 't4e_read_fixture'], 't4e_read_fixture');
        $manager = $capsule->getDatabaseManager();
        $manager->extend('mysql', fn () => $this->connection);
        $manager->setDefaultConnection('t4e_read_fixture');
        $this->httpApp->instance('db', $manager);
        DB::clearResolvedInstance('db');
        Model::clearBootedModels();
        Model::setConnectionResolver($manager);
        Model::setEventDispatcher($this->httpApp['events']);
        Schema::swap(new class($this->missing)
        {
            public function __construct(private array &$missing) {}

            public function hasTable(string $table): bool
            {
                return ! in_array($table, $this->missing, true);
            }

            public function hasColumn(string $table, string $column): bool
            {
                return ! in_array($table.'.'.$column, $this->missing, true);
            }

            public function hasColumns(string $table, array $columns): bool
            {
                return array_all($columns, fn (string $column): bool => $this->hasColumn($table, $column));
            }
        });
        $actor = new User;
        $actor->setRawAttributes($this->connection->rows['users']);
        $actor->exists = true;
        $this->httpApp['auth']->guard()->setUser($actor);
        // Aislar el contrato JSON de las consultas ajenas de props compartidas de Inertia.
        $this->httpApp['router']->getRoutes()->getByName('app.transformation.data_bi.information_requirements.show')
            ->withoutMiddleware(HandleInertiaRequests::class);
    }

    protected function tearDown(): void
    {
        $this->stopHttpApplication();
        parent::tearDown();
    }

    private function questionnaire()
    {
        return $this->declarationHttp('GET', '/app/transformacion-360/datos-bi/requerimientos/101');
    }

    public function test_published_authorized_questionnaire_enables_response_using_real_t4c_checks(): void
    {
        $response = $this->questionnaire();
        $response->assertOk()->assertJsonPath('can_respond', true)->assertJsonPath('version', 1);
        self::assertNotEmpty($response->json('requirements'));
        self::assertStringNotContainsString('content_sha256', $response->getContent());
        self::assertNotContains('data_bi_tenant_declarations', array_keys($this->connection->rows));
    }

    #[DataProvider('missingSchema')]
    public function test_missing_schema_keeps_the_questionnaire_readable_and_response_disabled(string $missing): void
    {
        $this->missing[] = $missing;
        $this->questionnaire()->assertOk()->assertJsonPath('can_respond', false)->assertJsonCount(1, 'objectives');
    }

    public static function missingSchema(): iterable
    {
        yield 'table' => ['data_bi_tenant_declarations'];
        yield 'publication audit' => ['data_bi_information_requests.sent_by_user_id'];
        foreach (['id', 'data_bi_information_request_id', 'company_id', 'status', 'revision', 'declaration_snapshot',
            'content_sha256', 'created_by_user_id', 'updated_by_user_id', 'submitted_by_user_id', 'submitted_at', 'created_at', 'updated_at'] as $column) {
            yield $column => ['data_bi_tenant_declarations.'.$column];
        }
    }

    public function test_missing_endpoint_fails_closed_without_losing_readonly_publication(): void
    {
        $this->httpApp['router']->getRoutes()->getByName('app.transformation.data_bi.information_requirements.declaration.submit')->name('.disabled');
        $this->httpApp['router']->getRoutes()->refreshNameLookups();
        $this->questionnaire()->assertOk()->assertJsonPath('can_respond', false);
    }

    public function test_inactive_subscriber_or_company_cannot_enable_response(): void
    {
        foreach (['companies', 'subscribers'] as $table) {
            $this->connection->rows[$table]['active'] = 0;
            $this->questionnaire()->assertOk()->assertJsonPath('can_respond', false);
            $this->connection->rows[$table]['active'] = 1;
        }
    }

    public function test_unknown_membership_is_concealed(): void
    {
        unset($this->connection->rows['subscriber_user']);
        $this->questionnaire()->assertNotFound();
    }

    public function test_corrupt_published_hash_does_not_enable_editing(): void
    {
        $this->connection->rows['data_bi_information_requests']['content_sha256'] = str_repeat('f', 64);
        $this->questionnaire()->assertOk()->assertJsonPath('can_respond', false);
    }
}

/** Toda lectura sale del fixture; cualquier acceso a PDO o DML falla inmediatamente. */
final class T4eReadFixtureConnection extends MySqlConnection
{
    public function __construct(public array $rows)
    {
        parent::__construct(fn () => throw new LogicException('PDO prohibido en QA pura T4E.'), 't4e_fixture', '', ['name' => 't4e_read_fixture']);
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
        if (! preg_match('/\Aselect .* from `([a-z_]+)`/is', $query, $match)) {
            throw new LogicException('Solo se permiten lecturas simuladas conocidas.');
        }
        $row = $this->rows[$match[1]] ?? null;
        if ($row === null || (str_contains($query, '`active` = ?') && ($row['active'] ?? 1) !== 1)) {
            return [];
        }

        return [(object) $row];
    }

    public function transaction(Closure $callback, $attempts = 1)
    {
        return $callback($this);
    }
}
