<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transformation_implementation_commercial_engagements', function (Blueprint $table) {
            // NULL means an agreement created by the legacy commercial contract.
            $table->unsignedTinyInteger('contract_scope_schema_version')->nullable();
            $table->json('contracted_scope_snapshot')->nullable();
            $table->json('contracted_deliverables_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transformation_implementation_commercial_engagements', function (Blueprint $table) {
            $table->dropColumn([
                'contract_scope_schema_version',
                'contracted_scope_snapshot',
                'contracted_deliverables_snapshot',
            ]);
        });
    }
};
