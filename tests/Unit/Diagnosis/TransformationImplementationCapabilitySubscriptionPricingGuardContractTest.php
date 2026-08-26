<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationCapabilitySubscriptionPricingGuardContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function capabilityService(): string
    {
        return file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        );
    }

    private function engine(): string
    {
        return file_get_contents(
            $this->root()
            .'/app/Services/Billing/ServicePricingEngine.php'
        );
    }

    private function compact(string $source): string
    {
        return preg_replace('/\s+/', ' ', trim($source)) ?? $source;
    }

    public function test_r2j_delegates_pricing_to_central_engine(): void
    {
        $service = $this->capabilityService();

        $this->assertStringContainsString(
            'use App\\Services\\Billing\\ServicePricingEngine;',
            $service
        );

        $this->assertStringContainsString(
            'ServicePricingEngine::class',
            $service
        );

        $this->assertStringContainsString(
            '->quote(',
            $service
        );
    }

    public function test_billable_recurring_service_requires_configured_cycle_price(): void
    {
        $engine = $this->compact($this->engine());

        $this->assertStringContainsString(
            "\$cycle === 'yearly' ? \$service->yearly_price : \$service->monthly_price",
            $engine
        );

        $this->assertStringContainsString(
            '(bool) $service->billable && $rawPrice === null',
            $engine
        );

        $this->assertStringContainsString(
            'El Service facturable no tiene precio {$cycle} configurado.',
            $engine
        );

        $this->assertStringNotContainsString(
            '(float) ($service->monthly_price ?? 0)',
            $engine
        );

        $this->assertStringNotContainsString(
            '(float) ($service->yearly_price ?? 0)',
            $engine
        );
    }

    public function test_zero_price_is_not_rejected_as_missing(): void
    {
        $engine = $this->compact($this->engine());

        $this->assertStringContainsString(
            '$rawPrice === null',
            $engine
        );

        $this->assertStringNotContainsString(
            '$rawPrice <= 0',
            $engine
        );
    }

    public function test_service_and_subscription_currency_must_match(): void
    {
        $engine = $this->compact($this->engine());

        $this->assertStringContainsString(
            '$serviceCurrency !== $subscriptionCurrency',
            $engine
        );

        $this->assertStringContainsString(
            'La moneda del Service debe coincidir con la moneda de la Subscription.',
            $engine
        );

        $this->assertStringContainsString(
            'No se permite conversión FX implícita.',
            $engine
        );
    }

    public function test_usage_semantics_remain_zero_base_with_overage_snapshot(): void
    {
        $engine = $this->compact($this->engine());

        $this->assertStringContainsString(
            '$model === self::MODEL_USAGE',
            $engine
        );

        $this->assertStringContainsString(
            "'unit_price' => 0.0",
            $engine
        );

        $this->assertStringContainsString(
            "'amount' => 0.0",
            $engine
        );

        $this->assertStringContainsString(
            "'overage_unit_price' =>",
            $engine
        );
    }

    public function test_per_user_is_owned_by_engine_not_r2j(): void
    {
        $engine = $this->compact($this->engine());
        $service = $this->compact($this->capabilityService());

        $this->assertStringContainsString(
            "MODEL_PER_USER = 'per_user'",
            $engine
        );

        $this->assertStringContainsString(
            '$price * $resolvedQuantity',
            $engine
        );

        $this->assertStringContainsString(
            "'subscriber_user.active'",
            $engine
        );

        $this->assertStringNotContainsString(
            '$price * $resolvedQuantity',
            $service
        );
    }

    public function test_seat_block_is_tier_based_without_legacy_fallback(): void
    {
        $engine = $this->compact($this->engine());

        $this->assertStringContainsString(
            'resolveSeatBlockTier(',
            $engine
        );

        $this->assertStringContainsString(
            'ServicePricingTier::query()',
            $engine
        );

        $this->assertStringNotContainsString(
            'legacy_seat_block',
            $engine
        );
    }
}
