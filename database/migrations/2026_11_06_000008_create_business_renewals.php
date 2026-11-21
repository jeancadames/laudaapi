<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_renewal_offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_contract_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('period_number');
            $table->unsignedInteger('revision');
            $table->timestamp('period_starts_at');
            $table->timestamp('period_ends_at');
            $table->timestamp('valid_until');
            $table->json('terms');
            $table->char('terms_hash', 64);
            $table->foreignId('offered_by')->constrained('users')->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->char('request_hash', 64);
            $table->timestamps();
            $table->unique(['business_contract_id', 'period_number', 'revision'], 'biz_renewal_offer_revision');
            $table->unique(['business_contract_id', 'idempotency_key'], 'biz_renewal_offer_key');
        });
        Schema::create('business_renewal_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_renewal_offer_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('business_contract_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('period_number');
            $table->char('terms_hash', 64);
            $table->foreignId('accepted_by')->constrained('users')->restrictOnDelete();
            $table->string('accepted_name');
            $table->timestamp('accepted_at');
            $table->uuid('idempotency_key');
            $table->timestamps();
            $table->unique(['business_contract_id', 'period_number'], 'biz_renewal_acceptance_period');
            $table->unique(['business_contract_id', 'idempotency_key'], 'biz_renewal_acceptance_key');
        });
        Schema::create('business_renewal_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_contract_id')->constrained()->restrictOnDelete();
            $table->foreignId('business_renewal_acceptance_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('period_number');
            $table->timestamp('period_starts_at');
            $table->timestamp('period_ends_at');
            $table->char('terms_hash', 64);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax_total', 10, 2);
            $table->decimal('total', 10, 2);
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->timestamps();
            $table->unique(['business_contract_id', 'period_number'], 'biz_renewal_invoice_period');
            $table->unique(['business_contract_id', 'idempotency_key'], 'biz_renewal_invoice_key');
        });
    }

    public function down(): void
    {
        foreach (['business_renewal_periods', 'business_renewal_acceptances', 'business_renewal_offers'] as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException('No se puede eliminar el historial de renovación Business.');
            }
        }
        Schema::dropIfExists('business_renewal_periods');
        Schema::dropIfExists('business_renewal_acceptances');
        Schema::dropIfExists('business_renewal_offers');
    }
};
