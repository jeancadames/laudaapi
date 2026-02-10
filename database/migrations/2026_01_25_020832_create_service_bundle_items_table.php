<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_bundle_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bundle_service_id')
                ->constrained('services')
                ->cascadeOnDelete();

            $table->foreignId('included_service_id')
                ->constrained('services')
                ->cascadeOnDelete();

            $table->boolean('required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            // Índice único con nombre corto
            $table->unique(
                ['bundle_service_id', 'included_service_id'],
                'bundle_items_uq'
            );

            $table->index(['bundle_service_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_bundle_items');
    }
};
