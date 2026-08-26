<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'subscribers',
        'subscriptions',
        'subscription_items',
        'services',
        'invoices',
        'payments',
        'payment_methods',
        'payment_transactions',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (
                ! Schema::hasTable($table)
                || ! Schema::hasColumn($table, 'currency')
            ) {
                continue;
            }

            DB::statement(
                "ALTER TABLE `{$table}` "
                ."ALTER COLUMN `currency` SET DEFAULT 'DOP'"
            );
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (
                ! Schema::hasTable($table)
                || ! Schema::hasColumn($table, 'currency')
            ) {
                continue;
            }

            DB::statement(
                "ALTER TABLE `{$table}` "
                ."ALTER COLUMN `currency` SET DEFAULT 'USD'"
            );
        }
    }
};
