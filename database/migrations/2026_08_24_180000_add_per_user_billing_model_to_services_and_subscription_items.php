<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE services
             MODIFY billing_model
             ENUM('flat','per_user','seat_block','usage')
             NOT NULL DEFAULT 'flat'"
        );

        DB::statement(
            "ALTER TABLE subscription_items
             MODIFY billing_model
             ENUM('flat','per_user','seat_block','usage')
             NOT NULL DEFAULT 'flat'"
        );
    }

    public function down(): void
    {
        if (
            DB::table('services')
                ->where('billing_model', 'per_user')
                ->exists()
            || DB::table('subscription_items')
                ->where('billing_model', 'per_user')
                ->exists()
        ) {
            throw new RuntimeException(
                'No se puede revertir mientras existan registros per_user.'
            );
        }

        DB::statement(
            "ALTER TABLE services
             MODIFY billing_model
             ENUM('flat','seat_block','usage')
             NOT NULL DEFAULT 'flat'"
        );

        DB::statement(
            "ALTER TABLE subscription_items
             MODIFY billing_model
             ENUM('flat','seat_block','usage')
             NOT NULL DEFAULT 'flat'"
        );
    }
};
