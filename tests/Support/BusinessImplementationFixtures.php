<?php

namespace Tests\Support;

final class BusinessImplementationFixtures
{
    public static function schema(): void
    {
        BusinessQuoteCheckoutFixtures::schema();
        (require base_path('database/migrations/2026_11_06_000006_create_business_implementation_orders.php'))->up();
    }
}
