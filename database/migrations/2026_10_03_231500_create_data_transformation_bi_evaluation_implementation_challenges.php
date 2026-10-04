<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Human-authored implementation implications for one
         * Data BI diagnostic evaluation.
         *
         * They are intentionally separate from evaluation findings.
         *
         * No raw source values, source samples, automatic scores,
         * commercial state or roadmap state is persisted here.
         */
        Schema::create(
            'data_transformation_bi_evaluation_implementation_challenges',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'data_transformation_bi_evaluation_id'
                );

                $table->unsignedBigInteger(
                    'company_id'
                );

                $table->string(
                    'title',
                    191
                );

                $table->text(
                    'details'
                );

                /*
                 * Optional human-authored mitigation / implementation
                 * response.
                 *
                 * This is not automatically derived from a finding
                 * recommendation.
                 */
                $table->text(
                    'recommended_response'
                )->nullable();

                /*
                 * Professional/editorial priority only.
                 *
                 * high | medium | low
                 *
                 * This is not a statistical risk score, confidence
                 * score or global BI-readiness score.
                 */
                $table->string(
                    'priority',
                    16
                )->nullable();

                /*
                 * Pins this professional implication to the exact
                 * diagnostic evidence version reviewed by LAUDA.
                 */
                $table->unsignedInteger(
                    'evidence_version'
                );

                $table->unsignedInteger(
                    'sort_order'
                )->default(0);

                $table->unsignedBigInteger(
                    'created_by_user_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'updated_by_user_id'
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'data_transformation_bi_evaluation_id',
                    'dtbi_impl_challenge_eval_fk'
                )
                    ->references('id')
                    ->on(
                        'data_transformation_bi_evaluations'
                    )
                    ->cascadeOnDelete();

                $table->foreign(
                    'company_id',
                    'dtbi_impl_challenge_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();

                $table->foreign(
                    'created_by_user_id',
                    'dtbi_impl_challenge_created_by_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign(
                    'updated_by_user_id',
                    'dtbi_impl_challenge_updated_by_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->index(
                    [
                        'data_transformation_bi_evaluation_id',
                        'evidence_version',
                    ],
                    'dtbi_impl_challenge_eval_evidence_idx'
                );

                $table->index(
                    [
                        'company_id',
                        'priority',
                    ],
                    'dtbi_impl_challenge_company_priority_idx'
                );

                $table->index(
                    [
                        'data_transformation_bi_evaluation_id',
                        'sort_order',
                    ],
                    'dtbi_impl_challenge_eval_order_idx'
                );
            }
        );

        /*
         * Optional traceability from implementation implications back
         * to professional diagnostic findings.
         *
         * The relationship is many-to-many because one challenge may
         * arise from several findings and one finding may influence
         * several implementation challenges.
         */
        Schema::create(
            'data_transformation_bi_impl_challenge_findings',
            function (Blueprint $table): void {
                $table->unsignedBigInteger(
                    'data_transformation_bi_evaluation_implementation_challenge_id'
                );

                $table->unsignedBigInteger(
                    'data_transformation_bi_evaluation_finding_id'
                );

                $table->timestamps();

                $table->primary(
                    [
                        'data_transformation_bi_evaluation_implementation_challenge_id',
                        'data_transformation_bi_evaluation_finding_id',
                    ],
                    'dtbi_impl_challenge_finding_pk'
                );

                $table->foreign(
                    'data_transformation_bi_evaluation_implementation_challenge_id',
                    'dtbi_impl_challenge_finding_challenge_fk'
                )
                    ->references('id')
                    ->on(
                        'data_transformation_bi_evaluation_implementation_challenges'
                    )
                    ->cascadeOnDelete();

                $table->foreign(
                    'data_transformation_bi_evaluation_finding_id',
                    'dtbi_impl_challenge_finding_finding_fk'
                )
                    ->references('id')
                    ->on(
                        'data_transformation_bi_evaluation_findings'
                    )
                    ->cascadeOnDelete();

                $table->index(
                    'data_transformation_bi_evaluation_finding_id',
                    'dtbi_impl_challenge_finding_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'data_transformation_bi_impl_challenge_findings'
        );

        Schema::dropIfExists(
            'data_transformation_bi_evaluation_implementation_challenges'
        );
    }
};
