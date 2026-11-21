<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->timestamps();
        });
        Schema::create('service_catalog_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_catalog_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->unsignedInteger('lock_version')->default(1);
            $table->string('status', 20)->default('draft');
            $table->json('payload');
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['service_catalog_id', 'version']);
            $table->index(['service_catalog_id', 'effective_at']);
        });
        Schema::create('service_commercial_quotes', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('service_catalog_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscriber_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->string('request_hash', 64);
            $table->json('snapshot');
            $table->timestamp('issued_at');
            $table->timestamp('valid_until');
            $table->timestamps();
            $table->unique(['company_id', 'idempotency_key'], 'commercial_quote_company_key_unique');
        });
    }

    public function down(): void
    {
        // Historical commercial records require an explicit retention decision.
        foreach (['service_commercial_quotes', 'service_catalog_versions'] as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException('No se revierte un catálogo con historial comercial.');
            }
        }
        Schema::dropIfExists('service_commercial_quotes');
        Schema::dropIfExists('service_catalog_versions');
        Schema::dropIfExists('service_catalogs');
    }
};
