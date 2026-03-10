<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscriber_id')
                ->constrained('subscribers')
                ->cascadeOnDelete();

            $table->foreignId('created_by_user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // trialing | active | past_due | cancelled | expired
            $table->string('status')->default('trialing')->index();

            // monthly | yearly
            $table->string('billing_cycle')->default('monthly')->index();

            $table->enum('currency', ['USD', 'DOP', 'EUR'])->default('DOP');

            // Totales snapshot
            $table->decimal('subtotal_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);

            $table->timestamp('trial_ends_at')->nullable()->index();
            $table->timestamp('current_period_start')->nullable()->index();
            $table->timestamp('current_period_end')->nullable()->index();

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Gateway subscription id (sub_xxx)
            $table->string('provider')->nullable()->index();
            $table->string('provider_subscription_id')->nullable()->index();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['subscriber_id', 'status']);
            $table->index(['subscriber_id', 'billing_cycle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
