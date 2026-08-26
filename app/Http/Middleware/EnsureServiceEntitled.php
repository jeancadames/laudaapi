<?php

namespace App\Http\Middleware;

use App\Models\Service;
use App\Models\Subscription;
use App\Services\Entitlements\ServiceEntitlementPolicy;
use App\Services\Subscribers\SubscriberResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnsureServiceEntitled
{
    public function handle(
        Request $request,
        Closure $next,
        string $serviceSlugs
    ) {
        $user = $request->user();
        abort_unless($user, 403);

        /*
         * 1) Preferir subscriber ya resuelto por EnsureErpAccess.
         * 2) Fallback por SubscriberResolver.
         */
        $subscriberId = (int) $request
            ->attributes
            ->get(
                'resolved_subscriber_id',
                0
            );

        if ($subscriberId <= 0) {
            $subscriberId = (int) app(
                SubscriberResolver::class
            )->resolve(
                $user
            );
        }

        abort_unless(
            $subscriberId > 0,
            403
        );

        $slugs = collect(
            preg_split(
                '/[|,]/',
                $serviceSlugs
            )
        )
            ->map(
                fn ($slug) =>
                    trim(
                        (string) $slug
                    )
            )
            ->filter()
            ->unique()
            ->values()
            ->all();

        abort_unless(
            count($slugs) > 0,
            403
        );

        /*
         * La existencia del slug es configuración:
         * si falta uno, conservar 404.
         */
        $services = Service::query()
            ->whereIn(
                'slug',
                $slugs
            )
            ->get([
                'id',
                'slug',
                'active',
            ]);

        if (
            $services->count()
            !== count($slugs)
        ) {
            abort(404);
        }

        $subscriptionId = Subscription::query()
            ->where(
                'subscriber_id',
                $subscriberId
            )
            ->whereIn(
                'status',
                ServiceEntitlementPolicy::SUBSCRIPTION_STATUSES
            )
            ->orderByRaw(
                "FIELD(status,'active','trialing')"
            )
            ->latest('id')
            ->value('id');

        if (! $subscriptionId) {
            abort(403);
        }

        /*
         * Autorización fresca: NO cachear.
         *
         * Un Service concede acceso únicamente si:
         * - Service está active;
         * - Subscription está active|trialing;
         * - SubscriptionItem está active|trialing.
         *
         * Para aliases a|b|c basta que uno cumpla.
         */
        $allowed = DB::table(
            'subscription_items'
        )
            ->join(
                'services',
                'services.id',
                '=',
                'subscription_items.service_id'
            )
            ->where(
                'subscription_items.subscription_id',
                $subscriptionId
            )
            ->whereIn(
                'subscription_items.service_id',
                $services
                    ->pluck('id')
                    ->all()
            )
            ->whereIn(
                'subscription_items.status',
                ServiceEntitlementPolicy::ITEM_STATUSES
            )
            ->where(
                'services.active',
                true
            )
            ->exists();

        abort_unless(
            $allowed,
            403
        );

        return $next(
            $request
        );
    }
}
