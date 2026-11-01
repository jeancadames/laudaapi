<?php

declare(strict_types=1);

namespace Tests\Unit\Diagnosis;

use App\Models\DataBiDeclarationClarification;
use App\Models\DataBiDeclarationClarificationEvent;
use App\Models\DataBiDeclarativeReviewClosure;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use App\Services\Diagnosis\DataBiDeclarativeReviewService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\RequestGuard;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tests\Support\BuildsDataBiDeclarativeReviewContexts;
use Tests\Support\DataBiReviewMemoryConnection;
use Throwable;

final class DataBiDeclarativeReviewServiceTest extends TestCase
{
    use BuildsDataBiDeclarativeReviewContexts;

    private DataBiReviewMemoryConnection $connection;

    private DataBiDeclarativeReviewService $service;

    private TransformationImplementationRequest $request;

    private array $previous;

    private array $missing = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->previous = ['container' => Container::getInstance(), 'facades' => Facade::getFacadeApplication(),
            'roots' => (new ReflectionProperty(Facade::class, 'resolvedInstance'))->getValue(),
            'resolver' => Model::getConnectionResolver(), 'dispatcher' => Model::getEventDispatcher(), 'clock' => CarbonImmutable::getTestNow()];
        foreach (['booted', 'bootedCallbacks', 'globalScopes'] as $field) {
            $this->previous[$field] = (new ReflectionProperty(Model::class, $field))->getValue();
        }
        $app = new Container;
        $app->instance('config', new Repository);
        $app->instance('validator', new Factory(new Translator(new ArrayLoader, 'es'), $app));
        Container::setInstance($app);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        $published = $this->publication();
        $published['review_snapshot'] = json_encode($published['review_snapshot'], JSON_THROW_ON_ERROR);
        $declaration = $this->declaration(gaps: true);
        $declaration['declaration_snapshot'] = json_encode($declaration['declaration_snapshot'], JSON_THROW_ON_ERROR);
        $this->connection = new DataBiReviewMemoryConnection([
            'transformation_implementation_requests' => [$this->requestData()],
            'companies' => [['id' => 7, 'subscriber_id' => 17, 'owner_user_id' => 20, 'active' => 1]],
            'subscribers' => [['id' => 17, 'active' => 1]],
            'users' => [['id' => 9, 'role' => 'admin', 'email_verified_at' => '2026-10-30 08:00:00'],
                ['id' => 21, 'role' => 'subscriber', 'email_verified_at' => '2026-10-30 08:00:00']],
            'subscriber_user' => [['id' => 1, 'subscriber_id' => 17, 'user_id' => 21, 'active' => 1, 'role' => 'admin']],
            'data_bi_information_requests' => [$published], 'data_bi_tenant_declarations' => [$declaration],
        ]);
        $capsule = new Capsule($app);
        $capsule->addConnection(['driver' => 'mysql', 'name' => 'b2_memory'], 'b2_memory');
        $manager = $capsule->getDatabaseManager();
        $manager->extend('mysql', fn () => $this->connection);
        $manager->setDefaultConnection('b2_memory');
        $app->instance('db', $manager);
        $capsule->setEventDispatcher(new Dispatcher($app));
        Model::clearBootedModels();
        $capsule->bootEloquent();
        Schema::swap(new class($this->missing)
        {
            public function __construct(private array &$missing) {}

            public function hasTable(string $table): bool
            {
                return ! in_array($table, $this->missing, true);
            }

            public function hasColumns(string $table, array $columns): bool
            {
                return array_all($columns, fn ($column): bool => ! in_array($table.'.'.$column, $this->missing, true));
            }
        });
        $this->request = (new TransformationImplementationRequest)->setRawAttributes($this->requestData(), true);
        $this->request->exists = true;
        $this->service = new DataBiDeclarativeReviewService;
        CarbonImmutable::setTestNow('2026-10-31T10:00:00Z');
        $this->authenticate(9);
    }

    protected function tearDown(): void
    {
        try {
            self::assertFalse($this->connection->pdoAttempted);
            self::assertSame(0, $this->connection->transactionLevel());
        } finally {
            Container::setInstance($this->previous['container']);
            Facade::setFacadeApplication($this->previous['facades']);
            (new ReflectionProperty(Facade::class, 'resolvedInstance'))->setValue(null, $this->previous['roots']);
            foreach (['booted', 'bootedCallbacks', 'globalScopes'] as $field) {
                (new ReflectionProperty(Model::class, $field))->setValue(null, $this->previous[$field]);
            }
            $this->previous['resolver'] === null ? Model::unsetConnectionResolver() : Model::setConnectionResolver($this->previous['resolver']);
            $this->previous['dispatcher'] === null ? Model::unsetEventDispatcher() : Model::setEventDispatcher($this->previous['dispatcher']);
            CarbonImmutable::setTestNow($this->previous['clock']);
            parent::tearDown();
        }
    }

    private function authenticate(?int $id): void
    {
        $user = null;
        if ($id !== null) {
            $user = (new User)->setRawAttributes(['id' => $id, 'role' => 'untrusted_guard_role'], true);
            $user->exists = true;
        }
        Auth::swap(new RequestGuard(static fn () => $user, Request::create('/')));
    }

    private function createQuestion(): array
    {
        $this->authenticate(9);

        return $this->service->requestClarification($this->request, $this->question());
    }

    public function test_full_lifecycle_retries_read_only_queries_and_original_data_are_preserved(): void
    {
        $original = array_intersect_key($this->connection->rows, array_flip(['transformation_implementation_requests', 'data_bi_information_requests', 'data_bi_tenant_declarations']));
        $requested = $this->createQuestion();
        self::assertSame(2, count($this->connection->dml));
        $dml = $this->connection->dml;
        self::assertSame($requested, $this->service->requestClarification($this->request, $this->question()));
        self::assertSame($dml, $this->connection->dml);
        $this->authenticate(21);
        $input = ['version' => 3, 'message' => 'Período declarado aclarado.'];
        $answered = $this->service->answerClarification($this->request, $requested['id'], $input, 1);
        self::assertSame('answered', $answered['status']);
        $dml = $this->connection->dml;
        CarbonImmutable::setTestNow('2026-10-31T11:00:00Z');
        self::assertSame($answered, $this->service->answerClarification($this->request, $requested['id'], $input, 1));
        self::assertSame($dml, $this->connection->dml);
        $this->authenticate(9);
        $resolution = ['version' => 3, 'message' => 'Alcance atendido declarativamente.'];
        $resolved = $this->service->resolveClarification($this->request, $requested['id'], $resolution, 2);
        $dml = $this->connection->dml;
        self::assertSame($resolved, $this->service->resolveClarification($this->request, $requested['id'], $resolution, 2));
        self::assertSame($dml, $this->connection->dml);
        $closure = $this->service->closeReview($this->request, $this->conclusion());
        self::assertCount(3, $closure['declared_gaps']);
        self::assertFalse($closure['commercial_approval']);
        $dml = $this->connection->dml;
        self::assertSame($closure, $this->service->closeReview($this->request, $this->conclusion()));
        self::assertSame($closure, $this->service->review($this->request)['closure']);
        $this->authenticate(21);
        self::assertSame($resolved, $this->service->answerClarification($this->request, $requested['id'], $input, 1));
        self::assertSame($closure, $this->service->review($this->request)['closure']);
        self::assertSame($dml, $this->connection->dml);
        self::assertCount(3, $this->connection->rows['data_bi_declaration_clarification_events']);
        self::assertSame($original, array_intersect_key($this->connection->rows, $original));
        $reads = array_column($this->connection->queries, 0);
        self::assertStringContainsString('for update', $reads[0]);
        self::assertStringContainsString('transformation_implementation_requests', $reads[0]);
        self::assertStringContainsString('companies', $reads[1]);
        self::assertStringContainsString('subscribers', $reads[2]);
        self::assertStringContainsString('users', $reads[3]);
    }

    public function test_cancellation_retry_does_not_append_or_change_audit(): void
    {
        $question = $this->createQuestion();
        $input = ['version' => 3, 'message' => 'Consulta retirada por estar fuera del alcance.'];
        $cancelled = $this->service->cancelClarification($this->request, $question['id'], $input, 1);
        $dml = $this->connection->dml;
        self::assertSame('cancelled', $cancelled['status']);
        self::assertSame($cancelled, $this->service->cancelClarification($this->request, $question['id'], $input, 1));
        self::assertSame($dml, $this->connection->dml);
    }

    #[DataProvider('denials')]
    public function test_denials_and_integrity_errors_perform_no_writes(string $case): void
    {
        $question = $this->createQuestion();
        $expected = InvalidArgumentException::class;
        switch ($case) {
            case 'guest': $this->authenticate(null);
                $expected = AuthenticationException::class;
                break;
            case 'admin as tenant': $expected = AuthorizationException::class;
                break;
            case 'unverified': $this->connection->rows['users'][1]['email_verified_at'] = null;
                $this->authenticate(21);
                $expected = AuthorizationException::class;
                break;
            case 'inactive': $this->connection->rows['subscriber_user'][0]['active'] = 0;
                $this->authenticate(21);
                $expected = AuthorizationException::class;
                break;
            case 'member': $this->connection->rows['subscriber_user'][0]['role'] = 'member';
                $this->authenticate(21);
                $expected = AuthorizationException::class;
                break;
            case 'other subscriber': $this->connection->rows['subscriber_user'][0]['subscriber_id'] = 99;
                $this->authenticate(21);
                $expected = AuthorizationException::class;
                break;
            case 'company inactive': $this->connection->rows['companies'][0]['active'] = 0;
                $this->authenticate(21);
                $expected = AuthorizationException::class;
                break;
            case 'subscriber inactive': $this->connection->rows['subscribers'][0]['active'] = 0;
                $this->authenticate(21);
                $expected = AuthorizationException::class;
                break;
            case 'stale': $this->authenticate(21);
                break;
            case 'draft': $this->connection->rows['data_bi_tenant_declarations'][0]['status'] = 'draft';
                $this->authenticate(21);
                break;
            case 'history altered': $this->connection->rows['data_bi_declaration_clarification_events'][0]['message'] = 'Alterado.';
                $this->authenticate(21);
                break;
            case 'projection altered': $this->connection->rows['data_bi_declaration_clarifications'][0]['status'] = 'resolved';
                $this->authenticate(21);
                break;
            case 'wrong scope': $this->connection->rows['data_bi_declaration_clarifications'][0]['company_id'] = 8;
                $this->authenticate(21);
                break;
            case 'missing event': $this->connection->rows['data_bi_declaration_clarification_events'] = [];
                $this->authenticate(21);
                break;
            case 'schema': $this->missing = ['data_bi_declaration_clarification_events.projection_sha256'];
                $this->authenticate(21);
                $expected = ValidationException::class;
                break;
            case 'revoked after lock':
                $this->authenticate(21);
                $expected = AuthorizationException::class;
                $this->connection->afterParentLock = function (): void {
                    $this->connection->rows['subscriber_user'][0]['active'] = 0;
                };
                break;
        }
        $before = $this->connection->dml;
        try {
            $this->service->answerClarification($this->request, $question['id'], ['version' => 3, 'message' => 'Respuesta.'], $case === 'stale' ? 0 : 1);
            self::fail('Debe rechazar: '.$case);
        } catch (Throwable $e) {
            self::assertInstanceOf($expected, $e);
        }
        self::assertSame($before, $this->connection->dml);
    }

    public static function denials(): iterable
    {
        foreach (['guest', 'admin as tenant', 'unverified', 'inactive', 'member', 'other subscriber', 'company inactive',
            'subscriber inactive', 'stale', 'draft', 'history altered', 'projection altered', 'wrong scope', 'missing event', 'schema', 'revoked after lock'] as $case) {
            yield $case => [$case];
        }
    }

    public function test_new_publication_freezes_operations_but_keeps_historical_reads(): void
    {
        $question = $this->createQuestion();
        $new = $this->publication(4);
        $new['review_snapshot'] = json_encode($new['review_snapshot'], JSON_THROW_ON_ERROR);
        $this->connection->rows['data_bi_information_requests'][] = $new;
        $dml = $this->connection->dml;
        $history = $this->service->review($this->request, 3);
        self::assertFalse($history['is_current']);
        self::assertSame([$question], $history['clarifications']);
        self::assertSame($dml, $this->connection->dml);
        $this->expectException(InvalidArgumentException::class);
        $this->service->cancelClarification($this->request, $question['id'], ['version' => 3, 'message' => 'Cancelar.'], 1);
    }

    public function test_open_clarification_blocks_closure_without_writes(): void
    {
        $this->createQuestion();
        $before = $this->connection->rows;
        try {
            $this->service->closeReview($this->request, $this->conclusion());
            self::fail('No puede cerrar con aclaraciones abiertas.');
        } catch (InvalidArgumentException) {
            self::assertSame($before, $this->connection->rows);
        }
    }

    public function test_query_without_clarifications_or_closure_is_read_only(): void
    {
        $before = $this->connection->rows;
        $result = $this->service->review($this->request);
        self::assertTrue($result['is_current']);
        self::assertSame([], $result['clarifications']);
        self::assertNull($result['closure']);
        self::assertSame([], $this->connection->dml);
        self::assertSame($before, $this->connection->rows);
    }

    #[DataProvider('modelMutations')]
    public function test_models_defend_immutable_events_closures_and_clarification_identity(string $target, string $operation): void
    {
        $question = $this->createQuestion();
        $this->service->cancelClarification($this->request, $question['id'], ['version' => 3, 'message' => 'Retirada justificada.'], 1);
        $this->service->closeReview($this->request, $this->conclusion());
        $row = $this->connection->transaction(fn () => match ($target) {
            'event' => DataBiDeclarationClarificationEvent::query()->where('id', 1)->firstOrFail(),
            'closure' => DataBiDeclarativeReviewClosure::query()->where('id', 1)->firstOrFail(),
            default => DataBiDeclarationClarification::query()->where('id', $question['id'])->firstOrFail(),
        });
        $before = $this->connection->dml;
        try {
            if ($operation === 'delete') {
                $row->delete();
            } else {
                $row->forceFill(match ($target) {
                    'event' => ['message' => 'Cambio histórico.'],
                    'closure' => ['conclusion' => 'Cambio histórico.'],
                    default => ['company_id' => 8],
                })->save();
            }
            self::fail('Debe proteger la inmutabilidad.');
        } catch (\LogicException $e) {
            $messages = [
                'event' => ['update' => 'No se permite editar eventos de aclaración.', 'delete' => 'No se permite eliminar eventos de aclaración.'],
                'closure' => ['update' => 'El cierre declarativo es inmutable.', 'delete' => 'El cierre declarativo histórico no se elimina.'],
                'clarification' => ['update' => 'Identidad e historial de aclaración inmutables; requiere una nueva transición.',
                    'delete' => 'Las aclaraciones conservan su historia; no se eliminan.'],
            ];
            self::assertSame($messages[$target][$operation], $e->getMessage());
            self::assertSame($before, $this->connection->dml);
        }
    }

    public static function modelMutations(): iterable
    {
        foreach (['event', 'closure', 'clarification'] as $target) {
            foreach (['update', 'delete'] as $operation) {
                yield [$target, $operation];
            }
        }
    }

    public function test_an_open_clarification_cannot_change_its_original_scope(): void
    {
        $question = $this->createQuestion();
        $row = $this->connection->transaction(fn () => DataBiDeclarationClarification::query()->where('id', $question['id'])->firstOrFail());
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Identidad e historial de aclaración inmutables');
        $row->forceFill(['company_id' => 8, 'revision' => 2])->save();
    }
}
