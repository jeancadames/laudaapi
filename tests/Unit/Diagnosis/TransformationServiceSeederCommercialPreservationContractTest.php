<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationServiceSeederCommercialPreservationContractTest extends TestCase
{
    private function source(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/database/seeders/ServiceSeeder.php'
        );
    }

    public function test_existing_service_preserves_commercial_pricing_on_reseed(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            '$existing = DB::table(\'services\')',
            $source
        );

        $this->assertStringContainsString(
            "\$payload['currency'] =",
            $source
        );

        $this->assertStringContainsString(
            '$existing->currency',
            $source
        );

        $this->assertStringContainsString(
            "\$payload['monthly_price'] =",
            $source
        );

        $this->assertStringContainsString(
            '$existing->monthly_price',
            $source
        );

        $this->assertStringContainsString(
            "\$payload['yearly_price'] =",
            $source
        );

        $this->assertStringContainsString(
            '$existing->yearly_price',
            $source
        );
    }

    public function test_new_service_still_receives_catalog_defaults(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            "'currency'          => 'USD'",
            $source
        );

        $this->assertStringContainsString(
            "'monthly_price'     => null",
            $source
        );

        $this->assertStringContainsString(
            "'yearly_price'      => null",
            $source
        );

        $this->assertStringContainsString(
            "DB::table('services')\n                        ->insert(\$payload)",
            $source
        );
    }

    public function test_digital_presence_is_catalogued_without_price_or_fake_href(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            "'service_key'       => 'digital_presence'",
            $source
        );

        $this->assertStringContainsString(
            "'slug'              => 'digital-presence'",
            $source
        );

        $this->assertStringContainsString(
            "'category'          => 'transformation'",
            $source
        );

        $this->assertStringContainsString(
            "'monthly_price'     => null",
            $source
        );

        $this->assertStringContainsString(
            "'yearly_price'      => null",
            $source
        );

        $this->assertStringContainsString(
            "'href'              => null",
            $source
        );

        $this->assertStringContainsString(
            "'launchable' => false",
            $source
        );
    }
}
