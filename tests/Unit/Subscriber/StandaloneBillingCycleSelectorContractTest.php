<?php

namespace Tests\Unit\Subscriber;

use PHPUnit\Framework\TestCase;

class StandaloneBillingCycleSelectorContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function read(string $relative): string
    {
        return file_get_contents(
            $this->root().'/'.$relative
        );
    }

    public function test_checkout_preview_is_canonical_and_non_persistent(): void
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
            'ServicePricingEngine::class',
            '$pricingProbe = new Subscription([',
            "'monthly'",
            "'yearly'",
            "'subscription_locked'",
            "'active_subscription_id'",
            "'amount_due'",
            'La Subscription activa existente',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $preview
            );
        }

        $checkout = explode(
            'public function checkout(',
            $source,
            2
        )[1];

        $this->assertStringContainsString(
            '$this->previewQuote(',
            $checkout
        );
    }

    public function test_billed_activation_requires_explicit_cycle(): void
    {
        $source = $this->read(
            'app/Http/Controllers/Subscriber/SubscriberServiceActivationController.php'
        );

        foreach ([
            "'required_if:mode,billed'",
            "'in:monthly,yearly'",
            'StandaloneServiceCheckoutService::class',
            ')->checkout(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        $this->assertStringNotContainsString(
            "\$data['billing_cycle'] ?? 'monthly'",
            $source
        );
    }

    public function test_my_services_payload_uses_backend_quotes(): void
    {
        $source = $this->read(
            'app/Http/Controllers/Subscriber/SubscriberMyServicesController.php'
        );

        foreach ([
            "'billing_options' =>",
            'private function billingOptions(',
            'StandaloneServiceCheckoutService::class',
            ')->previewQuote(',
            "'monthly' => 'Mensual'",
            "'yearly' => 'Anual'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_ui_selects_cycle_and_posts_it(): void
    {
        $source = $this->read(
            'resources/js/pages/Subscriber/Services/My.vue'
        );

        foreach ([
            "type BillingCycle = 'monthly' | 'yearly'",
            'billingCycleByService',
            'selectedBillingCycle(r)',
            'Ciclo de facturación',
            'Mensual',
            'Anual',
            'Solicitar activación',
            "mode: 'billed'",
            'billing_cycle: billingCycle',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        foreach ([
            'Activar (Trial)',
            'Activar (Pago)',
            "mode: 'trial'",
            "billing_cycle: 'monthly'",
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_ui_does_not_price_from_raw_catalog_fields(): void
    {
        $source = $this->read(
            'resources/js/pages/Subscriber/Services/My.vue'
        );

        $selector = explode(
            "type BillingCycle = 'monthly' | 'yearly'",
            $source,
            2
        )[1];

        foreach ([
            '.monthly_price',
            '.yearly_price',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $selector
            );
        }
    }

    public function test_active_subscription_locks_cycle(): void
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
            "'status',",
            "'active'",
            '$activeCycle !== $billingCycle',
            "'subscription_locked'",
            "'active_subscription_id'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $preview
            );
        }
    }

    public function test_catalog_request_has_no_subscription_pre_gate(): void
    {
        $controller = $this->read(
            'app/Http/Controllers/Subscriber/SubscriberServiceCatalogController.php'
        );

        $category = explode(
            'public function category(',
            $controller,
            2
        )[1];

        $category = explode(
            'private function canSelectServicesFromSubscription(',
            $category,
            2
        )[0];

        $this->assertStringContainsString(
            '$activationRequest !== null',
            $category
        );

        $this->assertStringNotContainsString(
            '$this->canSelectServicesFromSubscription($sub)',
            $category
        );

        $requestController = $this->read(
            'app/Http/Controllers/Subscriber/SubscriberServiceRequestController.php'
        );

        $this->assertStringContainsString(
            'No pre-gate Subscription',
            $requestController
        );
    }

    public function test_social_is_not_special_cased(): void
    {
        foreach ([
            'app/Services/Billing/StandaloneServiceCheckoutService.php',
            'app/Http/Controllers/Subscriber/SubscriberMyServicesController.php',
            'resources/js/pages/Subscriber/Services/My.vue',
        ] as $path) {
            $source = $this->read($path);

            $this->assertStringNotContainsString(
                "'social'",
                $source
            );

            $this->assertStringNotContainsString(
                '"social"',
                $source
            );
        }
    }
}
