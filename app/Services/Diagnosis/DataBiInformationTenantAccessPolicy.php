<?php

namespace App\Services\Diagnosis;

/**
 * D2F-T3D2.
 * Evalua una membresia ya verificada contra el subscriber real.
 * No autoriza mediante workspace_company_id.
 */
final class DataBiInformationTenantAccessPolicy
{
    public static function mayRead(
        int $userId,
        int $companyOwnerId,
        ?string $membershipRole,
        bool $isMemberOfCompanySubscriber
    ): bool {
        if ($userId <= 0 || !$isMemberOfCompanySubscriber) {
            return false;
        }

        if ($companyOwnerId > 0 && $companyOwnerId === $userId) {
            return true;
        }

        return in_array(
            $membershipRole,
            ['owner', 'admin'],
            true
        );
    }
}
