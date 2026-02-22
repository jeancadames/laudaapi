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

            // Código humano: 606, 607, IT-1, IR-3, IR-17, etc.
            $table->string('code', 40);
            $table->string('name', 160);
            $table->text('description')->nullable();

            // ✅ enum para evitar valores basura
            $table->enum('frequency', ['monthly', 'quarterly', 'annual', 'weekly', 'once'])
                ->default('monthly')
                ->index();

            // JSON rules
            $table->json('due_rule');
            $table->json('applicability_rule')->nullable();

            $table->json('default_reminders')->nullable(); // [7,3,1]
            $table->unsignedInteger('version')->default(1);

            // vigencia del template (no rompe histórico)
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->boolean('active')->default(true)->index();

            $table->string('official_ref_url')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            // ✅ Unicidad + índices útiles
            $table->unique(['authority_id', 'code', 'version'], 'uniq_tpl_authority_code_ver');

            $table->index(['code'], 'idx_tpl_code');
            $table->index(['authority_id', 'country_code', 'active'], 'idx_tpl_authority_country_active');
            $table->index(['active', 'effective_from', 'effective_to'], 'idx_tpl_active_effective');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_obligation_templates');
    }
};
