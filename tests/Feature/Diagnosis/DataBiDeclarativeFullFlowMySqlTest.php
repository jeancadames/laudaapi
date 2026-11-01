<?php

declare(strict_types=1);

namespace Tests\Feature\Diagnosis;

use App\Models\TransformationImplementationRequest;
use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Foundation\Providers\FoundationServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Routing\Pipeline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use LogicException;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Tests\Support\BuildsDataBiTenantDeclarationHttpApplication;
use Tests\Support\DataBiFullFlowScopeGuard;
use Throwable;

require_once __DIR__.'/DataBiDeclarativeReviewServiceMySqlTest.php';

/**
 * B5 PREPARED ONLY. Real routes/controllers/services/MySQL; no domain mocks.
 * Requires D2F_T4F_B5_MYSQL_QA=rollback-only AND the explicit approved B2 credentials.
 * B2's existing seeded scope is an untouched control. The entire HTTP journey uses
 * another new synthetic scope, with its own publication initially absent.
 * Outer rollback, fresh probe, trigger/InnoDB checks and ID tracking remain B2's.
 */
#[Group('mysql-dev-t4fb5-full-flow-rollback')]
final class DataBiDeclarativeFullFlowMySqlTest extends TestCase
{
    use BuildsDataBiTenantDeclarationHttpApplication {
        startHttpApplication as private startBaseHttpApplication;
        stopHttpApplication as private stopBaseHttpApplication;
        assertDeclarationError as private assertBaseDeclarationError;
    }

    private const DOMAIN = ['data_bi_information_requests', 'data_bi_tenant_declarations',
        'data_bi_declaration_clarifications', 'data_bi_declaration_clarification_events', 'data_bi_declarative_review_closures'];

    private DataBiDeclarativeReviewServiceMySqlTest $fixture;

    private bool $ready = false;

    private bool $httpStarted = false;

    private bool $domainOnly = false;

    private array $initialIds = [];

    private array $protectedBefore = [];

    private TransformationImplementationRequest $request;

    private User $admin;

    private User $tenant;

    private string $adminRoot;

    private string $tenantRoot;

    private array $requirements = [];

    private int $version = 0;

    private int $revision = 0;

    private ?int $sessionUserId = null;

    private ?array $previousRequestMacros = null;

    protected function startHttpApplication(): void
    {
        $this->previousRequestMacros = (new ReflectionProperty(Request::class, 'macros'))->getValue();
        $this->startBaseHttpApplication();
        // The reduced HTTP bootstrap omits this framework provider. Admin draft/send
        // use Request::validate(), unlike the B3 endpoints exercised previously.
        // Install Laravel's own macros without booting dotenv or business providers.
        (new FoundationServiceProvider($this->httpApp))->registerRequestValidation();
    }

