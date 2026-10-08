<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'data_transformation_bi_implementation_materialization_runs',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->char(
                        'run_uuid',
                        36
                    )
                    ->unique(
                        'dtbi_impl_run_uuid_uq'
                    );

                $table->unsignedBigInteger(
                    'company_id'
                );

                $table->unsignedBigInteger(
                    'transformation_implementation_request_id'
                );

                $table->unsignedBigInteger(
                    'data_transformation_bi_intake_session_id'
                );

                $table->unsignedBigInteger(
                    'requested_by_user_id'
                );

                $table
                    ->string(
                        'status',
                        32
                    )
                    ->default('queued');

                /*
                 * Counts describe the whole session-run, not an individual
                 * ImplementationDataset.
                 */
                $table
                    ->unsignedInteger(
                        'selected_mapping_count'
                    )
                    ->default(0);

                $table
                    ->unsignedInteger(
                        'materialized_dataset_count'
                    )
                    ->default(0);

                $table
                    ->unsignedInteger(
                        'reused_dataset_count'
                    )
                    ->default(0);

                /*
                 * Successful runs preserve their deterministic summary.
                 * We deliberately do not duplicate canonical row payloads here.
                 */
                $table
                    ->json(
                        'result_snapshot'
                    )
                    ->nullable();

                $table
                    ->string(
                        'failure_code',
                        100
                    )
                    ->nullable();

                /*
                 * User/Admin-visible failure remains generic.
                 * Exception detail belongs in application logs.
                 */
                $table
                    ->text(
                        'failure_message'
                    )
                    ->nullable();

                $table
                    ->timestamp(
                        'queued_at'
                    )
                    ->nullable();

                $table
                    ->timestamp(
                        'started_at'
                    )
                    ->nullable();

                $table
                    ->timestamp(
                        'finished_at'
                    )
                    ->nullable();

                $table->timestamps();

                $table
                    ->foreign(
                        'company_id',
                        'dtbi_impl_run_company_fk'
                    )
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();

                $table
                    ->foreign(
                        'transformation_implementation_request_id',
                        'dtbi_impl_run_request_fk'
                    )
                    ->references('id')
                    ->on(
                        'transformation_implementation_requests'
                    )
                    ->cascadeOnDelete();

                $table
                    ->foreign(
                        'data_transformation_bi_intake_session_id',
                        'dtbi_impl_run_session_fk'
                    )
                    ->references('id')
                    ->on(
                        'data_transformation_bi_intake_sessions'
                    )
                    ->cascadeOnDelete();

                $table
                    ->foreign(
                        'requested_by_user_id',
                        'dtbi_impl_run_user_fk'
                    )
                    ->references('id')
                    ->on('users');

                $table->index(
                    [
                        'company_id',
                        'data_transformation_bi_intake_session_id',
                        'status',
                    ],
                    'dtbi_impl_run_session_status_idx'
                );

                $table->index(
                    [
                        'transformation_implementation_request_id',
                        'status',
                    ],
                    'dtbi_impl_run_request_status_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'data_transformation_bi_implementation_materialization_runs'
        );
    }
};
