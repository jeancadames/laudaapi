<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnosis_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Se deja sin FK hasta conectar este módulo con el modelo canónico
            // de organizaciones del Core LAUDAAPI.
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->string('organization_name');

            $table->string('methodology_version', 20)->default('1.0');
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedTinyInteger('current_step')->default(1);

            $table->json('answers')->nullable();
            $table->json('notes')->nullable();

            $table->unsignedTinyInteger('maturity_score')->nullable();
            $table->unsignedTinyInteger('capacity_score')->nullable();
            $table->unsignedTinyInteger('urgency_score')->nullable();
            $table->json('dimension_scores')->nullable();

            $table->string('maturity_level')->nullable();
            $table->string('urgency_level')->nullable();
            $table->string('recommended_modality', 30)->nullable();
            $table->string('recommended_modality_label')->nullable();
            $table->boolean('review_required')->default(false);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnosis_assessments');
    }
};
