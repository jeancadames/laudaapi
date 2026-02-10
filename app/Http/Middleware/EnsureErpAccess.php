<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use App\Services\Subscribers\SubscriberResolver;
use Closure;
use Illuminate\Http\Request;

class EnsureErpAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) abort(403);

        // ✅ Resolver subscriber real (pivot/company)
        $subscriberId = app(SubscriberResolver::class)->resolve($user);

        if (!$subscriberId) {
            $msg = 'No tienes acceso al ERP. Tu usuario no está asociado a un subscriber.';

            // ✅ Inertia/XHR: no redirects
            if ($request->expectsJson()) {
                abort(403, $msg);
            }

            return redirect('/')->with('error', $msg);
        }

        // ✅ Exigir suscripción activa/trialing (alineado a tu Entitlements)
        $hasSubscription = Subscription::query()
            ->where('subscriber_id', $subscriberId)
            ->whereIn('status', ['active', 'trialing'])
            ->exists();

        if (!$hasSubscription) {
            $msg = 'Tu suscripción no está activa. Verifica tu plan para acceder al ERP.';

            if ($request->expectsJson()) {
                abort(403, $msg);
            }

            return redirect('/subscriber/subscription')->with('error', $msg);
        }

        // ✅ Útil para controllers (evita recalcular resolver)
        $request->attributes->set('resolved_subscriber_id', $subscriberId);

        return $next($request);
    }
}
