<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'transformation_capability_evaluation_summaries',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'transformation_capability_activation_id'
                );

                /*
                 * pending
                 * draft_generated
                 * reviewed
                 */
                $table->string(
                    'status',
                    40
                )->default('pending');

                /*
                 * Síntesis automática.
                 *
                 * Se genera únicamente desde decisiones
                 * profesionales confirmadas por LAUDA.
                 */
                $table->json(
                    'generated_payload'
                )->nullable();

                $table->json(
                    'generation_context'
                )->nullable();

                $table->unsignedInteger(
                    'generation_version'
                )->default(0);

                $table->timestamp(
                    'generated_at'
                )->nullable();

                /*
                 * Reservado para la revisión profesional
                 * de la síntesis en S12-G6E.
                 */
                $table->json(
                    'reviewed_payload'
                )->nullable();

                $table->foreignId(
                    'reviewed_by_user_id'
                )->nullable();

                $table->timestamp(
                    'reviewed_at'
                )->nullable();

                $table->timestamps();

                $table->unique(
                    'transformation_capability_activation_id',
                    'tces_activation_uq'
                );

                $table->index(
                    'status',
                    'tces_status_idx'
                );

                $table->foreign(
                    'transformation_capability_activation_id',
                    'tces_activation_fk'
                )
                    ->references('id')
                    ->on(
                        'transformation_capability_activations'
                    )
                    ->onDelete('cascade');

                $table->foreign(
                    'reviewed_by_user_id',
                    'tces_reviewed_by_fk'
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
            'transformation_capability_evaluation_summaries'
        );
    }
};
