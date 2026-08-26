<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationServiceCommercialPricingContractTest extends TestCase
{
    private function source(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Services/Diagnosis/TransformationServiceCommercialPricingService.php'
        );
    }

    public function test_only_transformation_service_keys_are_allowed(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            'TransformationServiceCapabilityCatalog::all()',
            $source
        );

        $this->assertStringContainsString(
            "->pluck('service_key')",
            $source
        );

        $this->assertStringContainsString(
            'allowedServiceKeys()',
            $source
        );
    }

    public function test_preview_is_separate_from_apply(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            'public function preview(',
            $source
        );

        $this->assertStringContainsString(
            'public function apply(',
            $source
        );

        $this->assertStringContainsString(
            '$preview = $this->preview(',
            $source
        );
    }

    public function test_currency_requires_explicit_iso_code(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            "preg_match('/^[A-Z]{3}$/', \$currency)",
            $source
        );

        $this->assertStringNotContainsString(
            'exchangeRate',
            $source
        );

        $this->assertStringNotContainsString(
            'convertCurrency',
            $source
        );
    }

    public function test_monthly_and_yearly_prices_are_explicit(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            "'monthly_price'",
            $source
        );

        $this->assertStringContainsString(
            "'yearly_price'",
            $source
        );

        $this->assertStringContainsString(
            'El precio debe ser numérico y explícito.',
            $source
        );

        $this->assertStringContainsString(
            'El precio no puede ser negativo.',
            $source
        );
    }

    public function test_apply_updates_only_commercial_fields(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            "'currency' =>",
            $source
        );

        $this->assertStringContainsString(
            "'monthly_price' =>",
            $source
        );

        $this->assertStringContainsString(
            "'yearly_price' =>",
            $source
        );

        foreach ([
            "'active' =>",
            "'service_key' =>",
            "'slug' =>",
            "'parent_id' =>",
            "'billing_model' =>",
        ] as $forbidden) {
            $apply = explode(
                'public function apply(',
                $source,
                2
            )[1];

            $apply = explode(
                'public function readiness(',
                $apply,
                2
            )[0];

            $this->assertStringNotContainsString(
                $forbidden,
                $apply
            );
        }
    }

    public function test_service_pricing_does_not_create_mappings_or_subscriptions(): void
    {
        $source = $this->source();

        foreach ([
            'TransformationImplementationCapabilityServiceMapping::create',
            'Subscription::create',
            'SubscriptionItem::create',
            'Subscription::query()->create',
            'SubscriptionItem::query()->create',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }
}