    protected function stopHttpApplication(): void
    {
        try {
            $this->stopBaseHttpApplication();
        } finally {
            if ($this->previousRequestMacros !== null) {
                (new ReflectionProperty(Request::class, 'macros'))->setValue(null, $this->previousRequestMacros);
                $this->previousRequestMacros = null;
            }
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        // This check precedes even instantiating the approved MySQL fixture.
        if (getenv('D2F_T4F_B5_MYSQL_QA') !== 'rollback-only') {
            self::markTestSkipped('FULL_FLOW_MYSQL_QA=PREPARED: no autorización de ejecución B5.');
        }
        $this->fixture = new DataBiDeclarativeReviewServiceMySqlTest('test_lifecycle_and_all_retries_preserve_events_audit_and_original_data');
        $this->invoke('setUp'); // B2's exact DEV database, credentials and rollback guards.
        $this->ready = true;
        try {
            $manager = $this->property('capsule')->getDatabaseManager();
            $this->initialIds = $this->property('fixtureIds');
            $manager->connection()->beforeExecuting(function (string $sql, array $bindings): void {
                $owned = $this->property('fixtureIds');
                if ($this->domainOnly) {
                    foreach (self::DOMAIN as $table) {
                        $owned[$table] = array_diff($owned[$table] ?? [], $this->initialIds[$table] ?? []);
                    }
                    $owned['companies'] = [(int) $this->request->company_id];
                    $owned['transformation_implementation_requests'] = [(int) $this->request->id];
                    if (preg_match('/\A\s*(insert into|update|delete from) `([a-z0-9_]+)`/i', $sql, $m)
                        && ! in_array($m[2], self::DOMAIN, true)) {
                        throw new LogicException('B5: el recorrido HTTP intentó escribir fuera de las cinco tablas declarativas.');
                    }
                }
                DataBiFullFlowScopeGuard::assertSql($sql, $bindings, $owned);
            });
            $this->httpStarted = true;
            $this->startHttpApplication();
            $this->httpApp->instance('db', $manager);
            $this->httpApp->bind('db.schema', fn () => $manager->connection()->getSchemaBuilder());
            DB::clearResolvedInstance('db');
            Schema::clearResolvedInstance('db.schema');
            $this->httpApp['router']->middleware('web')->group(dirname(__DIR__, 3).'/routes/admin.php');
            $this->httpApp['router']->getRoutes()->refreshNameLookups();
            $this->admin = $this->property('admin');
            $this->invoke('invokeFixture', 'createSyntheticScope');
            $this->request = $this->invoke('getFixture', 'request');
            $this->tenant = $this->verified($this->invoke('getFixture', 'actor'));
            DB::table('transformation_implementation_requests')->where('id', $this->request->id)->update([
                'source_snapshot' => json_encode(['business_needs' => ['improvements' => ['gestion_clientes']]], JSON_THROW_ON_ERROR),
            ]);
            $this->request->refresh();
            $this->adminRoot = '/admin/transformation-360/implementation-requests/'.$this->request->id;
            $this->tenantRoot = '/app/transformacion-360/datos-bi/requerimientos/'.$this->request->id;
            self::assertNotSame($this->admin->id, $this->tenant->id);
            self::assertSame('admin', $this->admin->role);
            self::assertSame('subscriber', $this->tenant->role);
            self::assertSame(0, DB::table('subscriber_user')->where('user_id', $this->admin->id)->count());
            self::assertSame(0, DB::table('data_bi_information_requests')->where('transformation_implementation_request_id', $this->request->id)->count());
            $this->protectedBefore = $this->protectedScope();
            $this->domainOnly = true;
            $this->login($this->admin);
        } catch (Throwable $e) {
            $this->finish();
            throw $e;
        }
    }

    protected function tearDown(): void
    {
        try {
            if ($this->ready && $this->protectedBefore !== []) {
                self::assertSame($this->protectedBefore, $this->protectedScope(), 'Identidad, solicitud, diagnóstico y plan deben conservarse.');
            }
        } finally {
            try {
                $this->finish(); // B2 rolls back every tracked ID and verifies absence with a fresh probe.
            } finally {
                parent::tearDown();
            }
        }
    }

    private function finish(): void
    {
        try {
            if ($this->httpStarted) {
                $this->stopHttpApplication();
                $this->httpStarted = false;
            }
        } finally {
            if ($this->ready) {
                $this->ready = false;
                $this->invoke('tearDown');
            }
        }
    }

    private function property(string $name): mixed
    {
        return (new ReflectionProperty($this->fixture, $name))->getValue($this->fixture);
    }

    private function invoke(string $method, mixed ...$args): mixed
    {
        return (new ReflectionMethod($this->fixture, $method))->invoke($this->fixture, ...$args);
    }

    private function verified(User $user): User
    {
        DB::table('users')->where('id', $user->id)->update(['role' => 'subscriber', 'email_verified_at' => now()]);

        return $user->fresh();
    }

    private function fixtureMutation(callable $operation): mixed
    {
        $this->domainOnly = false;
        try {
            return $operation();
        } finally {
            $this->domainOnly = true;
        }
    }

    private function protectedScope(): array
    {
        $rows = [];
        foreach (['transformation_implementation_requests' => $this->request->id,
            'companies' => $this->request->company_id, 'diagnosis_assessments' => $this->request->diagnosis_assessment_id,
            'transformation_implementation_plans' => $this->request->transformation_implementation_plan_id] as $table => $id) {
            $rows[$table] = (array) DB::table($table)->where('id', $id)->first();
        }

        return $rows;
    }

    private function login(?User $user): void
    {
        $guard = $this->httpApp['auth']->guard();
        $guard->logout();
        if ($user !== null) {
            self::assertTrue($user->hasVerifiedEmail());
            $guard->login($user); // Real SessionGuard; no setUser shortcut or credentials from real accounts.
        }
        $guard->forgetUser();
        $this->sessionUserId = $user === null ? null : (int) $user->id;
    }

    private function http(string $method, string $url, array $body = [], bool $csrf = true, bool $cookie = true): TestResponse
    {
        $this->httpReportedErrors = []; // Keep exception attribution local to this request.
        $session = $this->httpApp['session.store'];
        $session->save();
        $encrypter = $this->httpApp['encrypter'];
        $name = $session->getName();
        $encrypted = $encrypter->encrypt(CookieValuePrefix::create($name, $encrypter->getKey()).$session->getId(), false);
        $this->httpApp['auth']->guard()->forgetUser(); // Each request reloads identity from the session and the MySQL provider.
        $request = Request::create($url, $method, [], $cookie ? [$name => $encrypted] : [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            ...($csrf ? ['HTTP_X_CSRF_TOKEN' => $session->token()] : []),
        ], $method === 'GET' ? '' : json_encode($body, JSON_THROW_ON_ERROR));
        $this->httpApp->instance('request', $request);
        $request->setUserResolver(fn () => $this->httpApp['auth']->guard()->user());
        $before = $this->property('dml');
        $response = (new Pipeline($this->httpApp))->send($request)
            ->through([TrimStrings::class, ConvertEmptyStringsToNull::class])
            ->then(fn (Request $http) => $this->httpApp['router']->dispatch($http));
        $response = TestResponse::fromBaseResponse($response);
        $this->diagnoseHttpFailure($request, $response);
        if ($cookie) {
            self::assertSame($session->getId(), $request->cookie($name), 'La cookie cifrada debe recuperar la sesión web real.');
            self::assertSame($this->sessionUserId, $request->user() === null ? null : (int) $request->user()->id,
                'La identidad debe recargarse mediante el provider, sin setUser.');
        }
        if ($method === 'GET') {
            self::assertSame($before, $this->property('dml'), 'Todo GET integral debe ser de solo lectura.');
        }

        return $response;
    }

    /** Opt-in B5 diagnosis only; never output messages, traces, bodies or bindings. */
    private function diagnoseHttpFailure(Request $request, TestResponse $response): void
    {
        if (getenv('D2F_T4F_B5_HTTP_DIAGNOSTICS') !== 'sanitized' || $response->getStatusCode() < 500) {
            return;
        }
        $error = $response->baseResponse->exception ?? end($this->httpReportedErrors);
        $diagnostic = $this->httpFailureDiagnostic($request, $response->getStatusCode(), $error instanceof Throwable ? $error : null);
        fwrite(STDERR, 'B5_HTTP_DIAGNOSTIC='.json_encode($diagnostic, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    private function httpFailureDiagnostic(Request $request, int $status, ?Throwable $error): array
    {
        $chain = [];
        for ($cause = $error; $cause !== null; $cause = $cause->getPrevious()) {
            $chain[] = $cause;
        }
        $original = $chain === [] ? null : $chain[count($chain) - 1];
        $ownFile = null;
        $ownLine = null;
        $sqlstate = null;
        $root = dirname(__DIR__, 3).'/';
        foreach (array_reverse($chain) as $cause) {
            if ($sqlstate === null && $cause instanceof PDOException) {
                $code = $cause->errorInfo[0] ?? $cause->getCode();
                if (preg_match('/\A[0-9A-Z]{5}\z/', (string) $code)) {
                    $sqlstate = (string) $code;
                }
            }
            foreach ([['file' => $cause->getFile(), 'line' => $cause->getLine()], ...$cause->getTrace()] as $frame) {
                $file = $frame['file'] ?? '';
                if ($ownFile === null && str_starts_with($file, $root)
                    && preg_match('~\A(?:app|tests|routes|bootstrap)/[a-zA-Z0-9_./-]+\.php\z~', substr($file, strlen($root)))) {
                    $ownFile = substr($file, strlen($root));
                    $ownLine = $frame['line'] ?? null;
                }
            }
        }
        $route = $request->route()?->getName();
        $stage = str_replace([
            'admin.transformation360.implementation_requests.information_requirements.',
            'admin.transformation360.implementation_requests.declarative_review.',
            'app.transformation.data_bi.information_requirements.',
        ], ['admin.publication.', 'admin.declarative_review.', 'tenant.publication.'], $route ?? 'route_unresolved');
        $category = match (true) {
            $original instanceof \BadMethodCallException
                && $original->getMessage() === 'Method Illuminate\\Http\\Request::validate does not exist.' => 'missing_framework_request_validation_macro',
            str_starts_with($ownFile ?? '', 'tests/Support/') => 'qa_guard',
            $error instanceof QueryException, $original instanceof PDOException => 'database',
            $original instanceof \Illuminate\Auth\AuthenticationException => 'authentication',
            $original instanceof \Illuminate\Session\TokenMismatchException => 'csrf',
            $original instanceof \Illuminate\Database\Eloquent\ModelNotFoundException => 'route_binding',
            $original instanceof \Illuminate\Auth\Access\AuthorizationException => 'authorization',
            str_starts_with($ownFile ?? '', 'app/Services/') => 'service',
            str_starts_with($ownFile ?? '', 'app/Http/Controllers/') => 'controller',
            str_starts_with($ownFile ?? '', 'app/Http/Middleware/') => 'middleware',
            default => 'undetermined',
        };
        $class = $original === null ? null : get_class($original);
        // Anonymous class names contain filesystem paths; keep those out of output.
        if ($class !== null && (new \ReflectionClass($original))->isAnonymous()) {
            $class = 'anonymous_throwable';
        }

        return [
            'test' => $this->name(), 'stage' => $stage, 'method' => $request->method(), 'route' => $route,
            'status' => $status, 'original_exception' => $class,
            'own_file' => $ownFile, 'own_line' => $ownLine, 'sqlstate' => $sqlstate, 'category' => $category,
        ];
    }

    private function ok(TestResponse $response): TestResponse
    {
        // Fail with status only before Laravel can append response/error contents.
        self::assertSame(200, $response->getStatusCode(), 'B5: estado HTTP inesperado; diagnóstico técnico mediante D2F_T4F_B5_HTTP_DIAGNOSTICS=sanitized.');
        $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));

        return $response;
    }

    protected function assertDeclarationError(TestResponse $response, int $status, string $code): void
    {
        // The shared helper includes full Throwables on a status mismatch. B5 must
        // fail before that diagnostic; all original contract assertions still run.
        self::assertSame($status, $response->getStatusCode(), 'B5: estado HTTP de rechazo inesperado.');
        $this->assertBaseDeclarationError($response, $status, $code);
    }

    private function noDml(callable $operation): mixed
    {
        $before = $this->property('dml');
        try {
            return $operation();
        } finally {
            self::assertSame($before, $this->property('dml'));
        }
    }

    private function jsonObjects(mixed $value): mixed
    {
        // Only associative object-key ordering is insignificant; list order and scalar types remain strict.
        if (! is_array($value)) {
            return $value;
        }
        $normalized = array_map($this->jsonObjects(...), $value);
        if (! array_is_list($normalized)) {
            ksort($normalized, SORT_STRING);
        }

        return $normalized;
    }

    private function publish(): void
    {
        $this->login($this->admin);
        $preview = $this->ok($this->http('GET', $this->adminRoot.'/information-requirements/preview'))->json('draft');
        self::assertNotEmpty($preview['requirements']);
        $this->ok($this->http('GET', $this->adminRoot.'/information-requirements/draft'));
        $requirements = array_map(static fn (array $r): array => [
            'key' => $r['key'], 'included' => true, 'label' => $r['label'],
            'guidance' => $r['guidance'],
        ], $preview['requirements']);
        $saved = $this->ok($this->http('POST', $this->adminRoot.'/information-requirements/draft', [
            'expected_version' => $this->version, 'requirements' => $requirements,
        ]))->json();
        // expected_version belongs only to the earlier Admin publication contract; B3 never accepts it.
        $this->version = $saved['version'];
        $this->ok($this->http('POST', $this->adminRoot.'/information-requirements/send', ['expected_version' => $this->version]))
            ->assertJsonPath('status', 'sent')->assertJsonPath('published_to_tenant', true)->assertJsonPath('notification_sent', false);
        $this->login($this->tenant);
        $published = $this->ok($this->http('GET', $this->tenantRoot))->assertJsonPath('can_respond', true)->json();
        self::assertSame($this->version, $published['version']);
        $this->requirements = $published['requirements'];
        self::assertNotEmpty($this->requirements);
    }

    private function declaration(bool $complete): array
    {
        return ['version' => $this->version, 'declarations' => ! $complete ? [] : array_map(
            static fn (array $r, int $i): array => [
                'requirement_key' => $r['key'], 'availability' => $i === 0 ? 'partial' : 'unavailable',
                'notes' => 'Brecha declarada, sin inspección de registros.',
                'sources' => $i === 0 ? [['name' => 'ERP sintético', 'type' => 'software', 'structures' => ['Clientes'], 'notes' => 'Solo nombres conocidos.']] : [],
            ], $this->requirements, array_keys($this->requirements)),
            'expected_revision' => $this->revision];
    }

    private function submit(): array
    {
        $this->publish();
        $this->ok($this->http('GET', $this->tenantRoot.'/declaracion'))->assertJsonPath('data.declaration', null);
        $draft = $this->ok($this->http('POST', $this->tenantRoot.'/declaracion/borrador', $this->declaration(false)))->json('data.declaration');
        $this->revision = $draft['revision'];
        self::assertSame('draft', $draft['status']);
        $draft = $this->ok($this->http('POST', $this->tenantRoot.'/declaracion/borrador', $this->declaration(true)))->json('data.declaration');
        $this->revision = $draft['revision'];
        $input = $this->declaration(true);
        $submitted = $this->ok($this->http('POST', $this->tenantRoot.'/declaracion/enviar', $input))->json();
        $this->noDml(fn () => self::assertSame($this->jsonObjects($submitted), $this->jsonObjects($this->ok($this->http('POST', $this->tenantRoot.'/declaracion/enviar', $input))->json())));
        $this->revision = $submitted['data']['declaration']['revision'];
        self::assertSame('submitted', $submitted['data']['declaration']['status']);
        $this->ok($this->http('GET', $this->tenantRoot.'/declaracion'))->assertJsonPath('data.declaration.status', 'submitted');
        $this->login($this->admin);
        $this->ok($this->http('GET', $this->adminRoot.'/information-requirements/tenant-declarations'))
            ->assertJsonPath('data.selected.declaration.status', 'submitted');

        return (array) DB::table('data_bi_tenant_declarations')->where('id', $submitted['data']['declaration']['id'])->first();
    }

    private function ask(): array
    {
        $this->login($this->admin);
        $input = ['version' => $this->version, 'requirement_key' => $this->requirements[0]['key'],
            'message' => '¿Qué parte falta por identificar?', 'expected_revision' => 0];

        return [$this->ok($this->http('POST', $this->adminRoot.'/declarative-review/clarifications', $input))->json('data.clarifications.0'), $input];
    }

    private function closeInput(): array
    {
        return ['version' => $this->version, 'conclusion' => 'Examen declarativo gratuito terminado con brechas.',
            'limitations' => 'Fuentes no verificadas técnicamente; sin aprobación comercial.', 'expected_revision' => 0];
    }

    private function events(int $id): array
    {
        return DB::table('data_bi_declaration_clarification_events')->where('data_bi_declaration_clarification_id', $id)
            ->orderBy('sequence')->get()->map(static fn ($row): array => (array) $row)->all();
    }

    public function test_full_http_journey_closes_with_gaps_preserves_original_and_has_no_side_effects(): void
    {
        $original = $this->submit();
        $publication = (array) DB::table('data_bi_information_requests')->where('id', $original['data_bi_information_request_id'])->first();
        [$question, $input] = $this->ask();
        $firstEvents = $this->events($question['id']);
        $requested = $this->ok($this->http('GET', $this->adminRoot.'/declarative-review'))->json();
        $this->noDml(fn () => self::assertSame($requested, $this->ok($this->http('POST', $this->adminRoot.'/declarative-review/clarifications', $input))->json()));
        $this->login($this->tenant);
        $this->ok($this->http('GET', $this->tenantRoot.'/aclaraciones'))->assertJsonPath('data.clarifications.0.allowed_actions.answer', true);
        $input = ['version' => $this->version, 'message' => 'Se desconoce el período inicial; la brecha permanece.', 'expected_revision' => $question['revision']];
        $answerUrl = $this->tenantRoot.'/aclaraciones/'.$question['id'].'/responder';
        $answered = $this->ok($this->http('POST', $answerUrl, $input))->json();
        $answerEvents = $this->events($question['id']);
        self::assertSame($firstEvents, array_slice($answerEvents, 0, count($firstEvents)));
        $this->noDml(fn () => self::assertSame($answered, $this->ok($this->http('POST', $answerUrl, $input))->json()));
        $this->login($this->admin);
        $input = ['version' => $this->version, 'message' => 'Brecha revisada y conservada como limitación.', 'expected_revision' => $answered['data']['clarifications'][0]['revision']];
        $resolveUrl = $this->adminRoot.'/declarative-review/clarifications/'.$question['id'].'/resolve';
        $resolved = $this->ok($this->http('POST', $resolveUrl, $input))->json();
        $resolvedEvents = $this->events($question['id']);
        self::assertSame($answerEvents, array_slice($resolvedEvents, 0, count($answerEvents)));
        $this->noDml(fn () => self::assertSame($resolved, $this->ok($this->http('POST', $resolveUrl, $input))->json()));
        $closed = $this->ok($this->http('POST', $this->adminRoot.'/declarative-review/close', $this->closeInput()))->assertJsonPath('data.review_status', 'closed')->json();
        self::assertNotEmpty($closed['data']['closure']['declared_gaps']);
        $closureRow = (array) DB::table('data_bi_declarative_review_closures')->where('data_bi_tenant_declaration_id', $original['id'])->first();
        $this->noDml(fn () => self::assertSame($closed, $this->ok($this->http('POST', $this->adminRoot.'/declarative-review/close', $this->closeInput()))->json()));
        self::assertSame($original, (array) DB::table('data_bi_tenant_declarations')->where('id', $original['id'])->first());
        self::assertSame($resolvedEvents, $this->events($question['id']));
        self::assertSame($closureRow, (array) DB::table('data_bi_declarative_review_closures')->where('id', $closureRow['id'])->first());
        self::assertSame($publication, (array) DB::table('data_bi_information_requests')->where('id', $publication['id'])->first());
        self::assertSame(3, DB::table('data_bi_declaration_clarification_events')->where('data_bi_declaration_clarification_id', $question['id'])->count());
        foreach ($closed['data']['clarifications'] as $c) {
            self::assertSame(['answer' => false, 'resolve' => false, 'cancel' => false], $c['allowed_actions']);
        }
        self::assertSame(['request_clarification' => false, 'close_review' => false], $closed['data']['allowed_actions']);
        $changed = [...$this->closeInput(), 'conclusion' => 'Intento de reemplazar el cierre.'];
        $this->noDml(fn () => $this->assertDeclarationError($this->http('POST', $this->adminRoot.'/declarative-review/close', $changed), 409, 'review_closed'));
        $this->login($this->tenant);
        $changed = $this->declaration(true);
        $changed['declarations'][0]['notes'] = 'Intento de sustituir respuestas formales.';
        $this->noDml(fn () => $this->assertDeclarationError($this->http('POST', $this->tenantRoot.'/declaracion/borrador', $changed), 409, 'declaration_submitted'));
        self::assertSame($original, (array) DB::table('data_bi_tenant_declarations')->where('id', $original['id'])->first());
    }

    public function test_other_tenant_membership_role_and_admin_impersonation_are_rejected_before_dml(): void
    {
        $this->submit();
        [$question] = $this->ask();
        $url = $this->tenantRoot.'/aclaraciones/'.$question['id'].'/responder';
        $input = ['version' => $this->version, 'message' => 'Descripción.', 'expected_revision' => 1];
        $this->noDml(fn () => $this->assertDeclarationError($this->http('POST', $url, $input), 404, 'not_found'));
        $member = $this->fixtureMutation(fn () => $this->verified($this->invoke('invokeFixture', 'tenant', null, 'member')));
        $this->login($member);
        $this->noDml(fn () => $this->assertDeclarationError($this->http('POST', $url, $input), 404, 'not_found'));
        $membership = DB::table('subscriber_user')->where('user_id', $this->tenant->id)->value('id');
        $this->fixtureMutation(fn () => DB::table('subscriber_user')->where('id', $membership)->update(['active' => 0]));
        $this->login($this->tenant);
        $this->noDml(fn () => $this->assertDeclarationError($this->http('POST', $url, $input), 404, 'not_found'));
        $this->fixtureMutation(fn () => $this->invoke('invokeFixture', 'createSyntheticScope'));
        $other = $this->fixtureMutation(fn () => $this->verified($this->invoke('getFixture', 'actor')));
        $this->login($other);
        $this->noDml(fn () => $this->assertDeclarationError($this->http('GET', $this->tenantRoot.'/aclaraciones'), 404, 'not_found'));
        $this->noDml(fn () => $this->assertDeclarationError($this->http('POST', $url, $input), 404, 'not_found'));
    }

    public function test_draft_is_private_and_review_requires_formal_submission(): void
    {
        $this->publish();
        $this->ok($this->http('POST', $this->tenantRoot.'/declaracion/borrador', $this->declaration(false)));
        $this->login($this->admin);
        $this->ok($this->http('GET', $this->adminRoot.'/information-requirements/tenant-declarations'))->assertJsonPath('data.selected.declaration', null);
        $this->noDml(fn () => $this->assertDeclarationError($this->http('GET', $this->adminRoot.'/declarative-review'), 409, 'declaration_not_submitted'));
    }

    public function test_csrf_sessions_stale_revision_duplicates_and_open_closure_preserve_history(): void
    {
        $this->submit();
        [$question, $questionInput] = $this->ask();
        $this->noDml(fn () => $this->assertDeclarationError($this->http('POST', $this->adminRoot.'/declarative-review/close', $this->closeInput(), false), 419, 'csrf_token_mismatch'));
        $this->noDml(fn () => $this->assertDeclarationError($this->http('POST', $this->adminRoot.'/declarative-review/close', $this->closeInput()), 409, 'clarifications_open'));
        // Same normalized question with different bytes is a duplicate, not an identical retry.
        $questionInput['message'] = mb_strtoupper($questionInput['message'], 'UTF-8');
        $this->noDml(fn () => $this->assertDeclarationError($this->http('POST', $this->adminRoot.'/declarative-review/clarifications', $questionInput), 409, 'duplicate_clarification'));
        $this->login($this->tenant);
        $this->noDml(fn () => $this->assertDeclarationError($this->http('POST', $this->tenantRoot.'/aclaraciones/'.$question['id'].'/responder', [
            'version' => $this->version, 'message' => 'Descripción.', 'expected_revision' => 0,
        ]), 409, 'revision_conflict'));
        $this->noDml(fn () => $this->assertDeclarationError($this->http('GET', $this->adminRoot.'/declarative-review'), 403, 'forbidden'));
        $this->login(null);
        $this->noDml(fn () => $this->assertDeclarationError($this->http('GET', $this->tenantRoot.'/aclaraciones'), 401, 'unauthenticated'));
        self::assertSame(1, DB::table('data_bi_declaration_clarification_events')->where('data_bi_declaration_clarification_id', $question['id'])->count());
    }

    public function test_superseded_questionnaire_preserves_closed_history_and_blocks_old_operations(): void
    {
        $this->submit();
        // No clarification is required to close; gaps are legitimate.
        $oldVersion = $this->version;
        $closed = $this->ok($this->http('POST', $this->adminRoot.'/declarative-review/close', $this->closeInput()))->json('data.closure');
        $oldInput = $this->closeInput();
        // Existing Admin draft policy deliberately forbids editing a sent publication.
        // Supersession is injected with the approved synthetic publication fixture, not by changing that policy.
        $this->invoke('setFixture', 'actor', $this->admin);
        $next = $this->invoke('invokeFixture', 'publish');
        self::assertGreaterThan($oldVersion, $next->version);
        $this->login($this->admin);
        $this->ok($this->http('GET', $this->adminRoot.'/declarative-review?version='.$oldVersion))
            ->assertJsonPath('data.is_current', false)->assertJsonPath('data.closure.questionnaire_version', $oldVersion);
        self::assertSame($closed, $this->http('GET', $this->adminRoot.'/declarative-review?version='.$oldVersion)->json('data.closure'));
        $this->noDml(fn () => $this->assertDeclarationError($this->http('POST', $this->adminRoot.'/declarative-review/close', $oldInput), 409, 'questionnaire_replaced'));
        $this->noDml(fn () => $this->assertDeclarationError($this->http('GET', $this->adminRoot.'/declarative-review'), 409, 'declaration_not_submitted'));
    }
}
