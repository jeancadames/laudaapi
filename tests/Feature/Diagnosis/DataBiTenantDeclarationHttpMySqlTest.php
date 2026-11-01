<?php

namespace Tests\Feature\Diagnosis;

use App\Models\DataBiInformationRequest;
use App\Models\DataBiTenantDeclaration;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use App\Services\Diagnosis\DataBiInformationTenantDeclarationContract;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connectors\MySqlConnector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use LogicException;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\Support\BuildsDataBiTenantDeclarationHttpApplication;
use Tests\Support\BuildsDataBiTenantDeclarationPayloads;
use Throwable;

// Reutiliza exclusivamente la conexión rollback-only ya verificada en T4C-M5.
// Cargar su definición no ejecuta ningún test ni abre conexiones.
require_once __DIR__.'/DataBiTenantDeclarationServiceMySqlTest.php';

/**
 * D2F-T4D · PREPARADA, NO EJECUTAR sin autorización posterior.
 * --no-configuration --bootstrap vendor/autoload.php (nunca PHPUnit SQLite ni dotenv).
 * APP_ENV=local, D2F_T4D_HTTP_MYSQL_QA=rollback-only,
 * D2F_T4D_QA_DATABASE=laudaapi_dev, D2F_T4D_QA_HOST=127.0.0.1|localhost,
 * D2F_T4D_QA_USER, D2F_T4D_QA_PASSWORD explícitos; D2F_T4D_QA_PORT opcional.
 * No migra, no confirma fixtures, no utiliza empresas ni solicitudes existentes.
 * Sesión web, cookie cifrada, binding real, CSRF activo y servicio T4C real.
 */
#[Group('mysql-dev-http-rollback')]
final class DataBiTenantDeclarationHttpMySqlTest extends TestCase
{
    use BuildsDataBiTenantDeclarationHttpApplication;
    use BuildsDataBiTenantDeclarationPayloads;

    private ?Capsule $capsule = null;

    private array $ownedConnections = [];

    private array $fixtureIds = [];

    private array $dml = [];

    private string $primaryName;

    private string $probeName;

    private int $assessmentId;

    private int $planId;

    private int $phaseId;

    private int $subscriberId;

    private TransformationImplementationRequest $request;

