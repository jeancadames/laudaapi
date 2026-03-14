<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dgii_certificates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // Identificación / UI
            $table->string('label')->nullable();
            $table->enum('type', ['p12', 'pfx', 'cer', 'crt'])->index();
            $table->boolean('is_default')->default(false)->index();

            // Storage
            $table->string('file_disk')->default('private');
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_sha256', 64)->nullable();

            // Datos leídos del certificado
            $table->string('subject_cn')->nullable();
            $table->string('subject_rnc')->nullable();
            $table->string('issuer_cn')->nullable();
            $table->string('serial_number')->nullable();

            // Mejor dateTime para evitar temas con timestamp/timezone
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_to')->nullable();

            // Estado / validación
            $table->boolean('has_private_key')->default(false);

            // ✅ NULL = no se pudo determinar (OpenSSL3 unsupported, etc.)
            $table->boolean('password_ok')->nullable();

            $table->json('meta')->nullable();

            // ✅ default "unparsed" para uploads; luego lo actualizas
            $table->enum('status', ['unparsed', 'active', 'expired', 'invalid', 'revoked'])
                ->default('unparsed')
                ->index();

            $table->timestamps();

            // Índices útiles
            $table->index(['company_id', 'type']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'is_default']);

            $table->unique(['company_id', 'type'], 'dgii_certificates_company_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dgii_certificates');
    }
};
