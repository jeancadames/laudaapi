<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Current physical data artifact for one logical SourceAsset.
         *
         * SourceAsset describes WHAT the client's source is.
         * SourceAssetFile describes the current CSV/XLSX actually
         * delivered to LAUDA.
         *
         * No client raw rows are copied into this table.
         */
        Schema::create(
            'data_transformation_bi_source_asset_files',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'data_transformation_bi_source_asset_id'
                );

                $table->unsignedBigInteger(
                    'company_id'
                );

                /*
                 * One current physical artifact per logical source.
                 *
                 * Replacing a delivery updates this record while
                 * preserving the SourceAsset identity.
                 */
                $table->string(
                    'status',
                    32
                )->default('uploaded');

                /*
                 * Private storage metadata.
                 *
                 * source_path must never be exposed through HTTP state.
                 */
                $table->string(
                    'source_disk',
                    64
                );

                $table->text(
                    'source_path'
                );

                $table->string(
                    'original_filename',
                    255
                );

                $table->string(
                    'source_format',
                    16
                );

                $table->string(
                    'source_mime_type',
                    191
                )->nullable();

                $table->unsignedBigInteger(
                    'source_size_bytes'
                );

                $table->char(
                    'source_sha256',
                    64
                );

                /*
                 * Reader metadata only.
                 *
                 * No raw client rows.
                 */
                $table->json(
                    'reader_configuration'
                )->nullable();

                $table->json(
                    'source_structure_snapshot'
                )->nullable();

                $table->unsignedBigInteger(
                    'source_row_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'uploaded_by_user_id'
                )->nullable();

                $table->timestamp(
                    'uploaded_at'
                )->nullable();

                $table->string(
                    'failure_code',
                    100
                )->nullable();

                $table->text(
                    'failure_message'
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'data_transformation_bi_source_asset_id',
                    'dtbi_sa_files_asset_fk'
                )
                    ->references('id')
                    ->on(
                        'data_transformation_bi_source_assets'
                    )
                    ->cascadeOnDelete();

                $table->foreign(
                    'company_id',
                    'dtbi_sa_files_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();

                $table->foreign(
                    'uploaded_by_user_id',
                    'dtbi_sa_files_uploaded_by_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->unique(
                    'data_transformation_bi_source_asset_id',
                    'dtbi_sa_files_asset_uniq'
                );

                $table->index(
                    [
                        'company_id',
                        'status',
                    ],
                    'dtbi_sa_files_company_status_idx'
                );

                $table->index(
                    [
                        'company_id',
                        'source_sha256',
                    ],
                    'dtbi_sa_files_company_sha_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'data_transformation_bi_source_asset_files'
        );
    }
};
