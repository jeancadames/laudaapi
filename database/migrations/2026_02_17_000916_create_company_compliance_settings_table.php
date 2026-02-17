<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('company_compliance_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // Si null => usa companies.timezone
            $table->string('timezone')->nullable();

            // next_business_day | previous_business_day | none
            $table->string('weekend_shift', 30)->default('next_business_day');

            // En MVP: feriados opcional. Luego puedes crear tabla holidays por país.
            $table->boolean('use_holidays')->default(false);

            // Ej: [7,3,1,0]
            $table->json('default_reminders')->nullable();

            // Canales (fase 1: email)
            $table->json('channels')->nullable(); // ej: {"email":true,"in_app":true}

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_compliance_settings');
    }
};
