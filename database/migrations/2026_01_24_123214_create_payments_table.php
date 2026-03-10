<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('invoice_id')
                ->constrained('invoices')
                ->cascadeOnDelete();

            $table->enum('method', ['card', 'bank_transfer', 'cash', 'check', 'other'])->default('other');
            $table->enum('currency', ['USD', 'DOP', 'EUR'])->default('DOP');
            $table->decimal('amount', 10, 2);

            $table->timestamp('paid_at')->nullable();

            // Referencias (stripe charge id, transferencia, etc.)
            $table->string('reference')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
