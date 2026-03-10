<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();

            // Identidad visible
            $table->string('title');
            $table->string('short_description', 160)->nullable();
            $table->text('description')->nullable();

            // Identidad técnica / catálogo
            $table->string('slug')->unique();
            $table->string('service_key')->nullable()->unique();

            // Navegación / acceso
            $table->string('href')->nullable();

            // ✅ Nuevo: cómo se abre el servicio
            $table->enum('launch_mode', ['internal', 'external', 'embedded', 'api'])
                ->default('internal');

            // ✅ Nuevo: destino real si es externo
            $table->string('external_url')->nullable();
            $table->string('launch_path')->nullable();

            // ✅ Nuevo: cómo se integra con LaudaAPI
            $table->enum('integration_mode', ['none', 'sso', 'token', 'api_key', 'oauth'])
                ->default('none');

            // ✅ Nuevo: si puede operar fuera del ERP
            $table->boolean('is_standalone')->default(false);

            // Seguridad / visibilidad
            $table->json('roles')->nullable();
            $table->string('required_plan')->nullable();

            // UI
            $table->string('icon')->nullable();
            $table->string('badge')->nullable();
            $table->string('category')->nullable();

            // Jerarquía
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('services')
                ->nullOnDelete();

            // Catálogo comercial
            $table->enum('type', ['core', 'addon', 'usage'])->default('addon');

            // Venta
            $table->boolean('billable')->default(true);

            // Cobro
            $table->enum('billing_model', ['flat', 'seat_block', 'usage'])->default('flat');

            $table->enum('currency', ['USD', 'DOP', 'EUR'])->default('DOP');
            $table->decimal('monthly_price', 10, 2)->nullable();
            $table->decimal('yearly_price', 10, 2)->nullable();

            // seat_block / usage
            $table->unsignedInteger('block_size')->nullable();
            $table->unsignedInteger('included_units')->nullable();
            $table->string('unit_name')->nullable();
            $table->decimal('overage_unit_price', 12, 4)->nullable();

            // ✅ Nuevo: metadata flexible por servicio
            $table->json('config')->nullable();

            // Estado / orden
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            // Índices
            $table->index('parent_id');
            $table->index(['active', 'sort_order']);
            $table->index(['type', 'billing_model']);
            $table->index(['launch_mode', 'active']);
            $table->index(['category', 'active']);
            $table->index(['required_plan', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
