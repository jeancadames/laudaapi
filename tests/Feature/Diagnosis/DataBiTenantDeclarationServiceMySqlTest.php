<?php

namespace Tests\Feature\Diagnosis;

use App\Models\DataBiInformationRequest;
use App\Models\DataBiTenantDeclaration;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use App\Services\Diagnosis\DataBiTenantDeclarationService;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\RequestGuard;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connectors\MySqlConnector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\QueryException;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use LogicException;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\SkippedTest;
use PHPUnit\Framework\AssertionFailedError;
use ReflectionProperty;
use Tests\Support\BuildsDataBiTenantDeclarationPayloads;
use Throwable;

/**
 * QA MySQL preparada, sin dotenv, migraciones ni registros empresariales prestados.
 * Requiere autorización posterior y variables de proceso APP_ENV=local,
 * D2F_T4C_MYSQL_QA=rollback-only,
 * D2F_T4C_QA_DATABASE=laudaapi_dev, D2F_T4C_QA_HOST=127.0.0.1 (o localhost),
 * D2F_T4C_QA_USER, D2F_T4C_QA_PASSWORD y puerto opcional. No recibe REQUEST_ID.
 * Las tablas deben existir, ser InnoDB y no tener triggers. Se exige visibilidad TRIGGER
 * directa a nivel global o del esquema para acreditar esa ausencia antes del DML.
 * La conexión secundaria es solo de lectura; nunca se utiliza como default del servicio.
 * La concurrencia entre servicios queda NOT_VERIFIED y se omite antes de abrir conexiones.
 * Los métodos test_harness_* son pruebas puras: PDO simulado, sin conexión ni SQL real.
 * MySQL puede consumir IDs AUTO_INCREMENT aunque se haga rollback.
 */
#[Group('mysql-dev-rollback')]
final class DataBiTenantDeclarationServiceMySqlTest extends TestCase
{
    use BuildsDataBiTenantDeclarationPayloads;

    private ?Capsule $capsule = null;

    private array $previous = [];

    /** Solo conexiones creadas por este caso, nunca las del contenedor anterior. */
    private array $ownedConnections = [];

    private string $primaryName;

    private string $probeName;

    private int $assessmentId;

    private int $planId;

    private int $phaseId;

    private TransformationImplementationRequest $request;

    private DataBiInformationRequest $questionnaire;

    private DataBiTenantDeclarationService $service;

    private User $actor;

    private int $subscriberId;

