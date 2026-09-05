<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'transformation_implementation_definitions',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'transformation_implementation_plan_id'
                );

                $table->unsignedBigInteger(
                    'diagnosis_assessment_id'
                );

                $table->unsignedBigInteger(
                    'company_id'
                );

                $table->unsignedInteger(
                    'version'
                )->default(1);

                /*
                 * Etapa funcional/técnica.
                 * NO representa estado comercial.
                 */
                $table->string(
                    'status',
                    30
                )->default('draft');

                /*
                 * Snapshot inmutable del Plan consultivo
                 * que origina esta definición.
                 */
                $table->json(
                    'source_snapshot'
                );

                /*
                 * Contenido propio de la preparación
                 * de implementación.
                 */
                $table->json(
                    'implementation_scope'
                )->nullable();

                $table->json(
                    'deliverables'
                )->nullable();

                $table->json(
                    'dependencies'
                )->nullable();

                $table->json(
                    'responsibility_model'
                )->nullable();

                $table->json(
                    'readiness'
                )->nullable();

                $table->text(
                    'internal_notes'
                )->nullable();

                $table->unsignedBigInteger(
                    'created_by_user_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'updated_by_user_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'reviewed_by_user_id'
                )->nullable();

                $table->timestamp(
                    'reviewed_at'
                )->nullable();

                $table->timestamp(
                    'ready_at'
                )->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'transformation_implementation_plan_id',
                        'version',
                    ],
                    'tid_plan_version_uq'
                );

                $table->index(
                    [
                        'company_id',
                        'status',
                    ],
                    'tid_company_status_idx'
                );

                $table->index(
                    [
                        'diagnosis_assessment_id',
                        'status',
                    ],
                    'tid_assessment_status_idx'
                );

                $table->foreign(
                    'transformation_implementation_plan_id',
                    'tid_plan_fk'
                )
                    ->references('id')
                    ->on(
                        'transformation_implementation_plans'
                    )
                    ->restrictOnDelete();

                $table->foreign(
                    'diagnosis_assessment_id',
                    'tid_assessment_fk'
                )
                    ->references('id')
                    ->on('diagnosis_assessments')
                    ->restrictOnDelete();

                $table->foreign(
                    'company_id',
                    'tid_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->restrictOnDelete();

                foreach ([
                    'created_by_user_id' =>
                        'tid_created_user_fk',

                    'updated_by_user_id' =>
                        'tid_updated_user_fk',

                    'reviewed_by_user_id' =>
                        'tid_reviewed_user_fk',
                ] as $column => $constraint) {
                    $table->foreign(
                        $column,
                        $constraint
                    )
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                }
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'transformation_implementation_definitions'
        );
    }
};
