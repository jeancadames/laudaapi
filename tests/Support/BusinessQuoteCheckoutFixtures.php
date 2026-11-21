<?php

namespace Tests\Support;

use App\Models\ServiceCommercialQuote;

final class BusinessQuoteCheckoutFixtures
{
    public static function schema(): void
    {
        BusinessQuoteFixtures::schema();
        foreach ([
            '2026_01_11_204200_create_contact_requests_table.php',
            '2026_01_11_204227_create_activation_requests_table.php',
            '2026_01_17_211335_create_activation_request_service_table.php',
            '2026_01_24_123138_invoice_items_table.php',
            '2026_08_27_150000_create_standalone_service_settlements_table.php',
            '2026_11_06_000005_create_service_quote_checkouts.php',
        ] as $migration) {
            (require base_path('database/migrations/'.$migration))->up();
        }
    }

    public static function seed(): ServiceCommercialQuote
    {
        config()->set('business_commercial.checkout_enabled', true);

        return BusinessQuoteFixtures::seed();
    }
}
