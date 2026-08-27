<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationE2EContractTest extends TestCase
{
    private function project(string $path): string
    {
        return dirname(__DIR__, 3).'/'.$path;
    }

    private function read(string $path): string
    {
        return file_get_contents($this->project($path));
    }

    public function test_plan_publication_gate_remains_owned_by_foundation_contract(): void
    {
        $foundation = $this->read(
            'tests/Unit/Diagnosis/TransformationImplementationPlanFoundationContractTest.php'
        );

        $plan = $this->read(
            'app/Services/Diagnosis/TransformationImplementationPlanService.php'
        );

        $this->assertStringContainsString(
            'plan can be created from official assessment or published roadmap',
            strtolower(str_replace('_', ' ', $foundation))
        );

        $this->assertNotSame('', trim($plan));
    }

    public function test_commercial_definition_precedes_execution(): void
    {
        $phase = $this->read(
            'app/Services/Diagnosis/TransformationImplementationPhaseService.php'
        );

        $pricing = $this->read(
            'app/Services/Diagnosis/TransformationImplementationPricingService.php'
        );

        $execution = $this->read(
            'app/Services/Diagnosis/TransformationImplementationExecutionService.php'
        );

        $this->assertStringContainsString('createPhaseFromRoadmap', $phase);
        $this->assertStringContainsString('upsertEstimate', $pricing);

        $this->assertStringContainsString(
            "\$phase->plan->status !== 'accepted'",
            $execution
        );
    }

    public function test_modality_api_is_split_between_service_and_catalog(): void
    {
        $catalog = $this->read(
            'app/Services/Diagnosis/TransformationImplementationModalityCatalog.php'
        );

        $service = $this->read(
            'app/Services/Diagnosis/TransformationImplementationModalityService.php'
        );

        $this->assertStringContainsString('function exists(', $catalog);
        $this->assertStringContainsString('function get(', $catalog);

        $this->assertStringContainsString('function options(', $service);
        $this->assertStringContainsString('function select(', $service);
    }

    public function test_implementation_billing_does_not_start_subscription(): void
    {
        $billing = $this->read(
            'app/Services/Diagnosis/TransformationImplementationMilestoneBillingService.php'
        );

        foreach ([
            'Subscription::create',
            'Subscriber::create',
            'Company::create',
            'SubscriptionItem::create',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $billing);
        }
    }

    public function test_completed_capability_is_not_automatically_live(): void
    {
        $execution = $this->read(
            'app/Services/Diagnosis/TransformationImplementationExecutionService.php'
        );

        $goLive = $this->read(
            'app/Services/Diagnosis/TransformationImplementationGoLiveService.php'
        );

        $this->assertStringContainsString('completeCapability', $execution);
        $this->assertStringNotContainsString('function goLive(', $execution);

        $this->assertStringContainsString('createAttempt', $goLive);
        $this->assertStringContainsString('markReady', $goLive);
        $this->assertStringContainsString('function goLive(', $goLive);
    }

    public function test_subscription_cannot_start_before_live(): void
    {
        $r2i = $this->read(
            'app/Services/Diagnosis/TransformationImplementationSubscriptionService.php'
        );

        $central = $this->read(
            'app/Services/Entitlements/CentralEntitlementActivationService.php'
        );

        $this->assertStringContainsString(
            'TransformationImplementationCapabilityGoLive::STATUS_LIVE',
            $r2i
        );

        $this->assertStringContainsString(
            '->ensureSubscription(',
            $r2i
        );

        $this->assertStringContainsString(
            '$goLive->went_live_at',
            $r2i
        );

        $this->assertStringContainsString(
            "'starts_at' =>",
            $central
        );

        $this->assertStringContainsString(
            '$effectiveAt',
            $central
        );

        $this->assertStringContainsString(
            "'trial_ends_at' => null",
            $central
        );
    }

    public function test_subscription_is_idempotent_per_go_live(): void
    {
        $migration = $this->read(
            'database/migrations/2026_08_24_164000_create_transformation_implementation_subscription_activations_table.php'
        );

        $subscription = $this->read(
            'app/Services/Diagnosis/TransformationImplementationSubscriptionService.php'
        );

        $this->assertStringContainsString('tip_sa_go_live_uq', $migration);
        $this->assertStringContainsString('$existingActivation', $subscription);
        $this->assertStringContainsString('TYPE_REUSED', $subscription);
    }

    public function test_each_live_capability_activates_only_its_mapped_service(): void
    {
        $capability = $this->read(
            'app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        );

        foreach ([
            '$capabilityKey',
            'TransformationImplementationCapabilityServiceMapping::query()',
            "'capability_key'",
            "'service_id' =>",
            '->activateCommercialItem(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $capability
            );
        }

        $this->assertStringNotContainsString(
            'foreach ($phase->capabilities',
            $capability
        );

        $this->assertStringNotContainsString(
            'foreach ($plan->phases',
            $capability
        );
    }

    public function test_service_mapping_has_no_hardcoded_crm_social_or_service_id(): void
    {
        $capability = $this->read(
            'app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        );

        foreach ([
            "'erp_crm'",
            "'social'",
            "'presencia_digital'",
            "'service_id' => 1",
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $capability);
        }
    }

    public function test_subscription_item_pricing_uses_central_catalog_engine(): void
    {
        $r2j = $this->read(
            'app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        );

        $central = $this->read(
            'app/Services/Entitlements/CentralEntitlementActivationService.php'
        );

        $engine = $this->read(
            'app/Services/Billing/ServicePricingEngine.php'
        );

        $this->assertStringContainsString(
            '->activateCommercialItem(',
            $r2j
        );

        $this->assertStringContainsString(
            'ServicePricingEngine::class',
            $central
        );

        $this->assertStringContainsString(
            '->quote(',
            $central
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

    public function test_subscription_item_activation_is_idempotent(): void
    {
        $migration = $this->read(
            'database/migrations/2026_08_24_165100_create_transformation_implementation_subscription_item_activations_table.php'
        );

        $capability = $this->read(
            'app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        );

        $this->assertStringContainsString('tip_sia_go_live_uq', $migration);
        $this->assertStringContainsString('$existingActivation', $capability);

        $this->assertStringContainsString(
            "->lockForUpdate()",
            $capability
        );
    }

    public function test_go_live_rollback_is_not_subscription_cancellation(): void
    {
        $goLive = $this->read(
            'app/Services/Diagnosis/TransformationImplementationGoLiveService.php'
        );

        $this->assertStringContainsString('function rollback(', $goLive);
        $this->assertStringContainsString('STATUS_ROLLED_BACK', $goLive);

        foreach ([
            'Subscription::',
            'SubscriptionItem::',
            'Subscriber::',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $goLive);
        }
    }

    public function test_ecosystem_activation_is_progressive(): void
    {
        $subscription = $this->read(
            'app/Services/Diagnosis/TransformationImplementationSubscriptionService.php'
        );

        $capability = $this->read(
            'app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        );

        $this->assertStringContainsString('activateFromGoLive', $subscription);
        $this->assertStringContainsString('activateFromGoLive', $capability);

        $this->assertStringNotContainsString(
            'assertAllCapabilitiesLive',
            $subscription
        );

        $this->assertStringNotContainsString(
            'assertAllCapabilitiesLive',
            $capability
        );
    }

    public function test_subscription_creation_exists_only_after_go_live_boundary(): void
    {
        $billing = $this->read(
            'app/Services/Diagnosis/TransformationImplementationMilestoneBillingService.php'
        );

        $execution = $this->read(
            'app/Services/Diagnosis/TransformationImplementationExecutionService.php'
        );

        $goLive = $this->read(
            'app/Services/Diagnosis/TransformationImplementationGoLiveService.php'
        );

        $r2i = $this->read(
            'app/Services/Diagnosis/TransformationImplementationSubscriptionService.php'
        );

        $r2j = $this->read(
            'app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        );

        $central = $this->read(
            'app/Services/Entitlements/CentralEntitlementActivationService.php'
        );

        foreach ([
            $billing,
            $execution,
            $goLive,
            $r2i,
            $r2j,
        ] as $adapter) {
            $this->assertStringNotContainsString(
                'Subscription::query()->create',
                $adapter
            );

            $this->assertStringNotContainsString(
                'SubscriptionItem::query()->create',
                $adapter
            );
        }

        $this->assertStringContainsString(
            'TransformationImplementationCapabilityGoLive::STATUS_LIVE',
            $r2i
        );

        $this->assertStringContainsString(
            '->ensureSubscription(',
            $r2i
        );

        $this->assertStringContainsString(
            '->activateCommercialItem(',
            $r2j
        );

        $this->assertStringContainsString(
            'Subscription::query()->create',
            $central
        );

        $this->assertStringContainsString(
            'SubscriptionItem::query()',
            $central
        );
    }
}
