<?php

namespace Tests\Unit\Subscriber;

use App\Http\Controllers\Subscriber\SubscriberServiceActivationController;
use App\Models\Service;
use App\Models\Subscription;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class SubscriberServiceActivationPricingGuardContractTest extends TestCase
{
    private function invoke(
        string $method,
        Service $service,
        Subscription $subscription
    ): array|string {
        $controller =
            new SubscriberServiceActivationController();

        $reflection = new ReflectionMethod(
            $controller,
            $method
        );

        $reflection->setAccessible(
            true
        );

        return $reflection->invoke(
            $controller,
            $service,
            $subscription
        );
    }

    private function service(
        array $attributes
    ): Service {
        return new Service(
            array_merge(
                [
                    'active' =>
                        true,
                    'billable' =>
                        true,
                    'billing_model' =>
                        'flat',
                    'currency' =>
                        'DOP',
                ],
                $attributes
            )
        );
    }

    private function subscription(
        array $attributes
    ): Subscription {
        return new Subscription(
            array_merge(
                [
                    'billing_cycle' =>
                        'monthly',
                    'currency' =>
                        'DOP',
                ],
                $attributes
            )
        );
    }

    public function test_billed_billable_service_rejects_missing_monthly_price(): void
    {
        $service = $this->service([
            'monthly_price' => null,
            'yearly_price' => 1200,
        ]);

        $this->expectException(
            ValidationException::class
        );

        $this->invoke(
            'buildPriceSnapshot',
            $service,
            $this->subscription([])
        );
    }

    public function test_billed_billable_service_rejects_missing_yearly_price(): void
    {
        $service = $this->service([
            'monthly_price' => 100,
            'yearly_price' => null,
        ]);

        $this->expectException(
            ValidationException::class
        );

        $this->invoke(
            'buildPriceSnapshot',
            $service,
            $this->subscription([
                'billing_cycle' =>
                    'yearly',
            ])
        );
    }

    public function test_billed_snapshot_uses_real_dop_price(): void
    {
        $snapshot = $this->invoke(
            'buildPriceSnapshot',
            $this->service([
                'monthly_price' =>
                    1500,
                'yearly_price' =>
                    15000,
            ]),
            $this->subscription([])
        );

        $this->assertSame(
            'DOP',
            $snapshot['currency']
        );

        $this->assertSame(
            1500.0,
            $snapshot['unit_price']
        );

        $this->assertSame(
            1500.0,
            $snapshot['amount_due_now']
        );
    }

    public function test_currency_mismatch_is_rejected(): void
    {
        $this->expectException(
            ValidationException::class
        );

        $this->invoke(
            'resolveCommercialCurrency',
            $this->service([
                'monthly_price' =>
                    100,
                'yearly_price' =>
                    1000,
                'currency' =>
                    'USD',
            ]),
            $this->subscription([
                'currency' =>
                    'DOP',
            ])
        );
    }

    public function test_usage_keeps_zero_base_amount(): void
    {
        $snapshot = $this->invoke(
            'buildPriceSnapshot',
            $this->service([
                'billing_model' =>
                    'usage',
                'monthly_price' =>
                    null,
                'yearly_price' =>
                    null,
                'overage_unit_price' =>
                    5,
            ]),
            $this->subscription([])
        );

        $this->assertSame(
            'usage',
            $snapshot['billing_model']
        );

        $this->assertSame(
            'DOP',
            $snapshot['currency']
        );

        $this->assertSame(
            0,
            $snapshot['amount_due_now']
        );
    }

    public function test_trial_pricing_builder_no_longer_exists_in_legacy_controller(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Http/Controllers/Subscriber/SubscriberServiceActivationController.php'
        );

        $this->assertStringNotContainsString(
            'buildTrialItem',
            $source
        );

        $this->assertStringContainsString(
            'legacy_service_trial_activation_blocked_t360',
            $source
        );

        $this->assertStringContainsString(
            "'status' =>\n                            'pending_payment'",
            $source
        );
    }
}
