<?php

declare(strict_types=1);

namespace Tests\Unit\Diagnosis;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Routing\Pipeline;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Tests\Feature\Diagnosis\DataBiDeclarativeFullFlowMySqlTest;
use Tests\Support\BuildsDataBiTenantDeclarationHttpApplication;

require_once dirname(__DIR__, 2).'/Feature/Diagnosis/DataBiDeclarativeFullFlowMySqlTest.php';

/** Preparation/real Blade and web-session checks only. No database connection. */
final class DataBiFullFlowPreparationTest extends TestCase
{
    use BuildsDataBiTenantDeclarationHttpApplication;

    public function test_mysql_suite_is_inert_until_separate_b5_authorization(): void
    {
        $previous = getenv('D2F_T4F_B5_MYSQL_QA');
        putenv('D2F_T4F_B5_MYSQL_QA');
        $case = new DataBiDeclarativeFullFlowMySqlTest('test_full_http_journey_closes_with_gaps_preserves_original_and_has_no_side_effects');
        try {
            try {
                (new ReflectionMethod($case, 'setUp'))->invoke($case);
                self::fail('Se requiere autorización B5 independiente.');
            } catch (\PHPUnit\Framework\SkippedTest $e) {
                self::assertStringContainsString('FULL_FLOW_MYSQL_QA=PREPARED', $e->getMessage());
                self::assertFalse((new ReflectionProperty($case, 'fixture'))->isInitialized($case));
                self::assertFalse((new ReflectionProperty($case, 'ready'))->getValue($case));
            }
        } finally {
            $previous === false ? putenv('D2F_T4F_B5_MYSQL_QA') : putenv('D2F_T4F_B5_MYSQL_QA='.$previous);
        }
    }

    public function test_real_inertia_layout_renders_the_actual_web_session_csrf_meta(): void
    {
        $this->startHttpApplication();
        try {
            $root = (new ReflectionProperty(HandleInertiaRequests::class, 'rootView'))->getDefaultValue();
            self::assertSame('app', $root);
            $layout = file_get_contents(dirname(__DIR__, 3).'/resources/views/'.$root.'.blade.php');
            self::assertSame(1, preg_match('/<meta\s+name="csrf-token"\s+content="\{\{ csrf_token\(\) \}\}">/', $layout, $match));
            $render = function () use ($match): string {
                $compiled = $this->httpApp['blade.compiler']->compileString($match[0]);
                ob_start();
                try {
                    eval('?>'.$compiled); // Only the trusted, existing Blade meta tag, not arbitrary page/user content.

                    return ob_get_contents();
                } finally {
                    ob_end_clean();
                }
            };
            $token = $this->httpApp['session.store']->token();
            self::assertNotEmpty($token);
            self::assertSame('<meta name="csrf-token" content="'.$token.'">', $render());
            $this->httpApp['session.store']->regenerateToken();
            self::assertNotSame($token, $this->httpApp['session.store']->token());
            self::assertSame('<meta name="csrf-token" content="'.$this->httpApp['session.store']->token().'">', $render());
            // Both workspaces use Inertia's existing app root; no page-specific root override.
            foreach (['AppHubDataTransformationBiController.php', 'Admin/AdminTransformationImplementationRequestController.php'] as $file) {
                $source = file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/'.$file);
                self::assertStringContainsString('Inertia::render(', $source);
                self::assertStringNotContainsString('setRootView(', $source);
            }
        } finally {
            $this->stopHttpApplication();
        }
    }

    public function test_b5_flag_alone_cannot_bypass_approved_b2_database_guards(): void
    {
        $saved = [getenv('D2F_T4F_B5_MYSQL_QA'), getenv('D2F_T4F_B2_MYSQL_QA')];
        putenv('D2F_T4F_B5_MYSQL_QA=rollback-only');
        putenv('D2F_T4F_B2_MYSQL_QA');
        $case = new DataBiDeclarativeFullFlowMySqlTest('test_full_http_journey_closes_with_gaps_preserves_original_and_has_no_side_effects');
        try {
            try {
                (new ReflectionMethod($case, 'setUp'))->invoke($case);
                self::fail('La autorización B5 no sustituye los guards MySQL B2.');
            } catch (\PHPUnit\Framework\SkippedTest $e) {
                self::assertStringContainsString('MYSQL_QA=NOT_EXECUTED', $e->getMessage());
                $fixture = (new ReflectionProperty($case, 'fixture'))->getValue($case);
                self::assertNull((new ReflectionProperty($fixture, 'capsule'))->getValue($fixture));
                self::assertFalse((new ReflectionProperty($case, 'ready'))->getValue($case));
            }
        } finally {
            foreach (['D2F_T4F_B5_MYSQL_QA', 'D2F_T4F_B2_MYSQL_QA'] as $i => $key) {
                $saved[$i] === false ? putenv($key) : putenv($key.'='.$saved[$i]);
            }
        }
    }

