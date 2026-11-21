<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_quote_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_commercial_quote_id')->unique('business_acceptance_quote')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscriber_id')->constrained()->restrictOnDelete();
            $table->foreignId('accepted_by')->constrained('users')->restrictOnDelete();
            $table->string('accepted_name');
            $table->uuid('idempotency_key');
            $table->char('snapshot_hash', 64);
            $table->json('snapshot');
            $table->timestamp('accepted_at');
            $table->timestamps();
            $table->unique(['company_id', 'idempotency_key'], 'business_acceptance_company_key');
        });
    }

    public function down(): void
    {
        if (DB::table('service_quote_acceptances')->exists()) {
            throw new RuntimeException('No se puede eliminar el historial de aceptación de cotizaciones.');
        }
        Schema::dropIfExists('service_quote_acceptances');
    }
};
