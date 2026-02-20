<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fiscal_parties', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('name', 255);
            $table->string('country_code', 2)->default('DO')->index();

            $table->string('tax_id', 30)->nullable()->index();
            $table->string('tax_id_type', 20)->default('RNC');
            $table->string('tax_id_normalized', 30)->nullable()->index(); // mejora

            $table->string('email', 190)->nullable();
            $table->string('phone', 30)->nullable();

            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();

            $table->boolean('active')->default(true)->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'tax_id_type', 'tax_id_normalized'], 'idx_party_tax_norm');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_parties');
    }
};