    public function test_real_login_rotation_rejects_stale_meta_but_accepts_current_encrypted_xsrf_cookie(): void
    {
        $this->startHttpApplication();
        try {
            $session = $this->httpApp['session.store'];
            $oldMeta = $session->token();
            $user = new User;
            $user->setRawAttributes(['id' => 21, 'role' => 'subscriber']);
            // Real login()/Store::regenerate(): no database access and no backend flow substitution.
            $this->httpApp['auth']->guard()->login($user);
            self::assertNotSame($oldMeta, $session->token());
            $this->httpApp['router']->match(['GET', 'POST'], '/__b5/csrf-proof', static fn () => response()->json(['ok' => true]))
                ->middleware('web')->withoutMiddleware(HandleInertiaRequests::class);
            $request = function (string $method, array $headers = []) use ($session) {
                $session->save();
                $cipher = $this->httpApp['encrypter'];
                $name = $session->getName();
                $cookie = $cipher->encrypt(CookieValuePrefix::create($name, $cipher->getKey()).$session->getId(), false);
                $http = Request::create('/__b5/csrf-proof', $method, [], [$name => $cookie], [], [
                    'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json', ...$headers,
                ], $method === 'POST' ? '{}' : '');
                $this->httpApp->instance('request', $http);
                $http->setUserResolver(fn () => $this->httpApp['auth']->guard()->user());

                return (new Pipeline($this->httpApp))->send($http)->then(fn ($r) => $this->httpApp['router']->dispatch($r));
            };
            $get = $request('GET');
            self::assertSame(200, $get->getStatusCode());
            $cookies = array_values(array_filter($get->headers->getCookies(), static fn ($c): bool => $c->getName() === 'XSRF-TOKEN'));
            self::assertCount(1, $cookies);
            $currentCookie = $cookies[0]->getValue();
            self::assertNotSame($session->token(), $currentCookie, 'EncryptCookies entrega el token cifrado al navegador.');
            self::assertSame(419, $request('POST', ['HTTP_X_CSRF_TOKEN' => $oldMeta])->getStatusCode());
            self::assertSame(200, $request('POST', ['HTTP_X_XSRF_TOKEN' => $currentCookie])->getStatusCode());
            self::assertSame(419, $request('POST', ['HTTP_X_CSRF_TOKEN' => $oldMeta, 'HTTP_X_XSRF_TOKEN' => $currentCookie])->getStatusCode(),
                'Laravel da prioridad al header meta: el cliente debe enviar solo el mecanismo vigente.');
        } finally {
            $this->stopHttpApplication();
        }
    }

    public function test_retry_comparison_ignores_only_object_key_order(): void
    {
        $case = new DataBiDeclarativeFullFlowMySqlTest('test_full_http_journey_closes_with_gaps_preserves_original_and_has_no_side_effects');
        $method = new ReflectionMethod($case, 'jsonObjects');
        $a = ['declarations' => [['availability' => 'partial', 'structures' => ['Clientes', 'Ventas']]], 'revision' => 3];
        $b = ['revision' => 3, 'declarations' => [['structures' => ['Clientes', 'Ventas'], 'availability' => 'partial']]];
        self::assertSame($method->invoke($case, $a), $method->invoke($case, $b));
        $b['declarations'][0]['structures'] = ['Ventas', 'Clientes'];
        self::assertNotSame($method->invoke($case, $a), $method->invoke($case, $b));
        $b = $a;
        $b['revision'] = '3';
        self::assertNotSame($method->invoke($case, $a), $method->invoke($case, $b));
        $b = $a;
        $b['unexpected'] = true;
        self::assertNotSame($method->invoke($case, $a), $method->invoke($case, $b));
    }

    public function test_all_seven_actual_b3_routes_keep_web_csrf_middleware(): void
    {
        $this->startHttpApplication();
        try {
            $router = $this->httpApp['router'];
            $router->middleware('web')->group(dirname(__DIR__, 3).'/routes/admin.php');
            $routes = array_filter(iterator_to_array($router->getRoutes()), static fn ($r): bool => str_contains((string) $r->getName(), '.declarative_review.')
                || str_starts_with((string) $r->getName(), 'app.transformation.data_bi.information_requirements.clarifications.'));
            self::assertCount(7, $routes);
            foreach ($routes as $route) {
                self::assertContains(ValidateCsrfToken::class, $router->gatherRouteMiddleware($route), $route->getName());
            }
        } finally {
            $this->stopHttpApplication();
        }
    }
}
