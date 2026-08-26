<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class LaudaCommercialCurrencyDefaultsDopContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_new_migration_sets_commercial_defaults_to_dop(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/database/migrations/2026_08_24_170000_normalize_lauda_commercial_currency_defaults_to_dop.php'
        );

        foreach ([
            'subscribers',
            'subscriptions',
            'subscription_items',
            'services',
            'invoices',
            'payments',
            'payment_methods',
            'payment_transactions',
        ] as $table) {
            $this->assertStringContainsString(
                "'{$table}'",
                $source
            );
        }

        $this->assertStringContainsString(
            "SET DEFAULT 'DOP'",
            $source
        );

        $this->assertStringContainsString(
            "SET DEFAULT 'USD'",
            $source
        );
    }

    public function test_migration_changes_defaults_not_existing_rows(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/database/migrations/2026_08_24_170000_normalize_lauda_commercial_currency_defaults_to_dop.php'
        );

        $this->assertStringNotContainsString(
            '->update(',
            $source
        );

        $this->assertStringNotContainsString(
            'UPDATE ',
            $source
        );
    }

    public function test_laudaone_fallback_is_dop(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Provisioning/LaudaOneProvisioner.php'
        );

        $this->assertStringContainsString(
            "\$allowed = ['DOP', 'USD', 'EUR'];",
            $source
        );

        $this->assertMatchesRegularExpression(
            "/\\?\\s*\\(string\\)\\s*\\\$currency\\s*:\\s*'DOP'\\s*;/s",
            $source
        );
    }

    public function test_multicurrency_support_is_not_removed(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Provisioning/LaudaOneProvisioner.php'
        );

        $this->assertStringContainsString(
            "['DOP', 'USD', 'EUR']",
            $source
        );
    }
}
