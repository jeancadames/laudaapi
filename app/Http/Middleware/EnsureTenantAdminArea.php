<?php

namespace App\Http\Middleware;

use App\Services\Subscribers\SubscriberResolver;
use App\Services\Subscribers\TenantAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTenantAdminArea
{
    public function __construct(
        private readonly SubscriberResolver $subscriberResolver,
        private readonly TenantAccessService $tenantAccessService,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user, 403);

        /*
         * El administrador global de LAUDAAPI conserva compatibilidad
         * con las rutas centrales existentes.
         */
        if (($user->role ?? null) === 'admin') {
            return $next($request);
        }

        /*
         * /subscriber es solamente el gateway legacy hacia /app.
         * Se mantiene accesible para que un member sea redirigido
         * correctamente a Mis Apps.
         */
        if ($request->is('subscriber')) {
            return $next($request);
        }

        $subscriberId = (int) (
            $this->subscriberResolver->resolve($user)
            ?? 0
        );

        abort_unless($subscriberId > 0, 403);

        $tenantAccess = $this->tenantAccessService->resolve(
            $user,
            $subscriberId
        );

        abort_unless(
            ($tenantAccess['mode'] ?? null)
                === TenantAccessService::SUBSCRIBER_ADMIN,
            403
        );

        return $next($request);
    }
}
