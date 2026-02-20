<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('company_fiscal_signals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('source_module', 40)->index();   // sales|rrhh|banking|...
            $table->string('event_type', 80)->index();      // ecf_generated, payroll_closed...
            $table->string('source_ref', 120);              // id/uuid/ulid del evento en el módulo

            $table->string('period_key', 20)->index();      // 2026-01 / 2026-Q1 / 2026
            $table->timestamp('occurred_at')->index();

            $table->json('payload');
            $table->string('payload_hash', 64)->nullable()->index();

            $table->timestamps();

            $table->unique(['company_id', 'source_module', 'source_ref'], 'uniq_signal_source_ref');
            $table->index(['company_id', 'event_type', 'period_key'], 'idx_signal_event_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_fiscal_signals');
    }
};
