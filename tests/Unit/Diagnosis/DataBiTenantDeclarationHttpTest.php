<?php

namespace Tests\Unit\Diagnosis;

use App\Http\Controllers\AppHubDataBiInformationRequirementsController;
use App\Http\Controllers\AppHubDataBiTenantDeclarationController;
use App\Http\Middleware\DataBiTenantDeclarationJson;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\BuildsDataBiTenantDeclarationHttpApplication;

/** Ejecutable con --no-configuration; ninguna conexión ni escritura SQL. */
final class DataBiTenantDeclarationHttpTest extends TestCase
{
    use BuildsDataBiTenantDeclarationHttpApplication;

    private ?object $scopeRow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startHttpApplication();
        $actor = new User;
        $actor->setDateFormat('Y-m-d H:i:s');
        $actor->setRawAttributes(['id' => 501, 'email_verified_at' => '2026-10-31 00:00:00']);
        $actor->exists = true;
        $this->httpApp['auth']->guard()->setUser($actor);
        $this->scopeRow = (object) ['owner_user_id' => 501, 'role' => 'admin'];
        $scope = $this->createStub(Builder::class);
        $scope->method('join')->willReturnSelf();
        $scope->method('where')->willReturnSelf();
        $scope->method('first')->willReturnCallback(fn () => $this->scopeRow);
        DB::swap(new class($scope) extends ConnectionResolver
        {
            public function __construct(private Builder $scope) {}

            public function table(string $table): Builder
            {
                return $this->scope;
            }
        });
        // Solo estas pruebas sin BD sustituyen el binding; la suite MySQL usa el binding real.
        $this->httpApp['router']->bind('implementationRequest', function (): TransformationImplementationRequest {
            $model = new TransformationImplementationRequest;
            $model->setRawAttributes(['id' => 101, 'company_id' => 701, 'capability_key' => 'data_transformation_bi']);
            $model->exists = true;

            return $model;
        });
    }

    protected function tearDown(): void
    {
        $this->stopHttpApplication();
        parent::tearDown();
    }

    public function test_real_routes_keep_web_auth_verified_binding_and_csrf_with_json_outermost(): void
    {
        $router = $this->httpApp['router'];
        foreach (['show' => ['GET', '', 'show'], 'save_draft' => ['POST', '/borrador', 'saveDraft'],
            'submit' => ['POST', '/enviar', 'submit']] as $name => [$verb, $suffix, $method]) {
            $route = $router->getRoutes()->getByName('app.transformation.data_bi.information_requirements.declaration.'.$name);
            self::assertNotNull($route);
            self::assertSame(ltrim($this->declarationUrl($suffix, 101), '/'),
                str_replace('{implementationRequest}', '101', $route->uri()));
            self::assertContains($verb, $route->methods());
            self::assertSame(AppHubDataBiTenantDeclarationController::class.'@'.$method, $route->getActionName());
            self::assertSame('[0-9]+', $route->wheres['implementationRequest']);
            self::assertContains('web', $route->middleware());
            self::assertContains('auth', $route->middleware());
            self::assertContains('verified', $route->middleware());
            $stack = $router->gatherRouteMiddleware($route);
            self::assertSame(DataBiTenantDeclarationJson::class, $stack[0]);
            self::assertContains(StartSession::class, $stack);
            self::assertContains(ValidateCsrfToken::class, $stack);
            self::assertNotContains(HandleInertiaRequests::class, $stack);
        }
        $existing = $router->getRoutes()->getByName('app.transformation.data_bi.information_requirements.show');
        self::assertSame(AppHubDataBiInformationRequirementsController::class,
            $existing->getAction('controller'));
        self::assertNotContains(DataBiTenantDeclarationJson::class, $router->gatherRouteMiddleware($existing));
    }

    #[DataProvider('operations')]
    public function test_guests_get_json_401_even_when_accepting_html(string $verb, string $suffix): void
    {
        $this->httpApp['auth']->guard()->forgetUser();
        $this->assertDeclarationError($this->declarationHttp($verb, $this->declarationUrl($suffix)), 401, 'unauthenticated');
    }

    #[DataProvider('operations')]
    public function test_unverified_users_get_json_403(string $verb, string $suffix): void
    {
        $this->httpApp['auth']->guard()->user()->email_verified_at = null;
        $this->assertDeclarationError($this->declarationHttp($verb, $this->declarationUrl($suffix)), 403, 'email_unverified');
    }

    public static function operations(): iterable
    {
        yield 'show' => ['GET', ''];
        yield 'draft' => ['POST', '/borrador'];
        yield 'submit' => ['POST', '/enviar'];
    }

    #[DataProvider('posts')]
    public function test_csrf_is_enforced_by_real_web_middleware(string $suffix): void
    {
        self::assertFalse($this->httpApp->runningUnitTests(), 'No permitir el bypass CSRF de testing.');
        $this->assertDeclarationError($this->declarationHttp('POST', $this->declarationUrl($suffix), [], false),
            419, 'csrf_token_mismatch');
    }

    public static function posts(): iterable
    {
        yield 'draft' => ['/borrador'];
        yield 'submit' => ['/enviar'];
    }

    #[DataProvider('badEnvelopes')]
    public function test_invalid_http_payloads_are_rejected_before_service_or_database(array|string $body): void
    {
        foreach (['/borrador', '/enviar'] as $suffix) {
            $this->assertDeclarationError($this->declarationHttp('POST', $this->declarationUrl($suffix), $body),
                422, 'validation_failed');
        }
    }

    public static function badEnvelopes(): iterable
    {
        $valid = ['version' => 1, 'declarations' => [], 'expected_revision' => 0];
        foreach (['company_id', 'subscriber_id', 'actor_id', 'user_id', 'workspace_company_id',
            'information_request_id', 'questionnaire_id', 'expected_version', 'status', 'revision',
            'declaration_snapshot', 'content_sha256', '_method'] as $field) {
            yield 'extra '.$field => [[...$valid, $field => 123]];
        }
        foreach (['version', 'declarations', 'expected_revision'] as $field) {
            $missing = $valid;
            unset($missing[$field]);
            yield 'missing '.$field => [$missing];
        }
        foreach (['0', 0, true, 1.0, 10000, null] as $index => $value) {
            yield 'version type or range '.$index => [[...$valid, 'version' => $value]];
        }
        foreach (['0', -1, false, 0.5, 4294967296, null] as $index => $value) {
            yield 'revision type or range '.$index => [[...$valid, 'expected_revision' => $value]];
        }
        yield 'object declarations' => ['{"version":1,"declarations":{},"expected_revision":0}'];
        yield 'null declarations' => [[...$valid, 'declarations' => null]];
        yield 'invalid JSON' => ['{"version":'];
        yield 'list body' => ['[]'];
        yield 'scalar body' => ['null'];
    }

    public function test_transport_only_accepts_json_and_rejects_query_attributes(): void
    {
        $this->assertDeclarationError($this->declarationHttp('POST', $this->declarationUrl('/borrador'), '',
            true, 'application/x-www-form-urlencoded'), 415, 'unsupported_media_type');
        $this->assertDeclarationError($this->declarationHttp('POST', $this->declarationUrl('/borrador').'?company_id=99',
            ['version' => 1, 'expected_revision' => 0, 'declarations' => []]), 422, 'validation_failed');
        $this->assertDeclarationError($this->declarationHttp('GET', $this->declarationUrl().'?version=1'), 422, 'validation_failed');
    }

    public function test_schema_unavailable_get_is_a_safe_503(): void
    {
        Schema::swap(new class
        {
            public function hasTable(string $table): bool
            {
                return false;
            }
        });
        $this->assertDeclarationError($this->declarationHttp('GET', $this->declarationUrl()), 503, 'schema_unavailable');
        foreach (['/borrador', '/enviar'] as $suffix) {
            $this->assertDeclarationError($this->declarationHttp('POST', $this->declarationUrl($suffix),
                ['version' => 1, 'expected_revision' => 0, 'declarations' => []]), 503, 'schema_unavailable');
        }
    }

    public function test_unauthorized_scope_is_concealed_before_any_payload_validation_or_schema_discovery(): void
    {
        foreach ([null, (object) ['owner_user_id' => 999, 'role' => 'member'],
            (object) ['owner_user_id' => 999, 'role' => 'unknown']] as $scope) {
            $this->scopeRow = $scope;
            $this->assertDeclarationError($this->declarationHttp('GET', $this->declarationUrl().'?company_id=99'), 404, 'not_found');
            foreach (['/borrador', '/enviar'] as $suffix) {
                $this->assertDeclarationError($this->declarationHttp('POST', $this->declarationUrl($suffix), '{'), 404, 'not_found');
                $this->assertDeclarationError($this->declarationHttp('POST', $this->declarationUrl($suffix), '',
                    true, 'application/x-www-form-urlencoded'), 404, 'not_found');
            }
        }
    }

    public function test_absent_binding_does_not_expose_model_or_request_identifier(): void
    {
        $this->httpApp['router']->bind('implementationRequest', static function (): never {
            throw (new ModelNotFoundException)->setModel(TransformationImplementationRequest::class, [999]);
        });
        $response = $this->declarationHttp('GET', $this->declarationUrl('', 999));
        $this->assertDeclarationError($response, 404, 'not_found');
        self::assertStringNotContainsString('TransformationImplementationRequest', $response->getContent());
        self::assertStringNotContainsString('999', $response->getContent());
    }

    public function test_unexpected_exceptions_stay_500_and_debug_details_are_removed(): void
    {
        $this->httpApp['router']->bind('implementationRequest', static function (): never {
            throw new \RuntimeException('PRIVATE_SENTINEL');
        });
        $response = $this->declarationHttp('GET', $this->declarationUrl());
        $this->assertDeclarationError($response, 500, 'http_error');
        self::assertStringNotContainsString('PRIVATE_SENTINEL', $response->getContent());
    }
}
