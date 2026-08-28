<?php

namespace App\Services\Subscribers;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class TenantAccessService
{
    public const PLATFORM_ADMIN = 'platform.admin';
    public const SUBSCRIBER_ADMIN = 'subscriber.admin';
    public const SUBSCRIBER_USER = 'subscriber.user';
    public const SUBSCRIBER_BILLING = 'subscriber.billing';

    public function resolve(User $user, ?int $subscriberId = null): array
    {
        if (($user->role ?? null) === 'admin') {
            return $this->payload(
                self::PLATFORM_ADMIN,
                null,
                true,
                true,
                true,
                true,
                true,
                false
            );
        }

        $subscriberId = (int) (
            $subscriberId
            ?: app(SubscriberResolver::class)->resolve($user)
            ?: 0
        );

        $pivotRole = null;

        if ($subscriberId > 0) {
            $pivotRole = DB::table('subscriber_user')
                ->where('user_id', $user->id)
                ->where('subscriber_id', $subscriberId)
                ->where('active', 1)
                ->value('role');
        }

        $pivotRole = strtolower(trim((string) $pivotRole));

        return match ($pivotRole) {
            'owner', 'admin' => $this->payload(
                self::SUBSCRIBER_ADMIN,
                $pivotRole,
                false,
                true,
                true,
                true,
                true,
                true
            ),

            'billing' => $this->payload(
                self::SUBSCRIBER_BILLING,
                $pivotRole,
                false,
                false,
                false,
                true,
                true,
                false
            ),

            default => $this->payload(
                self::SUBSCRIBER_USER,
                $pivotRole !== '' ? $pivotRole : 'member',
                false,
                false,
                false,
                false,
                true,
                false
            ),
        };
    }

    private function payload(
        string $mode,
        ?string $pivotRole,
        bool $platformAdmin,
        bool $tenantAdmin,
        bool $canBrowseStore,
        bool $canManageBilling,
        bool $canLaunchApps,
        bool $canManageCompany
    ): array {
        return [
            'mode' => $mode,
            'pivot_role' => $pivotRole,
            'platform_admin' => $platformAdmin,
            'tenant_admin' => $tenantAdmin,
            'can_browse_store' => $canBrowseStore,
            'can_view_solution_insights' => $tenantAdmin,
            'can_manage_billing' => $canManageBilling,
            'can_launch_apps' => $canLaunchApps,
            'can_manage_company' => $canManageCompany,
        ];
    }
}
