<?php

declare(strict_types=1);

namespace Tests\Unit\Diagnosis;

use App\Http\Controllers\DataBiDeclarativeReviewController;
use App\Http\Middleware\DataBiDeclarativeReviewAudience;
use App\Http\Middleware\DataBiDeclarativeReviewJson;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\BuildsDataBiDeclarativeReviewContexts;
use Tests\Support\BuildsDataBiTenantDeclarationHttpApplication;
use Tests\Support\DataBiReviewMemoryConnection;

/** Router/web/CSRF, controlador y B2 reales. Almacenamiento PHP: PDO prohibido. */
final class DataBiDeclarativeReviewHttpTest extends TestCase
{
    use BuildsDataBiDeclarativeReviewContexts;
    use BuildsDataBiTenantDeclarationHttpApplication;

    private DataBiReviewMemoryConnection $connection;

    private array $missing = [];

    private mixed $clock;

    private string $adminBase = '/admin/transformation-360/implementation-requests/11/declarative-review';

    private string $tenantBase = '/app/transformacion-360/datos-bi/requerimientos/11/aclaraciones';

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = CarbonImmutable::getTestNow();
        CarbonImmutable::setTestNow('2026-10-31T14:00:00Z');
        $this->startHttpApplication();
        $this->httpApp['router']->middleware('web')->group(dirname(__DIR__, 3).'/routes/admin.php');
        $this->httpApp['router']->getRoutes()->refreshNameLookups();
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
        $capsule = new Capsule($this->httpApp);
        $capsule->addConnection(['driver' => 'mysql', 'name' => 'b2_memory'], 'b2_memory');
        $manager = $capsule->getDatabaseManager();
        $manager->extend('mysql', fn () => $this->connection);
        $manager->setDefaultConnection('b2_memory');
        $this->httpApp->instance('db', $manager);
        DB::clearResolvedInstance('db');
        $capsule->setEventDispatcher($this->httpApp['events']);
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
        // Solo el adaptador de almacenamiento del binding: consulta Model real, sin PDO.
        $this->httpApp['router']->bind('implementationRequest', fn (string $id) => $this->connection->transaction(
            fn () => TransformationImplementationRequest::query()->findOrFail((int) $id)));
        $this->loginAs(9);
    }

    protected function tearDown(): void
    {
        try {
            self::assertFalse($this->connection->pdoAttempted);
            self::assertSame(0, $this->connection->transactionLevel());
        } finally {
            $this->stopHttpApplication();
            CarbonImmutable::setTestNow($this->clock);
            parent::tearDown();
        }
    }

    private function loginAs(?int $id): void
    {
        $guard = $this->httpApp['auth']->guard();
        $guard->forgetUser();
        if ($id !== null) {
            $row = array_values(array_filter($this->connection->rows['users'], static fn ($row) => $row['id'] === $id))[0];
            $user = (new User)->setRawAttributes($row, true);
            $user->exists = true;
            $guard->setUser($user);
        }
    }

    private function http(string $method, string $url, array|string $input = '', bool $csrf = true)
    {
        $response = $this->declarationHttp($method, $url, $input, $csrf);
        if ($this->name() !== 'test_infrastructure_and_unexpected_errors_never_expose_traces_or_success') {
            self::assertNotSame(500, $response->getStatusCode(), implode("\n", array_map(static fn ($error): string => (string) $error, $this->httpReportedErrors)));
        }

        return $response;
    }

    private function createQuestion(): array
    {
        $this->loginAs(9);

        return $this->http('POST', $this->adminBase.'/clarifications', [...$this->question(), 'expected_revision' => 0])
            ->assertOk()->json('data.clarifications.0');
    }

    public function test_seven_routes_keep_real_middleware_and_the_t4fa_get(): void
    {
        $router = $this->httpApp['router'];
        $names = ['admin.transformation360.implementation_requests.declarative_review.' => ['show', 'clarifications.request', 'clarifications.resolve', 'clarifications.cancel', 'close'],
            'app.transformation.data_bi.information_requirements.clarifications.' => ['show', 'answer']];
        foreach ($names as $prefix => $suffixes) {
            foreach ($suffixes as $suffix) {
                $route = $router->getRoutes()->getByName($prefix.$suffix);
                self::assertNotNull($route);
                self::assertContains('web', $route->middleware());
                self::assertContains('auth', $route->middleware());
                self::assertContains('verified', $route->middleware());
                self::assertSame('[0-9]+', $route->wheres['implementationRequest']);
                self::assertStringStartsWith(DataBiDeclarativeReviewController::class.'@', $route->getActionName());
                $stack = $router->gatherRouteMiddleware($route);
                self::assertSame(DataBiDeclarativeReviewJson::class, $stack[0]);
                self::assertContains(StartSession::class, $stack);
                self::assertContains(ValidateCsrfToken::class, $stack);
                $audience = array_values(array_filter($stack, static fn ($name) => str_starts_with($name, DataBiDeclarativeReviewAudience::class)))[0];
                self::assertLessThan(array_search(SubstituteBindings::class, $stack), array_search($audience, $stack));
                if (str_starts_with($prefix, 'admin.')) {
                    self::assertContains('role:admin', $route->middleware());
                }
            }
        }
        $fa = $router->getRoutes()->getByName('admin.transformation360.implementation_requests.information_requirements.tenant_declarations.show');
        self::assertSame(['GET', 'HEAD'], $fa->methods());
        self::assertStringContainsString('AdminDataBiTenantDeclarationsController', $fa->getActionName());
    }

    public function test_both_gets_with_no_clarifications_are_read_only(): void
    {
        $before = $this->connection->rows;
        foreach ([9 => $this->adminBase, 21 => $this->tenantBase] as $actor => $url) {
            $this->loginAs($actor);
            $response = $this->http('GET', $url)->assertOk()->assertJsonPath('data.clarifications', [])
                ->assertJsonPath('data.closure', null)->assertJsonPath('data.questionnaire_version', 3);
            self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
            self::assertTrue($response->headers->hasCacheControlDirective('private'));
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
        }
        self::assertSame([], $this->connection->dml);
        self::assertSame($before, $this->connection->rows);
    }

    public function test_lifecycle_uses_b2_and_idempotent_retries_preserve_public_and_internal_state(): void
    {
        $before = $this->connection->rows['data_bi_tenant_declarations'];
        $question = $this->createQuestion();
        $dml = $this->connection->dml;
        $this->http('POST', $this->adminBase.'/clarifications', [...$this->question(), 'expected_revision' => 0])->assertOk()
            ->assertJsonPath('data.clarifications.0.id', $question['id']);
        self::assertSame($dml, $this->connection->dml);
        self::assertSame(['answer' => false, 'resolve' => false, 'cancel' => true], $question['allowed_actions']);
        $this->loginAs(21);
        $this->http('GET', $this->tenantBase)->assertOk()->assertJsonPath('data.clarifications.0.allowed_actions.answer', true);
        $input = ['version' => 3, 'message' => 'Alcance descriptivo aclarado.', 'expected_revision' => 1];
        $answerUrl = $this->tenantBase.'/'.$question['id'].'/responder';
        $answer = $this->http('POST', $answerUrl, $input)->assertOk()->assertJsonPath('data.clarifications.0.status', 'answered')->json();
        $dml = $this->connection->dml;
        CarbonImmutable::setTestNow('2026-10-31T15:00:00Z');
        self::assertSame($answer, $this->http('POST', $answerUrl, $input)->assertOk()->json());
        self::assertSame($dml, $this->connection->dml);
        $this->loginAs(9);
        $resolution = ['version' => 3, 'message' => 'Alcance atendido.', 'expected_revision' => 2];
        $resolveUrl = $this->adminBase.'/clarifications/'.$question['id'].'/resolve';
        $resolved = $this->http('POST', $resolveUrl, $resolution)->assertOk()->assertJsonPath('data.clarifications.0.status', 'resolved')->json();
        $dml = $this->connection->dml;
        self::assertSame($resolved, $this->http('POST', $resolveUrl, $resolution)->assertOk()->json());
        self::assertSame($dml, $this->connection->dml);
        $closeInput = [...$this->conclusion(), 'expected_revision' => 0];
        $closed = $this->http('POST', $this->adminBase.'/close', $closeInput)->assertOk()
            ->assertJsonPath('data.review_status', 'closed')->assertJsonCount(3, 'data.closure.declared_gaps')->json();
        self::assertFalse($closed['data']['allowed_actions']['request_clarification']);
        $dml = $this->connection->dml;
        self::assertSame($closed, $this->http('POST', $this->adminBase.'/close', $closeInput)->assertOk()->json());
        self::assertSame($dml, $this->connection->dml);
        self::assertSame($before, $this->connection->rows['data_bi_tenant_declarations']);
        $keys = [];
        array_walk_recursive($closed, static function ($value, $key) use (&$keys): void {
            $keys[] = $key;
        });
        foreach (['content_sha256', 'questionnaire_sha256', 'actor_user_id', 'company_id', 'subscriber_id', 'reference',
            'declaration_id', 'clarification_snapshot', 'closure_snapshot', 'closed_by_user_id', 'created_by_user_id'] as $forbidden) {
            self::assertNotContains($forbidden, $keys);
        }
    }

    public function test_cancellation_and_its_retry_preserve_events(): void
    {
        $question = $this->createQuestion();
        $url = $this->adminBase.'/clarifications/'.$question['id'].'/cancel';
        $input = ['version' => 3, 'message' => 'Retirada por estar fuera del alcance.', 'expected_revision' => 1];
        $cancelled = $this->http('POST', $url, $input)->assertOk()->assertJsonPath('data.clarifications.0.status', 'cancelled')->json();
        $dml = $this->connection->dml;
        self::assertSame($cancelled, $this->http('POST', $url, $input)->assertOk()->json());
        self::assertSame($dml, $this->connection->dml);
    }

    #[DataProvider('middlewareOperations')]
    public function test_guests_unverified_users_and_csrf_receive_private_json(string $method, string $url): void
    {
        $this->loginAs(null);
        $this->assertDeclarationError($this->http($method, $url), 401, 'unauthenticated');
        $this->connection->rows['users'][0]['email_verified_at'] = null;
        $this->loginAs(9);
        $this->assertDeclarationError($this->http($method, $url), 403, 'email_unverified');
        if ($method === 'POST') {
            $this->connection->rows['users'][0]['email_verified_at'] = '2026-10-30 08:00:00';
            $this->loginAs(9);
            $this->assertDeclarationError($this->http($method, $url, [], false), 419, 'csrf_token_mismatch');
        }
    }

    public static function middlewareOperations(): iterable
    {
        $base = '/admin/transformation-360/implementation-requests/11/declarative-review';
        yield ['GET', $base];
        yield ['POST', $base.'/clarifications'];
        yield ['POST', $base.'/clarifications/999/resolve'];
        yield ['POST', $base.'/clarifications/999/cancel'];
        yield ['POST', $base.'/close'];
        yield ['GET', '/app/transformacion-360/datos-bi/requerimientos/11/aclaraciones'];
        yield ['POST', '/app/transformacion-360/datos-bi/requerimientos/11/aclaraciones/999/responder'];
    }

    public function test_role_audience_is_checked_before_binding_even_for_missing_ids(): void
    {
        $this->loginAs(21);
        foreach ([$this->adminBase, str_replace('/11/', '/999/', $this->adminBase)] as $url) {
            $this->assertDeclarationError($this->http('POST', $url.'/close', '{bad'), 403, 'forbidden');
        }
        $this->loginAs(9);
        foreach ([$this->tenantBase, str_replace('/11/', '/999/', $this->tenantBase)] as $url) {
            $this->assertDeclarationError($this->http('GET', $url), 404, 'not_found');
        }
    }

    #[DataProvider('unauthorizedScopes')]
    public function test_tenant_scope_denials_precede_payload_validation(string $case): void
    {
        $this->loginAs(21);
        match ($case) {
            'membership inactive' => $this->connection->rows['subscriber_user'][0]['active'] = 0,
            'member' => $this->connection->rows['subscriber_user'][0]['role'] = 'member',
            'other subscriber' => $this->connection->rows['subscriber_user'][0]['subscriber_id'] = 18,
            'company inactive' => $this->connection->rows['companies'][0]['active'] = 0,
            'subscriber inactive' => $this->connection->rows['subscribers'][0]['active'] = 0,
            'wrong capability' => $this->connection->rows['transformation_implementation_requests'][0]['capability_key'] = 'other',
        };
        $this->assertDeclarationError($this->http('POST', $this->tenantBase.'/999/responder', '{bad'), 404, 'not_found');
        self::assertSame([], $this->connection->dml);
    }

    public static function unauthorizedScopes(): iterable
    {
        foreach (['membership inactive', 'member', 'other subscriber', 'company inactive', 'subscriber inactive', 'wrong capability'] as $case) {
            yield [$case];
        }
    }

    #[DataProvider('badInputs')]
    public function test_extra_attributes_and_altered_inputs_are_rejected(array|string $input): void
    {
        $this->assertDeclarationError($this->http('POST', $this->adminBase.'/clarifications', $input), 422, 'validation_failed');
        self::assertSame([], $this->connection->dml);
    }

    public static function badInputs(): iterable
    {
        $input = ['version' => 3, 'requirement_key' => 'req_1', 'message' => 'Pregunta declarativa.', 'expected_revision' => 0];
        foreach (['actor_id', 'company_id', 'user_id', 'subscriber_id', 'questionnaire_id', 'expected_version', 'status', 'reference', 'files'] as $field) {
            yield $field => [[...$input, $field => 1]];
        }
        foreach (['version', 'requirement_key', 'message', 'expected_revision'] as $field) {
            $missing = $input;
            unset($missing[$field]);
            yield 'missing '.$field => [$missing];
        }
        yield [[...$input, 'version' => '3']];
        yield [[...$input, 'expected_revision' => '0']];
        yield [[...$input, 'message' => '']];
        yield [[...$input, 'message' => 'password=hidden']];
        yield [[...$input, 'message' => new \stdClass]];
        yield [[...$input, 'requirement_key' => 'foreign']];
        yield ['{bad'];
        yield ['[]'];
    }

    public function test_stale_revision_invalid_transition_open_closure_and_substitution_are_distinct(): void
    {
        $question = $this->createQuestion();
        $this->assertDeclarationError($this->http('POST', $this->adminBase.'/close', [...$this->conclusion(), 'expected_revision' => 0]), 409, 'clarifications_open');
        $this->assertDeclarationError($this->http('POST', $this->adminBase.'/clarifications/'.$question['id'].'/resolve',
            ['version' => 3, 'message' => 'Atendida.', 'expected_revision' => 1]), 409, 'clarification_state_conflict');
        $this->loginAs(21);
        $this->assertDeclarationError($this->http('POST', $this->tenantBase.'/'.$question['id'].'/responder',
            ['version' => 3, 'message' => 'Respuesta.', 'expected_revision' => 0]), 409, 'revision_conflict');
        $new = $this->publication(4);
        $new['review_snapshot'] = json_encode($new['review_snapshot'], JSON_THROW_ON_ERROR);
        $this->connection->rows['data_bi_information_requests'][] = $new;
        $this->http('GET', $this->tenantBase.'?version=3')->assertOk()->assertJsonPath('data.is_current', false)
            ->assertJsonPath('data.clarifications.0.allowed_actions.answer', false);
        $this->assertDeclarationError($this->http('POST', $this->tenantBase.'/'.$question['id'].'/responder',
            ['version' => 3, 'message' => 'Respuesta.', 'expected_revision' => 1]), 409, 'questionnaire_replaced');
        $this->loginAs(9);
        $this->assertDeclarationError($this->http('POST', $this->adminBase.'/clarifications',
            [...$this->question(), 'expected_revision' => 0]), 409, 'questionnaire_replaced');
        $this->assertDeclarationError($this->http('POST', $this->adminBase.'/close',
            [...$this->conclusion(), 'expected_revision' => 0]), 409, 'questionnaire_replaced');
    }

    public function test_absent_submission_and_schema_or_integrity_failures_are_distinct(): void
    {
        $this->connection->rows['data_bi_tenant_declarations'][0]['status'] = 'draft';
        $this->assertDeclarationError($this->http('GET', $this->adminBase), 409, 'declaration_not_submitted');
        $this->connection->rows['data_bi_tenant_declarations'][0]['status'] = 'submitted';
        $this->missing = ['data_bi_declaration_clarification_events.projection_sha256'];
        $this->assertDeclarationError($this->http('GET', $this->adminBase), 503, 'schema_unavailable');
        $this->missing = [];
        $this->connection->rows['data_bi_tenant_declarations'][0]['content_sha256'] = str_repeat('f', 64);
        $this->assertDeclarationError($this->http('GET', $this->adminBase), 409, 'declarative_integrity_conflict');
    }

    public function test_unknown_or_foreign_clarification_cannot_be_probed_with_invalid_payload(): void
    {
        $question = $this->createQuestion();
        $this->connection->rows['data_bi_declaration_clarifications'][0]['transformation_implementation_request_id'] = 99;
        $this->loginAs(21);
        foreach (['{bad', ['version' => 3, 'message' => 'Respuesta.', 'expected_revision' => 1]] as $body) {
            $this->assertDeclarationError($this->http('POST', $this->tenantBase.'/'.$question['id'].'/responder', $body), 404, 'not_found');
            $this->assertDeclarationError($this->http('POST', $this->tenantBase.'/999/responder', $body), 404, 'not_found');
        }
    }

    public function test_query_parameters_and_get_bodies_are_strict(): void
    {
        foreach (['?company_id=7', '?version[]=3', '?version=0'] as $suffix) {
            $this->assertDeclarationError($this->http('GET', $this->adminBase.$suffix), 422, 'validation_failed');
        }
        $this->assertDeclarationError($this->http('GET', $this->adminBase, '{}'), 422, 'validation_failed');
    }

    public function test_an_existing_foreign_request_is_indistinguishable_from_a_missing_one(): void
    {
        $this->connection->rows['transformation_implementation_requests'][] = ['id' => 12, 'company_id' => 8, 'capability_key' => 'data_transformation_bi'];
        $this->connection->rows['companies'][] = ['id' => 8, 'subscriber_id' => 18, 'owner_user_id' => 22, 'active' => 1];
        $this->connection->rows['subscribers'][] = ['id' => 18, 'active' => 1];
        $this->loginAs(21);
        $responses = [];
        foreach ([12, 999] as $id) {
            $url = str_replace('/11/', '/'.$id.'/', $this->tenantBase);
            $response = $this->http('POST', $url.'/999/responder', '{bad');
            $this->assertDeclarationError($response, 404, 'not_found');
            $responses[] = $response->json();
        }
        self::assertSame($responses[0], $responses[1]);
        self::assertSame([], $this->connection->dml);
    }

    public function test_duplicate_requests_changed_submitted_answers_and_closed_review_keep_distinct_errors(): void
    {
        $question = $this->createQuestion();
        $this->assertDeclarationError($this->http('POST', $this->adminBase.'/clarifications',
            [...$this->question(mb_strtoupper($this->question()['message'])), 'expected_revision' => 0]), 409, 'duplicate_clarification');
        $this->loginAs(21);
        $url = $this->tenantBase.'/'.$question['id'].'/responder';
        $this->http('POST', $url, ['version' => 3, 'message' => 'Respuesta.', 'expected_revision' => 1])->assertOk();
        $this->assertDeclarationError($this->http('POST', $url,
            ['version' => 3, 'message' => 'Otra respuesta.', 'expected_revision' => 1]), 409, 'revision_conflict');
        $this->assertDeclarationError($this->http('POST', $url,
            ['version' => 3, 'message' => 'Otra respuesta.', 'expected_revision' => 2]), 409, 'clarification_state_conflict');
        $this->loginAs(9);
        $this->http('POST', $this->adminBase.'/clarifications/'.$question['id'].'/resolve',
            ['version' => 3, 'message' => 'Atendida.', 'expected_revision' => 2])->assertOk();
        $this->http('POST', $this->adminBase.'/close', [...$this->conclusion(), 'expected_revision' => 0])->assertOk();
        $before = $this->connection->dml;
        $this->assertDeclarationError($this->http('POST', $this->adminBase.'/close',
            [...$this->conclusion(), 'conclusion' => 'Conclusión cambiada.', 'expected_revision' => 0]), 409, 'review_closed');
        $this->assertDeclarationError($this->http('POST', $this->adminBase.'/clarifications',
            [...$this->question('Otra consulta.'), 'expected_revision' => 0]), 409, 'review_closed');
        self::assertSame($before, $this->connection->dml);
    }

    public function test_tenant_cannot_invoke_admin_actions_and_verification_is_reloaded_from_server(): void
    {
        $this->loginAs(21);
        foreach (['/clarifications', '/clarifications/999/resolve', '/clarifications/999/cancel', '/close'] as $suffix) {
            $this->assertDeclarationError($this->http('POST', $this->adminBase.$suffix, '{bad'), 403, 'forbidden');
        }
        $this->loginAs(9);
        $this->connection->rows['users'][0]['email_verified_at'] = null; // guard todavía contiene la versión verificada.
        $this->assertDeclarationError($this->http('GET', $this->adminBase), 403, 'email_unverified');
        self::assertSame([], $this->connection->dml);
    }

    public function test_token_transport_media_type_and_post_queries_are_strict(): void
    {
        $this->declarationHttp('POST', $this->adminBase.'/clarifications', 'version=3', true, 'application/x-www-form-urlencoded')
            ->assertStatus(415)->assertJsonPath('error.code', 'unsupported_media_type');
        $this->assertDeclarationError($this->http('POST', $this->adminBase.'/clarifications?company_id=7',
            [...$this->question(), 'expected_revision' => 0]), 422, 'validation_failed');
        $this->http('POST', $this->adminBase.'/clarifications', [...$this->question(), 'expected_revision' => 0,
            '_token' => $this->httpApp['session.store']->token()])->assertOk();
    }

    public function test_infrastructure_and_unexpected_errors_never_expose_traces_or_success(): void
    {
        $bound = (new TransformationImplementationRequest)->setRawAttributes($this->requestData(), true);
        $bound->exists = true;
        $this->httpApp['router']->bind('implementationRequest', static fn () => $bound);
        $this->connection->afterParentLock = static function (): void {
            $previous = new PDOException('credential=INTERNAL_SECRET');
            $previous->errorInfo = ['HY000', 2002, 'INTERNAL_SECRET'];
            throw new QueryException('b3_memory', 'SELECT INTERNAL_SECRET', [], $previous);
        };
        $response = $this->http('GET', $this->adminBase);
        $this->assertDeclarationError($response, 503, 'service_unavailable');
        self::assertStringNotContainsString('INTERNAL_SECRET', $response->getContent());
        $this->connection->afterParentLock = static fn () => throw new RuntimeException('UNEXPECTED_SECRET');
        $response = $this->http('GET', $this->adminBase);
        $this->assertDeclarationError($response, 500, 'http_error');
        self::assertStringNotContainsString('UNEXPECTED_SECRET', $response->getContent());
    }

    public function test_database_failure_during_binding_also_has_private_json_503(): void
    {
        $this->connection->afterParentLock = static function (): void {
            $previous = new PDOException('BINDING_SECRET');
            $previous->errorInfo = ['HY000', 2002, 'BINDING_SECRET'];
            throw new QueryException('b2_memory', 'SELECT BINDING_SECRET', [], $previous);
        };
        $response = $this->http('GET', $this->adminBase);
        $this->assertDeclarationError($response, 503, 'service_unavailable');
        self::assertStringNotContainsString('BINDING_SECRET', $response->getContent());
    }
}
