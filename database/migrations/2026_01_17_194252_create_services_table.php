<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();

            $table->string('title');

            // ✅ NUEVO: descripción corta (ideal para cards)
            $table->string('short_description', 160)->nullable();

            $table->string('slug')->unique();
            $table->string('href')->nullable();

            $table->json('roles')->nullable();

            $table->string('icon')->nullable();
            $table->string('badge')->nullable();

            $table->string('required_plan')->nullable();

            // Jerarquía
            $table->foreignId('parent_id')->nullable()
                ->constrained('services')
                ->nullOnDelete();

            // Catálogo
            $table->enum('type', ['core', 'addon', 'usage', 'external'])->default('addon');

            // Venta
            $table->boolean('billable')->default(true);

            // Cobro
            $table->enum('billing_model', ['flat', 'seat_block', 'usage'])->default('flat');

            $table->string('currency', 3)->default('USD');
            $table->decimal('monthly_price', 10, 2)->nullable();
            $table->decimal('yearly_price', 10, 2)->nullable();

            // seat_block / usage
            $table->unsignedInteger('block_size')->nullable();
            $table->unsignedInteger('included_units')->nullable();
            $table->string('unit_name')->nullable();
            $table->decimal('overage_unit_price', 12, 4)->nullable();

            $table->text('description')->nullable();

            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->index('parent_id');
            $table->index(['active', 'sort_order']);
            $table->index(['type', 'billing_model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
