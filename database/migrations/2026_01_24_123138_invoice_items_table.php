<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')
                ->constrained('invoices')
                ->cascadeOnDelete();

            $table->foreignId('service_id')->nullable()
                ->constrained('services')
                ->nullOnDelete();

            $table->string('description');

            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);

            $table->decimal('line_subtotal', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);

            $table->decimal('tax_rate', 6, 3)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);

            $table->decimal('line_total', 10, 2)->default(0);

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index('service_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
