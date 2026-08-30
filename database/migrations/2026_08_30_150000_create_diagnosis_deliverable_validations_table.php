<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'diagnosis_deliverable_validations',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'diagnosis_assessment_id'
                );

                $table->string(
                    'deliverable_type',
                    40
                );

                $table->unsignedBigInteger(
                    'deliverable_id'
                );

                $table->unsignedInteger(
                    'deliverable_version'
                )->default(1);

                $table->foreignId(
                    'reviewed_by_user_id'
                )->nullable();

                $table->timestamp(
                    'reviewed_at'
                )->nullable();

                $table->foreignId(
                    'validated_by_user_id'
                )->nullable();

                $table->timestamp(
                    'validated_at'
                )->nullable();

                $table->foreignId(
                    'adjustment_requested_by_user_id'
                )->nullable();

                $table->timestamp(
                    'adjustment_requested_at'
                )->nullable();

                $table->text(
                    'adjustment_note'
                )->nullable();

                $table->timestamps();

                /*
                 * Nombres explícitos y cortos:
                 * MySQL limita identifiers a 64 caracteres.
                 */

                $table->foreign(
                    'diagnosis_assessment_id',
                    'ddv_assessment_fk'
                )
                    ->references('id')
                    ->on('diagnosis_assessments')
                    ->cascadeOnDelete();

                $table->foreign(
                    'reviewed_by_user_id',
                    'ddv_reviewed_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign(
                    'validated_by_user_id',
                    'ddv_validated_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign(
                    'adjustment_requested_by_user_id',
                    'ddv_adjustment_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->unique(
                    [
                        'deliverable_type',
                        'deliverable_id',
                    ],
                    'diagnosis_deliverable_validation_unique'
                );

                $table->index(
                    [
                        'diagnosis_assessment_id',
                        'deliverable_type',
                    ],
                    'diagnosis_deliverable_validation_assessment_type_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'diagnosis_deliverable_validations'
        );
    }
};
