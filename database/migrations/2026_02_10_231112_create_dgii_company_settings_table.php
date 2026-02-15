<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dgii_company_settings', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id')->unique();

            // precert | cert | prod (interno tuyo)
            $table->string('environment', 20)->default('precert');

            // ✅ DGII real: testecf | certecf | ecf
            $table->string('cf_prefix', 20)->default('testecf');

            // usa directorio DGII / catálogo local
            $table->boolean('use_directory')->default(true);

            /**
             * Overrides por compañía
             * - NULL => fallback al dgii_endpoint_catalog
             * - JSON => overrides (UrlDGII, UrlGetSeed, etc) o tu shape que decidas
             */
            $table->json('endpoints')->nullable();

            /**
             * Meta libre (flags extra, notas, etc.)
             */
            $table->json('meta')->nullable();

            /**
             * =========================
             * Token DGII (1 hora)
             * =========================
             */

            // Control: auto-generación
            // true => se refresca solo cuando se necesita
            // false => modo manual (no solicita token automáticamente)
            $table->boolean('dgii_token_auto')->default(true);

            // Pre-warm opcional:
            // 0 => desactivado
            // ej 180 => refresca si faltan <= 180 segundos
            $table->unsignedSmallInteger('dgii_token_refresh_before_seconds')->default(0);

            // Token y vigencia
            $table->text('dgii_access_token')->nullable();
            $table->timestamp('dgii_token_issued_at')->nullable();
            $table->timestamp('dgii_token_expires_at')->nullable();

            // Debug / auditoría
            $table->timestamp('dgii_token_last_requested_at')->nullable();
            $table->string('dgii_token_last_error', 255)->nullable();

            $table->timestamps();

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->cascadeOnDelete();

            // Índices útiles (aunque company_id sea unique, esto ayuda en queries compuestas)
            $table->index(['company_id', 'environment'], 'dgii_settings_company_env_idx');
            $table->index(['dgii_token_expires_at'], 'dgii_settings_token_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dgii_company_settings');
    }
};
