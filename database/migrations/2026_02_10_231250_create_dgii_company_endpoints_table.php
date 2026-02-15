<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dgii_company_endpoints', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('endpoint_id'); // fk a dgii_endpoint_catalog

            // overrides (si null => usa el catálogo)
            $table->string('base_url', 255)->nullable();
            $table->string('path', 255)->nullable();
            $table->string('method', 10)->nullable();

            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->cascadeOnDelete();

            $table->foreign('endpoint_id')
                ->references('id')
                ->on('dgii_endpoint_catalog')
                ->cascadeOnDelete();

            $table->unique(['company_id', 'endpoint_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dgii_company_endpoints');
    }
};
