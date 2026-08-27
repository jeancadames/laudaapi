<?php

namespace Tests\Unit\Subscriber;

use PHPUnit\Framework\TestCase;

class LegacyServiceActivationHardeningContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function controller(): string
    {
        return file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Subscriber/SubscriberServiceActivationController.php'
        );
    }

    public function test_trial_mode_remains_blocked(): void
    {
        $source = $this->controller();

        foreach ([
            "if (\$mode === 'trial')",
            'legacy_service_trial_activation_blocked_t360',
            'direct_trial_activation_disabled',
        ] as $required) {
            $this->assertStringContainsString($required, $source);
        }

        foreach ([
            'buildTrialItem',
            'LaudaOneProvisioner',
            'SubscriptionTotalsService',
            'SubscriptionItem::query()->create',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }
    }

    public function test_billed_mode_is_checkout_but_not_entitlement(): void
    {
        $controller = $this->controller();

        $checkout = file_get_contents(
            $this->root()
            .'/app/Services/Billing/StandaloneServiceCheckoutService.php'
        );

        foreach ([
            'StandaloneServiceCheckoutService::class',
            ')->checkout(',
            "'billing_cycle'",
        ] as $required) {
            $this->assertStringContainsString($required, $controller);
        }

        foreach ([
            "'pending_payment'",
            "'entitlement_granted' => false",
            'Invoice::query()->create',
            'InvoiceItem::query()->create',
            'StandaloneServiceSettlementService::class',
        ] as $required) {
            $this->assertStringContainsString($required, $checkout);
        }

        foreach ([
            'Subscription::query()->create',
            'SubscriptionItem::query()->create',
            'activateCommercial(',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $checkout);
        }
    }

    public function test_existing_subscription_is_no_longer_required_for_checkout(): void
    {
        $source = $this->controller();

        $this->assertStringNotContainsString(
            'no_eligible_existing_subscription',
            $source
        );

        $this->assertStringNotContainsString(
            'Subscription::query()',
            $source
        );

        $this->assertStringContainsString(
            'ServiceEntitlementPolicy::SUBSCRIPTION_STATUSES',
            $source
        );
    }

    public function test_real_entitlement_remains_owned_by_central_owner(): void
    {
        $settlement = file_get_contents(
            $this->root()
            .'/app/Services/Billing/StandaloneServiceSettlementService.php'
        );

        $central = file_get_contents(
            $this->root()
            .'/app/Services/Entitlements/CentralEntitlementActivationService.php'
        );

        foreach ([
            'SOURCE_STANDALONE_SETTLEMENT',
            '->activateCommercial(',
        ] as $required) {
            $this->assertStringContainsString($required, $settlement);
        }

        foreach ([
            'Subscription::query()->create',
            'SubscriptionItem::query()',
        ] as $required) {
            $this->assertStringContainsString($required, $central);
        }
    }

    public function test_subscriber_ui_still_has_no_direct_trial_activation(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/resources/js/pages/Subscriber/Services/My.vue'
        );

        $this->assertStringNotContainsString(
            'Activar (Trial)',
            $source
        );

        $this->assertStringNotContainsString(
            "activateRequested(Number(r.service_id), 'trial')",
            $source
        );

        $this->assertStringContainsString(
            'Solicitar activación',
            $source
        );
    }
}
