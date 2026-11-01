<?php

declare(strict_types=1);

namespace Tests\Feature\Diagnosis;

use App\Models\TransformationImplementationRequest;
use App\Models\User;
use App\Services\Diagnosis\DataBiDeclarativeReviewService;
use App\Services\Diagnosis\DataBiTenantDeclarationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\RequestGuard;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connectors\MySqlConnector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Tests\Support\DataBiDeclarativeReviewRollbackConnection;
use Throwable;

require_once __DIR__.'/DataBiTenantDeclarationServiceMySqlTest.php';

/**
 * PREPARADA, NO EJECUTADA en B2. Sin dotenv, SQLite, migración automática o fixtures prestados.
 * Requiere APP_ENV=local, D2F_T4F_B2_MYSQL_QA=rollback-only y D2F_T4F_B2_QA_*
 * DATABASE=laudaapi_dev, HOST=127.0.0.1|localhost, USER/PASSWORD explícitos, PORT opcional.
 * Composición del constructor de fixtures T4C aprobado, sin ejecutar sus tests o su setUp.
 */
#[Group('mysql-dev-t4fb2-rollback')]
final class DataBiDeclarativeReviewServiceMySqlTest extends TestCase
{
    private array $previous = [];

    private array $connections = [];

    private array $fixtureIds = [];

    private array $dml = [];

    private ?Capsule $capsule = null;

    private string $primaryName;

    private string $probeName;

    private DataBiTenantDeclarationServiceMySqlTest $fixture;

    private DataBiDeclarativeReviewService $service;

    private User $admin;

    private User $tenant;

    private TransformationImplementationRequest $request;

    private array $question;

    private array $conclusion;

