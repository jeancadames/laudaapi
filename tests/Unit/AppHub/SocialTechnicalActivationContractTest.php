<?php

namespace Tests\Unit\AppHub;

use PHPUnit\Framework\TestCase;

class SocialTechnicalActivationContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_migration_enables_only_technical_availability(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/database/migrations/2026_08_27_141500_activate_social_technical_ready_for_ecosystem_hub.php'
        );

        foreach ([
            "where('service_key', 'social')",
            "'active' => true",
            "'billable' => false",
            "'operational_ready'] = true",
            "'ecosystem_hub_stage'] = false",
            "'social_sso_e2e_certified'",
            "'pricing_required_before_billing'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        foreach ([
            "'monthly_price' =>",
            "'yearly_price' =>",
            "Subscription::",
            "SubscriptionItem::",
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_down_refuses_to_break_active_entitlements(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/database/migrations/2026_08_27_141500_activate_social_technical_ready_for_ecosystem_hub.php'
        );

        $this->assertStringContainsString(
            "->whereIn(\n                    'status',\n                    ['active', 'trialing']",
            $source
        );

        $this->assertStringContainsString(
            'existen entitlements activos',
            $source
        );
    }

    public function test_hub_still_requires_entitlement_for_launch_url(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Ecosystem/EcosystemHubService.php'
        );

        foreach ([
            '$entitled',
            '$ready',
            'userCanAccess(',
            "'erp.services.open'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }
}
