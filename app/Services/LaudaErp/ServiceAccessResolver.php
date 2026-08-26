<?php

namespace App\Services\LaudaErp;

use App\Models\Company;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Entitlements\ServiceEntitlementPolicy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ServiceAccessResolver
{
    public function userCanAccess(User $user, Company $company, Service $service): bool
    {
        if (! $service->active) {
            return false;
        }

        if (! $this->passesRoleCheck($user, $service)) {
            return false;
        }

        return $this->hasActiveEntitlement(
            subscriberId: (int) $company->subscriber_id,
            serviceSlugs: [$service->slug],
        );
    }

    public function canAccessAny(User $user, Company $company, array $serviceSlugs): bool
    {
        $serviceSlugs = collect($serviceSlugs)
            ->map(fn($s) => trim((string) $s))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($serviceSlugs)) {
            return false;
        }

        return $this->hasActiveEntitlement(
            subscriberId: (int) $company->subscriber_id,
            serviceSlugs: $serviceSlugs,
        );
    }

    private function passesRoleCheck(User $user, Service $service): bool
    {
        $roles = $service->roles;

        if (is_string($roles)) {
            $roles = json_decode($roles, true);
        }

        if (! is_array($roles) || empty($roles)) {
            return true;
        }

        $userRole = method_exists($user, 'getRoleNames')
            ? $user->getRoleNames()->first()
            : ($user->role ?? null);

        if (! $userRole) {
            return false;
        }

        return in_array($userRole, $roles, true);
    }

    private function hasActiveEntitlement(
        int $subscriberId,
        array $serviceSlugs
    ): bool {
        if (
            $subscriberId <= 0
            || $serviceSlugs === []
        ) {
            return false;
        }

        $serviceSlugs = array_values(
            array_unique(
                array_filter(
                    array_map(
                        fn ($slug) =>
                            trim(
                                (string) $slug
                            ),
                        $serviceSlugs
                    ),
                    fn (string $slug): bool =>
                        $slug !== ''
                )
            )
        );

        if ($serviceSlugs === []) {
            return false;
        }

        /*
         * Autorización no se cachea entre requests:
         * una cancelación debe revocar el acceso inmediatamente.
         */
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
            ->value('id');

        if (! $subscriptionId) {
            return false;
        }

        return DB::table(
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
                'subscription_items.status',
                ServiceEntitlementPolicy::ITEM_STATUSES
            )
            ->where(
                'services.active',
                true
            )
            ->whereIn(
                'services.slug',
                $serviceSlugs
            )
            ->exists();
    }
}
