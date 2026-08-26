<?php

namespace Tests\Unit\Billing;

use App\Models\Service;
use App\Models\Subscription;
use App\Services\Billing\ServicePricingEngine;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ServicePricingEngineContractTest extends TestCase
{
    private function subscription(
        string $cycle = 'monthly',
        string $currency = 'DOP'
    ): Subscription {
        return new Subscription([
            'subscriber_id' => 1,
            'billing_cycle' => $cycle,
            'currency' => $currency,
        ]);
    }

    private function service(
        string $model,
        ?float $monthly = 100,
        ?float $yearly = 1000
    ): Service {
        return new Service([
            'title' => 'Fixture',
            'slug' => 'fixture',
            'billing_model' => $model,
            'billable' => true,
            'currency' => 'DOP',
            'monthly_price' => $monthly,
            'yearly_price' => $yearly,
            'active' => true,
        ]);
    }

    public function test_flat_remains_fixed(): void
    {
        $quote = app(ServicePricingEngine::class)
            ->quote(
                $this->service('flat', 500, 5000),
                $this->subscription(),
                9
            );

        $this->assertSame('flat', $quote['billing_model']);
        $this->assertSame(1, $quote['quantity']);
        $this->assertSame(500.0, $quote['unit_price']);
        $this->assertSame(500.0, $quote['amount']);
    }

    public function test_per_user_multiplies_unit_price_by_quantity(): void
    {
        $quote = app(ServicePricingEngine::class)
            ->quote(
                $this->service('per_user', 250, 2500),
                $this->subscription(),
                4
            );

        $this->assertSame('per_user', $quote['billing_model']);
        $this->assertSame(4, $quote['quantity']);
        $this->assertSame('explicit', $quote['quantity_source']);
        $this->assertSame(250.0, $quote['unit_price']);
        $this->assertSame(1000.0, $quote['amount']);
        $this->assertSame('usuario', $quote['unit_name']);
    }

    public function test_per_user_rejects_zero_quantity(): void
    {
        $this->expectException(
            ValidationException::class
        );

        app(ServicePricingEngine::class)
            ->quote(
                $this->service('per_user'),
                $this->subscription(),
                0
            );
    }

    public function test_seat_block_is_tier_based_in_6b(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Services/Billing/ServicePricingEngine.php'
        );

        $this->assertStringContainsString(
            'ServicePricingTier::query()',
            $source
        );

        $this->assertStringContainsString(
            'resolveSeatBlockTier(',
            $source
        );

        $this->assertStringContainsString(
            "'tier_id' =>",
            $source
        );

        $this->assertStringContainsString(
            "'tier_min_quantity' =>",
            $source
        );

        $this->assertStringContainsString(
            "'tier_max_quantity' =>",
            $source
        );

        $this->assertStringNotContainsString(
            'legacy_seat_block',
            $source
        );
    }

    public function test_usage_preserves_zero_base_amount(): void
    {
        $service = $this->service(
            'usage',
            null,
            null
        );

        $service->included_units = 100;
        $service->unit_name = 'documento';
        $service->overage_unit_price = 5;

        $quote = app(ServicePricingEngine::class)
            ->quote(
                $service,
                $this->subscription()
            );

        $this->assertSame('usage', $quote['billing_model']);
        $this->assertSame(0.0, $quote['unit_price']);
        $this->assertSame(0.0, $quote['amount']);
        $this->assertSame(100, $quote['included_units']);
        $this->assertSame(
            5.0,
            $quote['overage_unit_price']
        );
    }

    public function test_yearly_uses_yearly_price(): void
    {
        $quote = app(ServicePricingEngine::class)
            ->quote(
                $this->service('per_user', 100, 1000),
                $this->subscription('yearly'),
                3
            );

        $this->assertSame('yearly', $quote['cycle']);
        $this->assertSame(1000.0, $quote['unit_price']);
        $this->assertSame(3000.0, $quote['amount']);
    }

    public function test_currency_mismatch_is_blocked(): void
    {
        $this->expectException(
            ValidationException::class
        );

        app(ServicePricingEngine::class)
            ->quote(
                $this->service('flat'),
                $this->subscription('monthly', 'USD')
            );
    }
}
