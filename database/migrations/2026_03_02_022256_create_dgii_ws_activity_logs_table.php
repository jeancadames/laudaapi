<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dgii_ws_activity_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id')->nullable()->index();

            // inbound: DGII -> tu WS
            // outbound: tu app -> DGII
            $table->string('direction', 10); // inbound|outbound

            // ej: ws.semilla, ws.validacion, ws.recepcion, ws.aprobacion, dgii.http
            $table->string('channel', 40)->index();

            // ej: seed_requested, cert_validated, ecf_received, acecf_sent_to_dgii, etc.
            $table->string('event', 60)->index();

            $table->string('http_method', 10)->nullable();
            $table->text('url')->nullable();

            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            // correlación por request (ideal para agrupar: validación -> recepción -> aprobación)
            $table->uuid('correlation_id')->nullable()->index();

            // si DGII te devuelve/manda trackId / numeroRecepcion / etc
            $table->string('dgii_track_id', 80)->nullable()->index();

            // request info
            $table->string('ip', 64)->nullable();
            $table->text('user_agent')->nullable();

            // payloads (guardarlos truncados + redacted)
            $table->json('request_headers')->nullable();
            $table->longText('request_body')->nullable();

            $table->json('response_headers')->nullable();
            $table->longText('response_body')->nullable();

            // extra info: tenant, company_slug, filenames, hashes, errores, etc.
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'created_at'], 'dgii_ws_logs_company_created_idx');
            $table->index(['channel', 'created_at'], 'dgii_ws_logs_channel_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dgii_ws_activity_logs');
    }
};
