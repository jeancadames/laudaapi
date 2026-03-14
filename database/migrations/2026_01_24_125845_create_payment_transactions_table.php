<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();

            // Relación básica (útil en multi-tenant + auditoría)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Multi-company (lo dejamos sin FK fijo por orden de migraciones)
            $table->unsignedBigInteger('company_id')->nullable()->index();

            // Si el pago está ligado directamente a una subscription (opcional)
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();

            // Enlace flexible a "lo que se paga": Order, Invoice, SubscriptionItem, etc.
            $table->nullableMorphs('payable'); // payable_type, payable_id

            // Proveedor de pagos (Stripe, PayPal, etc.)
            $table->string('provider')->default('stripe'); // stripe | paypal | ...
            $table->string('provider_mode')->default('live'); // live | test

            // IDs del proveedor (según gateway)
            $table->string('provider_transaction_id')->nullable();      // charge id, txn id, etc.
            $table->string('provider_payment_intent_id')->nullable();   // stripe payment_intent
            $table->string('provider_checkout_session_id')->nullable(); // stripe checkout session
            $table->string('provider_customer_id')->nullable();         // customer id

            // Estado (no enum para no amarrarte, pero igual bien controlado en backend)
            // pending | requires_action | succeeded | failed | cancelled | refunded | partially_refunded
            $table->string('status')->default('pending')->index();

            // Método de pago (opcional, útil para soporte)
            $table->string('payment_method')->nullable();        // card, bank_transfer, etc.
            $table->string('payment_method_brand')->nullable();  // visa, mastercard
            $table->string('payment_method_last4', 4)->nullable();

            // Montos (siempre calcula en backend, nunca confiar en request)
            $table->decimal('amount', 12, 2);                   // total cobrado
            $table->decimal('tax_amount', 12, 2)->default(0);   // ITBIS/imp u otros (si aplica)
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('fee_amount', 12, 2)->nullable();   // fee del proveedor
            $table->decimal('net_amount', 12, 2)->nullable();   // amount - fee (opcional)

            $table->string('currency', 3)->default('USD');

            // Para RD (si facturas en DOP): guardar tasa y equivalente
            $table->decimal('exchange_rate', 12, 6)->nullable(); // USD->DOP
            $table->decimal('amount_local', 12, 2)->nullable();  // equivalente DOP
            $table->string('local_currency', 3)->nullable();  

            // Para evitar dobles cobros / reintentos
            $table->string('idempotency_key')->nullable()->unique();

            // Extras (JSON: límites, payload del gateway, etc.)
            $table->json('metadata')->nullable();

            // Tiempos relevantes
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            // Reembolsos
            $table->timestamp('refunded_at')->nullable();
            $table->decimal('refunded_amount', 12, 2)->nullable();

            // Fallos
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();

            $table->timestamps();

            // Índices útiles
            $table->index(['provider', 'provider_transaction_id']);
            $table->index(['provider', 'provider_payment_intent_id']);
            $table->index(['provider', 'provider_checkout_session_id']);
            $table->index(['company_id', 'created_at']);
        });

        // FK condicional para companies (si ya existe en este punto)
        if (Schema::hasTable('companies')) {
            Schema::table('payment_transactions', function (Blueprint $table) {
                $table->foreign('company_id')
                    ->references('id')
                    ->on('companies')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
