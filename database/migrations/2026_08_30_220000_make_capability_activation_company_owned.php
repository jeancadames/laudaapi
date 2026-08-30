<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicate = DB::table('transformation_capability_activations')
            ->select('company_id', 'capability_key')
            ->groupBy('company_id', 'capability_key')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate) {
            throw new RuntimeException(
                'S12-G2 requiere una sola activación por company_id + capability_key antes de aplicar el índice único.'
            );
        }

        /*
         * MySQL puede estar utilizando el índice único histórico
         * tca_assessment_capability_uq como índice de soporte del FK.
         * Por eso el FK debe eliminarse ANTES que ese índice.
         */
        Schema::table(
            'transformation_capability_activations',
            function (Blueprint $table): void {
                $table->dropForeign('tca_assessment_fk');
            }
        );

        Schema::table(
            'transformation_capability_activations',
            function (Blueprint $table): void {
                $table->dropUnique('tca_assessment_capability_uq');
            }
        );

        Schema::table(
            'transformation_capability_activations',
            function (Blueprint $table): void {
                $table->unsignedBigInteger('diagnosis_assessment_id')
                    ->nullable()
                    ->change();

                $table->string('source_type', 60)
                    ->nullable()
                    ->change();

                $table->unsignedBigInteger('source_id')
                    ->nullable()
                    ->change();

                /*
                 * Índice explícito para que el FK no dependa del unique
                 * de negocio company_id + capability_key.
                 */
                $table->index(
                    'diagnosis_assessment_id',
                    'tca_assessment_idx'
                );

                $table->unique(
                    [
                        'company_id',
                        'capability_key',
                    ],
                    'tca_company_capability_uq'
                );

                $table->foreign(
                    'diagnosis_assessment_id',
                    'tca_assessment_fk'
                )
                    ->references('id')
                    ->on('diagnosis_assessments')
                    ->nullOnDelete();
            }
        );
    }

    public function down(): void
    {
        $hasManualContext = DB::table('transformation_capability_activations')
            ->where(function ($query): void {
                $query->whereNull('diagnosis_assessment_id')
                    ->orWhereNull('source_type')
                    ->orWhereNull('source_id');
            })
            ->exists();

        if ($hasManualContext) {
            throw new RuntimeException(
                'No se puede revertir S12-G2 mientras existan activaciones con contexto manual opcional.'
            );
        }

        Schema::table(
            'transformation_capability_activations',
            function (Blueprint $table): void {
                $table->dropForeign('tca_assessment_fk');
            }
        );

        Schema::table(
            'transformation_capability_activations',
            function (Blueprint $table): void {
                $table->dropUnique('tca_company_capability_uq');
                $table->dropIndex('tca_assessment_idx');
            }
        );

        Schema::table(
            'transformation_capability_activations',
            function (Blueprint $table): void {
                $table->unsignedBigInteger('diagnosis_assessment_id')
                    ->nullable(false)
                    ->change();

                $table->string('source_type', 60)
                    ->nullable(false)
                    ->change();

                $table->unsignedBigInteger('source_id')
                    ->nullable(false)
                    ->change();

                $table->unique(
                    [
                        'diagnosis_assessment_id',
                        'capability_key',
                    ],
                    'tca_assessment_capability_uq'
                );

                $table->foreign(
                    'diagnosis_assessment_id',
                    'tca_assessment_fk'
                )
                    ->references('id')
                    ->on('diagnosis_assessments')
                    ->onDelete('cascade');
            }
        );
    }
};
