<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_usage_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('business_contract_id')->constrained()->restrictOnDelete();
            $table->timestamp('period_starts_at');
            $table->timestamp('period_ends_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['business_contract_id', 'period_starts_at'], 'business_usage_period');
        });
        Schema::create('business_usage_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->constrained('business_usage_documents')->restrictOnDelete();
            // Source IDs and fiscal identities are hashed; no fiscal payload is stored.
            $table->char('identity_hash', 64);
            $table->string('kind', 10);
            $table->timestamp('created_at');
            $table->unique(['company_id', 'identity_hash'], 'business_usage_identity');
        });
        Schema::create('business_usage_reservations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('document_id')->constrained('business_usage_documents')->restrictOnDelete();
            $table->foreignUuid('identity_link_id')->constrained('business_identity_links')->restrictOnDelete();
            $table->foreignId('business_contract_id')->constrained()->restrictOnDelete();
            $table->timestamp('period_starts_at');
            $table->timestamp('period_ends_at');
            $table->uuid('operation_key');
            $table->char('request_hash', 64);
            $table->string('state', 12);
            $table->timestamp('valid_until');
            $table->timestamps();
            $table->unique(['identity_link_id', 'operation_key'], 'business_usage_operation');
            $table->index(['document_id', 'state']);
        });
        Schema::create('business_usage_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('reservation_id')->constrained('business_usage_reservations')->restrictOnDelete();
            $table->string('event', 12);
            $table->timestamp('created_at');
            $table->unique(['reservation_id', 'event']);
        });
    }

    public function down(): void
    {
        if (DB::table('business_usage_documents')->exists()) {
            throw new RuntimeException('El historial de consumo Business se conserva.');
        }
        Schema::dropIfExists('business_usage_events');
        Schema::dropIfExists('business_usage_reservations');
        Schema::dropIfExists('business_usage_aliases');
        Schema::dropIfExists('business_usage_documents');
    }
};
