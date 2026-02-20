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

            $table->string('timezone')->nullable();
            $table->string('weekend_shift', 30)->default('next_business_day');
            $table->boolean('use_holidays')->default(false);

            $table->json('default_reminders')->nullable(); // [7,3,1,0]
            $table->json('channels')->nullable(); // {"email":true,"in_app":true}
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->unique('company_id', 'uniq_company_compliance');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_compliance_settings');
    }
};
