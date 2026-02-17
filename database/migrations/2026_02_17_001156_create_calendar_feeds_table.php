<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('calendar_feeds', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // ics por ahora
            $table->string('type', 20)->default('ics');

            $table->string('label', 120)->default('Fiscal Compliance');
            $table->boolean('enabled')->default(true)->index();

            // Guarda el token hasheado (no plano)
            $table->string('token_hash', 64)->unique();

            $table->timestamp('last_rotated_at')->nullable();

            $table->foreignId('created_by_user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_feeds');
    }
};
