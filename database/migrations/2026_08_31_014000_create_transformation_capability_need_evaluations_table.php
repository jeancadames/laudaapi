<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'transformation_capability_need_evaluations',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'transformation_capability_need_id'
                );

                /*
                 * Lifecycle propio de la evaluación.
                 *
                 * pending
                 * draft_generated
                 * evaluated
                 */
                $table->string(
                    'status',
                    40
                )->default('pending');

                /*
                 * Borrador generado automáticamente.
                 *
                 * Nunca constituye la decisión profesional final.
                 */
                $table->string(
                    'suggested_result',
                    40
                )->nullable();

                $table->text(
                    'suggested_findings'
                )->nullable();

                $table->text(
                    'suggested_recommendation'
                )->nullable();

                $table->string(
                    'suggested_priority',
                    20
                )->nullable();

                $table->json(
                    'suggested_questions'
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
                 * Resultado profesional confirmado por LAUDA.
                 */
                $table->string(
                    'result',
                    40
                )->nullable();

                $table->text(
                    'findings'
                )->nullable();

                $table->text(
                    'recommendation'
                )->nullable();

                $table->string(
                    'priority',
                    20
                )->nullable();

                $table->foreignId(
                    'evaluated_by_user_id'
                )->nullable();

                $table->timestamp(
                    'evaluated_at'
                )->nullable();

                $table->timestamps();

                $table->unique(
                    'transformation_capability_need_id',
                    'tcne_need_uq'
                );

                $table->index(
                    'status',
                    'tcne_status_idx'
                );

                $table->index(
                    'result',
                    'tcne_result_idx'
                );

                $table->foreign(
                    'transformation_capability_need_id',
                    'tcne_need_fk'
                )
                    ->references('id')
                    ->on('transformation_capability_needs')
                    ->onDelete('cascade');

                $table->foreign(
                    'evaluated_by_user_id',
                    'tcne_evaluated_by_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        );

        /*
         * Backfill legítimo:
         * las áreas existentes pasan a disponer de una evaluación pendiente.
         *
         * No altera el status histórico "identified" de la tabla original.
         */
        DB::table('transformation_capability_needs')
            ->select('id')
            ->orderBy('id')
            ->chunkById(
                500,
                function ($needs): void {
                    $now = now();

                    $rows = collect($needs)
                        ->map(
                            fn ($need): array => [
                                'transformation_capability_need_id' =>
                                    $need->id,
                                'status' =>
                                    'pending',
                                'generation_version' =>
                                    0,
                                'created_at' =>
                                    $now,
                                'updated_at' =>
                                    $now,
                            ]
                        )
                        ->all();

                    if ($rows !== []) {
                        DB::table(
                            'transformation_capability_need_evaluations'
                        )->insertOrIgnore($rows);
                    }
                },
                'id'
            );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'transformation_capability_need_evaluations'
        );
    }
};
