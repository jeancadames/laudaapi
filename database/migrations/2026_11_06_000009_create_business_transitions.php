<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_commercial_quote_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('business_contract_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('subscription_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('executed_by')->constrained('users')->restrictOnDelete();
            $table->char('source_hash', 64);
            $table->json('snapshot');
            $table->timestamp('executed_at');
            $table->timestamps();
        });
        Schema::create('business_transition_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_transition_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_id')->unique()->constrained()->restrictOnDelete();
            $table->boolean('retained');
        });
        Schema::create('business_transition_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_transition_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_item_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('destination_item_id')->constrained('subscription_items')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('business_transitions')->exists()) {
            throw new RuntimeException('El historial de transición Business se conserva.');
        }
        Schema::dropIfExists('business_transition_items');
        Schema::dropIfExists('business_transition_subscriptions');
        Schema::dropIfExists('business_transitions');
    }
};
