<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnosis_detailed_roadmaps', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('diagnosis_assessment_id')
                ->constrained('diagnosis_assessments')
                ->cascadeOnDelete();

            $table->foreignId('source_expanded_report_id')
                ->nullable()
                ->constrained('diagnosis_expanded_reports')
                ->nullOnDelete();

            $table->unsignedInteger('version')->default(1);
            $table->string('status', 30)->default('draft')->index();

            $table->foreignId('generated_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('reviewed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('methodology_version', 50)->nullable();

            $table->json('source_snapshot');
            $table->json('roadmap');

            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable()->index();

            $table->timestamps();

            $table->unique(
                ['diagnosis_assessment_id', 'version'],
                'diag_detailed_roadmap_version_unique'
            );

            $table->index(
                ['diagnosis_assessment_id', 'status'],
                'diag_detailed_roadmap_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnosis_detailed_roadmaps');
    }
};
