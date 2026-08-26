<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transformation_implementation_plans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('diagnosis_assessment_id');

            $table->foreign(
                'diagnosis_assessment_id',
                'tip_assessment_fk'
            )
                ->references('id')
                ->on('diagnosis_assessments')
                ->cascadeOnDelete();

            $table->foreignId('diagnosis_detailed_roadmap_id');

            $table->foreign(
                'diagnosis_detailed_roadmap_id',
                'tip_roadmap_fk'
            )
                ->references('id')
                ->on('diagnosis_detailed_roadmaps')
                ->cascadeOnDelete();

            $table->unsignedInteger('version')->default(1);

            $table->string('status', 40)->default('draft');

            $table->string('recommended_modality', 20)->nullable();
            $table->string('recommended_modality_label', 100)->nullable();

            $table->string('selected_modality', 20)->nullable();
            $table->string('selected_modality_label', 100)->nullable();

            $table->json('source_snapshot')->nullable();
            $table->text('internal_notes')->nullable();

            $table->foreignId('created_by_user_id')->nullable();

            $table->foreign(
                'created_by_user_id',
                'tip_created_by_fk'
            )
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreignId('updated_by_user_id')->nullable();

            $table->foreign(
                'updated_by_user_id',
                'tip_updated_by_fk'
            )
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->timestamp('presented_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['diagnosis_assessment_id', 'version'],
                'transformation_plan_assessment_version_unique'
            );

            $table->index(
                ['diagnosis_detailed_roadmap_id', 'status'],
                'transformation_plan_roadmap_status_index'
            );

            $table->index(
                ['status', 'presented_at'],
                'transformation_plan_status_presented_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transformation_implementation_plans');
    }
};
