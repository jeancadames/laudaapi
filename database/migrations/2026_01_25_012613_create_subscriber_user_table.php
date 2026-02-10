<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscriber_user', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscriber_id')
                ->constrained('subscribers')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('role')->default('member'); // owner|admin|member|billing
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->unique(['subscriber_id', 'user_id']);
            $table->index(['user_id', 'active']);
            $table->index(['subscriber_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriber_user');
    }
};
