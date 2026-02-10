<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_customers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('provider');             // "stripe"
            $table->string('provider_customer_id'); // cus_xxx

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['provider', 'provider_customer_id']);
            $table->unique(['company_id', 'provider']);
            $table->index(['company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_customers');
    }
};
