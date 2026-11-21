<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_capacity_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_contract_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('identity_link_id')->constrained('business_identity_links')->restrictOnDelete();
            $table->unsignedBigInteger('owner_user_id');
            $table->uuid('inventory_key');
            $table->boolean('ready')->default(false);
            $table->timestamps();
            $table->unique(['business_contract_id', 'identity_link_id'], 'business_capacity_source');
        });
        Schema::create('business_capacity_users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_id')->constrained('business_capacity_sources')->restrictOnDelete();
            $table->unsignedBigInteger('local_user_id');
            $table->uuid('operation_key');
            $table->string('state', 16);
            $table->timestamps();
            $table->unique(['source_id', 'local_user_id'], 'business_capacity_local_user');
        });
        Schema::create('business_capacity_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_id')->constrained('business_capacity_sources')->restrictOnDelete();
            $table->unsignedBigInteger('local_user_id')->nullable();
            $table->uuid('operation_key');
            $table->string('action', 24);
            $table->index(['source_id', 'operation_key', 'action'], 'business_capacity_event_operation');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        if (DB::table('business_capacity_sources')->exists()) {
            throw new RuntimeException('El registro de capacidades utilizado conserva su historia.');
        }
        Schema::dropIfExists('business_capacity_events');
        Schema::dropIfExists('business_capacity_users');
        Schema::dropIfExists('business_capacity_sources');
    }
};
