<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activation_requests', function (Blueprint $table) {
            $table->id();

            // Relación con el contacto original
            $table->foreignId('contact_request_id')
                ->nullable()
                ->constrained('contact_requests')
                ->nullOnDelete();

            // IdUsr solicitante
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Datos del solicitante
            $table->string('name');
            $table->string('company');
            $table->string('role')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();

            // Datos operativos
            $table->string('topic');
            $table->string('other_topic')->nullable();
            $table->string('system')->nullable();
            $table->string('volume')->nullable();
            $table->text('message')->nullable();

            $table->boolean('terms')->default(false); // ← agregado

            // Estado del proceso
            $table->string('status')->default('pending');
            // pending, contacted, activated, trialing, expired, converted, discarded

            // Manejo de prueba gratis
            $table->dateTime('trial_starts_at')->nullable();
            $table->dateTime('trial_ends_at')->nullable();
            $table->integer('trial_days')->default(30);

            // Auditoría
            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activation_requests');
    }
};
