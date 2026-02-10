<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Tipo de evento: contact_created, activation_created, etc.
            $table->string('event');

            // Modelo afectado
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();

            // Datos relevantes del evento
            $table->json('data')->nullable();

            // Información del cliente
            $table->string('ip')->nullable();
            $table->text('user_agent')->nullable();

            // Usuario autenticado (si aplica)
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->timestamps();

            // Índices útiles
            $table->index('event');
            $table->index(['model_type', 'model_id']);
            $table->index('user_id');
            $table->index('ip');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
