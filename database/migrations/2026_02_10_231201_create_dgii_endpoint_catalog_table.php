<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dgii_endpoint_catalog', function (Blueprint $table) {
            $table->id();

            // precert | cert | prod
            $table->string('environment', 20)->index();

            // key estable para referenciar desde código/UI
            $table->string('key', 120);

            $table->string('name', 180)->nullable();
            $table->text('description')->nullable();

            // ejemplo: https://ecf.dgii.gov.do  | https://fc.dgii.gov.do | https://statusecf.dgii.gov.do
            $table->string('base_url', 255);

            // ejemplo: /{cf}/autenticacion/api/autenticacion/semilla
            $table->string('path', 255);

            // GET | POST | etc
            $table->string('method', 10)->default('GET');

            // indica si el path contiene placeholders {cf} {trackid} etc
            $table->boolean('is_templated')->default(true);

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            // meta libre: headers, query template, etc.
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['environment', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dgii_endpoint_catalog');
    }
};
