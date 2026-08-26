<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'service_pricing_tiers',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('service_id')
                    ->constrained('services')
                    ->cascadeOnDelete();

                $table->enum(
                    'billing_cycle',
                    ['monthly', 'yearly']
                );

                $table->unsignedInteger('min_quantity');
                $table->unsignedInteger('max_quantity')
                    ->nullable();

                $table->decimal('price', 12, 2);

                $table->string(
                    'currency',
                    3
                )->default('DOP');

                $table->boolean('active')
                    ->default(true);

                $table->unsignedInteger('sort_order')
                    ->default(0);

                $table->json('metadata')
                    ->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'service_id',
                        'billing_cycle',
                        'active',
                    ],
                    'service_pricing_tiers_lookup_idx'
                );

                $table->index(
                    [
                        'service_id',
                        'billing_cycle',
                        'min_quantity',
                    ],
                    'service_pricing_tiers_range_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'service_pricing_tiers'
        );
    }
};
