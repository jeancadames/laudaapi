<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;

class EnsureActiveSubscription
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) abort(403);

        // Resolver company (mismo patrón que dashboard)
        $company = null;

        if (!empty($user->company_id)) {
            $company = Company::query()->find($user->company_id);
        }
        if (!$company) {
            $company = Company::query()->where('owner_user_id', $user->id)->first();
        }
        if (!$company && !empty($user->subscriber_id)) {
            $company = Company::query()->where('subscriber_id', $user->subscriber_id)->first();
        }

        if (!$company || !$company->subscriber_id) {
            abort(403, 'No tienes compañía/suscriptor asignado.');
        }

        $now = now();

        /**
         * ✅ Regla:
         * - active: OK (si no venció)
         * - trialing: OK (si trial no venció)
         */
        $allowed = Subscription::query()
            ->where('subscriber_id', $company->subscriber_id)
            ->where(function ($q) use ($now) {
                $q->where(function ($q2) use ($now) {
                    $q2->where('status', 'active')
                        ->where(function ($qq) use ($now) {
                            $qq->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                        })
                        ->where(function ($qq) use ($now) {
                            $qq->whereNull('current_period_end')->orWhere('current_period_end', '>=', $now);
                        });
                })->orWhere(function ($q2) use ($now) {
                    $q2->where('status', 'trialing')
                        ->where(function ($qq) use ($now) {
                            $qq->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                        })
                        ->where(function ($qq) use ($now) {
                            $qq->whereNull('trial_ends_at')->orWhere('trial_ends_at', '>=', $now);
                        });
                });
            })
            ->exists();

        if (!$allowed) {
            abort(403, 'Requiere una suscripción activa o un trial vigente para seleccionar servicios.');
        }

        return $next($request);
    }
}
