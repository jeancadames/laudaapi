<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataBiInformationTenantAccessPolicy;
use PHPUnit\Framework\TestCase;

final class DataBiInformationTenantAccessPolicyTest extends TestCase
{
    public function test_owner_with_membership_is_allowed(): void
    {
        self::assertTrue(
            DataBiInformationTenantAccessPolicy::mayRead(
                29, 29, 'member', true
            )
        );
    }

    public function test_admin_of_same_subscriber_is_allowed(): void
    {
        self::assertTrue(
            DataBiInformationTenantAccessPolicy::mayRead(
                30, 29, 'admin', true
            )
        );
    }

    public function test_owner_role_of_same_subscriber_is_allowed(): void
    {
        self::assertTrue(
            DataBiInformationTenantAccessPolicy::mayRead(
                30, 29, 'owner', true
            )
        );
    }

    public function test_regular_member_is_denied(): void
    {
        self::assertFalse(
            DataBiInformationTenantAccessPolicy::mayRead(
                30, 29, 'member', true
            )
        );
    }

    public function test_other_subscriber_is_denied(): void
    {
        self::assertFalse(
            DataBiInformationTenantAccessPolicy::mayRead(
                30, 29, 'admin', false
            )
        );
    }

    public function test_owner_without_membership_is_denied(): void
    {
        self::assertFalse(
            DataBiInformationTenantAccessPolicy::mayRead(
                29, 29, null, false
            )
        );
    }

    public function test_unknown_role_is_denied(): void
    {
        self::assertFalse(
            DataBiInformationTenantAccessPolicy::mayRead(
                30, 29, 'editor', true
            )
        );
    }

    public function test_invalid_user_is_denied(): void
    {
        self::assertFalse(
            DataBiInformationTenantAccessPolicy::mayRead(
                0, 0, 'admin', true
            )
        );
    }
}
