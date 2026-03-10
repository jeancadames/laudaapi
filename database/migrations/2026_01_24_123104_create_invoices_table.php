<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('subscription_id')->nullable()
                ->constrained('subscriptions')
                ->nullOnDelete();

            // Numeración interna
            $table->string('number')->unique();

            $table->enum('status', ['draft', 'issued', 'paid', 'void', 'overdue'])->default('draft');

            $table->date('issued_on')->nullable();
            $table->date('due_on')->nullable();

            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();

            $table->enum('currency', ['USD', 'DOP', 'EUR'])->default('DOP');

            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('tax_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('amount_paid', 10, 2)->default(0);

            $table->json('billing_snapshot')->nullable();

            // DGII
            $table->enum('document_class', ['NCF', 'ECF'])->nullable();
            $table->string('document_type', 3)->nullable();
            $table->string('fiscal_number')->nullable();
            $table->string('security_code')->nullable();
            $table->json('fiscal_meta')->nullable();

            // Online payments (opcional)
            $table->string('provider')->nullable();                 // "stripe"
            $table->string('provider_invoice_id')->nullable();      // in_xxx (si usas invoices del gateway)
            $table->string('hosted_invoice_url')->nullable();       // URL del gateway
            $table->string('payment_url')->nullable();              // checkout/payment link

            $table->timestamps();

            $table->unique(['company_id', 'document_class', 'fiscal_number']);

            $table->index(['company_id', 'status']);
            $table->index(['issued_on', 'status']);
            $table->index(['provider', 'provider_invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
