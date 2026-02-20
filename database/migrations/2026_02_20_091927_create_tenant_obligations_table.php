<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_obligations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('template_id')
                ->constrained('compliance_obligation_templates')
                ->cascadeOnDelete();

            $table->foreignId('owner_user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('enabled')->default(true)->index();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();

            $table->json('overrides')->nullable();
            $table->json('reminders')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'template_id'], 'uniq_company_template');
            $table->index(['company_id', 'enabled'], 'idx_company_enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_obligations');
    }
};
