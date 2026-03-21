<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('crm_customer_id')
                ->nullable()
                ->constrained('crm_customers')
                ->nullOnDelete();

            $table->foreignId('crm_contact_id')
                ->nullable()
                ->constrained('crm_contacts')
                ->nullOnDelete();

            $table->foreignId('crm_lead_id')
                ->nullable()
                ->constrained('crm_leads')
                ->nullOnDelete();

            $table->foreignId('crm_opportunity_id')
                ->nullable()
                ->constrained('crm_opportunities')
                ->nullOnDelete();

            $table->string('type')->default('task'); // task | call | meeting | visit | email | note
            $table->string('title');
            $table->text('description')->nullable();

            $table->string('status')->default('pending'); // pending | completed | cancelled
            $table->string('priority')->default('normal'); // low | normal | high | urgent

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->foreignId('assigned_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'type']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'priority']);
            $table->index(['company_id', 'assigned_user_id']);
            $table->index(['company_id', 'scheduled_at']);
            $table->index(['company_id', 'crm_customer_id']);
            $table->index(['company_id', 'crm_opportunity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
    }
};
