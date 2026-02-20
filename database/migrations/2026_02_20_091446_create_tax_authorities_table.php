<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tax_authorities', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 2)->default('DO')->index();
            $table->string('code', 30)->index(); // DGII, TSS, etc.
            $table->string('name', 160);
            $table->boolean('active')->default(true)->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['country_code', 'code'], 'uniq_country_authority_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_authorities');
    }
};
