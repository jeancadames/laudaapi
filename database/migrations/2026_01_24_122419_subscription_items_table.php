<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')
                ->constrained('subscriptions')
                ->cascadeOnDelete();

            $table->foreignId('service_id')
                ->constrained('services')
                ->cascadeOnDelete();

            // trialing | active | pending | cancelled
            $table->string('status')->default('trialing')->index();

            $table->enum('billing_model', ['flat', 'seat_block', 'usage'])->default('flat');

            $table->unsignedInteger('quantity')->default(1);

            // Precio snapshot
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->enum('currency', ['USD', 'DOP', 'EUR'])->default('DOP');

            $table->unsignedInteger('block_size')->nullable();

            $table->string('unit_name')->nullable();
            $table->unsignedInteger('included_units')->nullable();
            $table->decimal('overage_unit_price', 12, 4)->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['subscription_id', 'service_id']);
            $table->index(['subscription_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_items');
    }
};
