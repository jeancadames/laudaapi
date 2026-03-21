<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crm_opportunities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('crm_customer_id')
                ->nullable()
                ->constrained('crm_customers')
                ->nullOnDelete();

            $table->foreignId('crm_lead_id')
                ->nullable()
                ->constrained('crm_leads')
                ->nullOnDelete();

            $table->string('title');
            $table->string('stage')->default('lead'); // lead | qualified | proposal | negotiation | won | lost
            $table->string('status')->default('open'); // open | won | lost | cancelled

            $table->decimal('amount', 14, 2)->nullable();
            $table->unsignedTinyInteger('probability')->default(0); // 0-100

            $table->date('expected_close_date')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->foreignId('assigned_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->text('loss_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'stage']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'assigned_user_id']);
            $table->index(['company_id', 'expected_close_date']);
            $table->index(['company_id', 'crm_customer_id']);
            $table->index(['company_id', 'crm_lead_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_opportunities');
    }
};
