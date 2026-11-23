<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_package_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_contract_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('replacement_quote_id')->nullable()->unique()->constrained('service_commercial_quotes')->restrictOnDelete();
            $table->dateTime('effective_at')->index();
            $table->json('products');
            $table->char('terms_hash', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_package_changes');
    }
};
