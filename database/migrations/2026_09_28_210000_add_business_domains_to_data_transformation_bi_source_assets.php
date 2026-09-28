<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'data_transformation_bi_source_assets',
            function (Blueprint $table): void {
                $table
                    ->json('business_domains')
                    ->nullable()
                    ->after('owner');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'data_transformation_bi_source_assets',
            function (Blueprint $table): void {
                $table->dropColumn(
                    'business_domains'
                );
            }
        );
    }
};
