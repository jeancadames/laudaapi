<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * One SourceAsset may feed one or many canonical entities.
         *
         * A mapping is pinned to:
         * - the logical SourceAsset;
         * - the current SourceAssetFile;
         * - the exact SHA-256 profiled by LAUDA;
         * - one native source sheet.
         *
         * canonical_entity_key is intentionally independent from the
         * legacy seven-domain Standard Intake contract.
         */
        Schema::create(
            'data_transformation_bi_source_asset_mappings',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'data_transformation_bi_source_asset_id'
                );

                $table->unsignedBigInteger(
                    'data_transformation_bi_source_asset_file_id'
                );

                $table->unsignedBigInteger(
                    'data_transformation_bi_intake_session_id'
                );

                $table->unsignedBigInteger(
                    'company_id'
                );

                $table->string(
                    'canonical_entity_key',
                    100
                );

                /*
                 * SourceAssetFile is the current physical artifact and may be
                 * replaced in place. The SHA therefore pins this mapping to
                 * the exact bytes that were profiled.
                 */
                $table->char(
                    'source_sha256',
                    64
                );

                $table->unsignedInteger(
                    'source_profile_version'
                )->default(1);

                /*
                 * Sheet 0 is also the natural sheet for CSV.
                 *
                 * Keeping sheet identity at mapping level allows one workbook
                 * to feed multiple canonical entities without assuming that a
                 * workbook itself equals one canonical entity.
                 */
                $table->unsignedInteger(
                    'source_sheet_index'
                )->default(0);

                $table->string(
                    'source_sheet_name',
                    191
                )->nullable();

                $table->unsignedInteger(
                    'mapping_version'
                )->default(1);

                $table->string(
                    'status',
                    32
                )->default('draft');

                $table->text(
                    'notes'
                )->nullable();

                $table->unsignedBigInteger(
                    'created_by_user_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'updated_by_user_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'validated_by_user_id'
                )->nullable();

                $table->timestamp(
                    'validated_at'
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'data_transformation_bi_source_asset_id',
                    'dtbi_sa_map_asset_fk'
                )
                    ->references('id')
                    ->on('data_transformation_bi_source_assets')
                    ->cascadeOnDelete();

                $table->foreign(
                    'data_transformation_bi_source_asset_file_id',
                    'dtbi_sa_map_file_fk'
                )
                    ->references('id')
                    ->on('data_transformation_bi_source_asset_files')
                    ->cascadeOnDelete();

                $table->foreign(
                    'data_transformation_bi_intake_session_id',
                    'dtbi_sa_map_session_fk'
                )
                    ->references('id')
                    ->on('data_transformation_bi_intake_sessions')
                    ->cascadeOnDelete();

                $table->foreign(
                    'company_id',
                    'dtbi_sa_map_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();

                $table->foreign(
                    'created_by_user_id',
                    'dtbi_sa_map_created_by_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign(
                    'updated_by_user_id',
                    'dtbi_sa_map_updated_by_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign(
                    'validated_by_user_id',
                    'dtbi_sa_map_validated_by_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                /*
                 * Mapping versions are scoped to source + canonical entity +
                 * source sheet.
                 */
                $table->unique(
                    [
                        'data_transformation_bi_source_asset_id',
                        'canonical_entity_key',
                        'source_sheet_index',
                        'mapping_version',
                    ],
                    'dtbi_sa_map_entity_sheet_ver_uq'
                );

                /*
                 * Artifact identity is searchable but not unique.
                 *
                 * LAUDA may create multiple semantic mapping versions over
                 * the exact same profiled source artifact.
                 */
                $table->index(
                    [
                        'data_transformation_bi_source_asset_id',
                        'canonical_entity_key',
                        'source_sheet_index',
                        'source_sha256',
                    ],
                    'dtbi_sa_map_entity_sheet_sha_idx'
                );

                $table->index(
                    [
                        'company_id',
                        'status',
                    ],
                    'dtbi_sa_map_company_status_idx'
                );

                $table->index(
                    [
                        'data_transformation_bi_source_asset_id',
                        'canonical_entity_key',
                        'status',
                    ],
                    'dtbi_sa_map_asset_entity_status_idx'
                );

                $table->index(
                    [
                        'data_transformation_bi_source_asset_file_id',
                        'source_sha256',
                    ],
                    'dtbi_sa_map_file_sha_idx'
                );
            }
        );

        /*
         * Target-centric mapping decisions.
         *
         * There is one decision per canonical target field inside one
         * SourceAssetMapping. Source column metadata is copied only as
         * structural metadata from the technical profile; no client values
         * or samples are persisted here.
         */
        Schema::create(
            'data_transformation_bi_source_asset_field_mappings',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'data_transformation_bi_source_asset_mapping_id'
                );

                $table->unsignedBigInteger(
                    'company_id'
                );

                $table->string(
                    'canonical_field_key',
                    100
                );

                /*
                 * Null for default/unmapped decisions.
                 */
                $table->string(
                    'source_column_key',
                    191
                )->nullable();

                $table->unsignedInteger(
                    'source_column_index'
                )->nullable();

                /*
                 * Header snapshot is structural metadata only.
                 */
                $table->text(
                    'source_header'
                )->nullable();

                /*
                 * direct
                 * default
                 * transform
                 * unmapped
                 */
                $table->string(
                    'mapping_type',
                    32
                )->default('direct');

                $table->text(
                    'default_value'
                )->nullable();

                /*
                 * Controlled transformation catalog key.
                 * Never executable PHP/SQL.
                 */
                $table->string(
                    'transformation_key',
                    100
                )->nullable();

                $table->json(
                    'configuration_snapshot'
                )->nullable();

                $table->string(
                    'status',
                    32
                )->default('draft');

                $table->text(
                    'notes'
                )->nullable();

                $table->unsignedBigInteger(
                    'created_by_user_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'updated_by_user_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'validated_by_user_id'
                )->nullable();

                $table->timestamp(
                    'validated_at'
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'data_transformation_bi_source_asset_mapping_id',
                    'dtbi_sa_fmap_mapping_fk'
                )
                    ->references('id')
                    ->on('data_transformation_bi_source_asset_mappings')
                    ->cascadeOnDelete();

                $table->foreign(
                    'company_id',
                    'dtbi_sa_fmap_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();

                $table->foreign(
                    'created_by_user_id',
                    'dtbi_sa_fmap_created_by_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign(
                    'updated_by_user_id',
                    'dtbi_sa_fmap_updated_by_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign(
                    'validated_by_user_id',
                    'dtbi_sa_fmap_validated_by_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                /*
                 * One decision per canonical target field.
                 */
                $table->unique(
                    [
                        'data_transformation_bi_source_asset_mapping_id',
                        'canonical_field_key',
                    ],
                    'dtbi_sa_fmap_target_uq'
                );

                $table->index(
                    [
                        'company_id',
                        'status',
                    ],
                    'dtbi_sa_fmap_company_status_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'data_transformation_bi_source_asset_field_mappings'
        );

        Schema::dropIfExists(
            'data_transformation_bi_source_asset_mappings'
        );
    }
};
