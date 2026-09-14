<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'data_transformation_bi_intake_batches',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'company_id'
                );

                $table->unsignedBigInteger(
                    'transformation_implementation_request_id'
                );

                $table->unsignedBigInteger(
                    'transformation_implementation_definition_id'
                )->nullable();

                $table->unsignedInteger(
                    'definition_version'
                )->nullable();

                $table->unsignedSmallInteger(
                    'schema_version'
                );

                $table->string(
                    'source_disk',
                    32
                )->default('private');

                $table->string(
                    'source_path',
                    1024
                );

                $table->string(
                    'original_filename',
                    255
                );

                $table->string(
                    'source_format',
                    32
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

                $table->json(
                    'validation_snapshot'
                )->nullable();

                $table->string(
                    'status',
                    32
                )->default('pending');

                $table->unsignedInteger(
                    'domain_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'source_row_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'staged_row_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'rejected_row_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'created_by_user_id'
                )->nullable();

                /*
                 * Retention is explicit but no duration is invented here.
                 * The ingestion service may assign source_retention_until
                 * once an approved retention policy exists.
                 */
                $table->timestamp(
                    'source_retention_until'
                )->nullable();

                $table->timestamp(
                    'source_deleted_at'
                )->nullable();

                $table->timestamp(
                    'started_at'
                )->nullable();

                $table->timestamp(
                    'completed_at'
                )->nullable();

                $table->timestamp(
                    'failed_at'
                )->nullable();

                $table->string(
                    'failure_code',
                    100
                )->nullable();

                $table->text(
                    'failure_message'
                )->nullable();

                $table->timestamp(
                    'purged_at'
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'company_id',
                    'dtbi_batches_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();

                $table->foreign(
                    'transformation_implementation_request_id',
                    'dtbi_batches_request_fk'
                )
                    ->references('id')
                    ->on('transformation_implementation_requests')
                    ->cascadeOnDelete();

                $table->foreign(
                    'transformation_implementation_definition_id',
                    'dtbi_batches_definition_fk'
                )
                    ->references('id')
                    ->on('transformation_implementation_definitions')
                    ->nullOnDelete();

                $table->foreign(
                    'created_by_user_id',
                    'dtbi_batches_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                /*
                 * Same validated source for the same request + schema
                 * resolves to one logical ingestion batch.
                 */
                $table->unique(
                    [
                        'transformation_implementation_request_id',
                        'schema_version',
                        'source_sha256',
                    ],
                    'dtbi_batches_request_schema_hash_uq'
                );

                $table->index(
                    [
                        'company_id',
                        'status',
                    ],
                    'dtbi_batches_company_status_idx'
                );

                $table->index(
                    [
                        'transformation_implementation_request_id',
                        'status',
                    ],
                    'dtbi_batches_request_status_idx'
                );
            }
        );

        Schema::create(
            'data_transformation_bi_intake_batch_domains',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'data_transformation_bi_intake_batch_id'
                );

                $table->unsignedBigInteger(
                    'company_id'
                );

                $table->string(
                    'domain_key',
                    100
                );

                $table->string(
                    'status',
                    32
                )->default('pending');

                $table->unsignedBigInteger(
                    'source_row_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'staged_row_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'rejected_row_count'
                )->default(0);

                $table->text(
                    'failure_message'
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'data_transformation_bi_intake_batch_id',
                    'dtbi_domains_batch_fk'
                )
                    ->references('id')
                    ->on('data_transformation_bi_intake_batches')
                    ->cascadeOnDelete();

                $table->foreign(
                    'company_id',
                    'dtbi_domains_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();

                $table->unique(
                    [
                        'data_transformation_bi_intake_batch_id',
                        'domain_key',
                    ],
                    'dtbi_domains_batch_domain_uq'
                );

                $table->index(
                    [
                        'company_id',
                        'domain_key',
                    ],
                    'dtbi_domains_company_domain_idx'
                );
            }
        );

        Schema::create(
            'data_transformation_bi_intake_rows',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'data_transformation_bi_intake_batch_id'
                );

                $table->unsignedBigInteger(
                    'company_id'
                );

                $table->string(
                    'domain_key',
                    100
                );

                /*
                 * Row number refers to the original business-data row
                 * after the header row.
                 */
                $table->unsignedInteger(
                    'source_row_number'
                );

                $table->char(
                    'identity_hash',
                    64
                )->nullable();

                $table->char(
                    'row_sha256',
                    64
                );

                /*
                 * Canonical schema-v1 row only.
                 * This is staging, not the final BI model.
                 */
                $table->json(
                    'row_payload'
                );

                $table->timestamps();

                $table->foreign(
                    'data_transformation_bi_intake_batch_id',
                    'dtbi_rows_batch_fk'
                )
                    ->references('id')
                    ->on('data_transformation_bi_intake_batches')
                    ->cascadeOnDelete();

                $table->foreign(
                    'company_id',
                    'dtbi_rows_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();

                $table->unique(
                    [
                        'data_transformation_bi_intake_batch_id',
                        'domain_key',
                        'source_row_number',
                    ],
                    'dtbi_rows_batch_domain_row_uq'
                );

                $table->index(
                    [
                        'company_id',
                        'domain_key',
                    ],
                    'dtbi_rows_company_domain_idx'
                );

                $table->index(
                    [
                        'data_transformation_bi_intake_batch_id',
                        'identity_hash',
                    ],
                    'dtbi_rows_batch_identity_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'data_transformation_bi_intake_rows'
        );

        Schema::dropIfExists(
            'data_transformation_bi_intake_batch_domains'
        );

        Schema::dropIfExists(
            'data_transformation_bi_intake_batches'
        );
    }
};
