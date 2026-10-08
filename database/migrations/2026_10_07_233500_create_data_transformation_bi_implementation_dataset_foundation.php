<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'data_transformation_bi_implementation_datasets',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('company_id');

                $table
                    ->foreign(
                        'company_id',
                        'dtbi_impl_ds_company_fk'
                    )
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();

                $table
                    ->foreignId(
                        'transformation_implementation_request_id'
                    );

                $table
                    ->foreign(
                        'transformation_implementation_request_id',
                        'dtbi_impl_ds_request_fk'
                    )
                    ->references('id')
                    ->on(
                        'transformation_implementation_requests'
                    )
                    ->cascadeOnDelete();

                $table
                    ->foreignId(
                        'data_transformation_bi_intake_session_id'
                    );

                $table
                    ->foreign(
                        'data_transformation_bi_intake_session_id',
                        'dtbi_impl_ds_session_fk'
                    )
                    ->references('id')
                    ->on(
                        'data_transformation_bi_intake_sessions'
                    )
                    ->cascadeOnDelete();

                $table
                    ->foreignId(
                        'data_transformation_bi_source_asset_id'
                    );

                $table
                    ->foreign(
                        'data_transformation_bi_source_asset_id',
                        'dtbi_impl_ds_asset_fk'
                    )
                    ->references('id')
                    ->on(
                        'data_transformation_bi_source_assets'
                    )
                    ->cascadeOnDelete();

                $table
                    ->foreignId(
                        'data_transformation_bi_source_asset_file_id'
                    );

                $table
                    ->foreign(
                        'data_transformation_bi_source_asset_file_id',
                        'dtbi_impl_ds_file_fk'
                    )
                    ->references('id')
                    ->on(
                        'data_transformation_bi_source_asset_files'
                    )
                    ->cascadeOnDelete();

                $table
                    ->foreignId(
                        'data_transformation_bi_source_asset_mapping_id'
                    );

                $table
                    ->foreign(
                        'data_transformation_bi_source_asset_mapping_id',
                        'dtbi_impl_ds_mapping_fk'
                    )
                    ->references('id')
                    ->on(
                        'data_transformation_bi_source_asset_mappings'
                    )
                    ->cascadeOnDelete();

                $table
                    ->foreignId(
                        'data_transformation_bi_canonical_registry_version_id'
                    );

                $table
                    ->foreign(
                        'data_transformation_bi_canonical_registry_version_id',
                        'dtbi_impl_ds_registry_fk'
                    )
                    ->references('id')
                    ->on(
                        'data_transformation_bi_canonical_registry_versions'
                    )
                    ->cascadeOnDelete();

                /*
                 * Immutable semantic/source pins copied from the validated
                 * mapping so the produced dataset can later prove exactly
                 * which contract generated it.
                 */
                $table->unsignedInteger(
                    'canonical_registry_version'
                );

                $table->string(
                    'canonical_entity_key',
                    100
                );

                $table->unsignedInteger(
                    'mapping_version'
                );

                $table->unsignedInteger(
                    'source_profile_version'
                );

                $table->unsignedInteger(
                    'source_sheet_index'
                );

                $table
                    ->string(
                        'source_sheet_name',
                        191
                    )
                    ->nullable();

                $table->char(
                    'source_sha256',
                    64
                );

                /*
                 * Foundation only.
                 *
                 * BUILDING / READY / FAILED describe dataset materialization,
                 * not TransformationImplementationExecution.
                 */
                $table
                    ->string(
                        'status',
                        32
                    )
                    ->default('building');

                $table
                    ->unsignedBigInteger('row_count')
                    ->default(0);

                $table
                    ->char(
                        'dataset_sha256',
                        64
                    )
                    ->nullable();

                $table
                    ->foreignId(
                        'materialized_by_user_id'
                    )
                    ->nullable();

                $table
                    ->foreign(
                        'materialized_by_user_id',
                        'dtbi_impl_ds_user_fk'
                    )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table
                    ->timestamp(
                        'materialized_at'
                    )
                    ->nullable();

                $table
                    ->string(
                        'failure_code',
                        100
                    )
                    ->nullable();

                $table
                    ->text(
                        'failure_message'
                    )
                    ->nullable();

                $table->timestamps();

                /*
                 * One immutable output for one exact validated semantic
                 * mapping. Re-running the same mapping must resolve the same
                 * dataset boundary instead of silently creating duplicates.
                 */
                $table->unique(
                    [
                        'data_transformation_bi_source_asset_mapping_id',
                    ],
                    'dtbi_impl_ds_mapping_uq'
                );

                $table->index(
                    [
                        'company_id',
                        'canonical_entity_key',
                        'status',
                    ],
                    'dtbi_impl_ds_company_entity_status_idx'
                );

                $table->index(
                    [
                        'transformation_implementation_request_id',
                        'status',
                    ],
                    'dtbi_impl_ds_request_status_idx'
                );
            }
        );

        Schema::create(
            'data_transformation_bi_implementation_rows',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId(
                        'data_transformation_bi_implementation_dataset_id'
                    );

                $table
                    ->foreign(
                        'data_transformation_bi_implementation_dataset_id',
                        'dtbi_impl_rows_dataset_fk'
                    )
                    ->references('id')
                    ->on(
                        'data_transformation_bi_implementation_datasets'
                    )
                    ->cascadeOnDelete();

                $table
                    ->foreignId('company_id');

                $table
                    ->foreign(
                        'company_id',
                        'dtbi_impl_rows_company_fk'
                    )
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();

                $table->string(
                    'canonical_entity_key',
                    100
                );

                /*
                 * Physical source row preserves traceability to the immutable
                 * client artifact without persisting an additional raw-row
                 * staging copy.
                 */
                $table->unsignedBigInteger(
                    'source_row_number'
                );

                $table->char(
                    'source_row_sha256',
                    64
                );

                /*
                 * Result after direct/default/unmapped projection and
                 * canonical type coercion.
                 *
                 * TRANSFORM is deliberately unsupported in the first
                 * materializer.
                 */
                $table->json(
                    'canonical_payload'
                );

                $table->char(
                    'canonical_payload_sha256',
                    64
                );

                $table
                    ->json(
                        'projection_meta'
                    )
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'data_transformation_bi_implementation_dataset_id',
                        'source_row_number',
                    ],
                    'dtbi_impl_rows_dataset_source_row_uq'
                );

                $table->index(
                    [
                        'company_id',
                        'canonical_entity_key',
                    ],
                    'dtbi_impl_rows_company_entity_idx'
                );

                $table->index(
                    'canonical_payload_sha256',
                    'dtbi_impl_rows_payload_sha_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'data_transformation_bi_implementation_rows'
        );

        Schema::dropIfExists(
            'data_transformation_bi_implementation_datasets'
        );
    }
};
