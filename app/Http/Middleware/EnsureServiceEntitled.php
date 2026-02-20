<?php

namespace App\Http\Middleware;

use App\Models\Service;
use App\Models\Subscription;
use App\Services\Subscribers\SubscriberResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EnsureServiceEntitled
{
    public function handle(Request $request, Closure $next, string $serviceSlugs)
    {
        $user = $request->user();
        abort_unless($user, 403);

        // ✅ 1) Preferir subscriber ya resuelto por EnsureErpAccess (/erp)
        $subscriberId = (int) $request->attributes->get('resolved_subscriber_id', 0);

        // ✅ 2) Fallback robusto (pivot/company)
        if ($subscriberId <= 0) {
            $subscriberId = (int) app(SubscriberResolver::class)->resolve($user);
        }

        abort_unless($subscriberId > 0, 403);

        // Permite "a|b|c" o "a,b,c"
        $slugs = collect(preg_split('/[|,]/', $serviceSlugs))
            ->map(fn($s) => trim((string) $s))
            ->filter()
            ->unique()
            ->values()
            ->all();

        abort_unless(count($slugs) > 0, 403);

        $cacheKey = "entitled:{$subscriberId}:" . implode('|', $slugs);

        $allowed = Cache::remember($cacheKey, now()->addSeconds(45), function () use ($subscriberId, $slugs) {

            $serviceIds = Service::query()
                ->whereIn('slug', $slugs)
                ->pluck('id');

            // si un slug no existe => mala config
            if ($serviceIds->count() !== count($slugs)) {
                return null; // => 404
            }

            $subscriptionId = Subscription::query()
                ->where('subscriber_id', $subscriberId)
                ->whereIn('status', ['active', 'trialing'])
                ->orderByRaw("FIELD(status,'active','trialing')")
                ->latest('id')
                ->value('id');

            if (!$subscriptionId) return false;

            // ✅ alineado a tu sidebar: incluye pending
            return DB::table('subscription_items')
                ->where('subscription_id', $subscriptionId)
                ->whereIn('service_id', $serviceIds->all())
                ->whereIn('status', ['active', 'trialing', 'pending'])
                ->exists();
        });

        if ($allowed === null) abort(404);
        if ($allowed === false) abort(403);

        return $next($request);
    }
}
