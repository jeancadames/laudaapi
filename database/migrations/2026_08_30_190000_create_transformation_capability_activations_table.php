<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'transformation_capability_activations',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('company_id');
                $table->foreignId('diagnosis_assessment_id');

                $table->string('capability_key', 120);

                $table->string('source_type', 60);
                $table->unsignedBigInteger('source_id');
                $table->unsignedInteger('source_version')->nullable();
                $table->json('source_snapshot')->nullable();

                $table->string('status', 40)
                    ->default('activated');

                $table->foreignId('activated_by_user_id')
                    ->nullable();

                $table->timestamp('activated_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ready_for_review_at')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'diagnosis_assessment_id',
                        'capability_key',
                    ],
                    'tca_assessment_capability_uq'
                );

                $table->index(
                    ['company_id', 'status'],
                    'tca_company_status_idx'
                );

                $table->index(
                    ['source_type', 'source_id'],
                    'tca_source_idx'
                );

                $table->foreign(
                    'company_id',
                    'tca_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->onDelete('cascade');

                $table->foreign(
                    'diagnosis_assessment_id',
                    'tca_assessment_fk'
                )
                    ->references('id')
                    ->on('diagnosis_assessments')
                    ->onDelete('cascade');

                $table->foreign(
                    'activated_by_user_id',
                    'tca_activated_user_fk'
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
            'transformation_capability_activations'
        );
    }
};
