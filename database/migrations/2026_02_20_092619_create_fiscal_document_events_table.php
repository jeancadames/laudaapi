<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fiscal_document_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('document_id')
                ->constrained('fiscal_documents')
                ->cascadeOnDelete();

            // Denormalizado para filtrar sin join en timeline por empresa
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Ej: status_changed, received_xml, signed, submitted, dgii_response, retry_scheduled...
            $table->string('type', 60)->index();

            // info|warning|error (útil para UI badges)
            $table->enum('level', ['info', 'warning', 'error'])->default('info')->index();

            // Texto corto para UI
            $table->string('summary', 255)->nullable();

            // JSON: payload DGII, hashes, paths, status_from/to, http_code, etc.
            $table->json('payload')->nullable();

            // Marca de tiempo “real” del evento (no el created_at)
            $table->timestamp('occurred_at')->useCurrent()->index();

            $table->timestamps();

            $table->index(['document_id', 'type']);
            $table->index(['company_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_document_events');
    }
};
