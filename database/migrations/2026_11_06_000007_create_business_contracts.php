<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_quote_checkout_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('subscription_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscriber_id')->constrained()->restrictOnDelete();
            $table->foreignId('activated_by')->constrained('users')->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->char('request_hash', 64);
            $table->char('snapshot_hash', 64);
            $table->json('snapshot');
            $table->json('technical_evidence');
            $table->decimal('initial_invoice_total', 10, 2);
            $table->decimal('initial_invoice_subtotal', 10, 2);
            $table->decimal('initial_invoice_tax', 10, 2);
            $table->string('timezone');
            $table->timestamp('activated_at');
            $table->timestamp('period_ends_at');
            $table->timestamps();
            $table->unique(['company_id', 'idempotency_key'], 'business_contract_company_key');
        });
        Schema::create('business_contract_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_contract_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_item_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->uuid('identity_link_id');
            $table->foreign('identity_link_id')->references('id')->on('business_identity_links')->restrictOnDelete();
            $table->unsignedBigInteger('local_company_id');
            $table->unique(['business_contract_id', 'service_id'], 'business_contract_service');
        });
    }

    public function down(): void
    {
        if (DB::table('business_contracts')->exists() || DB::table('business_contract_items')->exists()) {
            throw new RuntimeException('No se puede eliminar el historial contractual Business.');
        }
        Schema::dropIfExists('business_contract_items');
        Schema::dropIfExists('business_contracts');
    }
};
