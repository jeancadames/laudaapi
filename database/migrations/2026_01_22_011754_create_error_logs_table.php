<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();

            /**
             * Identificación / clasificación
             */
            $table->string('level', 20)->default('error'); // debug|info|warning|error|critical|alert|emergency
            $table->string('type')->nullable(); // e.g. ValidationException, QueryException, RuntimeException
            $table->string('fingerprint', 64)->nullable(); // hash para agrupar/evitar duplicados
            $table->string('message'); // mensaje corto del error

            /**
             * Ubicación del error
             */
            $table->string('file')->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->string('code', 50)->nullable(); // código interno o code de excepción si aplica

            /**
             * Stacktrace (puede ser grande)
             */
            $table->longText('trace')->nullable();

            /**
             * Contexto de request
             */
            $table->string('method', 10)->nullable(); // GET/POST/...
            $table->text('url')->nullable();
            $table->text('route')->nullable(); // name o uri
            $table->string('request_id', 64)->nullable(); // correlación (si usas)
            $table->unsignedSmallInteger('status_code')->nullable(); // 500/422 etc.

            /**
             * Usuario / cliente
             */
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip', 45)->nullable(); // IPv4/IPv6
            $table->text('user_agent')->nullable();

            /**
             * Payloads (cuidado: evita guardar PII sensible sin filtrar)
             */
            $table->json('context')->nullable(); // cualquier metadata adicional
            $table->json('tags')->nullable(); // e.g. ["billing","activation","api"]

            /**
             * Frecuencia / agrupación
             */
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();

            /**
             * Índices
             */
            $table->index('level');
            $table->index('type');
            $table->index('fingerprint');
            $table->index('user_id');
            $table->index('ip');
            $table->index('status_code');
            $table->index('created_at');
            $table->index(['type', 'created_at']);
            $table->index(['fingerprint', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_logs');
    }
};
