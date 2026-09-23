<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'data_transformation_bi_source_asset_mappings',
            function (Blueprint $table): void {
                /*
                 * Mapping semantics are pinned not only to the source
                 * artifact/profile but also to the exact canonical registry
                 * contract against which LAUDA created the mapping.
                 */
                $table->unsignedInteger(
                    'canonical_registry_version'
                )
                    ->default(1)
                    ->after(
                        'canonical_entity_key'
                    );

                $table->index(
                    [
                        'data_transformation_bi_source_asset_id',
                        'canonical_registry_version',
                        'status',
                    ],
                    'dtbi_sa_map_asset_registry_status_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'data_transformation_bi_source_asset_mappings',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'dtbi_sa_map_asset_registry_status_idx'
                );

                $table->dropColumn(
                    'canonical_registry_version'
                );
            }
        );
    }
};
