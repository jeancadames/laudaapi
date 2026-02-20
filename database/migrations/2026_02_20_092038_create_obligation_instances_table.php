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

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('period_key', 20);

            $table->date('due_date')->index();
            $table->timestamp('due_at')->nullable()->index(); // mejora

            $table->string('status', 30)->default('pending')->index(); // pending|due_soon|overdue|filed|paid|not_applicable
            $table->string('status_reason', 140)->nullable(); // mejora

            $table->timestamp('filed_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->foreignId('completed_by_user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('completed_at')->nullable();

            $table->json('external_refs')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['tenant_obligation_id', 'period_key'], 'uniq_obligation_period');
            $table->index(['company_id', 'status'], 'idx_company_status');
            $table->index(['company_id', 'due_date'], 'idx_company_due');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obligation_instances');
    }
};
