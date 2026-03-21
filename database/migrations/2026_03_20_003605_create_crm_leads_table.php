<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crm_leads', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('type')->default('company'); // company | individual

            $table->string('name');
            $table->string('business_name')->nullable();

            $table->string('document_type', 20)->nullable();
            $table->string('document_number', 50)->nullable();

            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('mobile', 50)->nullable();

            $table->string('source')->nullable(); // web, referral, campaign, call, walkin
            $table->string('status')->default('new'); // new | qualified | unqualified | converted | lost

            $table->decimal('estimated_value', 14, 2)->nullable();
            $table->unsignedTinyInteger('score')->nullable(); // 0-100

            $table->foreignId('assigned_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('converted_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'source']);
            $table->index(['company_id', 'assigned_user_id']);
            $table->index(['company_id', 'name']);
            $table->index(['company_id', 'document_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_leads');
    }
};
