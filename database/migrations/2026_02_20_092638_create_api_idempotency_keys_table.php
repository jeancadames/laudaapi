<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_idempotency_keys', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // Idempotency-Key header (o key enviada por Sales)
            $table->string('key', 120);

            // Para “scope” del key (si el mismo key se reusa por endpoints distintos, esto evita choques)
            $table->string('method', 10)->default('POST');
            $table->string('path', 255)->nullable();

            // Hash del request (para detectar mismo key con payload distinto)
            $table->string('request_hash', 64)->index();

            // processing/completed/failed (para controlar concurrencia y reintentos)
            $table->enum('status', ['processing', 'completed', 'failed'])
                ->default('processing')
                ->index();

            // Respuesta cacheada (si llega el mismo key, devuelves esto tal cual)
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->json('response_headers')->nullable();
            $table->json('response_body')->nullable();

            // Lock timestamps
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // TTL del idempotency (recomendado)
            $table->timestamp('expires_at')->nullable()->index();

            $table->timestamps();

            $table->unique(['company_id', 'key'], 'uniq_company_idempotency_key');
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_idempotency_keys');
    }
};
