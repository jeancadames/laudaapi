<?php

namespace App\Http\Middleware;

use App\Models\Service;
use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EnsureServiceEntitled
{
    public function handle(Request $request, Closure $next, string $serviceSlug)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $subscriberId = (int) ($user->subscriber_id ?? 0);
        if ($subscriberId <= 0) {
            abort(403, 'Subscriber not found for user.');
        }

        // Cache corto para evitar hits repetidos en navegación
        $cacheKey = "entitled:{$subscriberId}:{$serviceSlug}";

        $allowed = Cache::remember($cacheKey, now()->addSeconds(45), function () use ($subscriberId, $serviceSlug) {
            $serviceId = Service::query()
                ->where('slug', $serviceSlug)
                ->value('id');

            if (!$serviceId) {
                return null; // => 404
            }

            // Elegir suscripción vigente: prioriza active sobre trialing
            $subscriptionId = Subscription::query()
                ->where('subscriber_id', $subscriberId)
                ->whereIn('status', ['active', 'trialing'])
                ->orderByRaw("FIELD(status,'active','trialing')")
                ->latest('id')
                ->value('id');

            if (!$subscriptionId) {
                return false;
            }

            // Validar item vigente para ese service
            $hasItem = \DB::connection('mysql')
                ->table('subscription_items')
                ->where('subscription_id', $subscriptionId)
                ->where('service_id', $serviceId)
                ->whereIn('status', ['active', 'trialing'])
                ->exists();

            return $hasItem ? true : false;
        });

        if ($allowed === null) {
            abort(404);
        }

        if ($allowed === false) {
            abort(403);
        }

        return $next($request);
    }
}
