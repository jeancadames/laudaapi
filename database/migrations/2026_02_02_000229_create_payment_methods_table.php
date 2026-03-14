<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();

            // ✅ multi-tenant: cada método pertenece a una company
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Clasificación general del método
            |--------------------------------------------------------------------------
            | - type: qué es (gateway online, transferencia, efectivo, cheque, etc.)
            | - provider: quién lo procesa (azul, cardnet, visanet, stripe, mio, manual)
            */
            $table->enum('type', [
                'gateway',        // procesador online (tarjeta / wallet)
                'bank_transfer',  // transferencia bancaria
                'cash',           // efectivo
                'check',          // cheque
                'other',          // otros (manual/externo)
            ])->default('other')->index();

            $table->string('provider', 50)->nullable()->index(); // azul|cardnet|visanet|mio|stripe|manual|...
            $table->string('name', 120);                         // Nombre visible (ej: "Tarjeta (Azul)", "Transferencia Banco Popular")
            $table->string('currency', 3)->default('USD');      // moneda principal para este método (puede variar por empresa)

            /*
            |--------------------------------------------------------------------------
            | Estado y preferencia
            |--------------------------------------------------------------------------
            */
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->boolean('is_default')->default(false)->index(); // preferido (no se fuerza único en DB; se controla en app)
            $table->unsignedInteger('sort_order')->default(0)->index();

            /*
            |--------------------------------------------------------------------------
            | Ambiente / modo
            |--------------------------------------------------------------------------
            | Útil para gateways: test vs live
            */
            $table->enum('mode', ['test', 'live'])->default('test')->index();

            /*
            |--------------------------------------------------------------------------
            | Campos opcionales para "bank_transfer"
            |--------------------------------------------------------------------------
            | (No son obligatorios para gateways)
            */
            $table->string('bank_name', 120)->nullable();        // "Banco Popular", etc.
            $table->string('bank_account_holder', 120)->nullable();
            $table->string('bank_account_number', 60)->nullable();
            $table->string('bank_account_type', 30)->nullable(); // "ahorro", "corriente"
            $table->string('bank_branch', 120)->nullable();
            $table->string('bank_swift', 20)->nullable();
            $table->string('bank_iban', 40)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Configuración del método
            |--------------------------------------------------------------------------
            | - credentials: credenciales del gateway (ideal: guardar ENCRIPTADO o referencia a Secrets/Vault)
            | - config: settings no sensibles (webhooks, callbacks, capturar/preautorizar, cuotas, etc.)
            | - instructions: texto a mostrar al cliente (transferencia / cheque / etc.)
            */
            $table->json('credentials')->nullable();
            $table->json('config')->nullable();
            $table->text('instructions')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Auditoría mínima
            |--------------------------------------------------------------------------
            */
            $table->foreignId('created_by_user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Índices útiles
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'type']);
            $table->index(['company_id', 'provider']);

            // Evita duplicados obvios por company (opcional, pero recomendado)
            $table->unique(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
