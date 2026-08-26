<?php

namespace App\Services\Entitlements;

final class ServiceEntitlementPolicy
{
    public const SUBSCRIPTION_STATUSES = [
        'active',
        'trialing',
    ];

    public const ITEM_STATUSES = [
        'active',
        'trialing',
    ];

    public static function subscriptionStatuses(): array
    {
        return self::SUBSCRIPTION_STATUSES;
    }

    public static function itemStatuses(): array
    {
        return self::ITEM_STATUSES;
    }

    public static function itemStatusGrantsAccess(
        ?string $status
    ): bool {
        return in_array(
            strtolower(
                trim(
                    (string) $status
                )
            ),
            self::ITEM_STATUSES,
            true
        );
    }

    public static function subscriptionStatusCanGrantAccess(
        ?string $status
    ): bool {
        return in_array(
            strtolower(
                trim(
                    (string) $status
                )
            ),
            self::SUBSCRIPTION_STATUSES,
            true
        );
    }
}