    protected function setUp(): void
    {
        parent::setUp();
        if ($this->name() === 'test_real_concurrency_between_two_services_remains_pending') {
            self::markTestSkipped('CONCURRENCY=NOT_VERIFIED: requiere dos sesiones con fixtures confirmados reversibles.');
        }
        if (getenv('APP_ENV') !== 'local' || getenv('D2F_T4F_B2_MYSQL_QA') !== 'rollback-only'
            || getenv('D2F_T4F_B2_QA_DATABASE') !== 'laudaapi_dev'
            || ! in_array(getenv('D2F_T4F_B2_QA_HOST'), ['127.0.0.1', 'localhost'], true)
            || ! is_string(getenv('D2F_T4F_B2_QA_USER')) || trim(getenv('D2F_T4F_B2_QA_USER')) === ''
            || getenv('D2F_T4F_B2_QA_PASSWORD') === false) {
            self::markTestSkipped('MYSQL_QA=NOT_EXECUTED: requiere autorización B2 y credenciales explícitas.');
        }
        $port = getenv('D2F_T4F_B2_QA_PORT') ?: '3306';
        if (! ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
            self::markTestSkipped('Puerto QA inválido; no se abren conexiones.');
        }
        $this->previous = ['container' => Container::getInstance(), 'facades' => Facade::getFacadeApplication(),
            'roots' => (new ReflectionProperty(Facade::class, 'resolvedInstance'))->getValue(),
            'resolver' => Model::getConnectionResolver(), 'dispatcher' => Model::getEventDispatcher()];
        foreach (['booted', 'bootedCallbacks', 'globalScopes'] as $field) {
            $this->previous[$field] = (new ReflectionProperty(Model::class, $field))->getValue();
        }
        try {
            $app = new Container;
            Container::setInstance($app);
            $app->instance('config', new Repository);
            $app->instance('validator', new Factory(new Translator(new ArrayLoader, 'es'), $app));
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($app);
            $this->capsule = new Capsule($app);
            $nonce = bin2hex(random_bytes(8));
            $this->primaryName = 't4fb2_primary_'.$nonce;
            $this->probeName = 't4fb2_probe_'.$nonce;
            foreach ([$this->primaryName, $this->probeName] as $name) {
                $this->capsule->addConnection(['driver' => 'mysql', 'name' => $name, 'host' => getenv('D2F_T4F_B2_QA_HOST'),
                    'port' => $port, 'database' => 'laudaapi_dev', 'username' => getenv('D2F_T4F_B2_QA_USER'),
                    'password' => getenv('D2F_T4F_B2_QA_PASSWORD'), 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
                    'timezone' => '+00:00', 'strict' => true], $name);
            }
            $manager = $this->capsule->getDatabaseManager();
            $manager->setDefaultConnection($this->primaryName);
            $manager->extend('mysql', function (array $configuration, string $name): DataBiDeclarativeReviewRollbackConnection {
                if (! in_array($name, [$this->primaryName, $this->probeName], true) || $configuration['name'] !== $name) {
                    throw new LogicException('Conexión QA no autorizada.');
                }
                $connection = new DataBiDeclarativeReviewRollbackConnection((new MySqlConnector)->connect($configuration), $name, $name === $this->probeName);
                $this->connections[$name] = $connection;
                $connection->beforeExecuting(fn () => $this->assertRouting());

                return $connection;
            });
            $this->capsule->setEventDispatcher(new Dispatcher($app));
            $app->instance('db', $manager);
            $app->bind('db.schema', fn () => $manager->connection()->getSchemaBuilder());
            Model::clearBootedModels();
            $this->capsule->bootEloquent();
            $primary = $manager->connection($this->primaryName);
            $probe = $manager->connection($this->probeName);
            $primary->openOuterTransaction();
            $probe->openOuterTransaction();
            self::assertNotSame($primary->getPdo(), $probe->getPdo());
            $sessions = [];
            foreach ($this->connections as $connection) {
                $session = $connection->selectOne('SELECT DATABASE() AS db, CONNECTION_ID() AS session_id');
                self::assertSame('laudaapi_dev', $session->db);
                $sessions[] = $session->session_id;
            }
            self::assertNotSame($sessions[0], $sessions[1]);
            foreach (DataBiDeclarativeReviewRollbackConnection::FIXTURE_TABLES as $table) {
                $engine = DB::selectOne('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', ['laudaapi_dev', $table]);
                self::assertNotNull($engine, 'Falta tabla previamente migrada: '.$table);
                self::assertSame('innodb', strtolower($engine->engine));
                DB::select('SELECT id FROM `'.$table.'` WHERE 1 = 0');
            }
            $grantee = "CONCAT(QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', 1)), '@', QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', -1)))";
            $global = DB::selectOne("SELECT COUNT(*) AS n FROM information_schema.USER_PRIVILEGES WHERE PRIVILEGE_TYPE = 'TRIGGER' AND GRANTEE = ".$grantee);
            $schema = DB::selectOne("SELECT COUNT(*) AS n FROM information_schema.SCHEMA_PRIVILEGES WHERE PRIVILEGE_TYPE = 'TRIGGER' AND TABLE_SCHEMA = DATABASE() AND GRANTEE = ".$grantee);
            if ((int) $global->n + (int) $schema->n === 0) {
                throw new LogicException('Sin visibilidad acreditada de triggers; no se permite DML.');
            }
            self::assertSame(0, DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', 'laudaapi_dev')
                ->whereIn('EVENT_OBJECT_TABLE', DataBiDeclarativeReviewRollbackConnection::FIXTURE_TABLES)->count());
            $this->capsule->getEventDispatcher()->listen(QueryExecuted::class, function (QueryExecuted $event): void {
                if ($event->connection->getName() !== $this->primaryName || ! preg_match('/\A\s*(insert|update|delete)\b/i', $event->sql)) {
                    return;
                }
                $this->dml[] = $event->sql;
                if (preg_match('/insert into `([a-z0-9_]+)` \(([^)]+)\)/i', $event->sql, $match)) {
                    preg_match_all('/`([a-z0-9_]+)`/', $match[2], $columns);
                    $position = array_search('id', $columns[1], true);
                    $id = $position === false ? (int) $event->connection->getPdo()->lastInsertId() : (int) $event->bindings[$position];
                    self::assertGreaterThan(0, $id);
                    $this->fixtureIds[$match[1]][$id] = $id;
                }
            });
            $primary->allowFixtures();
            $this->fixture = new DataBiTenantDeclarationServiceMySqlTest('test_harness_forbids_root_commit_and_preserves_nested_service_transactions');
            $this->setFixture('primaryName', $this->primaryName);
            $this->invokeFixture('createSyntheticScope');
            $this->request = $this->getFixture('request');
            $this->tenant = $this->getFixture('actor');
            DB::table('users')->where('id', $this->tenant->id)->update(['role' => 'subscriber', 'email_verified_at' => now()]);
            $this->tenant->refresh();
            $adminId = DB::table('users')->insertGetId(['name' => 'T4F-B2 QA Admin', 'email' => 't4fb2-'.bin2hex(random_bytes(12)).'@example.test',
                'password' => '*', 'role' => 'admin', 'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $this->admin = User::query()->findOrFail($adminId); // Sin membresía del Tenant.
            $this->setFixture('actor', $this->admin);
            $questionnaire = $this->invokeFixture('publish');
            $this->setFixture('actor', $this->tenant);
            $this->setFixture('questionnaire', $questionnaire);
            $this->authenticate($this->tenant);
            $input = $this->invokeFixture('input');
            foreach ($input['declarations'] as &$item) {
                $item['availability'] = 'unavailable';
                $item['sources'] = [];
                $item['notes'] = 'Brecha declarada por el Tenant.';
            }
            unset($item);
            (new DataBiTenantDeclarationService)->submit($this->request, $input, 0);
            $this->question = ['version' => $questionnaire->version, 'requirement_key' => $questionnaire->review_snapshot['requirements'][0]['key'], 'message' => '¿Cuál es el alcance de la brecha?'];
            $this->conclusion = ['version' => $questionnaire->version, 'conclusion' => 'Revisión declarativa terminada con brechas.', 'limitations' => 'Información declarada, sin verificación técnica.'];
            $this->service = new DataBiDeclarativeReviewService;
            $this->authenticate($this->admin);
            self::assertSame(0, $probe->table('transformation_implementation_requests')->where('id', $this->request->id)->count());
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
                foreach ($this->connections as $connection) {
                    self::assertSame(1, $connection->transactionLevel());
                    $connection->assertOuterTransaction();
                }
                $this->connections[$this->primaryName]->rollbackOuterTransaction();
                // Snapshot nuevo: una lectura REPEATABLE READ previa no puede ocultar residuos.
                $this->connections[$this->probeName]->rollbackOuterTransaction();
                $this->connections[$this->probeName]->openOuterTransaction();
                foreach ($this->fixtureIds as $table => $ids) {
                    self::assertSame(0, $this->connections[$this->probeName]->table($table)->whereIn('id', array_values($ids))->count(), 'Residuos tras rollback: '.$table);
                }
            }
        } finally {
            $this->cleanup();
            parent::tearDown();
        }
    }

    private function assertRouting(): void
    {
        $manager = $this->capsule->getDatabaseManager();
        self::assertSame($this->primaryName, $manager->getDefaultConnection());
        self::assertSame($manager, DB::getFacadeRoot());
        self::assertSame($manager, Model::getConnectionResolver());
        self::assertSame($this->connections[$this->primaryName], DB::connection());
    }

    private function cleanup(): void
    {
        if ($this->previous === []) {
            return;
        }
        $failure = null;
        try {
            foreach ($this->connections as $name => $connection) {
                try {
                    $connection->rollbackOuterTransaction();
                } catch (Throwable $e) {
                    $failure ??= $e;
                } finally {
                    $this->capsule?->getDatabaseManager()->purge($name);
                }
            }
        } finally {
            Container::setInstance($this->previous['container']);
            Facade::setFacadeApplication($this->previous['facades']);
            (new ReflectionProperty(Facade::class, 'resolvedInstance'))->setValue(null, $this->previous['roots']);
            foreach (['booted', 'bootedCallbacks', 'globalScopes'] as $field) {
                (new ReflectionProperty(Model::class, $field))->setValue(null, $this->previous[$field]);
            }
            $this->previous['resolver'] === null ? Model::unsetConnectionResolver() : Model::setConnectionResolver($this->previous['resolver']);
            $this->previous['dispatcher'] === null ? Model::unsetEventDispatcher() : Model::setEventDispatcher($this->previous['dispatcher']);
            $this->previous = [];
            $this->connections = [];
            $this->capsule = null;
        }
        if ($failure !== null) {
            throw $failure;
        }
    }

    private function getFixture(string $property): mixed
    {
        return (new ReflectionProperty($this->fixture, $property))->getValue($this->fixture);
    }

    private function setFixture(string $property, mixed $value): void
    {
        (new ReflectionProperty($this->fixture, $property))->setValue($this->fixture, $value);
    }

    private function invokeFixture(string $method, mixed ...$args): mixed
    {
        return (new ReflectionMethod($this->fixture, $method))->invoke($this->fixture, ...$args);
    }

    private function authenticate(User $user): void
    {
        Auth::swap(new RequestGuard(static fn () => $user, Request::create('/')));
    }

    private function question(): array
    {
        return $this->service->requestClarification($this->request, $this->question);
    }

    private function expectRejected(string $class, callable $operation, ?string $message = null): void
    {
        $before = $this->dml;
        try {
            $operation();
        } catch (Throwable $e) {
            self::assertInstanceOf($class, $e);
            if ($message !== null) {
                self::assertSame($message, $e->getMessage());
            }

            return;
        } finally {
            self::assertSame($before, $this->dml, 'Un rechazo no debe intentar DML.');
        }
        self::fail('Se esperaba rechazo.');
    }

    /** La condición negativa se inyecta solo después de acreditar un Tenant válido. */
    private function authenticateTenantForValidation(array $question): void
    {
        $before = $this->dml;
        $this->authenticate($this->tenant);
        self::assertSame((int) $this->tenant->id, (int) Auth::user()->getAuthIdentifier());
        $actor = $this->tenant->fresh();
        self::assertSame('subscriber', $actor->role);
        self::assertTrue($actor->hasVerifiedEmail());
        $review = $this->service->review($this->request, $this->question['version']);
        self::assertTrue($review['is_current']);
        self::assertSame($question['reference'], $review['reference']);
        self::assertSame([$question], $review['clarifications']);
        self::assertNull($review['closure']);
        self::assertSame('submitted', DB::table('data_bi_tenant_declarations')
            ->where('id', $question['reference']['declaration_id'])->first(['status'])?->status);
        self::assertSame($before, $this->dml, 'La comprobación previa de autorización debe ser de solo lectura.');
    }

    public function test_lifecycle_and_all_retries_preserve_events_audit_and_original_data(): void
    {
        $original = DB::table('data_bi_tenant_declarations')->where('data_bi_information_request_id', $this->getFixture('questionnaire')->id)->first();
        $request = DB::table('transformation_implementation_requests')->where('id', $this->request->id)->first();
        $questionnaire = $this->getFixture('questionnaire')->getRawOriginal();
        $question = $this->question();
        $before = $this->dml;
        self::assertSame($question, $this->question());
        self::assertSame($before, $this->dml);
        $this->authenticate($this->tenant);
        $input = ['version' => $this->question['version'], 'message' => 'No existe esa información en la empresa.'];
        $answer = $this->service->answerClarification($this->request, $question['id'], $input, 1);
        $before = $this->dml;
        self::assertSame($answer, $this->service->answerClarification($this->request, $question['id'], $input, 1));
        self::assertSame($before, $this->dml);
        $this->authenticate($this->admin);
        $resolution = ['version' => $input['version'], 'message' => 'La brecha queda documentada.'];
        $resolved = $this->service->resolveClarification($this->request, $question['id'], $resolution, 2);
        $before = $this->dml;
        self::assertSame($resolved, $this->service->resolveClarification($this->request, $question['id'], $resolution, 2));
        self::assertSame($before, $this->dml);
        $closure = $this->service->closeReview($this->request, $this->conclusion);
        self::assertNotEmpty($closure['declared_gaps']);
        self::assertFalse($closure['technical_validation_performed']);
        $before = $this->dml;
        self::assertSame($closure, $this->service->closeReview($this->request, $this->conclusion));
        self::assertSame($closure, $this->service->review($this->request)['closure']);
        self::assertSame($before, $this->dml);
        self::assertEquals($original, DB::table('data_bi_tenant_declarations')->where('id', $original->id)->first());
        self::assertEquals($request, DB::table('transformation_implementation_requests')->where('id', $request->id)->first());
        self::assertSame($questionnaire, $this->getFixture('questionnaire')->fresh()->getRawOriginal());
        self::assertSame(3, DB::table('data_bi_declaration_clarification_events')->where('data_bi_declaration_clarification_id', $question['id'])->count());
    }

    public function test_cancellation_is_justified_terminal_and_idempotent(): void
    {
        $question = $this->question();
        $input = ['version' => $this->question['version'], 'message' => 'Se cancela por estar fuera del alcance.'];
        $cancelled = $this->service->cancelClarification($this->request, $question['id'], $input, 1);
        $before = $this->dml;
        self::assertSame('cancelled', $cancelled['status']);
        self::assertSame($cancelled, $this->service->cancelClarification($this->request, $question['id'], $input, 1));
        self::assertSame($before, $this->dml);
        $this->authenticate($this->tenant);
        $this->expectRejected(InvalidArgumentException::class, fn () => $this->service->answerClarification($this->request, $question['id'], $input, 2));
    }

    #[DataProvider('negativeCases')]
    public function test_invalid_operations_are_rejected_before_dml(string $case): void
    {
        $question = $this->question();
        $input = ['version' => $this->question['version'], 'message' => 'Descripción aclaratoria.'];
        $exception = InvalidArgumentException::class;
        $message = null;
        $operation = fn () => $this->service->answerClarification($this->request, $question['id'], $input, 1);
        switch ($case) {
            case 'admin impersonation': $exception = AuthorizationException::class;
                break;
            case 'membership inactive': DB::table('subscriber_user')->where('user_id', $this->tenant->id)->update(['active' => 0]);
                $this->authenticate($this->tenant);
                $exception = AuthorizationException::class;
                break;
            case 'member':
                $member = $this->invokeFixture('tenant', null, 'member');
                DB::table('users')->where('id', $member->id)->update(['role' => 'subscriber', 'email_verified_at' => now()]);
                $this->authenticate($member);
                $exception = AuthorizationException::class;
                break;
            case 'foreign clarification': $this->authenticate($this->tenant);
                $operation = fn () => $this->service->answerClarification($this->request, PHP_INT_MAX, $input, 1);
                break;
            case 'stale': $this->authenticate($this->tenant);
                $operation = fn () => $this->service->answerClarification($this->request, $question['id'], $input, 0);
                break;
            case 'duplicate question': $operation = fn () => $this->service->requestClarification($this->request, [...$this->question, 'message' => mb_strtoupper($this->question['message'])]);
                break;
            case 'unanswered resolution': $operation = fn () => $this->service->resolveClarification($this->request, $question['id'], $input, 1);
                break;
            case 'open closure': $operation = fn () => $this->service->closeReview($this->request, $this->conclusion);
                break;
            case 'history tampered': DB::table('data_bi_declaration_clarification_events')->where('data_bi_declaration_clarification_id', $question['id'])->update(['message' => 'Alterado.']);
                $operation = fn () => $this->service->review($this->request);
                break;
            case 'wrong company':
                $this->authenticateTenantForValidation($question);
                $other = DB::table('companies')->insertGetId(['name' => 'T4F-B2 otro ámbito sintético', 'slug' => 't4fb2-'.bin2hex(random_bytes(12))]);
                DB::table('data_bi_declaration_clarifications')->where('id', $question['id'])->update(['company_id' => $other]);
                self::assertNotSame((int) $this->request->company_id, (int) $other);
                self::assertSame((int) $other, (int) DB::table('data_bi_declaration_clarifications')->where('id', $question['id'])->value('company_id'));
                $message = 'Historial, referencias o auditoría persistida inconsistentes.';
                break;
            case 'not submitted':
                $this->authenticateTenantForValidation($question);
                DB::table('data_bi_tenant_declarations')->where('id', $question['reference']['declaration_id'])->update(['status' => 'draft']);
                self::assertSame('draft', DB::table('data_bi_tenant_declarations')->where('id', $question['reference']['declaration_id'])->value('status'));
                $message = 'Se requiere una declaración enviada válida.';
                break;
            case 'superseded':
                $this->authenticateTenantForValidation($question);
                $replacement = $this->invokeFixture('publish');
                self::assertSame('sent', $replacement->status);
                self::assertSame((int) $this->request->id, (int) $replacement->transformation_implementation_request_id);
                self::assertSame((int) $this->request->company_id, (int) $replacement->company_id);
                self::assertGreaterThan($this->question['version'], (int) $replacement->version);
                self::assertSame((int) $replacement->id, (int) DB::table('data_bi_information_requests')
                    ->where('transformation_implementation_request_id', $this->request->id)->where('status', 'sent')->orderByDesc('version')->value('id'));
                $message = 'El cuestionario publicado fue sustituido o la versión es incorrecta.';
                break;
        }
        $this->expectRejected($exception, $operation, $message);
    }

    public static function negativeCases(): iterable
    {
        foreach (['admin impersonation', 'membership inactive', 'member', 'foreign clarification', 'stale', 'duplicate question',
            'unanswered resolution', 'open closure', 'history tampered', 'wrong company', 'not submitted', 'superseded'] as $case) {
            yield $case => [$case];
        }
    }

    public function test_historical_closure_remains_bound_to_its_publication(): void
    {
        $closure = $this->service->closeReview($this->request, $this->conclusion);
        $oldVersion = $this->question['version'];
        $new = $this->invokeFixture('publish');
        $this->setFixture('questionnaire', $new);
        $history = $this->service->review($this->request, $oldVersion);
        self::assertSame($closure, $history['closure']);
        self::assertFalse($history['is_current']);
        $this->authenticate($this->tenant);
        (new DataBiTenantDeclarationService)->submit($this->request, $this->invokeFixture('input'), 0);
        $this->authenticate($this->admin);
        self::assertNull($this->service->review($this->request, $new->version)['closure']);
    }

    public function test_database_uniques_and_restrictive_foreign_keys_are_prepared_for_verification(): void
    {
        $question = $this->question();
        $row = (array) DB::table('data_bi_declaration_clarifications')->where('id', $question['id'])->first();
        $row['id'] = random_int(1, 9007199254740991);
        $this->constraint(fn () => DB::table('data_bi_declaration_clarifications')->insert($row), 1062, 'dbdc_question_uq');
        $event = (array) DB::table('data_bi_declaration_clarification_events')->where('data_bi_declaration_clarification_id', $question['id'])->first();
        unset($event['id']);
        $this->constraint(fn () => DB::table('data_bi_declaration_clarification_events')->insert($event), 1062, 'dbdce_sequence_uq');
        $this->constraint(fn () => DB::table('data_bi_declaration_clarifications')->where('id', $question['id'])->delete(), 1451, 'dbdce_clarification_fk');
        $row['company_id'] = PHP_INT_MAX;
        $row['question_sha256'] = hash('sha256', 'QA FK inexistente '.bin2hex(random_bytes(12)));
        $this->constraint(fn () => DB::table('data_bi_declaration_clarifications')->insert($row), 1452, 'dbdc_company_fk');
        $this->service->cancelClarification($this->request, $question['id'], ['version' => $this->question['version'], 'message' => 'Consulta retirada.'], 1);
        $this->service->closeReview($this->request, $this->conclusion);
        $closure = (array) DB::table('data_bi_declarative_review_closures')->where('data_bi_tenant_declaration_id', $question['reference']['declaration_id'])->first();
        unset($closure['id']);
        $this->constraint(fn () => DB::table('data_bi_declarative_review_closures')->insert($closure), 1062, 'dbdrc_declaration_uq');
        $this->constraint(fn () => DB::table('data_bi_tenant_declarations')->where('id', $question['reference']['declaration_id'])->delete(), 1451);
        $foreignKeys = DB::table('information_schema.REFERENTIAL_CONSTRAINTS')->where('CONSTRAINT_SCHEMA', 'laudaapi_dev')
            ->whereIn('TABLE_NAME', ['data_bi_declaration_clarifications', 'data_bi_declaration_clarification_events', 'data_bi_declarative_review_closures'])->get();
        self::assertCount(13, $foreignKeys);
        foreach ($foreignKeys as $foreignKey) {
            self::assertSame('RESTRICT', $foreignKey->DELETE_RULE);
            self::assertSame('RESTRICT', $foreignKey->UPDATE_RULE);
        }
    }

    private function constraint(callable $operation, int $driverCode, ?string $name = null): void
    {
        try {
            $operation();
            self::fail('La restricción debe impedir el DML.');
        } catch (QueryException $e) {
            self::assertSame('23000', $e->errorInfo[0]);
            self::assertSame($driverCode, $e->errorInfo[1]);
            if ($name !== null) {
                self::assertStringContainsString($name, $e->errorInfo[2]);
            }
        }
    }

    public function test_a_real_foreign_clarification_cannot_bypass_the_associated_request(): void
    {
        $this->invokeFixture('createSyntheticScope');
        $otherRequest = $this->getFixture('request');
        $otherTenant = $this->getFixture('actor');
        DB::table('users')->where('id', $otherTenant->id)->update(['role' => 'subscriber', 'email_verified_at' => now()]);
        $otherTenant->refresh();
        $this->setFixture('actor', $this->admin);
        $questionnaire = $this->invokeFixture('publish');
        $this->setFixture('actor', $otherTenant);
        $this->setFixture('questionnaire', $questionnaire);
        $this->authenticate($otherTenant);
        (new DataBiTenantDeclarationService)->submit($otherRequest, $this->invokeFixture('input'), 0);
        $this->authenticate($this->admin);
        $foreign = $this->service->requestClarification($otherRequest, ['version' => $questionnaire->version,
            'requirement_key' => $questionnaire->review_snapshot['requirements'][0]['key'], 'message' => 'Pregunta de otro Tenant.']);
        $this->authenticate($this->tenant);
        $this->expectRejected(InvalidArgumentException::class, fn () => $this->service->answerClarification($this->request, $foreign['id'],
            ['version' => $this->question['version'], 'message' => 'Respuesta ajena.'], 1));
        $this->expectRejected(AuthorizationException::class, fn () => $this->service->review($otherRequest));
    }

    public function test_an_existing_closure_cannot_be_replaced_with_a_different_conclusion(): void
    {
        $closure = $this->service->closeReview($this->request, $this->conclusion);
        $this->expectRejected(InvalidArgumentException::class, fn () => $this->service->closeReview($this->request,
            [...$this->conclusion, 'conclusion' => 'Otra conclusión.']));
        self::assertSame($closure, $this->service->review($this->request)['closure']);
    }

    public function test_real_concurrency_between_two_services_remains_pending(): void
    {
        self::markTestSkipped('CONCURRENCY=NOT_VERIFIED: no confirmar fixtures en esta tarea.');
    }
}
