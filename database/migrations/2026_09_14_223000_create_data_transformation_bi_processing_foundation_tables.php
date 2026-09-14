<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'data_transformation_bi_processing_runs',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'data_transformation_bi_intake_batch_id'
                );

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

                $table->unsignedSmallInteger(
                    'profiling_version'
                )->default(1);

                $table->unsignedSmallInteger(
                    'normalization_version'
                )->default(1);

                $table->string(
                    'status',
                    32
                )->default('pending');

                $table->unsignedBigInteger(
                    'source_row_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'profiled_row_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'normalized_row_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'issue_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'blocking_issue_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'warning_issue_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'created_by_user_id'
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
                    96
                )->nullable();

                $table->text(
                    'failure_message'
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'data_transformation_bi_intake_batch_id',
                    'dtbi_pr_batch_fk'
                )
                    ->references('id')
                    ->on('data_transformation_bi_intake_batches')
                    ->cascadeOnDelete();

                $table->foreign(
                    'company_id',
                    'dtbi_pr_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();

                $table->foreign(
                    'transformation_implementation_request_id',
                    'dtbi_pr_request_fk'
                )
                    ->references('id')
                    ->on('transformation_implementation_requests')
                    ->cascadeOnDelete();

                $table->foreign(
                    'transformation_implementation_definition_id',
                    'dtbi_pr_definition_fk'
                )
                    ->references('id')
                    ->on('transformation_implementation_definitions')
                    ->nullOnDelete();

                $table->foreign(
                    'created_by_user_id',
                    'dtbi_pr_actor_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->unique(
                    [
                        'data_transformation_bi_intake_batch_id',
                        'profiling_version',
                        'normalization_version',
                    ],
                    'dtbi_pr_batch_versions_uq'
                );

                $table->index(
                    [
                        'company_id',
                        'status',
                    ],
                    'dtbi_pr_company_status_idx'
                );
            }
        );

        Schema::create(
            'data_transformation_bi_domain_profiles',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'data_transformation_bi_processing_run_id'
                );

                $table->unsignedBigInteger(
                    'data_transformation_bi_intake_batch_id'
                );

                $table->unsignedBigInteger(
                    'company_id'
                );

                $table->string(
                    'domain_key',
                    64
                );

                $table->string(
                    'status',
                    32
                )->default('pending');

                $table->unsignedBigInteger(
                    'row_count'
                )->default(0);

                $table->unsignedInteger(
                    'field_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'identity_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'duplicate_identity_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'issue_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'blocking_issue_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'warning_issue_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'normalized_row_count'
                )->default(0);

                $table->timestamps();

                $table->foreign(
                    'data_transformation_bi_processing_run_id',
                    'dtbi_dp_run_fk'
                )
                    ->references('id')
                    ->on('data_transformation_bi_processing_runs')
                    ->cascadeOnDelete();

                $table->foreign(
                    'data_transformation_bi_intake_batch_id',
                    'dtbi_dp_batch_fk'
                )
                    ->references('id')
                    ->on('data_transformation_bi_intake_batches')
                    ->cascadeOnDelete();

                $table->foreign(
                    'company_id',
                    'dtbi_dp_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();

                $table->unique(
                    [
                        'data_transformation_bi_processing_run_id',
                        'domain_key',
                    ],
                    'dtbi_dp_run_domain_uq'
                );
            }
        );

        Schema::create(
            'data_transformation_bi_field_profiles',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'data_transformation_bi_processing_run_id'
                );

                $table->unsignedBigInteger(
                    'data_transformation_bi_intake_batch_id'
                );

                $table->unsignedBigInteger(
                    'company_id'
                );

                $table->string(
                    'domain_key',
                    64
                );

                $table->string(
                    'field_key',
                    96
                );

                $table->string(
                    'data_type',
                    32
                );

                $table->boolean(
                    'required'
                )->default(false);

                $table->unsignedBigInteger(
                    'row_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'non_null_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'null_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'blank_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'distinct_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'invalid_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'issue_count'
                )->default(0);

                /*
                 * Aggregate metadata only.
                 * Never store raw-value samples here.
                 */
                $table->json(
                    'metrics'
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'data_transformation_bi_processing_run_id',
                    'dtbi_fp_run_fk'
                )
                    ->references('id')
                    ->on('data_transformation_bi_processing_runs')
                    ->cascadeOnDelete();

                $table->foreign(
                    'data_transformation_bi_intake_batch_id',
                    'dtbi_fp_batch_fk'
                )
                    ->references('id')
                    ->on('data_transformation_bi_intake_batches')
                    ->cascadeOnDelete();

                $table->foreign(
                    'company_id',
                    'dtbi_fp_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();

                $table->unique(
                    [
                        'data_transformation_bi_processing_run_id',
                        'domain_key',
                        'field_key',
                    ],
                    'dtbi_fp_run_domain_field_uq'
                );
            }
        );

        Schema::create(
            'data_transformation_bi_quality_issues',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'data_transformation_bi_processing_run_id'
                );

                $table->unsignedBigInteger(
                    'data_transformation_bi_intake_batch_id'
                );

                $table->unsignedBigInteger(
                    'data_transformation_bi_intake_row_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'company_id'
                );

                $table->string(
                    'domain_key',
                    64
                );

                $table->string(
                    'field_key',
                    96
                )->nullable();

                $table->unsignedBigInteger(
                    'source_row_number'
                )->nullable();

                $table->string(
                    'identity_hash',
                    64
                )->nullable();

                $table->string(
                    'issue_code',
                    96
                );

                $table->string(
                    'severity',
                    16
                );

                /*
                 * Human-readable diagnostic only.
                 * Do not embed raw source values.
                 */
                $table->text(
                    'message'
                );

                $table->json(
                    'meta'
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'data_transformation_bi_processing_run_id',
                    'dtbi_qi_run_fk'
                )
                    ->references('id')
                    ->on('data_transformation_bi_processing_runs')
                    ->cascadeOnDelete();

                $table->foreign(
                    'data_transformation_bi_intake_batch_id',
                    'dtbi_qi_batch_fk'
                )
                    ->references('id')
                    ->on('data_transformation_bi_intake_batches')
                    ->cascadeOnDelete();

                $table->foreign(
                    'data_transformation_bi_intake_row_id',
                    'dtbi_qi_row_fk'
                )
                    ->references('id')
                    ->on('data_transformation_bi_intake_rows')
                    ->nullOnDelete();

                $table->foreign(
                    'company_id',
                    'dtbi_qi_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();

                $table->index(
                    [
                        'data_transformation_bi_processing_run_id',
                        'severity',
                    ],
                    'dtbi_qi_run_severity_idx'
                );

                $table->index(
                    [
                        'domain_key',
                        'issue_code',
                    ],
                    'dtbi_qi_domain_code_idx'
                );
            }
        );

        Schema::create(
            'data_transformation_bi_normalized_rows',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'data_transformation_bi_processing_run_id'
                );

                $table->unsignedBigInteger(
                    'data_transformation_bi_intake_batch_id'
                );

                $table->unsignedBigInteger(
                    'data_transformation_bi_intake_row_id'
                );

                $table->unsignedBigInteger(
                    'company_id'
                );

                $table->string(
                    'domain_key',
                    64
                );

                $table->unsignedBigInteger(
                    'source_row_number'
                );

                $table->string(
                    'identity_hash',
                    64
                );

                $table->string(
                    'source_row_sha256',
                    64
                );

                $table->string(
                    'normalized_sha256',
                    64
                );

                $table->unsignedInteger(
                    'change_count'
                )->default(0);

                /*
                 * Derived canonical payload.
                 * This remains a preparation layer,
                 * never the final BI model.
                 */
                $table->json(
                    'normalized_payload'
                );

                $table->json(
                    'normalization_meta'
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'data_transformation_bi_processing_run_id',
                    'dtbi_nr_run_fk'
                )
                    ->references('id')
                    ->on('data_transformation_bi_processing_runs')
                    ->cascadeOnDelete();

                $table->foreign(
                    'data_transformation_bi_intake_batch_id',
                    'dtbi_nr_batch_fk'
                )
                    ->references('id')
                    ->on('data_transformation_bi_intake_batches')
                    ->cascadeOnDelete();

                $table->foreign(
                    'data_transformation_bi_intake_row_id',
                    'dtbi_nr_row_fk'
                )
                    ->references('id')
                    ->on('data_transformation_bi_intake_rows')
                    ->cascadeOnDelete();

                $table->foreign(
                    'company_id',
                    'dtbi_nr_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();

                $table->unique(
                    [
                        'data_transformation_bi_processing_run_id',
                        'data_transformation_bi_intake_row_id',
                    ],
                    'dtbi_nr_run_intake_row_uq'
                );

                $table->index(
                    [
                        'data_transformation_bi_processing_run_id',
                        'domain_key',
                    ],
                    'dtbi_nr_run_domain_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'data_transformation_bi_normalized_rows'
        );

        Schema::dropIfExists(
            'data_transformation_bi_quality_issues'
        );

        Schema::dropIfExists(
            'data_transformation_bi_field_profiles'
        );

        Schema::dropIfExists(
            'data_transformation_bi_domain_profiles'
        );

        Schema::dropIfExists(
            'data_transformation_bi_processing_runs'
        );
    }
};
