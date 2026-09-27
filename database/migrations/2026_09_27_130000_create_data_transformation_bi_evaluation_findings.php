<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Professional diagnostic findings for one session-level
         * Data BI evaluation.
         *
         * These records contain professional interpretation only.
         * Never persist raw client values or source samples here.
         */
        Schema::create(
            'data_transformation_bi_evaluation_findings',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'data_transformation_bi_evaluation_id'
                );

                $table->unsignedBigInteger(
                    'company_id'
                );

                /*
                 * weakness
                 * opportunity
                 * observation
                 */
                $table->string(
                    'finding_type',
                    32
                );

                $table->string(
                    'title',
                    191
                );

                $table->text(
                    'details'
                );

                $table->text(
                    'recommendation'
                )->nullable();

                /*
                 * Professional/editorial priority only.
                 *
                 * This is not an automatic score and does not imply
                 * statistical risk classification.
                 */
                $table->string(
                    'priority',
                    16
                )->nullable();

                /*
                 * Pins the human finding to the exact diagnostic
                 * evidence version reviewed by the Admin.
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
                    'dtbi_eval_finding_eval_fk'
                )
                    ->references('id')
                    ->on(
                        'data_transformation_bi_evaluations'
                    )
                    ->cascadeOnDelete();

                $table->foreign(
                    'company_id',
                    'dtbi_eval_finding_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();

                $table->foreign(
                    'created_by_user_id',
                    'dtbi_eval_finding_created_by_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign(
                    'updated_by_user_id',
                    'dtbi_eval_finding_updated_by_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->index(
                    [
                        'data_transformation_bi_evaluation_id',
                        'evidence_version',
                    ],
                    'dtbi_eval_finding_evidence_idx'
                );

                $table->index(
                    [
                        'company_id',
                        'finding_type',
                    ],
                    'dtbi_eval_finding_company_type_idx'
                );

                $table->index(
                    [
                        'data_transformation_bi_evaluation_id',
                        'sort_order',
                    ],
                    'dtbi_eval_finding_order_idx'
                );
            }
        );

        /*
         * A finding may be supported by zero, one or several
         * source assets.
         *
         * The application layer additionally verifies that every
         * referenced source belongs to the evaluation evidence.
         */
        Schema::create(
            'data_transformation_bi_evaluation_finding_sources',
            function (Blueprint $table): void {
                $table->unsignedBigInteger(
                    'data_transformation_bi_evaluation_finding_id'
                );

                $table->unsignedBigInteger(
                    'data_transformation_bi_source_asset_id'
                );

                $table->timestamps();

                $table->primary(
                    [
                        'data_transformation_bi_evaluation_finding_id',
                        'data_transformation_bi_source_asset_id',
                    ],
                    'dtbi_eval_finding_source_pk'
                );

                $table->foreign(
                    'data_transformation_bi_evaluation_finding_id',
                    'dtbi_eval_finding_source_finding_fk'
                )
                    ->references('id')
                    ->on(
                        'data_transformation_bi_evaluation_findings'
                    )
                    ->cascadeOnDelete();

                $table->foreign(
                    'data_transformation_bi_source_asset_id',
                    'dtbi_eval_finding_source_asset_fk'
                )
                    ->references('id')
                    ->on(
                        'data_transformation_bi_source_assets'
                    )
                    ->cascadeOnDelete();

                $table->index(
                    'data_transformation_bi_source_asset_id',
                    'dtbi_eval_finding_source_asset_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'data_transformation_bi_evaluation_finding_sources'
        );

        Schema::dropIfExists(
            'data_transformation_bi_evaluation_findings'
        );
    }
};
