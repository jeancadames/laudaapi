<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fiscal_year_end_catalog', function (Blueprint $table) {
            $table->id();

            $table->char('country_code', 2)->index(); // DO
            $table->unsignedTinyInteger('close_month'); // 1..12
            $table->unsignedTinyInteger('close_day');   // 1..31

            $table->string('label', 40); // "31 de Diciembre"
            $table->text('common_business_types')->nullable();

            $table->unsignedSmallInteger('ir2_due_days')->default(120);

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->unique(['country_code', 'close_month', 'close_day'], 'uniq_country_close');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_year_end_catalog');
    }
};
