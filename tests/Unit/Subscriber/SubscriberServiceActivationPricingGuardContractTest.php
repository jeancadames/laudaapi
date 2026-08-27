<?php

namespace Tests\Unit\Subscriber;

use PHPUnit\Framework\TestCase;

class SubscriberServiceActivationPricingGuardContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_activation_delegates_pricing_to_checkout_service(): void
    {
        $controller = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Subscriber/SubscriberServiceActivationController.php'
        );

        $checkout = file_get_contents(
            $this->root()
            .'/app/Services/Billing/StandaloneServiceCheckoutService.php'
        );

        $this->assertStringContainsString(
            'StandaloneServiceCheckoutService::class',
            $controller
        );

        $this->assertStringContainsString(
            'ServicePricingEngine::class',
            $checkout
        );

        $this->assertStringContainsString(
            '$pricingProbe = new Subscription([',
            $checkout
        );

        $this->assertStringContainsString(')->quote(', $checkout);
    }

    public function test_checkout_requires_billable_price_and_matching_currency(): void
    {
        $checkout = file_get_contents(
            $this->root()
            .'/app/Services/Billing/StandaloneServiceCheckoutService.php'
        );

        foreach ([
            'if (! $service->billable)',
            'if ($amountDue <= 0)',
            'No se permite FX implícito.',
            "'monthly'",
            "'yearly'",
        ] as $required) {
            $this->assertStringContainsString($required, $checkout);
        }
    }

    public function test_social_cannot_bypass_commercial_readiness(): void
    {
        $checkout = file_get_contents(
            $this->root()
            .'/app/Services/Billing/StandaloneServiceCheckoutService.php'
        );

        $this->assertStringNotContainsString("'social'", $checkout);
        $this->assertStringNotContainsString(
            "service_key === 'social'",
            $checkout
        );
    }
}
