<?php

namespace Tests\Unit\Billing;

use PHPUnit\Framework\TestCase;

class SubscriptionBillingSourceOfTruthContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function source(string $relative): string
    {
        return file_get_contents(
            $this->root().'/'.$relative
        );
    }

    public function test_pricing_engine_remains_canonical_recurring_pricing_source(): void
    {
        $source = $this->source(
            'app/Services/Billing/ServicePricingEngine.php'
        );

        foreach ([
            'MODEL_FLAT',
            'MODEL_PER_USER',
            'MODEL_SEAT_BLOCK',
            'MODEL_USAGE',
            'billingCycle(',
            'currency(',
            'cyclePrice(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_subscription_totals_uses_central_item_eligibility_policy(): void
    {
        $source = $this->source(
            'app/Services/Billing/SubscriptionTotalsService.php'
        );

        $this->assertStringContainsString(
            'use App\\Services\\Entitlements\\ServiceEntitlementPolicy;',
            $source
        );

        $this->assertStringContainsString(
            'ServiceEntitlementPolicy::ITEM_STATUSES',
            $source
        );

        $this->assertStringNotContainsString(
            "['active', 'trialing']",
            $source
        );

        foreach ([
            "'subtotal_amount' =>",
            "'discount_amount' =>",
            "'tax_amount' =>",
            "'total_amount' =>",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_bundle_engine_uses_same_central_item_eligibility_policy(): void
    {
        $source = $this->source(
            'app/Services/Billing/BundleDiscountEngine.php'
        );

        $this->assertStringContainsString(
            'use App\\Services\\Entitlements\\ServiceEntitlementPolicy;',
            $source
        );

        $this->assertStringContainsString(
            'ServiceEntitlementPolicy::ITEM_STATUSES',
            $source
        );

        $this->assertStringNotContainsString(
            "['active', 'trialing']",
            $source
        );
    }

    public function test_central_policy_excludes_pending_payment_and_cancelled(): void
    {
        $source = $this->source(
            'app/Services/Entitlements/ServiceEntitlementPolicy.php'
        );

        $this->assertStringContainsString(
            'public const ITEM_STATUSES',
            $source
        );

        $itemConstant = explode(
            'public const ITEM_STATUSES',
            $source,
            2
        )[1];

        $itemConstant = explode(
            ';',
            $itemConstant,
            2
        )[0];

        foreach ([
            "'active'",
            "'trialing'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $itemConstant
            );
        }

        foreach ([
            "'pending'",
            "'pending_payment'",
            "'cancelled'",
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $itemConstant
            );
        }
    }

    public function test_all_current_economic_item_mutation_hooks_use_canonical_totals_owner(): void
    {
        $root = dirname(__DIR__, 3);

        $r2j = file_get_contents(
            $root
            .'/app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        );

        $cancel = file_get_contents(
            $root
            .'/app/Http/Controllers/Subscriber/SubscriberServiceCancellationController.php'
        );

        $central = file_get_contents(
            $root
            .'/app/Services/Entitlements/CentralEntitlementActivationService.php'
        );

        /*
         * R2-J ya no es owner económico directo.
         * Debe delegar al owner central T360.
         */
        foreach ([
            'CentralEntitlementActivationService::class',
            '->activateCommercialItem(',
            'SOURCE_TRANSFORMATION_360',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $r2j
            );
        }

        /*
         * Cancelación directa sigue cerrando en totals.
         */
        foreach ([
            'SubscriptionTotalsService::class',
            'recalculate',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $cancel
            );
        }

        /*
         * Owner central: activación/revocación y recálculo.
         */
        foreach ([
            'SubscriptionTotalsService::class',
            'recalculate',
            'public function revokeCommercialItem(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $central
            );
        }
    }

    public function test_legacy_billed_request_stays_outside_subscription_totals(): void
    {
        $controller = $this->source(
            'app/Http/Controllers/Subscriber/SubscriberServiceActivationController.php'
        );

        $checkout = $this->source(
            'app/Services/Billing/StandaloneServiceCheckoutService.php'
        );

        $this->assertStringContainsString(
            'StandaloneServiceCheckoutService::class',
            $controller
        );

        foreach ([
            "'pending_payment'",
            "'entitlement_granted' => false",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $checkout
            );
        }

        foreach ([
            'SubscriptionTotalsService',
            'SubscriptionItem::query()->create',
            'activateCommercial(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $checkout
            );
        }
    }
}
