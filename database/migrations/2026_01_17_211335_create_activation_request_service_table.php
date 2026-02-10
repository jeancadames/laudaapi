<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activation_request_service', function (Blueprint $table) {
            $table->id();

            $table->foreignId('activation_request_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('service_id')
                ->constrained('services')
                ->cascadeOnDelete();

            $table->json('meta')->nullable();
            $table->string('status')->nullable(); // pending_payment, active, cancelled, etc.

            $table->timestamps();

            // Índice único con nombre corto
            $table->unique(
                ['activation_request_id', 'service_id'],
                'ars_request_service_uq'
            );

            $table->index('service_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activation_request_service');
    }
};
