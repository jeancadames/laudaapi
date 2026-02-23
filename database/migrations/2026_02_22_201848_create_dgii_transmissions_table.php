<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dgii_transmissions', function (Blueprint $table) {
            $table->id();

            // ✅ Multi-tenant
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // ✅ Id público para UI/soporte
            $table->ulid('public_id')->unique();

            // ✅ Documento real del ERP (opcional)
            $table->foreignId('fiscal_document_id')
                ->nullable()
                ->constrained('fiscal_documents')
                ->nullOnDelete();

            // ✅ A qué endpoint lógico se envió (dinámico)
            // ej: recepcion_ecf | aprobacion_comercial | recepcion_fc
            $table->string('endpoint_key', 120)->index();

            // ✅ Ambiente target
            $table->enum('environment', ['precert', 'cert', 'prod'])->index();

            // ✅ Puntero al XML firmado en private storage (NO guardamos XML “generado” aquí, solo referencia)
            $table->string('signed_xml_path', 255)->nullable();
            $table->string('signed_xml_sha256', 64)->nullable()->index();
            $table->unsignedBigInteger('signed_xml_size_bytes')->nullable();

            // ✅ Request audit (EN DB, NO en archivos)
            // OJO: esto puede crecer. En producción, muchas veces se guarda solo hash + path.
            // Pero tú pediste request/response en la tabla.
            $table->longText('request_xml')->nullable();
            $table->string('request_sha256', 64)->nullable()->index();
            $table->unsignedBigInteger('request_size_bytes')->nullable();
            $table->string('request_content_type', 120)->nullable();
            $table->json('request_headers')->nullable();

            // ✅ HTTP audit
            $table->string('url', 500);
            $table->string('http_method', 10)->default('POST');
            $table->unsignedSmallInteger('http_status')->nullable()->index();

            // ✅ Response audit (EN DB)
            $table->longText('response_body')->nullable();
            $table->string('response_sha256', 64)->nullable()->index();
            $table->unsignedBigInteger('response_size_bytes')->nullable();
            $table->string('response_content_type', 120)->nullable();
            $table->json('response_headers')->nullable();

            // ✅ Parse mínimo para UI/reportes (si aplica)
            $table->string('dgii_codigo', 50)->nullable()->index();
            $table->string('dgii_estado', 80)->nullable()->index();
            $table->string('dgii_track_id', 120)->nullable()->index();
            $table->json('dgii_mensajes')->nullable();

            // ✅ Estado del intento
            $table->enum('status', [
                'queued',
                'sending',
                'sent',      // HTTP 2xx
                'failed',    // HTTP != 2xx o exception
                'accepted',
                'rejected',
            ])->default('queued')->index();

            $table->unsignedInteger('attempt')->default(1);
            $table->string('idempotency_key', 120)->nullable()->index();

            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('received_at')->nullable()->index();

            $table->string('error_message', 500)->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'fiscal_document_id', 'created_at'], 'idx_dgii_tx_doc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dgii_transmissions');
    }
};