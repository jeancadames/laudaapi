<?php

namespace Tests\Unit\AppHub;

use PHPUnit\Framework\TestCase;

class EcosystemHubExternalServiceStagingContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_social_and_crm_are_staged_as_external_inactive_services(): void
    {
        $migration = file_get_contents(
            $this->root()
            .'/database/migrations/2026_08_27_133500_stage_social_crm_external_services_for_ecosystem_hub.php'
        );

        foreach ([
            "'service_key' => 'social'",
            "'https://social.laudaapi.com'",
            "'service_key' => 'crm'",
            "'https://crm.laudaapi.com'",
            "'launch_mode' => 'external'",
            "'launch_path' => '/launch'",
            "'integration_mode' => 'sso'",
            "'is_standalone' => true",
            "'billable' => false",
            "'active' => false",
            "'operational_ready' => false",
            "'individual_activation_supported' =>",
            "'transformation_360_supported' =>",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $migration
            );
        }
    }

    public function test_legacy_erp_crm_is_guarded_not_converted(): void
    {
        $migration = file_get_contents(
            $this->root()
            .'/database/migrations/2026_08_27_133500_stage_social_crm_external_services_for_ecosystem_hub.php'
        );

        $this->assertStringContainsString(
            "'service_key', 'erp_crm'",
            $migration
        );

        $this->assertStringContainsString(
            "\$legacy->launch_mode !== 'internal'",
            $migration
        );

        $this->assertStringNotContainsString(
            "where('service_key', 'erp_crm')->update",
            $migration
        );

        $this->assertStringNotContainsString(
            "where('service_key', 'erp_crm')->delete",
            $migration
        );
    }

    public function test_hub_targets_new_crm_service_key_but_keeps_legacy_reference(): void
    {
        $catalog = file_get_contents(
            $this->root()
            .'/config/ecosystem_hub.php'
        );

        $crmStart = strpos(
            $catalog,
            "'crm' => ["
        );

        $this->assertNotFalse($crmStart);

        $crmChunk = substr(
            $catalog,
            $crmStart,
            1200
        );

        $this->assertStringContainsString(
            "'service_key' => 'crm'",
            $crmChunk
        );

        $this->assertStringContainsString(
            "'legacy_service_key' => 'erp_crm'",
            $crmChunk
        );
    }
}
