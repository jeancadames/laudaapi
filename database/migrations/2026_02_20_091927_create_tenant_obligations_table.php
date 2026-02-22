<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_obligations', function (Blueprint $table) {
            $table->id();

            // ✅ Para exponer en UI/API sin id incremental
            $table->ulid('public_id')->unique();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // ⚠️ Mejor RESTRICT para no romper configuraciones si se “toca” el catálogo
            $table->foreignId('template_id')
                ->constrained('compliance_obligation_templates')
                ->restrictOnDelete();

            $table->foreignId('owner_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('enabled')->default(true)->index();

            // ✅ Ventana de vigencia de esta obligación para la empresa
            $table->date('starts_on')->nullable()->index();
            $table->date('ends_on')->nullable()->index();

            // ✅ Opcional: ayuda a debug / incremental sync
            $table->timestamp('last_synced_at')->nullable()->index();

            // JSON config por tenant
            // Ej overrides:
            // { "due_rule": {...}, "weekend_shift": "next_business_day", "timezone": "America/Santo_Domingo" }
            $table->json('overrides')->nullable();

            // Ej reminders:
            // { "days_before": [7,3,1,0], "channels": ["email","inapp"] }
            $table->json('reminders')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // ✅ 1 template por company
            $table->unique(['company_id', 'template_id'], 'uniq_company_template');

            // ✅ índices útiles para sync
            $table->index(['company_id', 'enabled'], 'idx_company_enabled');
            $table->index(['company_id', 'enabled', 'starts_on', 'ends_on'], 'idx_company_enabled_window');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_obligations');
    }
};
