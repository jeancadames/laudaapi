<?php

namespace Tests\Unit\Billing;

use PHPUnit\Framework\TestCase;

class BundleDiscountEngineContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_existing_service_bundle_items_is_reused_for_composition(): void
    {
        $engine = file_get_contents(
            $this->root()
            .'/app/Services/Billing/BundleDiscountEngine.php'
        );

        $this->assertStringContainsString(
            "'service_bundle_items'",
            $engine
        );

        $this->assertStringContainsString(
            "'bundle_service_id'",
            $engine
        );

        $this->assertStringContainsString(
            "'included_service_id'",
            $engine
        );

        $this->assertStringContainsString(
            "'required'",
            $engine
        );
    }

    public function test_bundle_discount_is_based_only_on_bundle_items(): void
    {
        $engine = file_get_contents(
            $this->root()
            .'/app/Services/Billing/BundleDiscountEngine.php'
        );

        $this->assertStringContainsString(
            '$bundleBase',
            $engine
        );

        $this->assertStringContainsString(
            '$matchedIds',
            $engine
        );

        $this->assertStringContainsString(
            '$amountByService',
            $engine
        );
    }

    public function test_percentage_and_fixed_amount_are_supported_without_stacking(): void
    {
        $engine = file_get_contents(
            $this->root()
            .'/app/Services/Billing/BundleDiscountEngine.php'
        );

        $rule = file_get_contents(
            $this->root()
            .'/app/Models/ServiceBundleDiscountRule.php'
        );

        $this->assertStringContainsString(
            "TYPE_PERCENTAGE = 'percentage'",
            $rule
        );

        $this->assertStringContainsString(
            "TYPE_FIXED_AMOUNT = 'fixed_amount'",
            $rule
        );

        $this->assertStringContainsString(
            '$winner = $candidates[0]',
            $engine
        );
    }

    public function test_priority_wins_and_discount_breaks_ties(): void
    {
        $engine = file_get_contents(
            $this->root()
            .'/app/Services/Billing/BundleDiscountEngine.php'
        );

        $this->assertStringContainsString(
            '$priorityCompare',
            $engine
        );

        $this->assertStringContainsString(
            '$discountCompare',
            $engine
        );
    }

    public function test_fixed_amount_requires_subscription_currency(): void
    {
        $engine = file_get_contents(
            $this->root()
            .'/app/Services/Billing/BundleDiscountEngine.php'
        );

        $this->assertStringContainsString(
            'El fixed_amount del bundle debe usar la misma moneda de la Subscription.',
            $engine
        );
    }

    public function test_totals_service_owns_discount_and_audit(): void
    {
        $totals = file_get_contents(
            $this->root()
            .'/app/Services/Billing/SubscriptionTotalsService.php'
        );

        foreach ([
            'BundleDiscountEngine::class',
            "'subtotal_amount' =>",
            "'discount_amount' =>",
            "'total_amount' =>",
            "'bundle_discount'",
            'SubscriptionBundleDiscountApplication::query()',
            "'fingerprint' =>",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $totals
            );
        }
    }

    public function test_r2j_delegates_totals_to_central_service(): void
    {
        $r2j = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        );

        $central = file_get_contents(
            $this->root()
            .'/app/Services/Entitlements/CentralEntitlementActivationService.php'
        );

        $this->assertStringContainsString(
            '->activateCommercialItem(',
            $r2j
        );

        $this->assertStringContainsString(
            'SubscriptionTotalsService::class',
            $central
        );

        $this->assertStringContainsString(
            ')->recalculate(',
            $central
        );
    }
}
