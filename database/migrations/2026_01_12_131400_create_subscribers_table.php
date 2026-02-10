<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();

            // Identidad comercial (display)
            $table->string('name');           // nombre comercial
            $table->string('slug')->unique();

            // Facturación
            $table->string('country_code', 2)->default('DO');
            $table->string('currency', 3)->default('USD');
            $table->string('timezone')->default('America/Bogota');

            // Integración pagos online
            $table->string('provider')->nullable();            // stripe
            $table->string('provider_mode')->default('live');  // live|test
            $table->string('provider_customer_id')->nullable(); // cus_xxx

            $table->boolean('active')->default(true);

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['active']);
            $table->index(['country_code', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscribers');
    }
};
