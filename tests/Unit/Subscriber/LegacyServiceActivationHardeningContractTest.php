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

    public function test_trial_mode_is_blocked_before_legacy_mutations(): void
    {
        $source = $this->controller();

        foreach ([
            "if (\$mode === 'trial')",
            'legacy_service_trial_activation_blocked_t360',
            'service_activation_requires_lauda360_golive',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        foreach ([
            'buildTrialItem',
            'LaudaOneProvisioner',
            '->provision(',
            'SubscriptionTotalsService',
            '$item->forceFill(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_billed_mode_is_request_only_and_grants_no_entitlement(): void
    {
        $source = $this->controller();

        foreach ([
            "'pending_payment'",
            "'activation_mode' =>",
            "'billed'",
            "'payment_required' =>",
            "'entitlement_granted' =>",
            'false',
            'buildPriceSnapshot(',
            'ServicePricingEngine::class',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        $this->assertStringNotContainsString(
            'SubscriptionItem::query()->create',
            $source
        );
    }

    public function test_existing_subscription_must_be_eligible(): void
    {
        $source = $this->controller();

        $this->assertStringContainsString(
            'ServiceEntitlementPolicy::SUBSCRIPTION_STATUSES',
            $source
        );

        $this->assertStringContainsString(
            'no_eligible_existing_subscription',
            $source
        );
    }

    public function test_real_item_activation_remains_owned_by_r2j_after_live(): void
    {
        $r2j = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        );

        foreach ([
            'activateFromGoLive(',
            'STATUS_LIVE',
            'buildActiveItem(',
            '$existingItem->forceFill(',
            "'status' => 'active'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $r2j
            );
        }
    }

    public function test_subscriber_ui_no_longer_offers_direct_trial_activation(): void
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
