<?php

namespace Tests\Unit\AppHub;

use PHPUnit\Framework\TestCase;

class PlanAwareCommercialFlowContractTest extends TestCase
{
    private function read(string $relative): string
    {
        $source = file_get_contents(
            dirname(__DIR__, 3).'/'.$relative
        );

        $this->assertIsString($source);

        return $source;
    }

    public function test_engine_keeps_legacy_and_adds_plan_pricing(): void
    {
        $source = $this->read(
            'app/Services/Billing/ServicePricingEngine.php'
        );

        foreach ([
            'public function quote(',
            'public function quotePlan(',
            'ServicePlan $plan',
            'ServicePlanPricingTier',
            "'pricing_source' => 'service_plan'",
            "'service_plan_id'",
            "'source_solution'",
            "'source_plan_key'",
            'planCyclePrice(',
            'resolvePlanSeatBlockTier(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_plan_preview_has_independent_cycle(): void
    {
        $source = $this->read(
            'app/Services/Billing/StandaloneServiceCheckoutService.php'
        );

        $preview = explode(
            'public function previewQuote(',
            $source,
            2
        )[1];

        $preview = explode(
            'public function checkout(',
            $preview,
            2
        )[0];

        foreach ([
            '?ServicePlan $servicePlan = null',
            'if ($servicePlan)',
            '->quotePlan(',
            "'subscription_cycle_locked' => false",
            "'service_plan_id'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $preview
            );
        }

        $planBranch = explode(
            'if ($servicePlan)',
            $preview,
            2
        )[1];

        $planBranch = explode(
            'Legacy: sin plan explícito',
            $planBranch,
            2
        )[0];

        $this->assertStringNotContainsString(
            '$activeCycle !== $billingCycle',
            $planBranch
        );
    }

    public function test_plan_is_propagated_through_checkout_and_settlement(): void
    {
        $checkout = $this->read(
            'app/Services/Billing/StandaloneServiceCheckoutService.php'
        );

        $settlement = $this->read(
            'app/Services/Billing/StandaloneServiceSettlementService.php'
        );

        foreach ([
            '?ServicePlan $servicePlan = null',
            "'service_plan_id'",
            '$servicePlan?->id',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $checkout
            );

            $this->assertStringContainsString(
                $required,
                $settlement
            );
        }

        $this->assertStringContainsString(
            '$locked->service_plan_id',
            $settlement
        );
    }

    public function test_central_owner_supports_item_cycle_without_replacing_legacy(): void
    {
        $source = $this->read(
            'app/Services/Entitlements/CentralEntitlementActivationService.php'
        );

        foreach ([
            'public function ensureSubscription(',
            'public function ensureSubscriptionForItem(',
            'public function activateCommercialItem(',
            'public function activateCommercial(',
            'public function revokeCommercialItem(',
            'resolveServicePlan(',
            'resolvePlanCurrency(',
            'assertSubscriptionItemContract(',
            '->quotePlan(',
            "'service_plan_id' =>",
            "'billing_cycle' =>",
            'legacy_default_item_cycles_independent',
            'entitlement_claims',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        $itemContract = explode(
            'private function assertSubscriptionItemContract(',
            $source,
            2
        )[1];

        $itemContract = explode(
            'private function assertSubscriptionContract(',
            $itemContract,
            2
        )[0];

        $this->assertStringNotContainsString(
            '$subscription->billing_cycle',
            $itemContract
        );
    }

    public function test_controller_accepts_optional_service_plan(): void
    {
        $source = $this->read(
            'app/Http/Controllers/Subscriber/SubscriberServiceActivationController.php'
        );

        foreach ([
            "'service_plan_id' => [",
            "'nullable'",
            "'exists:service_plans,id'",
            'ServicePlan::query()',
            '$servicePlan',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }
}
