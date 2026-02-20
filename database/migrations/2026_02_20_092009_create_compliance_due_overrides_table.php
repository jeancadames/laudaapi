<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('compliance_due_overrides', function (Blueprint $table) {
            $table->id();

            $table->foreignId('template_id')
                ->constrained('compliance_obligation_templates')
                ->cascadeOnDelete();

            $table->string('period_key', 20);
            $table->date('due_date');

            $table->string('source', 30)->default('manual')->index();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['template_id', 'period_key'], 'uniq_tpl_period_override');
            $table->index(['template_id', 'due_date'], 'idx_tpl_due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_due_overrides');
    }
};
