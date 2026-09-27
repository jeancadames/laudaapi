<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'data_transformation_bi_evaluations',
            function (Blueprint $table): void {
                $table->id();

                /*
                 * One diagnostic evaluation belongs to one submitted
                 * intake session / delivery.
                 */
                $table->unsignedBigInteger(
                    'data_transformation_bi_intake_session_id'
                );

                $table->unsignedBigInteger(
                    'company_id'
                );

                $table->unsignedBigInteger(
                    'transformation_implementation_request_id'
                );

                /*
                 * Diagnostic lifecycle:
                 *
                 * draft
                 * ready_for_review
                 * published
                 */
                $table->string(
                    'status',
                    32
                )->default('draft');

                /*
                 * A delivery manifest pins the tenant-controlled evidence.
                 *
                 * The evidence fingerprint separately pins the aggregate
                 * profiling state used by the professional evaluation.
                 */
                $table->char(
                    'submission_manifest_sha256',
                    64
                );

                $table->unsignedInteger(
                    'evidence_version'
                )->default(1);

                $table->char(
                    'evidence_sha256',
                    64
                );

                /*
                 * Aggregate diagnostic evidence only.
                 *
                 * Never store raw client values or value samples here.
                 */
                $table->json(
                    'evidence_snapshot'
                );

                $table->timestamp(
                    'evidence_captured_at'
                );

                $table->unsignedBigInteger(
                    'created_by_user_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'ready_for_review_by_user_id'
                )->nullable();

                $table->timestamp(
                    'ready_for_review_at'
                )->nullable();

                $table->unsignedBigInteger(
                    'published_by_user_id'
                )->nullable();

                $table->timestamp(
                    'published_at'
                )->nullable();

                $table->timestamps();

                $table->unique(
                    'data_transformation_bi_intake_session_id',
                    'dtbi_eval_session_uq'
                );

                $table->index(
                    [
                        'company_id',
                        'status',
                    ],
                    'dtbi_eval_company_status_idx'
                );

                $table->index(
                    'transformation_implementation_request_id',
                    'dtbi_eval_request_idx'
                );

                $table->foreign(
                    'data_transformation_bi_intake_session_id',
                    'dtbi_eval_session_fk'
                )
                    ->references('id')
                    ->on(
                        'data_transformation_bi_intake_sessions'
                    )
                    ->cascadeOnDelete();

                $table->foreign(
                    'company_id',
                    'dtbi_eval_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();

                $table->foreign(
                    'transformation_implementation_request_id',
                    'dtbi_eval_request_fk'
                )
                    ->references('id')
                    ->on(
                        'transformation_implementation_requests'
                    )
                    ->cascadeOnDelete();

                $table->foreign(
                    'created_by_user_id',
                    'dtbi_eval_created_by_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign(
                    'ready_for_review_by_user_id',
                    'dtbi_eval_review_by_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign(
                    'published_by_user_id',
                    'dtbi_eval_publish_by_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'data_transformation_bi_evaluations'
        );
    }
};
