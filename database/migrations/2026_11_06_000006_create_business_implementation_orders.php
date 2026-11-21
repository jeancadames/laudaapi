<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_implementation_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_quote_checkout_id')->unique('business_implementation_checkout')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->char('snapshot_hash', 64);
            $table->json('scope_snapshot');
            $table->string('status', 32)->default('queued')->index();
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('business_implementation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_implementation_order_id');
            $table->foreign('business_implementation_order_id', 'business_implementation_event_order_fk')
                ->references('id')->on('business_implementation_orders')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->char('request_hash', 64);
            $table->string('action', 32);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->unsignedInteger('version');
            $table->text('note');
            $table->timestamps();
            $table->unique(['business_implementation_order_id', 'idempotency_key'], 'business_implementation_event_key');
            $table->unique(['business_implementation_order_id', 'version'], 'business_implementation_event_version');
        });
    }

    public function down(): void
    {
        if (DB::table('business_implementation_orders')->exists() || DB::table('business_implementation_events')->exists()) {
            throw new RuntimeException('No se puede eliminar el historial de implementación Business.');
        }
        Schema::dropIfExists('business_implementation_events');
        Schema::dropIfExists('business_implementation_orders');
    }
};
