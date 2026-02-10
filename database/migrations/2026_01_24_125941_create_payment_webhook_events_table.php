<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();

            $table->string('provider');     // "stripe"
            $table->string('event_id');     // evt_xxx
            $table->string('event_type');   // payment_intent.succeeded, invoice.paid, etc.

            $table->enum('status', ['received', 'processed', 'failed'])->default('received');

            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();

            $table->text('error_message')->nullable();

            $table->json('payload'); // evento completo para auditoría

            $table->timestamps();

            $table->unique(['provider', 'event_id']);
            $table->index(['provider', 'event_type']);
            $table->index(['status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
    }
};
