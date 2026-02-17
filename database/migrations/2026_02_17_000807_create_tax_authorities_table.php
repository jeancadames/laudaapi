<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tax_authorities', function (Blueprint $table) {
            $table->id();

            $table->string('country_code', 2)->default('DO')->index(); // ISO-2
            $table->string('code', 20);   // DGII, TSS, etc.
            $table->string('name', 120);  // Dirección General de Impuestos Internos
            $table->string('website')->nullable();

            $table->boolean('active')->default(true)->index();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['country_code', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_authorities');
    }
};
