<?php

namespace Tests\Unit\Subscriber;

use PHPUnit\Framework\TestCase;

class StandaloneServiceRequestWithoutSubscriptionContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function source(string $relative): string
    {
        return file_get_contents($this->root().'/'.$relative);
    }

    public function test_request_and_checkout_routes_do_not_require_subscription(): void
    {
        $routes = $this->source('routes/subscriber.php');

        foreach ([
            "Route::post('/request'",
            "Route::post('/activate'",
        ] as $anchor) {
            $start = strpos($routes, $anchor);
            $this->assertNotFalse($start);

            $segment = substr($routes, $start, 300);

            $this->assertStringNotContainsString(
                "->middleware('subscription.active')",
                $segment
            );
        }

        $cancel = strpos($routes, "Route::post('/cancel'");
        $this->assertNotFalse($cancel);

        $this->assertStringContainsString(
            "->middleware('subscription.active')",
            substr($routes, $cancel, 300)
        );
    }

    public function test_request_controller_has_no_subscription_pre_gate(): void
    {
        $source = $this->source(
            'app/Http/Controllers/Subscriber/SubscriberServiceRequestController.php'
        );

        foreach ([
            'ServiceEntitlementPolicy::ITEM_STATUSES',
            'ServiceEntitlementPolicy::SUBSCRIPTION_STATUSES',
            '->whereHas(',
            "'subscription'",
            "'pending_payment'",
        ] as $required) {
            $this->assertStringContainsString($required, $source);
        }

        foreach ([
            'Subscription::query()',
            'subscriptionAllowsSelection(',
            'subscription_not_allowed',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }
    }

    public function test_activation_delegates_checkout_without_subscription_pre_gate(): void
    {
        $source = $this->source(
            'app/Http/Controllers/Subscriber/SubscriberServiceActivationController.php'
        );

        foreach ([
            'StandaloneServiceCheckoutService::class',
            ')->checkout(',
            "'billing_cycle'",
            "'in:monthly,yearly'",
            'ServiceEntitlementPolicy::ITEM_STATUSES',
            'ServiceEntitlementPolicy::SUBSCRIPTION_STATUSES',
        ] as $required) {
            $this->assertStringContainsString($required, $source);
        }

        foreach ([
            'Subscription::query()',
            'no_eligible_existing_subscription',
            'buildPriceSnapshot(',
            'SubscriptionItem::query()->create',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }
    }
}
