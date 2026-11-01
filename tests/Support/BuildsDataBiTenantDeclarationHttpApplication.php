<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\CookieServiceProvider;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\EncryptionServiceProvider;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Hashing\HashServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Routing\Pipeline;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\Facade;
use Illuminate\Testing\TestResponse;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionProperty;

/** HTTP real de Laravel sin bootstrap de dotenv, cachés, proveedores de negocio ni PHPUnit SQLite. */
trait BuildsDataBiTenantDeclarationHttpApplication
{
    protected Application $httpApp;

    private array $previousHttpState = [];

    protected array $httpReportedErrors = [];

    protected function startHttpApplication(): void
    {
        $this->previousHttpState = [
            'container' => Container::getInstance(), 'facades' => Facade::getFacadeApplication(),
            'roots' => (new ReflectionProperty(Facade::class, 'resolvedInstance'))->getValue(),
            'resolver' => Model::getConnectionResolver(), 'dispatcher' => Model::getEventDispatcher(),
        ];
        foreach (['booted', 'bootedCallbacks', 'globalScopes'] as $property) {
            $this->previousHttpState[$property] = (new ReflectionProperty(Model::class, $property))->getValue();
        }
        // Crear la aplicación NO ejecuta los bootstrappers del kernel.
        $this->httpApp = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $this->httpApp->instance('env', 'local'); // CSRF activo incluso bajo PHPUnit.
        $this->httpApp->instance('request', Request::create('/'));
        $this->httpApp->instance('config', new Repository([
            'app' => ['env' => 'local', 'debug' => true, 'locale' => 'es', 'fallback_locale' => 'es',
                'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC'],
            'auth' => ['defaults' => ['guard' => 'web'],
                'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']],
                'providers' => ['users' => ['driver' => 'eloquent', 'model' => User::class]]],
            'session' => ['driver' => 'array', 'lifetime' => 120, 'cookie' => 't4d_qa_session',
                'path' => '/', 'domain' => null, 'secure' => false, 'http_only' => true,
                'same_site' => 'lax', 'lottery' => [0, 100], 'expire_on_close' => false],
            'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]],
            'hashing' => ['driver' => 'bcrypt'],
            'view' => ['paths' => [], 'compiled' => '/tmp/d2f-t4d-unused-views'],
        ]));
        $this->httpApp->instance(LoggerInterface::class, new NullLogger);
        foreach ([HashServiceProvider::class,
            AuthServiceProvider::class, CookieServiceProvider::class,
            EncryptionServiceProvider::class, SessionServiceProvider::class,
            CacheServiceProvider::class, FilesystemServiceProvider::class,
            ViewServiceProvider::class, TranslationServiceProvider::class,
            ValidationServiceProvider::class] as $provider) {
            $this->httpApp->register($provider);
        }
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->httpApp);
        // Aplica exactamente los alias, grupos y prioridad configurados en bootstrap/app.php.
        $this->httpApp->make(Kernel::class);
        $this->httpApp->make(ExceptionHandler::class)
            ->reportable(function (\Throwable $exception): void {
                $this->httpReportedErrors[] = $exception;
            });
        $this->httpApp['router']->middleware('web')->group(dirname(__DIR__, 2).'/routes/web.php');
        $this->httpApp['router']->getRoutes()->refreshNameLookups();
        $this->httpApp['session.store']->start();
    }

    protected function stopHttpApplication(): void
    {
        if ($this->previousHttpState === []) {
            return;
        }
        $previous = $this->previousHttpState;
        Container::setInstance($previous['container']);
        Facade::setFacadeApplication($previous['facades']);
        (new ReflectionProperty(Facade::class, 'resolvedInstance'))->setValue(null, $previous['roots']);
        foreach (['booted', 'bootedCallbacks', 'globalScopes'] as $property) {
            (new ReflectionProperty(Model::class, $property))->setValue(null, $previous[$property]);
        }
        $previous['resolver'] !== null ? Model::setConnectionResolver($previous['resolver']) : Model::unsetConnectionResolver();
        $previous['dispatcher'] !== null ? Model::setEventDispatcher($previous['dispatcher']) : Model::unsetEventDispatcher();
        $this->previousHttpState = [];
    }

    protected function declarationHttp(
        string $method,
        string $url,
        array|string $body = [],
        bool $csrf = true,
        string $contentType = 'application/json'
    ): TestResponse {
        $session = $this->httpApp['session.store'];
        $session->save();
        $encrypter = $this->httpApp['encrypter'];
        $cookieName = $session->getName();
        $cookie = $encrypter->encrypt(
            CookieValuePrefix::create($cookieName, $encrypter->getKey()).$session->getId(), false
        );
        $request = Request::create($url, $method, [], [$cookieName => $cookie], [], [
            'CONTENT_TYPE' => $contentType, 'HTTP_ACCEPT' => 'text/html',
            ...($csrf ? ['HTTP_X_CSRF_TOKEN' => $session->token()] : []),
        ], is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
        $this->httpApp->instance('request', $request);
        $request->setUserResolver(fn () => $this->httpApp['auth']->guard()->user());
        $response = (new Pipeline($this->httpApp))->send($request)
            ->through([TrimStrings::class, ConvertEmptyStringsToNull::class])
            ->then(fn (Request $http) => $this->httpApp['router']->dispatch($http));

        return TestResponse::fromBaseResponse($response);
    }

    protected function declarationUrl(string $operation = '', int $requestId = 101): string
    {
        return '/app/transformacion-360/datos-bi/requerimientos/'.$requestId.'/declaracion'.$operation;
    }

    protected function assertDeclarationError(TestResponse $response, int $status, string $code): void
    {
        self::assertSame($status, $response->getStatusCode(), implode("\n", array_map(
            static fn (\Throwable $e): string => (string) $e, $this->httpReportedErrors
        )));
        $response->assertStatus($status)->assertJsonPath('error.code', $code)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'application/json');
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertArrayNotHasKey('data', $response->json());
        self::assertArrayNotHasKey('trace', $response->json());
        self::assertArrayNotHasKey('exception', $response->json());
    }
}
