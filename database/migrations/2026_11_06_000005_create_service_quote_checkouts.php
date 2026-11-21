<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_quote_checkouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_quote_acceptance_id')->unique('business_checkout_acceptance')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscriber_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->char('snapshot_hash', 64);
            $table->timestamps();
            $table->unique(['company_id', 'idempotency_key'], 'business_checkout_company_key');
        });
    }

    public function down(): void
    {
        if (DB::table('service_quote_checkouts')->exists()) {
            throw new RuntimeException('No se puede eliminar el historial de facturación Business.');
        }
        Schema::dropIfExists('service_quote_checkouts');
    }
};
