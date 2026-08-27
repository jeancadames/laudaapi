<?php

namespace Tests\Unit\Billing;

use PHPUnit\Framework\TestCase;

class ServicePricingTierContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_migration_creates_normalized_tier_table(): void
    {
        $migration = file_get_contents(
            $this->root()
            .'/database/migrations/2026_08_24_183000_create_service_pricing_tiers_table.php'
        );

        foreach ([
            "Schema::create(",
            "'service_pricing_tiers'",
            "'service_id'",
            "'billing_cycle'",
            "'min_quantity'",
            "'max_quantity'",
            "'price'",
            "'currency'",
            "'active'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $migration
            );
        }
    }

    public function test_service_has_pricing_tiers_relation(): void
    {
        $service = file_get_contents(
            $this->root()
            .'/app/Models/Service.php'
        );

        $this->assertStringContainsString(
            'function pricingTiers(): HasMany',
            $service
        );

        $this->assertStringContainsString(
            'ServicePricingTier::class',
            $service
        );
    }

    public function test_tier_service_rejects_overlap_and_currency_mismatch(): void
    {
        $service = file_get_contents(
            $this->root()
            .'/app/Services/Billing/ServicePricingTierService.php'
        );

        $this->assertStringContainsString(
            'assertNoOverlaps(',
            $service
        );

        $this->assertStringContainsString(
            'no pueden solaparse',
            $service
        );

        $this->assertStringContainsString(
            'La moneda del tier debe coincidir con la moneda del Service.',
            $service
        );
    }

    public function test_engine_requires_exactly_one_matching_tier(): void
    {
        $engine = file_get_contents(
            $this->root()
            .'/app/Services/Billing/ServicePricingEngine.php'
        );

        $this->assertStringContainsString(
            'if ($tiers->isEmpty())',
            $engine
        );

        $this->assertStringContainsString(
            'if ($tiers->count() !== 1)',
            $engine
        );

        $this->assertStringContainsString(
            'No existe un tier seat_block',
            $engine
        );

        $this->assertStringNotContainsString(
            'legacy_seat_block',
            $engine
        );
    }

    public function test_admin_uses_existing_service_patch_for_tiers(): void
    {
        $controller = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Admin/AdminServiceController.php'
        );

        $page = file_get_contents(
            $this->root()
            .'/resources/js/pages/Admin/Services/Index.vue'
        );

        $this->assertStringContainsString(
            "'pricing_tiers' => ['nullable', 'array']",
            $controller
        );

        $this->assertStringContainsString(
            'ServicePricingTierService::class',
            $controller
        );

        $this->assertStringContainsString(
            'Rangos de usuarios',
            $page
        );

        $this->assertStringContainsString(
            'addPricingTier(',
            $page
        );

        $this->assertStringContainsString(
            'removePricingTier(',
            $page
        );
    }

    public function test_r2j_snapshots_selected_tier(): void
    {
        $r2j = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        );

        $central = file_get_contents(
            $this->root()
            .'/app/Services/Entitlements/CentralEntitlementActivationService.php'
        );

        foreach ([
            "'pricing' => [",
            "'pricing_version' =>",
            "'quantity_source' =>",
            "'tier_id' =>",
            "'tier_min_quantity' =>",
            "'tier_max_quantity' =>",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $central
            );
        }

        $this->assertStringContainsString(
            "'price_snapshot' =>",
            $r2j
        );

        $this->assertStringContainsString(
            "\$central['pricing']",
            $r2j
        );
    }
}
