<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class LaudaCommercialCatalogDopContractTest extends TestCase
{
    private function seeder(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/database/seeders/ServiceSeeder.php'
        );
    }

    private function pricing(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Services/Diagnosis/TransformationServiceCommercialPricingService.php'
        );
    }

    public function test_service_catalog_default_currency_is_dop(): void
    {
        $source = $this->seeder();

        $this->assertStringContainsString(
            "'currency'          => 'DOP'",
            $source
        );
    }

    public function test_ecf_legacy_usd_price_is_not_relabelled(): void
    {
        $source = $this->seeder();

        $this->assertStringContainsString(
            "'service_key'       => 'api_facturacion_electronica'",
            $source
        );

        $this->assertStringContainsString(
            "'currency'          => 'USD'",
            $source
        );

        $this->assertStringContainsString(
            "'monthly_price'     => 29.00",
            $source
        );

        $this->assertStringContainsString(
            "'yearly_price'      => 290.00",
            $source
        );
    }

    public function test_transformation_commercial_pricing_accepts_only_dop(): void
    {
        $source = $this->pricing();

        $this->assertStringContainsString(
            "\$currency !== 'DOP'",
            $source
        );

        $this->assertStringContainsString(
            'Los Services comerciales de Transformación 360 se configuran únicamente en DOP.',
            $source
        );
    }

    public function test_no_fx_conversion_is_added(): void
    {
        $source = $this->pricing();

        foreach ([
            'exchange_rate',
            'exchangeRate',
            'convertCurrency',
            'fxRate',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }
}
