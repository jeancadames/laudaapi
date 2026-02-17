<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('obligation_instances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_obligation_id')
                ->constrained('tenant_obligations')
                ->cascadeOnDelete();

            // Denormalización útil para queries (sin join)
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // Periodo al que corresponde (mensual típico)
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();

            /**
             * period_key NO NULL para unicidad:
             * monthly: "2026-01"
             * annual:  "2026"
             * ad-hoc:  "2026-02-16#1"
             */
            $table->string('period_key', 20);

            // Fecha de vencimiento real calculada
            $table->date('due_date')->index();

            // pending | due_soon | overdue | filed | paid | not_applicable
            $table->string('status', 30)->default('pending')->index();

            // Trazabilidad / cumplimiento
            $table->timestamp('filed_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->foreignId('completed_by_user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('completed_at')->nullable();

            // refs externos (ej: numero recibo, acuse, etc.)
            $table->json('external_refs')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['tenant_obligation_id', 'period_key']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obligation_instances');
    }
};
