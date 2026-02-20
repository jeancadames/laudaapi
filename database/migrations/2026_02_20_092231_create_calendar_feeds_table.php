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

            $table->string('type', 20)->default('ics');
            $table->string('label', 120)->default('Fiscal Compliance');
            $table->boolean('enabled')->default(true)->index();

            $table->string('token_hash', 64)->unique();
            $table->string('token_prefix', 16)->nullable()->index(); // mejora
            $table->timestamp('expires_at')->nullable()->index();     // mejora

            $table->timestamp('last_rotated_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable()->index(); // mejora

            $table->foreignId('created_by_user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'enabled'], 'idx_company_enabled_feed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_feeds');
    }
};
