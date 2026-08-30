<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'transformation_capability_decisions',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('company_id');
                $table->foreignId('diagnosis_assessment_id');
                $table->string('capability_key', 120);

                $table->string('recommendation_status', 30);
                $table->string('decision', 20)
                    ->default('pending');

                $table->string('source_type', 60);
                $table->unsignedBigInteger('source_id');
                $table->unsignedInteger('source_version')->nullable();
                $table->json('source_snapshot')->nullable();

                $table->foreignId('decided_by_user_id')->nullable();
                $table->timestamp('decided_at')->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'diagnosis_assessment_id',
                        'capability_key',
                    ],
                    'tcd_assessment_capability_uq'
                );

                $table->index(
                    ['company_id', 'decision'],
                    'tcd_company_decision_idx'
                );

                $table->foreign(
                    'company_id',
                    'tcd_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->onDelete('cascade');

                $table->foreign(
                    'diagnosis_assessment_id',
                    'tcd_assessment_fk'
                )
                    ->references('id')
                    ->on('diagnosis_assessments')
                    ->onDelete('cascade');

                $table->foreign(
                    'decided_by_user_id',
                    'tcd_decided_user_fk'
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
            'transformation_capability_decisions'
        );
    }
};
