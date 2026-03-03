<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('ws_subdomain', 63)->nullable()->unique();

            $table->string('currency')->default('USD');
            $table->string('timezone')->default('America/Bogota');

            // Owner opcional (NO cascada)
            $table->foreignId('owner_user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('subscriber_id')
                ->nullable()
                ->constrained('subscribers')
                ->cascadeOnDelete();

            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index('active');
            $table->unique('subscriber_id');
            $table->index('subscriber_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
