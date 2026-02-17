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

            // Código humano: IT-1, IR-17, 606, TSS-PAGO, etc.
            $table->string('code', 40);

            $table->string('name', 160);
            $table->text('description')->nullable();

            // monthly|quarterly|annual|weekly|once
            $table->string('frequency', 20)->default('monthly')->index();

            /**
             * due_rule (JSON) examples:
             * { "type":"monthly_day", "day":20, "month_offset":1, "shift":"next_business_day" }
             * { "type":"year_table", "source":"tss", "year":2026 }
             */
            $table->json('due_rule');

            /**
             * applicability_rule (JSON) examples:
             * { "country":"DO", "requires":["invoicing_mode:ecf|both"] }
             * { "tax_regime":["general"], "excludes":["rst"] }
             */
            $table->json('applicability_rule')->nullable();

            // defaults de recordatorios por template (se puede sobrescribir por tenant)
            $table->json('default_reminders')->nullable(); // ej: [7,3,1]

            // Versionado/ vigencia (no rompes histórico)
            $table->unsignedInteger('version')->default(1);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->boolean('active')->default(true)->index();

            $table->string('official_ref_url')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['authority_id', 'code', 'version']);
            $table->index(['country_code', 'authority_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_obligation_templates');
    }
};
