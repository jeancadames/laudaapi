<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_fiscal_handoffs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('document_id')->constrained('business_usage_documents')->restrictOnDelete();
            $table->foreignUuid('source_reservation_id')->unique()->constrained('business_usage_reservations')->restrictOnDelete();
            $table->foreignUuid('target_link_id')->constrained('business_identity_links')->restrictOnDelete();
            $table->string('reference', 128);
            $table->char('payload_hash', 64);
            $table->string('environment', 20);
            $table->char('fiscal_identity_hash', 64)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique('document_id');
            $table->unique(['target_link_id', 'environment', 'reference'], 'business_fiscal_destination');
        });
    }

    public function down(): void
    {
        if (DB::table('business_fiscal_handoffs')->exists()) {
            throw new RuntimeException('El historial de entregas fiscales Business se conserva.');
        }
        Schema::dropIfExists('business_fiscal_handoffs');
    }
};
