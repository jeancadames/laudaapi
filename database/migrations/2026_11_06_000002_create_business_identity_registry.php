<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_identity_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('service', 20);
            $table->unsignedBigInteger('central_company_id');
            $table->unsignedBigInteger('central_subscriber_id');
            $table->unsignedBigInteger('local_company_id')->nullable();
            $table->boolean('active')->default(false);
            $table->timestamps();
            $table->unique(['service', 'central_company_id'], 'business_identity_central_company');
            $table->unique(['service', 'local_company_id'], 'business_identity_local_company');
        });
        Schema::create('business_identity_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('service', 20);
            $table->unsignedBigInteger('central_user_id');
            $table->unsignedBigInteger('local_user_id');
            $table->timestamp('created_at');
            $table->unique(['service', 'central_user_id'], 'business_identity_central_account');
            $table->unique(['service', 'local_user_id'], 'business_identity_local_account');
        });
        Schema::create('business_identity_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('link_id')->constrained('business_identity_links')->restrictOnDelete();
            $table->unsignedBigInteger('central_user_id');
            $table->unsignedBigInteger('local_user_id');
            $table->char('consent', 32);
            $table->boolean('active')->default(false);
            $table->timestamp('updated_at');
            $table->unique(['link_id', 'central_user_id'], 'business_identity_central_member');
            $table->unique(['link_id', 'local_user_id'], 'business_identity_local_member');
        });
        Schema::create('business_identity_intents', function (Blueprint $table): void {
            $table->char('nonce', 32)->primary();
            $table->foreignUuid('link_id')->constrained('business_identity_links')->restrictOnDelete();
            $table->unsignedBigInteger('central_user_id');
            $table->json('payload');
            $table->unsignedBigInteger('expires_at')->index();
            $table->char('proof_hash', 64)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at');
        });
        Schema::create('business_identity_nonces', function (Blueprint $table): void {
            $table->id();
            $table->string('service', 20);
            $table->string('purpose', 20);
            $table->char('nonce', 32);
            $table->unsignedBigInteger('expires_at')->index();
            $table->timestamp('created_at');
            $table->unique(['service', 'purpose', 'nonce'], 'business_identity_nonce');
        });
        Schema::create('business_identity_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('link_id')->constrained('business_identity_links')->restrictOnDelete();
            $table->string('event', 30);
            $table->unsignedBigInteger('central_user_id');
            $table->unsignedBigInteger('local_user_id')->nullable();
            $table->char('consent', 32)->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['business_identity_links', 'business_identity_accounts', 'business_identity_intents', 'business_identity_events', 'business_identity_nonces'] as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException('No se puede eliminar un registro de identidad utilizado.');
            }
        }
        foreach (['business_identity_events', 'business_identity_nonces', 'business_identity_intents', 'business_identity_members', 'business_identity_accounts', 'business_identity_links'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
