<?php

namespace App\Services\LaudaErp;

use App\Models\Company;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\User;
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

    private function hasActiveEntitlement(int $subscriberId, array $serviceSlugs): bool
    {
        if ($subscriberId <= 0 || empty($serviceSlugs)) {
            return false;
        }

        $serviceSlugs = collect($serviceSlugs)
            ->map(fn($s) => trim((string) $s))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($serviceSlugs)) {
            return false;
        }

        $cacheKey = "service-launch:entitled:{$subscriberId}:" . implode('|', $serviceSlugs);

        return Cache::remember($cacheKey, now()->addSeconds(45), function () use ($subscriberId, $serviceSlugs) {
            $serviceIds = Service::query()
                ->whereIn('slug', $serviceSlugs)
                ->pluck('id');

            // Si un slug no existe, no damos acceso
            if ($serviceIds->count() !== count($serviceSlugs)) {
                return false;
            }

            $subscriptionId = Subscription::query()
                ->where('subscriber_id', $subscriberId)
                ->whereIn('status', ['active', 'trialing'])
                ->orderByRaw("FIELD(status,'active','trialing')")
                ->latest('id')
                ->value('id');

            if (! $subscriptionId) {
                return false;
            }

            return DB::table('subscription_items')
                ->where('subscription_id', $subscriptionId)
                ->whereIn('service_id', $serviceIds->all())
                ->whereIn('status', ['active', 'trialing', 'pending'])
                ->exists();
        });
    }
}
