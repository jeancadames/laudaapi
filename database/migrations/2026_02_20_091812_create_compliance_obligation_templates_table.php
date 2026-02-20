<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('compliance_obligation_templates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('authority_id')
                ->constrained('tax_authorities')
                ->cascadeOnDelete();

            $table->string('country_code', 2)->default('DO')->index();
            $table->string('code', 40);
            $table->string('name', 160);
            $table->text('description')->nullable();

            $table->string('frequency', 20)->default('monthly')->index();
            $table->json('due_rule');
            $table->json('applicability_rule')->nullable();

            $table->json('default_reminders')->nullable(); // [7,3,1]
            $table->unsignedInteger('version')->default(1);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->boolean('active')->default(true)->index();
            $table->string('official_ref_url')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['authority_id', 'code', 'version'], 'uniq_tpl_authority_code_ver');
            $table->index(['country_code', 'authority_id', 'active'], 'idx_tpl_country_authority_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_obligation_templates');
    }
};
