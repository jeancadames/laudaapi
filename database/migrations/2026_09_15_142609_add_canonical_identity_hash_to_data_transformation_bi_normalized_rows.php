<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'data_transformation_bi_normalized_rows',
            function (Blueprint $table): void {
                $table
                    ->char(
                        'canonical_identity_hash',
                        64
                    )
                    ->nullable()
                    ->after(
                        'identity_hash'
                    );

                $table->unique(
                    [
                        'data_transformation_bi_processing_run_id',
                        'domain_key',
                        'canonical_identity_hash',
                    ],
                    'dtbi_nr_run_domain_canonical_identity_uq'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'data_transformation_bi_normalized_rows',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'dtbi_nr_run_domain_canonical_identity_uq'
                );

                $table->dropColumn(
                    'canonical_identity_hash'
                );
            }
        );
    }
};
