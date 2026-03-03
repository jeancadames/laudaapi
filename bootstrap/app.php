<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\EnsureActiveSubscription;
use App\Http\Middleware\EnsureActivationAccepted;
use App\Http\Middleware\EnsureServiceEntitled;
use App\Http\Middleware\EnsureErpAccess;
use App\Http\Middleware\ResolveCompanyFromHost;
use App\Http\Middleware\ResolveCompanyFromSubdomain;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

/**
 * ✅ OpenSSL Legacy Provider (OpenSSL 3.x)
 * - Dotenv llena $_ENV/$_SERVER, pero NO siempre getenv()
 * - OpenSSL usa getenv()/env real => forzamos putenv si existe en dotenv
 *
 * Requisito:
 *   .env => OPENSSL_CONF=/etc/ssl/openssl-legacy.cnf
 */


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__ . '/../routes/web.php',
            __DIR__ . '/../routes/erp.php',
            __DIR__ . '/../routes/admin.php',
            __DIR__ . '/../routes/subscriber.php',
        ],
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            // ✅ DGII WS: sin prefix /api, pero con middleware 'api' (stateless)
            Route::middleware('api')
                ->group(base_path('routes/dgii_ws.php'));
        },
    )

    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'role' => RoleMiddleware::class,

            // ✅ acceso subscriber solo con activation aceptada
            'activation.accepted' => EnsureActivationAccepted::class,

            // ✅ solo features con suscripción activa
            'subscription.active' => EnsureActiveSubscription::class,

            'entitled' => EnsureServiceEntitled::class,

            // ✅ agrega el nuevo (más claro)
            'service.entitled' => EnsureServiceEntitled::class,

            'erp.access' => EnsureErpAccess::class,

            'dgii.tenant' => ResolveCompanyFromHost::class,

            'tenant' => ResolveCompanyFromSubdomain::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
