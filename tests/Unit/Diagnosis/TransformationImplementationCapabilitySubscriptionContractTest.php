<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationCapabilitySubscriptionContractTest extends TestCase
{
    private function project(string $path): string
    {
        return dirname(__DIR__, 3).'/'.$path;
    }

    public function test_mapping_uses_capability_key_and_real_service_id(): void
    {
        $migration = file_get_contents($this->project(
            'database/migrations/2026_08_24_165000_create_transformation_implementation_capability_service_mappings_table.php'
        ));

        $this->assertStringContainsString(
            "Schema::create('transformation_implementation_capability_service_mappings'",
            $migration
        );
        $this->assertStringContainsString("'capability_key'", $migration);
        $this->assertStringContainsString("'service_id'", $migration);
        $this->assertStringContainsString("->on('services')", $migration);
        $this->assertStringContainsString('tip_csm_capability_uq', $migration);
    }

    public function test_activation_trace_links_go_live_mapping_service_and_item(): void
    {
        $migration = file_get_contents($this->project(
            'database/migrations/2026_08_24_165100_create_transformation_implementation_subscription_item_activations_table.php'
        ));

        foreach ([
            "'transformation_implementation_capability_go_live_id'",
            "'transformation_implementation_subscription_activation_id'",
            "'transformation_implementation_capability_service_mapping_id'",
            "'service_id'",
            "'subscription_item_id'",
            "'activation_type'",
            "'price_snapshot'",
            "'activated_at'",
        ] as $required) {
            $this->assertStringContainsString($required, $migration);
        }
    }

    public function test_mysql_identifiers_are_explicit_and_short(): void
    {
        $all = file_get_contents($this->project(
            'database/migrations/2026_08_24_165000_create_transformation_implementation_capability_service_mappings_table.php'
        ))."\n".file_get_contents($this->project(
            'database/migrations/2026_08_24_165100_create_transformation_implementation_subscription_item_activations_table.php'
        ));

        foreach ([
            'tip_csm_service_fk',
            'tip_csm_created_fk',
            'tip_csm_updated_fk',
            'tip_csm_capability_uq',
            'tip_csm_service_active_idx',
            'tip_sia_go_live_fk',
            'tip_sia_sub_activation_fk',
            'tip_sia_mapping_fk',
            'tip_sia_service_fk',
            'tip_sia_item_fk',
            'tip_sia_created_fk',
            'tip_sia_go_live_uq',
            'tip_sia_service_item_idx',
        ] as $identifier) {
            $this->assertStringContainsString($identifier, $all);
            $this->assertLessThanOrEqual(64, strlen($identifier));
        }
    }

    public function test_service_is_not_hardcoded_or_created(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        ));

        $this->assertStringContainsString('upsertMapping', $service);
        $this->assertStringContainsString("'capability_key' => \$capabilityKey", $service);
        $this->assertStringContainsString("'service_id' => \$service->id", $service);

        $this->assertStringNotContainsString('Service::create', $service);
        $this->assertStringNotContainsString("'erp_crm'", $service);
        $this->assertStringNotContainsString("'social'", $service);
    }

    public function test_subscription_item_requires_live_go_live_and_r2i_activation(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        ));

        $this->assertStringContainsString(
            'TransformationImplementationCapabilityGoLive::STATUS_LIVE',
            $service
        );

        $this->assertStringContainsString(
            'transformation_implementation_capability_go_live_id',
            $service
        );

        $this->assertStringContainsString(
            'La activación de suscripción debe pertenecer al mismo Go-Live.',
            $service
        );
    }

    public function test_price_uses_central_service_pricing_engine(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        ));

        $engine = file_get_contents($this->project(
            'app/Services/Billing/ServicePricingEngine.php'
        ));

        $this->assertStringContainsString(
            'ServicePricingEngine::class',
            $service
        );

        $this->assertStringContainsString(
            '->quote(',
            $service
        );

        foreach ([
            'monthly_price',
            'yearly_price',
            'billing_cycle',
            "'flat'",
            "'per_user'",
            "'seat_block'",
            "'usage'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $engine
            );
        }
    }

    public function test_created_item_is_active_post_go_live_not_trialing(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        ));

        $this->assertStringContainsString("'status' => 'active'", $service);
        $this->assertStringContainsString("'activation_mode' => 'post_go_live'", $service);
        $this->assertStringNotContainsString("'status' => 'trialing'", $service);
    }

    public function test_existing_active_item_is_reused(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        ));

        $this->assertStringContainsString("->lockForUpdate()", $service);
        $this->assertStringContainsString('TYPE_REUSED', $service);
        $this->assertStringContainsString('TYPE_CREATED', $service);
    }

    public function test_subscription_totals_are_recalculated_by_central_service(): void
    {
        $service = file_get_contents(
            dirname(__DIR__, 3).'/app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        );

        $totals = file_get_contents(
            dirname(__DIR__, 3).'/app/Services/Billing/SubscriptionTotalsService.php'
        );

        $this->assertStringContainsString(
            'recalculateSubscriptionTotals',
            $service
        );

        $this->assertStringContainsString(
            'SubscriptionTotalsService::class',
            $service
        );

        $this->assertStringContainsString(
            "->sum('amount')",
            $totals
        );

        $this->assertStringContainsString(
            "->whereIn(",
            $totals
        );


        $this->assertStringContainsString(
            "'subtotal_amount' =>",
            $totals
        );

        $this->assertStringContainsString(
            "'discount_amount' =>",
            $totals
        );

        $this->assertStringContainsString(
            "'total_amount' =>",
            $totals
        );

        $this->assertStringContainsString(
            'BundleDiscountEngine::class',
            $totals
        );
        $this->assertStringContainsString(
            'ServiceEntitlementPolicy::ITEM_STATUSES',
            $totals
        );

    }
}