    private DataBiInformationRequest $questionnaire;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        // M2: el comparador se prueba sin aplicación, credenciales, PDO ni fixtures.
        if (str_starts_with($this->name(), 'test_json_comparator_')) {
            return;
        }
        // Antes de crear aplicación, resolver PDO o leer configuración externa.
        if (getenv('D2F_T4D_HTTP_MYSQL_QA') !== 'rollback-only' || getenv('APP_ENV') !== 'local'
            || getenv('D2F_T4D_QA_DATABASE') !== 'laudaapi_dev'
            || ! in_array(getenv('D2F_T4D_QA_HOST'), ['127.0.0.1', 'localhost'], true)
            || ! is_string(getenv('D2F_T4D_QA_USER')) || trim(getenv('D2F_T4D_QA_USER')) === ''
            || getenv('D2F_T4D_QA_PASSWORD') === false) {
            self::markTestSkipped('HTTP_MYSQL_QA=PENDING: requiere autorización DEV rollback-only y credenciales de proceso explícitas.');
        }
        $port = getenv('D2F_T4D_QA_PORT') ?: '3306';
        if (! ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
            self::markTestSkipped('Puerto QA inválido; no se abren conexiones.');
        }
        try {
            $this->startHttpApplication();
            $this->capsule = new Capsule($this->httpApp);
            $suffix = bin2hex(random_bytes(8));
            $this->primaryName = 't4d_primary_'.$suffix;
            $this->probeName = 't4d_probe_'.$suffix;
            $configuration = [
                'driver' => 'mysql', 'host' => getenv('D2F_T4D_QA_HOST'), 'port' => $port,
                'database' => 'laudaapi_dev', 'username' => getenv('D2F_T4D_QA_USER'),
                'password' => getenv('D2F_T4D_QA_PASSWORD'), 'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
            ];
            foreach ([$this->primaryName, $this->probeName] as $name) {
                $this->capsule->addConnection([...$configuration, 'name' => $name], $name);
            }
            $manager = $this->capsule->getDatabaseManager();
            $manager->setDefaultConnection($this->primaryName);
            $manager->extend('mysql', function (array $config, string $name): T4cRollbackOnlyMySqlConnection {
                if (! in_array($name, [$this->primaryName, $this->probeName], true)
                    || ($config['name'] ?? null) !== $name) {
                    throw new LogicException('Identidad de conexión QA inválida.');
                }
                $connection = new T4cRollbackOnlyMySqlConnection(
                    (new MySqlConnector)->connect($config), $name, $name === $this->probeName
                );
                $this->ownedConnections[$name] = $connection;
                $connection->beforeExecuting(fn () => $this->assertDatabaseRouting());

                return $connection;
            });
            $this->capsule->setEventDispatcher($this->httpApp['events']);
            $this->httpApp->instance('db', $manager);
            $this->httpApp->bind('db.schema', fn () => $manager->connection()->getSchemaBuilder());
            DB::clearResolvedInstance('db');
            Schema::clearResolvedInstance('db.schema');
            Model::clearBootedModels();
            $this->capsule->bootEloquent();
            $primary = $manager->connection($this->primaryName);
            $probe = $manager->connection($this->probeName);
            $primary->openOuterTransaction();
            $probe->openOuterTransaction();
            self::assertNotSame($primary->getPdo(), $probe->getPdo());
            $sessions = [];
            foreach ($this->ownedConnections as $name => $connection) {
                self::assertSame($name, $connection->getName());
                self::assertSame('mysql', $connection->getDriverName());
                $session = $connection->selectOne('SELECT DATABASE() AS db, CONNECTION_ID() AS session_id');
                self::assertSame('laudaapi_dev', $session->db);
                $sessions[] = $session->session_id;
            }
            self::assertNotSame($sessions[0], $sessions[1]);
            foreach (T4cRollbackOnlyMySqlConnection::FIXTURE_TABLES as $table) {
                $engine = DB::selectOne('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
                    ['laudaapi_dev', $table]);
                self::assertNotNull($engine, 'Falta tabla previamente migrada: '.$table);
                self::assertSame('innodb', strtolower($engine->engine));
                // Metadata lock hasta rollback: no seleccionar datos empresariales.
                DB::select('SELECT id FROM `'.$table.'` WHERE 1 = 0');
            }
            self::assertTrue(Schema::hasColumn('data_bi_information_requests', 'sent_by_user_id'));
            $this->assertNoTriggers();
            $this->httpApp['events']->listen(QueryExecuted::class, function (QueryExecuted $event): void {
                if ($event->connection->getName() !== $this->primaryName
                    || ! preg_match('/\A\s*(insert|update|delete)\b/i', $event->sql)) {
                    return;
                }
                $this->dml[] = $event->sql;
                if (preg_match('/\A\s*insert\s+into\s+`([a-z0-9_]+)`/i', $event->sql, $match)) {
                    $id = (int) $event->connection->getPdo()->lastInsertId();
                    self::assertGreaterThan(0, $id);
                    $this->fixtureIds[$match[1]][$id] = $id;
                }
            });
            $primary->allowFixtures();
            $this->createSyntheticScope();
            $this->authenticate($this->actor);
            $this->questionnaire = $this->publish();
            self::assertSame(0, $probe->table('transformation_implementation_requests')->where('id', $this->request->id)->count());
        } catch (Throwable $e) {
            $this->cleanupDatabase();
            throw $e;
        }
    }

    protected function tearDown(): void
    {
        if (str_starts_with($this->name(), 'test_json_comparator_')) {
            parent::tearDown();

            return;
        }
        try {
            if ($this->capsule !== null) {
                $this->assertDatabaseRouting();
                foreach ($this->ownedConnections as $connection) {
                    $connection->assertOuterTransaction();
                    self::assertSame(1, $connection->transactionLevel());
                }
                $this->ownedConnections[$this->primaryName]->rollbackOuterTransaction();
                foreach ($this->fixtureIds as $table => $ids) {
                    self::assertSame(0, $this->ownedConnections[$this->probeName]->table($table)
                        ->whereIn('id', array_values($ids))->count(), 'Residuos sintéticos tras rollback: '.$table);
                }
            }
        } finally {
            try {
                $this->cleanupDatabase();
            } finally {
                Carbon::setTestNow();
                parent::tearDown();
            }
        }
    }

    private function cleanupDatabase(): void
    {
        $failure = null;
        try {
            foreach ($this->ownedConnections as $name => $connection) {
                try {
                    $connection->rollbackOuterTransaction();
                } catch (Throwable $e) {
                    $failure ??= $e;
                } finally {
                    try {
                        $this->capsule?->getDatabaseManager()->purge($name);
                    } catch (Throwable $e) {
                        $failure ??= $e;
                    }
                }
            }
        } finally {
            $this->ownedConnections = [];
            $this->capsule = null;
            $this->stopHttpApplication();
        }
        if ($failure !== null) {
            throw $failure;
        }
    }

    private function assertDatabaseRouting(): void
    {
        $manager = $this->capsule->getDatabaseManager();
        self::assertSame($manager, DB::getFacadeRoot());
        self::assertSame($manager, Model::getConnectionResolver());
        self::assertSame($this->primaryName, $manager->getDefaultConnection());
        self::assertSame($this->ownedConnections[$this->primaryName], DB::connection());
        foreach ([User::class, TransformationImplementationRequest::class,
            DataBiInformationRequest::class, DataBiTenantDeclaration::class] as $model) {
            self::assertSame(DB::connection(), (new $model)->getConnection());
        }
    }

    private function assertNoTriggers(): void
    {
        $grantee = "CONCAT(QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', 1)), '@', QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', -1)))";
        $global = DB::selectOne("SELECT COUNT(*) AS n FROM information_schema.USER_PRIVILEGES WHERE PRIVILEGE_TYPE = 'TRIGGER' AND GRANTEE = ".$grantee);
        $schema = DB::selectOne("SELECT COUNT(*) AS n FROM information_schema.SCHEMA_PRIVILEGES WHERE PRIVILEGE_TYPE = 'TRIGGER' AND TABLE_SCHEMA = DATABASE() AND GRANTEE = ".$grantee);
        if ((int) $global->n + (int) $schema->n === 0) {
            throw new LogicException('Sin visibilidad directa de triggers: QA bloqueada antes del DML.');
        }
        self::assertSame(0, DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', 'laudaapi_dev')
            ->whereIn('EVENT_OBJECT_TABLE', T4cRollbackOnlyMySqlConnection::FIXTURE_TABLES)->count(),
            'QA requiere ausencia acreditada de triggers antes de insertar fixtures.');
    }

    /** Cadena FK completa, nueva y reversible; nunca se selecciona una solicitud empresarial. */
    private function createSyntheticScope(): void
    {
        $this->subscriberId = DB::table('subscribers')->insertGetId([
            'name' => 'T4D QA sintético', 'slug' => 't4d-'.bin2hex(random_bytes(12)), 'active' => 1,
        ]);
        $this->actor = $this->tenant();
        $company = DB::table('companies')->insertGetId([
            'name' => 'T4D QA sintético', 'slug' => 't4d-'.bin2hex(random_bytes(12)),
            'subscriber_id' => $this->subscriberId, 'owner_user_id' => $this->actor->id, 'active' => 1,
        ]);
        $this->assessmentId = DB::table('diagnosis_assessments')->insertGetId([
            'user_id' => $this->actor->id, 'organization_id' => $company,
            'organization_name' => 'T4D QA sintético', 'status' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $roadmap = DB::table('diagnosis_detailed_roadmaps')->insertGetId([
            'diagnosis_assessment_id' => $this->assessmentId, 'version' => 1, 'status' => 'draft',
            'source_snapshot' => '{}', 'roadmap' => '{}', 'generated_by_user_id' => $this->actor->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->planId = DB::table('transformation_implementation_plans')->insertGetId([
            'diagnosis_assessment_id' => $this->assessmentId, 'diagnosis_detailed_roadmap_id' => $roadmap,
            'version' => 1, 'status' => 'draft', 'created_by_user_id' => $this->actor->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->phaseId = DB::table('transformation_implementation_phases')->insertGetId([
            'transformation_implementation_plan_id' => $this->planId,
            'sequence' => 1, 'name' => 'T4D QA sintético', 'created_by_user_id' => $this->actor->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->request = $this->syntheticRequest($company, 'data_transformation_bi');
        self::assertSame($this->primaryName, $this->request->getConnectionName());
    }

    private function syntheticRequest(int $company, string $capability): TransformationImplementationRequest
    {
        $phaseCapability = DB::table('transformation_implementation_phase_capabilities')->insertGetId([
            'transformation_implementation_phase_id' => $this->phaseId, 'sequence' => 1,
            'capability_key' => $capability, 'capability_label' => 'T4D QA sintético',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $id = DB::table('transformation_implementation_requests')->insertGetId([
            'company_id' => $company, 'diagnosis_assessment_id' => $this->assessmentId,
            'transformation_implementation_plan_id' => $this->planId,
            'transformation_implementation_phase_capability_id' => $phaseCapability,
            'capability_key' => $capability, 'attempt' => 1, 'source_type' => 'tenant_admin',
            'status' => 'requested', 'source_snapshot' => '{}', 'requested_by_user_id' => $this->actor->id,
            'requested_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return TransformationImplementationRequest::query()->findOrFail($id);
    }

    private function tenant(?int $subscriberId = null, string $role = 'admin'): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'T4D QA', 'email' => 't4d-'.bin2hex(random_bytes(12)).'@example.test',
            'password' => '*', 'role' => 'user', 'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('subscriber_user')->insert([
            'subscriber_id' => $subscriberId ?? $this->subscriberId, 'user_id' => $id,
            'role' => $role, 'active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }

    private function authenticate(?User $user): void
    {
        $guard = $this->httpApp['auth']->guard('web');
        $session = $this->httpApp['session.store'];
        $session->forget($guard->getName());
        if ($user !== null) {
            $session->put($guard->getName(), (int) $user->getKey());
        }
        $session->save();
        $this->httpApp['auth']->forgetGuards();
    }

    private function publish(bool $sent = true): DataBiInformationRequest
    {
        $version = (int) DataBiInformationRequest::query()
            ->where('transformation_implementation_request_id', $this->request->id)->max('version') + 1;
        self::assertLessThanOrEqual(9999, $version);
        $snapshot = $this->reviewSnapshot();
        $questionnaire = new DataBiInformationRequest;
        $questionnaire->forceFill([
            'transformation_implementation_request_id' => $this->request->id,
            'company_id' => $this->request->company_id, 'version' => $version,
            'status' => $sent ? 'sent' : 'draft', 'review_snapshot' => $snapshot,
            'content_sha256' => $snapshot['content_sha256'], 'created_by_user_id' => $this->actor->id,
            'sent_at' => $sent ? now() : null, 'sent_by_user_id' => $sent ? $this->actor->id : null,
        ])->save();

        return $questionnaire->refresh();
    }

    private function input(bool $complete = true): array
    {
        return $this->declarationInput($this->questionnaire->version, $this->questionnaire->review_snapshot, $complete);
    }

    private function url(string $suffix = ''): string
    {
        return $this->declarationUrl($suffix, (int) $this->request->id);
    }

    private function postDeclaration(string $suffix, bool $complete = false, int $revision = 0): TestResponse
    {
        return $this->declarationHttp('POST', $this->url($suffix), [...$this->input($complete), 'expected_revision' => $revision]);
    }

    private function row(): array
    {
        return (array) DB::table('data_bi_tenant_declarations')
            ->where('data_bi_information_request_id', $this->questionnaire->id)->sole();
    }

    private function assertPublicState(TestResponse $response, string $status, int $revision): void
    {
        $response->assertOk()->assertJsonPath('data.version', $this->questionnaire->version)
            ->assertJsonPath('data.declaration.status', $status)->assertJsonPath('data.declaration.revision', $revision)
            ->assertJsonPath('data.declaration.version', $this->questionnaire->version)
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertSame(['implementation_request_id', 'version', 'declaration'], array_keys($response->json('data')));
        self::assertSame(['id', 'status', 'revision', 'version', 'submitted_at', 'declarations'],
            array_keys($response->json('data.declaration')));
        foreach (['content_sha256', 'questionnaire_sha256', 'review_snapshot', 'declaration_snapshot',
            'company_id', 'subscriber_id', 'created_by_user_id', 'updated_by_user_id', 'submitted_by_user_id'] as $private) {
            self::assertStringNotContainsString('"'.$private.'"', $response->getContent());
        }
    }

    private static function assertSameJsonObjects(string $expected, string $actual): void
    {
        // Decodificar como objetos conserva {} frente a [] y claves numéricas de objetos.
        // Comparar el JSON normalizado con assertSame conserva los tipos de los escalares.
        $flags = JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_PRETTY_PRINT;
        self::assertSame(
            json_encode(self::orderedJsonObjects(json_decode($expected, flags: JSON_THROW_ON_ERROR)), $flags),
            json_encode(self::orderedJsonObjects(json_decode($actual, flags: JSON_THROW_ON_ERROR)), $flags)
        );
    }

    private static function orderedJsonObjects(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $properties = get_object_vars($value);
            foreach ($properties as &$property) {
                $property = self::orderedJsonObjects($property);
            }
            unset($property);
            ksort($properties, SORT_STRING);

            return (object) $properties;
        }

        // Las listas se recorren sin ordenar, eliminar ni agregar elementos.
        return is_array($value) ? array_map(self::orderedJsonObjects(...), $value) : $value;
    }

    #[Group('pure-json-comparator')]
    #[DataProvider('jsonComparatorModes')]
    public function test_json_comparator_accepts_reordered_objects_in_real_t4b_response_shapes(string $status): void
    {
        self::assertNull($this->capsule);
        self::assertSame([], $this->ownedConnections);
        $snapshot = $this->reviewSnapshot();
        $normalized = DataBiInformationTenantDeclarationContract::normalize([
            'id' => 202, 'version' => 1, 'status' => 'sent',
            'content_sha256' => $snapshot['content_sha256'], 'review_snapshot' => $snapshot,
        ], $this->declarationInput(1, $snapshot, $status === 'submitted'), $status === 'submitted' ? 'submit' : 'draft');
        $before = ['data' => ['implementation_request_id' => 101, 'version' => 1, 'declaration' => [
            'id' => 303, 'status' => $status, 'revision' => $status === 'draft' ? 1 : 2, 'version' => 1,
            'submitted_at' => $status === 'submitted' ? '2026-10-31T12:00:00+00:00' : null,
            'declarations' => $normalized['declarations'],
        ]]];
        $after = $before;
        $after['data']['declaration']['declarations'] = array_map(static fn (array $item): array => [
            'notes' => $item['notes'],
            'sources' => array_map(static fn (array $source): array => [
                'notes' => $source['notes'], 'structures' => $source['structures'],
                'name' => $source['name'], 'type' => $source['type'],
            ], $item['sources']),
            'availability' => $item['availability'], 'requirement_key' => $item['requirement_key'],
        ], $normalized['declarations']);
        // Reproduce el fallo anterior sin alterar ningún dato ni orden de listas.
        self::assertNotSame($before, $after);
        self::assertSameJsonObjects(json_encode($before, JSON_THROW_ON_ERROR), json_encode($after, JSON_THROW_ON_ERROR));
    }

    public static function jsonComparatorModes(): iterable
    {
        yield 'draft versus GET or identical retry' => ['draft'];
        yield 'submitted versus GET or identical retry' => ['submitted'];
    }

    #[Group('pure-json-comparator')]
    public function test_json_comparator_accepts_reordered_objects_at_every_depth(): void
    {
        self::assertSameJsonObjects(
            '{"version":1,"data":{"declarations":[{"requirement_key":"a","sources":[{"name":"ERP","structures":["A","B"]}]}]},"empty":{}}',
            '{"empty":{},"data":{"declarations":[{"sources":[{"structures":["A","B"],"name":"ERP"}],"requirement_key":"a"}]},"version":1}'
        );
        self::assertSameJsonObjects('{"1":"b","0":"a"}', '{"0":"a","1":"b"}');
    }

    #[Group('pure-json-comparator')]
    #[DataProvider('differentJsonValues')]
    public function test_json_comparator_rejects_every_difference_except_object_key_order(string $expected, string $actual): void
    {
        $this->expectException(AssertionFailedError::class);
        self::assertSameJsonObjects($expected, $actual);
    }

    public static function differentJsonValues(): iterable
    {
        yield 'requirement list order' => ['{"declarations":[{"requirement_key":"a"},{"requirement_key":"b"}]}',
            '{"declarations":[{"requirement_key":"b"},{"requirement_key":"a"}]}'];
        yield 'source list order' => ['{"declarations":[{"sources":[{"name":"ERP"},{"name":"CRM"}]}]}',
            '{"declarations":[{"sources":[{"name":"CRM"},{"name":"ERP"}]}]}'];
        yield 'structure list order' => ['{"sources":[{"structures":["A","B"]}]}', '{"sources":[{"structures":["B","A"]}]}'];
        yield 'list element missing' => ['{"items":["A","B"]}', '{"items":["A"]}'];
        yield 'list element added' => ['{"items":["A"]}', '{"items":["A","B"]}'];
        yield 'duplicate list element lost' => ['{"items":["A","A"]}', '{"items":["A"]}'];
        yield 'object attribute missing' => ['{"id":1,"notes":null}', '{"id":1}'];
        yield 'object attribute added' => ['{"id":1}', '{"id":1,"notes":null}'];
        yield 'integer versus string' => ['{"revision":1}', '{"revision":"1"}'];
        yield 'integer versus float' => ['{"revision":1}', '{"revision":1.0}'];
        yield 'boolean versus integer' => ['{"data_inspected":false}', '{"data_inspected":0}'];
        yield 'null versus empty string' => ['{"notes":null}', '{"notes":""}'];
        yield 'empty object versus list' => ['{"sources":{}}', '{"sources":[]}'];
        yield 'numeric object keys versus list' => ['{"items":{"1":"B","0":"A"}}', '{"items":["A","B"]}'];
        yield 'string whitespace' => ['{"notes":" texto "}', '{"notes":"texto"}'];
        foreach ([
            'status' => ['draft', 'submitted'], 'revision' => [1, 2], 'version' => [1, 2],
            'id' => [101, 102], 'implementation_request_id' => [201, 202],
            'submitted_at' => ['2026-10-31T12:00:00+00:00', '2026-10-31T12:00:01+00:00'],
            'created_by_user_id' => [301, 302], 'updated_by_user_id' => [301, 302], 'submitted_by_user_id' => [301, 302],
            'created_at' => ['2026-10-31 12:00:00', '2026-10-31 12:00:01'],
            'updated_at' => ['2026-10-31 12:00:00', '2026-10-31 12:00:01'],
            'content_sha256' => [str_repeat('a', 64), str_repeat('b', 64)],
            'questionnaire_sha256' => [str_repeat('a', 64), str_repeat('b', 64)],
        ] as $field => [$expected, $actual]) {
            yield 'protected value '.$field => [
                json_encode(['data' => ['declaration' => [$field => $expected]]], JSON_THROW_ON_ERROR),
                json_encode(['data' => ['declaration' => [$field => $actual]]], JSON_THROW_ON_ERROR),
            ];
        }
    }

    public function test_get_without_declaration_is_explicit_and_performs_no_dml(): void
    {
        $before = $this->dml;
        $response = $this->declarationHttp('GET', $this->url());
        $response->assertOk()->assertExactJson(['data' => [
            'implementation_request_id' => (int) $this->request->id,
            'version' => $this->questionnaire->version, 'declaration' => null,
        ]]);
        self::assertSame($before, $this->dml);
        self::assertFalse(DataBiTenantDeclaration::query()->where('data_bi_information_request_id', $this->questionnaire->id)->exists());
    }

    public function test_initial_incomplete_draft_and_get_return_only_normalized_public_fields(): void
    {
        $published = $this->questionnaire->getRawOriginal();
        $saved = $this->postDeclaration('/borrador');
        $this->assertPublicState($saved, 'draft', 1);
        $saved->assertJsonPath('data.declaration.submitted_at', null)
            ->assertJsonPath('data.declaration.declarations.0.availability', 'pending');
        $before = $this->dml;
        $get = $this->declarationHttp('GET', $this->url());
        $this->assertPublicState($get, 'draft', 1);
        self::assertSameJsonObjects($saved->getContent(), $get->getContent());
        self::assertSame($before, $this->dml);
        self::assertSame($published, $this->questionnaire->fresh()->getRawOriginal());
        self::assertSame((int) $this->actor->id, (int) $this->row()['created_by_user_id']);
    }

    public function test_empty_strings_and_whitespace_are_normalized_by_t4b_without_web_coercion(): void
    {
        $input = $this->input();
        $input['declarations'][0]['notes'] = '';
        $input['declarations'][0]['sources'][0]['name'] = '  ERP  ';
        $input['declarations'][0]['sources'][0]['notes'] = '';
        $expected = DataBiInformationTenantDeclarationContract::normalize($this->questionnaire->attributesToArray(), $input, 'draft');
        $response = $this->declarationHttp('POST', $this->url('/borrador'), [...$input, 'expected_revision' => 0]);
        $this->assertPublicState($response, 'draft', 1);
        self::assertSame($expected['declarations'], $response->json('data.declaration.declarations'));
    }

    public function test_edit_checks_revision_and_preserves_creator_while_recording_editor(): void
    {
        $this->postDeclaration('/borrador')->assertOk();
        $editor = $this->tenant();
        $this->authenticate($editor);
        $this->assertPublicState($this->postDeclaration('/borrador', true, 1), 'draft', 2);
        self::assertSame((int) $this->actor->id, (int) $this->row()['created_by_user_id']);
        self::assertSame((int) $editor->id, (int) $this->row()['updated_by_user_id']);
    }

    public function test_complete_submission_and_get_expose_submission_date_without_actor_audit(): void
    {
        $this->postDeclaration('/borrador', true)->assertOk();
        $response = $this->postDeclaration('/enviar', true, 1);
        $this->assertPublicState($response, 'submitted', 2);
        self::assertNotNull($response->json('data.declaration.submitted_at'));
        self::assertSame((int) $this->actor->id, (int) $this->row()['submitted_by_user_id']);
        $get = $this->declarationHttp('GET', $this->url());
        self::assertSameJsonObjects($response->getContent(), $get->getContent());
    }

    public function test_direct_complete_submission_is_supported(): void
    {
        $this->assertPublicState($this->postDeclaration('/enviar', true), 'submitted', 1);
    }

    public function test_identical_create_edit_and_submit_retries_do_no_dml_and_preserve_all_audit(): void
    {
        foreach ([['/borrador', false, 0], ['/borrador', true, 1], ['/enviar', true, 2]] as [$suffix, $complete, $revision]) {
            $this->authenticate($this->actor);
            Carbon::setTestNow(Carbon::now()->addSeconds(5)->microsecond(123456));
            $first = $this->postDeclaration($suffix, $complete, $revision)->assertOk();
            $beforeRow = $this->row();
            $otherAdmin = $this->tenant();
            $this->authenticate($otherAdmin);
            $beforeDml = $this->dml;
            Carbon::setTestNow(Carbon::now()->addSeconds(5));
            $retry = $this->postDeclaration($suffix, $complete, $revision)->assertOk();
            self::assertSameJsonObjects($first->getContent(), $retry->getContent());
            self::assertSame($beforeRow, $this->row());
            self::assertSame($beforeDml, $this->dml);
        }
        self::assertSame(1, DataBiTenantDeclaration::query()->where('data_bi_information_request_id', $this->questionnaire->id)->count());
    }

    public function test_stale_future_and_nonexistent_revisions_are_409_without_writes(): void
    {
        $before = $this->dml;
        $this->assertDeclarationError($this->postDeclaration('/borrador', false, 1), 409, 'revision_conflict');
        self::assertSame($before, $this->dml);
        $this->postDeclaration('/borrador')->assertOk();
        $this->postDeclaration('/borrador', true, 1)->assertOk();
        $beforeRow = $this->row();
        $before = $this->dml;
        foreach ([['/borrador', false, 1], ['/borrador', true, 0], ['/borrador', true, 3], ['/enviar', true, 1]] as [$suffix, $complete, $revision]) {
            $this->assertDeclarationError($this->postDeclaration($suffix, $complete, $revision), 409, 'revision_conflict');
        }
        self::assertSame($beforeRow, $this->row());
        self::assertSame($before, $this->dml);
    }

    public function test_incomplete_submission_is_422_and_keeps_draft(): void
    {
        $this->postDeclaration('/borrador')->assertOk();
        $row = $this->row();
        $before = $this->dml;
        $this->assertDeclarationError($this->postDeclaration('/enviar', false, 1), 422, 'validation_failed');
        self::assertSame($row, $this->row());
        self::assertSame($before, $this->dml);
    }

    public function test_submitted_declaration_cannot_be_changed_or_returned_to_draft(): void
    {
        $this->postDeclaration('/enviar', true)->assertOk();
        $row = $this->row();
        $changed = $this->input();
        $changed['declarations'][0]['notes'] = 'Cambio posterior.';
        $before = $this->dml;
        $this->assertDeclarationError($this->declarationHttp('POST', $this->url('/enviar'),
            [...$changed, 'expected_revision' => 1]), 409, 'declaration_submitted');
        $this->assertDeclarationError($this->postDeclaration('/borrador', true, 1), 409, 'declaration_submitted');
        self::assertSame($row, $this->row());
        self::assertSame($before, $this->dml);
    }

    public function test_replaced_questionnaire_returns_409_and_get_starts_new_scope_without_carrying_answers(): void
    {
        $this->postDeclaration('/borrador', true)->assertOk();
        $row = $this->row();
        $old = $this->input();
        $oldQuestionnaire = $this->questionnaire;
        $this->questionnaire = $this->publish();
        $before = $this->dml;
        foreach (['/borrador', '/enviar'] as $suffix) {
            $this->assertDeclarationError($this->declarationHttp('POST', $this->url($suffix),
                [...$old, 'expected_revision' => 1]), 409, 'questionnaire_replaced');
        }
        $this->declarationHttp('GET', $this->url())->assertOk()
            ->assertJsonPath('data.version', $this->questionnaire->version)->assertJsonPath('data.declaration', null);
        self::assertSame($before, $this->dml);
        self::assertSame($row, (array) DB::table('data_bi_tenant_declarations')
            ->where('data_bi_information_request_id', $oldQuestionnaire->id)->sole());
        $this->assertPublicState($this->postDeclaration('/borrador'), 'draft', 1);
    }

    public function test_new_lauda_draft_does_not_replace_the_published_version(): void
    {
        $this->publish(false);
        $this->assertPublicState($this->postDeclaration('/borrador'), 'draft', 1);
    }

    public function test_without_publication_all_operations_are_404_without_writes(): void
    {
        DB::table('data_bi_information_requests')->where('id', $this->questionnaire->id)
            ->update(['status' => 'draft', 'sent_at' => null, 'sent_by_user_id' => null]);
        $before = $this->dml;
        $this->assertDeclarationError($this->declarationHttp('GET', $this->url()), 404, 'questionnaire_not_published');
        $this->assertDeclarationError($this->postDeclaration('/borrador'), 404, 'questionnaire_not_published');
        $this->assertDeclarationError($this->postDeclaration('/enviar', true), 404, 'questionnaire_not_published');
        self::assertSame($before, $this->dml);
    }

    public function test_publication_without_audit_is_unavailable_and_does_not_write(): void
    {
        DB::table('data_bi_information_requests')->where('id', $this->questionnaire->id)->update(['sent_by_user_id' => null]);
        $before = $this->dml;
        $this->assertDeclarationError($this->postDeclaration('/borrador'), 503, 'questionnaire_unavailable');
        $this->assertDeclarationError($this->declarationHttp('GET', $this->url()), 503, 'questionnaire_unavailable');
        self::assertSame($before, $this->dml);
    }

    public function test_other_tenant_cannot_read_or_write_even_with_workspace_spoofing_or_invalid_payload(): void
    {
        $this->postDeclaration('/borrador', true)->assertOk();
        $row = $this->row();
        $subscriber = DB::table('subscribers')->insertGetId([
            'name' => 'T4D otro tenant', 'slug' => 't4d-'.bin2hex(random_bytes(12)), 'active' => 1,
        ]);
        $other = $this->tenant($subscriber);
        DB::table('users')->where('id', $other->id)->update(['workspace_company_id' => $this->request->company_id]);
        $this->authenticate($other);
        $before = $this->dml;
        $this->assertDeclarationError($this->declarationHttp('GET', $this->url()), 404, 'not_found');
        foreach (['/borrador', '/enviar'] as $suffix) {
            $this->assertDeclarationError($this->postDeclaration($suffix, true, 1), 404, 'not_found');
            $this->assertDeclarationError($this->declarationHttp('POST', $this->url($suffix), '{'), 404, 'not_found');
        }
        self::assertSame($before, $this->dml);
        self::assertSame($row, $this->row());
    }

    #[DataProvider('deniedMemberships')]
    public function test_membership_and_role_are_required_for_every_operation(string $case): void
    {
        $user = $this->tenant(role: $case === 'member' ? 'member' : 'admin');
        if ($case === 'inactive') {
            DB::table('subscriber_user')->where('user_id', $user->id)->update(['active' => 0]);
        } elseif ($case === 'missing') {
            DB::table('subscriber_user')->where('user_id', $user->id)->delete();
        } elseif ($case === 'owner_without_membership') {
            $user = $this->actor;
            DB::table('subscriber_user')->where('user_id', $user->id)->update(['active' => 0]);
        }
        $this->authenticate($user);
        $before = $this->dml;
        $this->assertDeclarationError($this->declarationHttp('GET', $this->url()), 404, 'not_found');
        $this->assertDeclarationError($this->postDeclaration('/borrador'), 404, 'not_found');
        $this->assertDeclarationError($this->postDeclaration('/enviar', true), 404, 'not_found');
        self::assertSame($before, $this->dml);
    }

    public static function deniedMemberships(): iterable
    {
        foreach (['member', 'inactive', 'missing', 'owner_without_membership'] as $case) {
            yield $case => [$case];
        }
    }

    public function test_inactive_company_or_subscriber_cannot_read_or_write(): void
    {
        foreach ([['companies', (int) $this->request->company_id], ['subscribers', $this->subscriberId]] as [$table, $id]) {
            DB::table($table)->where('id', $id)->update(['active' => 0]);
            $before = $this->dml;
            $this->assertDeclarationError($this->declarationHttp('GET', $this->url()), 404, 'not_found');
            $this->assertDeclarationError($this->postDeclaration('/borrador'), 404, 'not_found');
            $this->assertDeclarationError($this->postDeclaration('/enviar', true), 404, 'not_found');
            self::assertSame($before, $this->dml);
            DB::table($table)->where('id', $id)->update(['active' => 1]);
        }
    }

    public function test_csrf_blocks_both_posts_and_valid_session_token_permits_a_draft(): void
    {
        self::assertFalse($this->httpApp->runningUnitTests());
        $before = $this->dml;
        foreach (['/borrador', '/enviar'] as $suffix) {
            $this->assertDeclarationError($this->declarationHttp('POST', $this->url($suffix),
                [...$this->input(), 'expected_revision' => 0], false), 419, 'csrf_token_mismatch');
        }
        self::assertSame($before, $this->dml);
        $this->assertPublicState($this->postDeclaration('/borrador'), 'draft', 1);
    }

    public function test_tampered_nested_payloads_and_extra_identity_are_rejected_without_writes(): void
    {
        $variants = [];
        $base = [...$this->input(), 'expected_revision' => 0];
        foreach (['company_id', 'subscriber_id', 'actor_id', 'workspace_company_id', 'information_request_id',
            'expected_version', 'status', 'revision', 'content_sha256'] as $key) {
            $variants[] = [...$base, $key => 99];
        }
        $unknown = $base;
        $unknown['declarations'][0]['requirement_key'] = 'ajeno';
        $variants[] = $unknown;
        $extra = $base;
        $extra['declarations'][0]['company_id'] = 99;
        $variants[] = $extra;
        $extraSource = $base;
        $extraSource['declarations'][0]['sources'][0]['password'] = 'not-a-secret';
        $variants[] = $extraSource;
        $credential = $base;
        $credential['declarations'][0]['notes'] = 'password=not-a-secret';
        $variants[] = $credential;
        $controlCharacter = $base;
        $controlCharacter['declarations'][0]['notes'] = "\tTexto";
        $variants[] = $controlCharacter;
        $objectSources = $base;
        $objectSources['declarations'][0]['sources'] = (object) [];
        $variants[] = $objectSources;
        $objectStructures = $base;
        $objectStructures['declarations'][0]['sources'][0]['structures'] = (object) [];
        $variants[] = $objectStructures;
        $before = $this->dml;
        foreach ($variants as $variant) {
            foreach (['/borrador', '/enviar'] as $suffix) {
                $this->assertDeclarationError($this->declarationHttp('POST', $this->url($suffix), $variant), 422, 'validation_failed');
            }
        }
        self::assertSame($before, $this->dml);
    }

    public function test_missing_declaration_schema_is_503_without_ddl(): void
    {
        $schema = Schema::getFacadeRoot();
        try {
            Schema::swap(new class($schema)
            {
                public function __construct(private object $schema) {}

                public function hasTable(string $table): bool
                {
                    return $table !== 'data_bi_tenant_declarations' && $this->schema->hasTable($table);
                }

                public function __call(string $method, array $arguments): mixed
                {
                    return $this->schema->{$method}(...$arguments);
                }
            });
            $before = $this->dml;
            $this->assertDeclarationError($this->declarationHttp('GET', $this->url()), 503, 'schema_unavailable');
            $this->assertDeclarationError($this->postDeclaration('/borrador'), 503, 'schema_unavailable');
            $this->assertDeclarationError($this->postDeclaration('/enviar', true), 503, 'schema_unavailable');
            self::assertSame($before, $this->dml);
        } finally {
            Schema::swap($schema);
        }
    }

    public function test_corrupted_questionnaire_or_declaration_fails_closed_without_repair_writes(): void
    {
        $this->postDeclaration('/borrador')->assertOk();
        DB::table('data_bi_tenant_declarations')->where('id', $this->row()['id'])->update(['content_sha256' => str_repeat('f', 64)]);
        $before = $this->dml;
        $this->assertDeclarationError($this->declarationHttp('GET', $this->url()), 503, 'declaration_unavailable');
        $this->assertDeclarationError($this->postDeclaration('/borrador', true, 1), 503, 'declaration_unavailable');
        self::assertSame($before, $this->dml);
        DB::table('data_bi_information_requests')->where('id', $this->questionnaire->id)->update(['content_sha256' => str_repeat('f', 64)]);
        $before = $this->dml;
        $this->assertDeclarationError($this->declarationHttp('GET', $this->url()), 503, 'declaration_unavailable');
        self::assertSame($before, $this->dml);
    }
}