    protected function setUp(): void
    {
        parent::setUp();
        if (str_starts_with($this->name(), 'test_harness_')) {
            return;
        }
        if ($this->name() === 'test_competing_connection_cannot_bypass_the_parent_request_lock') {
            self::markTestSkipped('CONCURRENCY=NOT_VERIFIED: sin servicios secundarios ni fixtures confirmados.');
        }
        $this->requireAuthorization([
            'mode' => getenv('D2F_T4C_MYSQL_QA'), 'environment' => getenv('APP_ENV'),
            'database' => getenv('D2F_T4C_QA_DATABASE'), 'host' => getenv('D2F_T4C_QA_HOST'),
        ]);
        if (!is_string(getenv('D2F_T4C_QA_USER')) || trim(getenv('D2F_T4C_QA_USER')) === '') {
            self::markTestSkipped('Falta una cuenta QA explícita; no se utilizan credenciales por defecto.');
        }
        $port = getenv('D2F_T4C_QA_PORT') ?: '3306';
        if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
            self::markTestSkipped('Puerto MySQL QA inválido.');
        }
        $this->capturePreviousState();
        try {
            $container = new Container;
            $container->instance('config', new Repository);
            Container::setInstance($container);
            $this->capsule = new Capsule($container);
            $suffix = bin2hex(random_bytes(8));
            $this->primaryName = 't4c_primary_'.$suffix;
            $this->probeName = 't4c_probe_'.$suffix;
            $configuration = [
                'driver' => 'mysql', 'host' => getenv('D2F_T4C_QA_HOST'),
                'port' => $port, 'database' => 'laudaapi_dev',
                'username' => getenv('D2F_T4C_QA_USER'), 'password' => getenv('D2F_T4C_QA_PASSWORD') ?: '',
                'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
            ];
            // Nunca copiar getConfig(): ambas identidades se asignan explícitamente.
            foreach ([$this->primaryName, $this->probeName] as $name) {
                $this->capsule->addConnection([...$configuration, 'name' => $name], $name);
            }
            $manager = $this->capsule->getDatabaseManager();
            $manager->setDefaultConnection($this->primaryName);
            $manager->extend('mysql', function (array $config, string $name): T4cRollbackOnlyMySqlConnection {
                if (!in_array($name, [$this->primaryName, $this->probeName], true)
                    || ($config['name'] ?? null) !== $name) {
                    throw new LogicException('Conexión QA no autorizada o con identidad ambigua.');
                }
                $connection = new T4cRollbackOnlyMySqlConnection(
                    (new MySqlConnector)->connect($config), $name, $name === $this->probeName
                );
                $this->ownedConnections[$name] = $connection;
                $connection->beforeExecuting(fn () => $this->assertRouting());

                return $connection;
            });
            $this->capsule->setEventDispatcher(new Dispatcher($container));
            Model::clearBootedModels();
            $this->capsule->bootEloquent();
            $container->instance('db', $this->capsule->getDatabaseManager());
            $container->bind('db.schema', fn () => $this->ownedConnections[$this->primaryName]->getSchemaBuilder());
            $container->instance('validator', new Factory(new Translator(new ArrayLoader, 'es'), $container));
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($container);

            $primary = $manager->connection($this->primaryName);
            $probe = $manager->connection($this->probeName);
            $primary->openOuterTransaction();
            $probe->openOuterTransaction();
            self::assertNotSame($primary->getPdo(), $probe->getPdo(), 'Deben ser dos PDO independientes.');
            $sessions = [];
            foreach ($this->ownedConnections as $name => $connection) {
                self::assertSame($name, $connection->getName());
                self::assertSame('mysql', $connection->getDriverName());
                $session = $connection->selectOne('SELECT DATABASE() AS db, CONNECTION_ID() AS session_id');
                self::assertSame('laudaapi_dev', $session->db);
                $sessions[] = $session->session_id;
            }
            self::assertNotSame($sessions[0], $sessions[1]);
            $this->assertRouting();
            foreach (T4cRollbackOnlyMySqlConnection::FIXTURE_TABLES as $table) {
                $engine = DB::selectOne('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
                    ['laudaapi_dev', $table]);
                self::assertNotNull($engine, 'Falta tabla preparada: '.$table);
                self::assertSame('innodb', strtolower($engine->engine), 'QA requiere tablas transaccionales.');
                // Sin leer registros: mantener MDL hasta rollback para impedir DDL/triggers concurrentes.
                DB::select('SELECT id FROM `'.$table.'` WHERE 1 = 0');
            }
            self::assertTrue(Schema::hasColumn('data_bi_information_requests', 'sent_by_user_id'));
            $this->assertNoTriggers();
            $primary->allowFixtures();
            $this->createSyntheticScope();
            $this->authenticate($this->actor);
            $this->service = new DataBiTenantDeclarationService;
            $this->questionnaire = $this->publish();
        } catch (Throwable $e) {
            $this->cleanup();
            throw $e;
        }
    }

    protected function tearDown(): void
    {
        try {
            if ($this->capsule !== null) {
                $this->assertRouting();
                foreach ($this->ownedConnections as $connection) {
                    $connection->assertOuterTransaction();
                    self::assertSame(1, $connection->transactionLevel(), 'Transacciones anidadas sin cerrar.');
                }
            }
        } finally {
            try {
                $this->cleanup();
            } finally {
                parent::tearDown();
            }
        }
    }

    private function capturePreviousState(): void
    {
        $this->previous = [
            'container' => Container::getInstance(), 'facades' => Facade::getFacadeApplication(),
            'resolver' => Model::getConnectionResolver(), 'dispatcher' => Model::getEventDispatcher(),
            'facade_roots' => (new ReflectionProperty(Facade::class, 'resolvedInstance'))->getValue(),
        ];
        foreach (['booted', 'bootedCallbacks', 'globalScopes'] as $property) {
            $this->previous[$property] = (new ReflectionProperty(Model::class, $property))->getValue();
        }
    }

    private function cleanup(): void
    {
        if ($this->previous === []) {
            return;
        }
        $failure = null;
        try {
            foreach ($this->ownedConnections as $name => $connection) {
                try {
                    $connection->rollbackOuterTransaction();
                } catch (Throwable $e) {
                    $failure ??= $e;
                } finally {
                    // Solo conexiones de este Capsule, después de intentar rollback explícito.
                    try {
                        $this->capsule->getDatabaseManager()->purge($name);
                    } catch (Throwable $e) {
                        $failure ??= $e;
                    }
                }
            }
        } finally {
            Facade::setFacadeApplication($this->previous['facades']);
            (new ReflectionProperty(Facade::class, 'resolvedInstance'))->setValue(null, $this->previous['facade_roots']);
            Container::setInstance($this->previous['container']);
            foreach (['booted', 'bootedCallbacks', 'globalScopes'] as $property) {
                (new ReflectionProperty(Model::class, $property))->setValue(null, $this->previous[$property]);
            }
            if ($this->previous['resolver'] !== null) {
                Model::setConnectionResolver($this->previous['resolver']);
            } else {
                Model::unsetConnectionResolver();
            }
            if ($this->previous['dispatcher'] !== null) {
                Model::setEventDispatcher($this->previous['dispatcher']);
            } else {
                Model::unsetEventDispatcher();
            }
            $this->capsule = null;
            $this->ownedConnections = [];
            $this->previous = [];
        }
        if ($failure !== null) {
            throw $failure;
        }
    }

    private function requireAuthorization(array $flags): void
    {
        if (($flags['mode'] ?? null) !== 'rollback-only' || ($flags['environment'] ?? null) !== 'local'
            || ($flags['database'] ?? null) !== 'laudaapi_dev'
            || !in_array($flags['host'] ?? null, ['127.0.0.1', 'localhost'], true)) {
            self::markTestSkipped('MySQL QA no autorizado: se requiere local, laudaapi_dev y activación explícita.');
        }
    }

    private function assertRouting(): void
    {
        $manager = $this->capsule->getDatabaseManager();
        self::assertSame($this->primaryName, $manager->getDefaultConnection());
        self::assertSame($manager, DB::getFacadeRoot());
        self::assertSame($manager, Model::getConnectionResolver());
        self::assertSame($this->ownedConnections[$this->primaryName], DB::connection());
        foreach ([User::class, TransformationImplementationRequest::class,
            DataBiInformationRequest::class, DataBiTenantDeclaration::class] as $model) {
            self::assertSame(DB::connection(), (new $model)->getConnection());
        }
    }

    private function assertNoTriggers(): void
    {
        // Sin visibilidad de triggers no se puede afirmar que un DML sea completamente reversible.
        $grantee = "CONCAT(QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', 1)), '@', QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', -1)))";
        $global = DB::selectOne("SELECT COUNT(*) AS n FROM information_schema.USER_PRIVILEGES WHERE PRIVILEGE_TYPE = 'TRIGGER' AND GRANTEE = ".$grantee);
        $schema = DB::selectOne("SELECT COUNT(*) AS n FROM information_schema.SCHEMA_PRIVILEGES WHERE PRIVILEGE_TYPE = 'TRIGGER' AND TABLE_SCHEMA = DATABASE() AND GRANTEE = ".$grantee);
        if ((int) $global->n + (int) $schema->n === 0) {
            throw new LogicException('No se acredita visibilidad de triggers; fixtures bloqueados sin DML.');
        }
        $triggers = DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', 'laudaapi_dev')
            ->whereIn('EVENT_OBJECT_TABLE', T4cRollbackOnlyMySqlConnection::FIXTURE_TABLES)->count();
        if ($triggers !== 0) {
            throw new LogicException('Hay triggers en tablas de fixtures; requiere revisión antes de QA.');
        }
    }

    /** Cadena FK completa, nueva y reversible; nunca se selecciona una solicitud empresarial. */
    private function createSyntheticScope(): void
    {
        $this->subscriberId = DB::table('subscribers')->insertGetId([
            'name' => 'T4C QA sintético', 'slug' => 't4c-'.bin2hex(random_bytes(12)), 'active' => 1,
        ]);
        $this->actor = $this->tenant();
        $company = DB::table('companies')->insertGetId([
            'name' => 'T4C QA sintético', 'slug' => 't4c-'.bin2hex(random_bytes(12)),
            'subscriber_id' => $this->subscriberId, 'owner_user_id' => $this->actor->id, 'active' => 1,
        ]);
        $this->assessmentId = DB::table('diagnosis_assessments')->insertGetId([
            'user_id' => $this->actor->id, 'organization_id' => $company,
            'organization_name' => 'T4C QA sintético', 'status' => 'draft',
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
            'sequence' => 1, 'name' => 'T4C QA sintético', 'created_by_user_id' => $this->actor->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->request = $this->syntheticRequest($company, 'data_transformation_bi');
        self::assertSame($this->primaryName, $this->request->getConnectionName());
    }

    private function syntheticRequest(int $company, string $capability): TransformationImplementationRequest
    {
        $phaseCapability = DB::table('transformation_implementation_phase_capabilities')->insertGetId([
            'transformation_implementation_phase_id' => $this->phaseId, 'sequence' => 1,
            'capability_key' => $capability, 'capability_label' => 'T4C QA sintético',
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
            'name' => 'T4C QA', 'email' => 't4c-'.bin2hex(random_bytes(12)).'@example.test',
            'password' => '*', 'role' => 'user', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('subscriber_user')->insert([
            'subscriber_id' => $subscriberId ?? $this->subscriberId, 'user_id' => $id,
            'role' => $role, 'active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }

    private function authenticate(?User $user): void
    {
        Auth::swap(new RequestGuard(static fn () => $user, Request::create('/')));
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

    private function rejected(string $class, callable $operation): void
    {
        try {
            $operation();
        } catch (Throwable $e) {
            self::assertInstanceOf($class, $e);

            return;
        }
        self::fail('Se esperaba rechazo: '.$class);
    }

    private function assertSameDeclaration(DataBiTenantDeclaration $expected, DataBiTenantDeclaration $actual): void
    {
        self::assertSame($this->ordered($expected->attributesToArray()), $this->ordered($actual->attributesToArray()));
    }

    private function ordered(array $value): array
    {
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->ordered($item);
            }
        }
        unset($item);
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }

    #[Group('pure-harness')]
    #[DataProvider('unauthorizedContexts')]
    public function test_harness_rejects_unauthorized_context_before_opening_connections(array $flags): void
    {
        $candidate = new self('test_first_draft_and_lookup_do_not_modify_the_published_questionnaire');
        $before = Facade::getFacadeApplication();
        try {
            $candidate->requireAuthorization($flags);
            self::fail('No debe autorizar un contexto incompleto o ajeno a DEV.');
        } catch (SkippedTest $e) {
            self::assertStringContainsString('no autorizado', $e->getMessage());
        }
        self::assertNull($candidate->capsule);
        self::assertSame([], $candidate->ownedConnections);
        self::assertSame([], $candidate->previous);
        self::assertSame($before, Facade::getFacadeApplication());
    }

    public static function unauthorizedContexts(): iterable
    {
        $valid = ['mode' => 'rollback-only', 'environment' => 'local', 'database' => 'laudaapi_dev', 'host' => '127.0.0.1'];
        yield 'missing all' => [[]];
        foreach ($valid as $field => $value) {
            $missing = $valid;
            unset($missing[$field]);
            yield 'missing '.$field => [$missing];
        }
        yield 'disabled' => [[...$valid, 'mode' => 'enabled']];
        yield 'production' => [[...$valid, 'environment' => 'production']];
        yield 'other database' => [[...$valid, 'database' => 'laudaapi']];
        yield 'remote host' => [[...$valid, 'host' => 'mysql.example.test']];
    }

    private function fakeConnection(string $name = 't4c_unit_primary', bool $readOnly = false): array
    {
        $state = (object) ['active' => false];
        $pdo = $this->createMock(PDO::class);
        $pdo->method('beginTransaction')->willReturnCallback(static function () use ($state): bool {
            $state->active = true;

            return true;
        });
        $pdo->method('inTransaction')->willReturnCallback(static fn (): bool => $state->active);
        $pdo->method('rollBack')->willReturnCallback(static function () use ($state): bool {
            $state->active = false;

            return true;
        });
        $pdo->method('exec')->willReturn(0); // Solo emula control de transacciones; nunca ejecuta SQL.
        $pdo->expects(self::never())->method('commit');

        return [new T4cRollbackOnlyMySqlConnection($pdo, $name, $readOnly), $state];
    }

    #[Group('pure-harness')]
    public function test_harness_forbids_root_commit_and_preserves_nested_service_transactions(): void
    {
        [$connection] = $this->fakeConnection();
        $connection->openOuterTransaction();
        try {
            $this->rejected(LogicException::class, fn () => $connection->commit());
            self::assertSame('result', $connection->transaction(function ($connection): string {
                self::assertSame(2, $connection->transactionLevel());

                return $connection->transaction(function ($connection): string {
                    self::assertSame(3, $connection->transactionLevel());

                    return 'result';
                });
            }));
            self::assertSame(1, $connection->transactionLevel());
        } finally {
            $connection->rollbackOuterTransaction();
        }
        self::assertSame(0, $connection->transactionLevel());
        self::assertFalse($connection->getPdo()->inTransaction());
    }

    #[Group('pure-harness')]
    public function test_harness_rolls_back_nested_exceptions_without_confirming_fixtures(): void
    {
        [$connection] = $this->fakeConnection();
        $connection->openOuterTransaction();
        try {
            $this->rejected(LogicException::class, fn () => $connection->transaction(static function (): void {
                throw new LogicException('Servicio rechazado.');
            }));
            self::assertSame(1, $connection->transactionLevel());
            $connection->assertOuterTransaction();
        } finally {
            $connection->rollbackOuterTransaction();
        }
    }

    #[Group('pure-harness')]
    public function test_harness_blocks_unbalanced_service_transactions_before_root_commit(): void
    {
        [$connection] = $this->fakeConnection();
        $connection->openOuterTransaction();
        try {
            $this->rejected(LogicException::class, fn () => $connection->transaction(static function ($connection): void {
                $connection->commit(); // Simula un servicio que consume su savepoint indebidamente.
            }));
            $connection->assertOuterTransaction();
            $this->rejected(LogicException::class, fn () => $connection->rollBack(0));
        } finally {
            $connection->rollbackOuterTransaction();
        }
    }

    #[Group('pure-harness')]
    public function test_harness_dml_requires_outer_transaction_and_completed_schema_review(): void
    {
        [$connection] = $this->fakeConnection();
        $this->rejected(LogicException::class, fn () => $connection->guardSql('insert into `users` (`name`) values (?)'));
        $this->rejected(LogicException::class, fn () => $connection->transaction(static fn () => null));
        $connection->openOuterTransaction();
        try {
            $this->rejected(LogicException::class, fn () => $connection->guardSql('insert into `users` (`name`) values (?)'));
            $connection->allowFixtures();
            $connection->guardSql('insert into `users` (`name`) values (?)');
            self::assertSame(1, $connection->transactionLevel());
        } finally {
            $connection->rollbackOuterTransaction();
        }
        $this->rejected(LogicException::class, fn () => $connection->guardSql('update `users` set `name` = ? where `id` = ?'));
    }

    #[Group('pure-harness')]
    public function test_harness_secondary_has_a_different_name_and_pdo_and_rejects_all_dml(): void
    {
        [$primary] = $this->fakeConnection('t4c_unit_primary');
        [$probe] = $this->fakeConnection('t4c_unit_probe', true);
        self::assertNotSame($primary->getName(), $probe->getName());
        self::assertNotSame($primary->getPdo(), $probe->getPdo());
        $primary->openOuterTransaction();
        $probe->openOuterTransaction();
        try {
            $this->rejected(LogicException::class, fn () => $probe->allowFixtures());
            foreach (['insert into `users` values (?)', 'update `users` set `name` = ?',
                'delete from `users` where `id` = ?'] as $sql) {
                $this->rejected(LogicException::class, fn () => $probe->guardSql($sql));
            }
            $probe->guardSql('SELECT DATABASE() AS db');
            self::assertSame(1, $probe->transactionLevel());
        } finally {
            try {
                $probe->rollbackOuterTransaction();
            } finally {
                $primary->rollbackOuterTransaction();
            }
        }
    }

    #[Group('pure-harness')]
    public function test_harness_rejects_ddl_transaction_sql_and_writes_to_unrelated_tables(): void
    {
        [$connection] = $this->fakeConnection();
        $connection->openOuterTransaction();
        $connection->allowFixtures();
        try {
            foreach (['COMMIT', 'ROLLBACK', 'SET autocommit=1', 'TRUNCATE users', 'CREATE TABLE unsafe (id int)',
                'ALTER TABLE users ADD unsafe int', 'DROP TABLE users', 'CALL unsafe()',
                'select 1; COMMIT', "SELECT 1 INTO OUTFILE '/tmp/unsafe'", 'update `subscriptions` set `status` = ?'] as $sql) {
                $this->rejected(LogicException::class, fn () => $connection->guardSql($sql));
            }
        } finally {
            $connection->rollbackOuterTransaction();
        }
    }

    #[Group('pure-harness')]
    public function test_harness_does_not_reconnect_or_write_after_transaction_loss(): void
    {
        [$connection, $state] = $this->fakeConnection();
        $connection->openOuterTransaction();
        $connection->allowFixtures();
        $state->active = false; // Simula rollback del servidor o pérdida de sesión, sin MySQL.
        $this->rejected(LogicException::class, fn () => $connection->guardSql('insert into `users` values (?)'));
        $this->rejected(LogicException::class, fn () => $connection->reconnect());
        $this->rejected(LogicException::class, fn () => $connection->rollbackOuterTransaction());
        self::assertSame(0, $connection->transactionLevel());
    }

    #[Group('pure-harness')]
    public function test_harness_concurrency_case_is_disabled_before_environment_or_connections(): void
    {
        $candidate = new self('test_competing_connection_cannot_bypass_the_parent_request_lock');
        try {
            $candidate->setUp();
            self::fail('Concurrencia pendiente debe quedar desactivada.');
        } catch (SkippedTest $e) {
            self::assertStringContainsString('CONCURRENCY=NOT_VERIFIED', $e->getMessage());
        }
        self::assertNull($candidate->capsule);
        self::assertSame([], $candidate->previous);
    }

    #[Group('pure-harness')]
    #[DataProvider('cleanupFailures')]
    public function test_harness_routes_db_and_eloquent_and_restores_only_its_own_connections(bool $losePrimary): void
    {
        $original = new self($this->name());
        $original->capturePreviousState();
        [$foreign, $foreignState] = $this->fakeConnection('foreign_connection');
        $foreign->openOuterTransaction();
        $candidate = new self($this->name());
        try {
            $foreignContainer = new Container;
            $foreignContainer->instance('config', new Repository);
            $foreignCapsule = new Capsule($foreignContainer);
            $foreignManager = $foreignCapsule->getDatabaseManager();
            $foreignCapsule->addConnection(['driver' => 'mysql', 'name' => 'foreign_connection'], 'foreign_connection');
            $foreignManager->extend('mysql', static fn () => $foreign);
            $foreignManager->setDefaultConnection('foreign_connection');
            Container::setInstance($foreignContainer);
            Facade::setFacadeApplication($foreignContainer);
            Facade::clearResolvedInstances();
            DB::swap($foreignManager);
            Model::setConnectionResolver($foreignManager);
            self::assertSame($foreign, DB::connection());

            $candidate->capturePreviousState();
            $container = new Container;
            $container->instance('config', new Repository);
            $candidate->capsule = new Capsule($container);
            $candidate->primaryName = 't4c_unit_primary';
            $candidate->probeName = 't4c_unit_probe';
            [$primary, $primaryState] = $this->fakeConnection($candidate->primaryName);
            [$probe, $probeState] = $this->fakeConnection($candidate->probeName, true);
            $candidate->ownedConnections = [$candidate->primaryName => $primary, $candidate->probeName => $probe];
            foreach ($candidate->ownedConnections as $name => $connection) {
                $candidate->capsule->addConnection(['driver' => 'mysql', 'name' => $name], $name);
                $connection->openOuterTransaction();
            }
            $manager = $candidate->capsule->getDatabaseManager();
            $manager->extend('mysql', fn ($config, $name) => $candidate->ownedConnections[$name]);
            $manager->setDefaultConnection($candidate->primaryName);
            Container::setInstance($container);
            Facade::setFacadeApplication($container);
            Facade::clearResolvedInstances();
            DB::swap($manager);
            Model::setConnectionResolver($manager);
            Model::setEventDispatcher(new Dispatcher($container));
            Model::clearBootedModels();
            $candidate->assertRouting();
            self::assertSame($probe, (new DataBiTenantDeclaration)->setConnection($candidate->probeName)->getConnection());
            self::assertNotSame(DB::connection()->getPdo(), $probe->getPdo());
            $primary->getPdo()->expects(self::never())->method('prepare');
            $primary->beforeExecuting(fn () => $candidate->assertRouting());
            try {
                $manager->setDefaultConnection($candidate->probeName);
                $this->rejected(AssertionFailedError::class, fn () => $primary->select('SELECT 1'));
            } finally {
                $manager->setDefaultConnection($candidate->primaryName);
            }
            if ($losePrimary) {
                $primaryState->active = false;
                $this->rejected(LogicException::class, fn () => $candidate->cleanup());
            } else {
                $candidate->cleanup();
            }
            self::assertFalse($primaryState->active);
            self::assertFalse($probeState->active, 'Una falla en otra conexión no puede omitir este rollback.');
            self::assertTrue($foreignState->active, 'No revertir la conexión ajena.');
            self::assertSame($foreignManager, DB::getFacadeRoot());
            self::assertSame($foreign, DB::connection());
            self::assertSame($foreignManager, Model::getConnectionResolver());
            self::assertSame($foreignContainer, Facade::getFacadeApplication());
            self::assertSame([], $candidate->ownedConnections);
        } finally {
            try {
                $candidate->cleanup();
            } finally {
                try {
                    $foreign->rollbackOuterTransaction();
                } finally {
                    $original->cleanup();
                }
            }
        }
    }

    public static function cleanupFailures(): iterable
    {
        yield 'normal cleanup' => [false];
        yield 'lost primary transaction' => [true];
    }

    public function test_first_draft_and_lookup_do_not_modify_the_published_questionnaire(): void
    {
        $before = (array) DB::table('data_bi_information_requests')->where('id', $this->questionnaire->id)->first();
        self::assertNull($this->service->findExisting($this->request, $this->questionnaire->version));
        $row = $this->service->saveDraft($this->request, $this->input(false), 0);
        self::assertSame('draft', $row->status);
        self::assertSame(1, $row->revision);
        self::assertSame((int) $this->request->company_id, $row->company_id);
        self::assertSame((int) $this->actor->id, $row->created_by_user_id);
        self::assertNull($row->submitted_at);
        self::assertSame('pending', $row->declaration_snapshot['declarations'][0]['availability']);
        self::assertSame($row->id, $this->service->findExisting($this->request, $this->questionnaire->version)->id);
        self::assertSame($before, (array) DB::table('data_bi_information_requests')->where('id', $this->questionnaire->id)->first());
    }

    public function test_controlled_update_records_editor_and_preserves_creator(): void
    {
        $draft = $this->service->saveDraft($this->request, $this->input(false), 0);
        $editor = $this->tenant();
        $this->authenticate($editor);
        $updated = $this->service->saveDraft($this->request, $this->input(), 1);
        self::assertSame($draft->id, $updated->id);
        self::assertSame(2, $updated->revision);
        self::assertSame($draft->created_by_user_id, $updated->created_by_user_id);
        self::assertSame((int) $editor->id, $updated->updated_by_user_id);
    }

    public function test_identical_create_and_update_retries_keep_the_audit_and_revision(): void
    {
        $draft = $this->service->saveDraft($this->request, $this->input(false), 0);
        $this->assertSameDeclaration($draft, $this->service->saveDraft($this->request, $this->input(false), 0));
        $updated = $this->service->saveDraft($this->request, $this->input(), 1);
        $this->assertSameDeclaration($updated, $this->service->saveDraft($this->request, $this->input(), 1));
        self::assertSame(1, DataBiTenantDeclaration::query()->where('data_bi_information_request_id', $this->questionnaire->id)->count());
    }

    public function test_stale_edit_does_not_overwrite_a_newer_revision(): void
    {
        $this->service->saveDraft($this->request, $this->input(false), 0);
        $current = $this->service->saveDraft($this->request, $this->input(), 1);
        $this->rejected(ValidationException::class, fn () => $this->service->saveDraft($this->request, $this->input(false), 1));
        $this->assertSameDeclaration($current, $current->fresh());
    }

    public function test_valid_submission_and_identical_retry_preserve_submission_audit(): void
    {
        $this->service->saveDraft($this->request, $this->input(), 0);
        $sent = $this->service->submit($this->request, $this->input(), 1);
        self::assertSame('submitted', $sent->status);
        self::assertSame(2, $sent->revision);
        self::assertSame((int) $this->actor->id, $sent->submitted_by_user_id);
        self::assertNotNull($sent->submitted_at);
        $this->authenticate($this->tenant());
        $this->assertSameDeclaration($sent, $this->service->submit($this->request, $this->input(), 1));
    }

    public function test_incomplete_submission_is_rejected_without_changing_the_draft(): void
    {
        $draft = $this->service->saveDraft($this->request, $this->input(false), 0);
        $this->rejected(ValidationException::class, fn () => $this->service->submit($this->request, $this->input(false), 1));
        $this->assertSameDeclaration($draft, $draft->fresh());
    }

    public function test_submitted_content_cannot_be_changed_or_reopened_as_draft(): void
    {
        $sent = $this->service->submit($this->request, $this->input(), 0);
        $this->assertSameDeclaration($sent, $this->service->submit($this->request, $this->input(), 0));
        $changed = $this->input();
        $changed['declarations'][0]['notes'] = 'Cambio posterior.';
        $this->rejected(ValidationException::class, fn () => $this->service->submit($this->request, $changed, 1));
        $this->rejected(ValidationException::class, fn () => $this->service->saveDraft($this->request, $this->input(), 1));
        $this->assertSameDeclaration($sent, $sent->fresh());
    }

    public function test_model_also_prevents_updates_and_deletion_of_submitted_rows(): void
    {
        $sent = $this->service->submit($this->request, $this->input(), 0);
        $this->rejected(LogicException::class, fn () => $sent->forceFill(['revision' => 2])->save());
        $this->rejected(LogicException::class, fn () => $sent->delete());
        self::assertSame(1, $sent->fresh()->revision);
    }

    public function test_new_publication_preserves_old_answers_and_starts_with_pending_answers(): void
    {
        $old = $this->service->saveDraft($this->request, $this->input(), 0);
        $oldInput = $this->input();
        $this->questionnaire = $this->publish();
        $this->rejected(ValidationException::class, fn () => $this->service->submit($this->request, $oldInput, 1));
        $this->rejected(ValidationException::class, fn () => $this->service->findExisting($this->request, $oldInput['version']));
        $new = $this->service->saveDraft($this->request, $this->input(false), 0);
        self::assertNotSame($old->id, $new->id);
        $this->assertSameDeclaration($old, $old->fresh());
        self::assertSame('pending', $new->declaration_snapshot['declarations'][0]['availability']);
        self::assertNotSame($old->declaration_snapshot['version'], $new->declaration_snapshot['version']);
    }

    public function test_new_lauda_draft_does_not_replace_a_published_questionnaire(): void
    {
        $this->publish(false);
        self::assertSame(1, $this->service->saveDraft($this->request, $this->input(false), 0)->revision);
    }

    public function test_unpublished_reference_and_missing_publication_audit_are_rejected(): void
    {
        $draft = $this->publish(false);
        $input = $this->declarationInput($draft->version, $draft->review_snapshot);
        $this->rejected(ValidationException::class, fn () => $this->service->saveDraft($this->request, $input, 0));
        DB::table('data_bi_information_requests')->where('id', $this->questionnaire->id)->update(['sent_by_user_id' => null]);
        $this->rejected(ValidationException::class, fn () => $this->service->saveDraft($this->request, $this->input(), 0));
        DB::table('data_bi_information_requests')->where('id', $this->questionnaire->id)
            ->update(['sent_by_user_id' => $this->actor->id, 'sent_at' => null]);
        $this->rejected(ValidationException::class, fn () => $this->service->findExisting($this->request, $this->questionnaire->version));
    }

    public function test_corrupted_published_snapshot_is_rejected_before_any_declaration_write(): void
    {
        DB::table('data_bi_information_requests')->where('id', $this->questionnaire->id)
            ->update(['content_sha256' => str_repeat('f', 64)]);
        $this->rejected(ValidationException::class, fn () => $this->service->saveDraft($this->request, $this->input(false), 0));
        self::assertFalse(DataBiTenantDeclaration::query()->where('data_bi_information_request_id', $this->questionnaire->id)->exists());
    }

    public function test_guest_member_and_inactive_membership_are_rejected_on_all_operations(): void
    {
        $this->authenticate(null);
        $this->rejected(AuthenticationException::class, fn () => $this->service->findExisting($this->request, $this->questionnaire->version));
        $this->authenticate($this->tenant(role: 'member'));
        $this->rejected(AuthorizationException::class, fn () => $this->service->saveDraft($this->request, $this->input(false), 0));
        DB::table('subscriber_user')->where('user_id', $this->actor->id)->update(['active' => 0]);
        $this->authenticate($this->actor);
        $this->rejected(AuthorizationException::class, fn () => $this->service->submit($this->request, $this->input(), 0));
    }

    public function test_company_owner_still_needs_active_membership(): void
    {
        self::assertSame((int) $this->actor->id,
            (int) DB::table('companies')->where('id', $this->request->company_id)->value('owner_user_id'));
        DB::table('subscriber_user')->where('user_id', $this->actor->id)->update(['role' => 'member']);
        self::assertNotNull($this->service->saveDraft($this->request, $this->input(false), 0));
        DB::table('subscriber_user')->where('user_id', $this->actor->id)->update(['active' => 0]);
        $this->rejected(AuthorizationException::class, fn () => $this->service->findExisting($this->request, $this->questionnaire->version));
    }

    public function test_foreign_subscriber_and_spoofed_model_or_browser_identity_cannot_bypass_authorization(): void
    {
        $subscriber = DB::table('subscribers')->insertGetId([
            'name' => 'T4C otra empresa', 'slug' => 't4c-'.bin2hex(random_bytes(12)),
        ]);
        $other = $this->tenant($subscriber);
        $company = DB::table('companies')->insertGetId([
            'name' => 'T4C otra empresa', 'slug' => 't4c-'.bin2hex(random_bytes(12)),
            'subscriber_id' => $subscriber, 'owner_user_id' => $other->id,
        ]);
        $this->authenticate($other);
        $spoofed = clone $this->request;
        $spoofed->company_id = $company;
        $this->rejected(AuthorizationException::class, fn () => $this->service->saveDraft($spoofed, $this->input(false), 0));
        $this->authenticate($this->actor);
        foreach (['company_id', 'subscriber_id', 'actor_id', 'information_request_id', 'expected_version'] as $key) {
            $input = [...$this->input(false), $key => $company];
            $this->rejected(ValidationException::class, fn () => $this->service->saveDraft($this->request, $input, 0));
        }
    }

    public function test_wrong_capability_and_foreign_questionnaire_company_are_rejected(): void
    {
        $wrongCapability = $this->syntheticRequest((int) $this->request->company_id, 'other');
        $this->rejected(AuthorizationException::class, fn () => $this->service->saveDraft($wrongCapability, $this->input(false), 0));
        $otherCompany = DB::table('companies')->insertGetId([
            'name' => 'T4C ajena', 'slug' => 't4c-'.bin2hex(random_bytes(12)),
        ]);
        DB::table('data_bi_information_requests')->where('id', $this->questionnaire->id)
            ->update(['company_id' => $otherCompany]);
        $this->rejected(ValidationException::class, fn () => $this->service->saveDraft($this->request, $this->input(false), 0));
    }

    public function test_unique_constraint_rejects_a_second_row_for_the_same_questionnaire(): void
    {
        $draft = $this->service->saveDraft($this->request, $this->input(false), 0);
        $copy = $draft->getAttributes();
        unset($copy['id']);
        try {
            DB::table('data_bi_tenant_declarations')->insert($copy);
            self::fail('Se esperaba UNIQUE del cuestionario.');
        } catch (QueryException $e) {
            self::assertSame(1062, (int) $e->errorInfo[1]);
        }
        self::assertSame(1, DataBiTenantDeclaration::query()->where('data_bi_information_request_id', $this->questionnaire->id)->count());
    }

    public function test_competing_connection_cannot_bypass_the_parent_request_lock(): void
    {
        self::markTestSkipped('CONCURRENCY=NOT_VERIFIED: fixtures no confirmados, sin servicio secundario.');
    }

    public function test_foreign_key_prevents_a_declaration_for_an_unknown_questionnaire(): void
    {
        $draft = $this->service->saveDraft($this->request, $this->input(false), 0);
        $copy = $draft->getAttributes();
        unset($copy['id']);
        $copy['data_bi_information_request_id'] = PHP_INT_MAX;
        try {
            DB::table('data_bi_tenant_declarations')->insert($copy);
            self::fail('Se esperaba FK del cuestionario.');
        } catch (QueryException $e) {
            self::assertSame(1452, (int) $e->errorInfo[1]);
        }
    }

    public function test_corrupted_saved_declaration_is_not_silently_overwritten(): void
    {
        $draft = $this->service->saveDraft($this->request, $this->input(false), 0);
        DB::table('data_bi_tenant_declarations')->where('id', $draft->id)->update(['content_sha256' => str_repeat('f', 64)]);
        $this->rejected(ValidationException::class, fn () => $this->service->saveDraft($this->request, $this->input(), 1));
        $this->rejected(ValidationException::class, fn () => $this->service->findExisting($this->request, $this->questionnaire->version));
        self::assertSame(1, $draft->fresh()->revision);
    }
}

/** Infraestructura local de QA; nunca se registra en las conexiones de la aplicación real. */
final class T4cRollbackOnlyMySqlConnection extends MySqlConnection
{
    public const FIXTURE_TABLES = [
        'users', 'subscribers', 'companies', 'subscriber_user', 'diagnosis_assessments',
        'diagnosis_detailed_roadmaps', 'transformation_implementation_plans',
        'transformation_implementation_phases', 'transformation_implementation_phase_capabilities',
        'transformation_implementation_requests', 'data_bi_information_requests', 'data_bi_tenant_declarations',
    ];

    private ?PDO $outerPdo = null;

    private bool $fixturesAllowed = false;

    public function __construct(PDO $pdo, string $name, private bool $readOnly = false)
    {
        parent::__construct($pdo, 'laudaapi_dev', '', ['driver' => 'mysql', 'name' => $name]);
        $this->beforeExecuting(fn (string $sql) => $this->guardSql($sql));
    }

    public function openOuterTransaction(): void
    {
        if ($this->outerPdo !== null || $this->transactionLevel() !== 0 || $this->getPdo()->inTransaction()) {
            throw new LogicException('La conexión debe ser nueva y no puede reutilizar otra transacción.');
        }
        if ($this->readOnly) {
            $this->getPdo()->exec('SET TRANSACTION READ ONLY');
        }
        parent::beginTransaction();
        $this->outerPdo = $this->getPdo();
        $this->assertOuterTransaction();
    }

    public function assertOuterTransaction(): void
    {
        if ($this->outerPdo === null || $this->getRawPdo() !== $this->outerPdo
            || $this->transactionLevel() < 1 || !$this->outerPdo->inTransaction()) {
            throw new LogicException('Se perdió la transacción exterior QA; no se permite continuar.');
        }
    }

    public function allowFixtures(): void
    {
        $this->assertOuterTransaction();
        if ($this->readOnly) {
            throw new LogicException('La conexión secundaria nunca admite fixtures ni DML.');
        }
        $this->fixturesAllowed = true;
    }

    public function guardSql(string $sql): void
    {
        $this->assertOuterTransaction();
        if (str_contains($sql, ';') || preg_match('/\b(?:outfile|dumpfile|load_file)\b/i', $sql)
            || !preg_match('/\A\s*(select|show|insert|update|delete)\b/i', $sql, $verb)) {
            throw new LogicException('SQL no autorizado: sin DDL, cambios de sesión, commits ni operaciones externas.');
        }
        if (in_array(strtolower($verb[1]), ['select', 'show'], true)) {
            return;
        }
        if ($this->readOnly || !$this->fixturesAllowed
            || !preg_match('/\A\s*(?:insert\s+into|update|delete\s+from)\s+`?([a-z0-9_]+)`?\b/i', $sql, $table)
            || !in_array($table[1], self::FIXTURE_TABLES, true)) {
            throw new LogicException('DML bloqueado fuera del ámbito sintético autorizado.');
        }
    }

    public function beginTransaction()
    {
        $this->assertOuterTransaction();
        parent::beginTransaction();
    }

    /** Solo savepoints: nunca entra en la rama PDO::commit() de una transacción raíz. */
    public function transaction(Closure $callback, $attempts = 1)
    {
        $this->assertOuterTransaction();
        $level = $this->transactionLevel();
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->assertOuterTransaction();
            if ($this->transactionLevel() !== $level + 1) {
                throw new LogicException('El servicio alteró el nivel de transacción esperado.');
            }
            $this->commit(); // Nivel >= 2: confirma únicamente el savepoint lógico de Laravel.

            return $result;
        } catch (Throwable $e) {
            if ($this->getRawPdo() === $this->outerPdo && $this->outerPdo->inTransaction()
                && $this->transactionLevel() > $level) {
                parent::rollBack($level);
            }
            // Sin reintentos tras una pérdida de transacción: el caso debe fallar y limpiar.
            throw $e;
        }
    }

    public function commit()
    {
        $this->assertOuterTransaction();
        if ($this->transactionLevel() <= 1) {
            throw new LogicException('Se prohíbe confirmar la transacción exterior de fixtures.');
        }
        parent::commit();
    }

    public function rollBack($toLevel = null)
    {
        $this->assertOuterTransaction();
        if (($toLevel ?? $this->transactionLevel() - 1) < 1) {
            throw new LogicException('Solo cleanup puede revertir la transacción exterior QA.');
        }
        parent::rollBack($toLevel);
    }

    public function rollbackOuterTransaction(): void
    {
        if ($this->outerPdo === null) {
            return;
        }
        try {
            $this->assertOuterTransaction();
            parent::rollBack(0);
            if ($this->transactionLevel() !== 0 || $this->outerPdo->inTransaction()) {
                throw new LogicException('No se completó el rollback exterior.');
            }
        } finally {
            $this->fixturesAllowed = false;
            $this->outerPdo = null;
            $this->transactions = 0;
        }
    }

    public function reconnect()
    {
        throw new LogicException('QA no reconecta sesiones: podría perder el rollback exterior.');
    }
}
