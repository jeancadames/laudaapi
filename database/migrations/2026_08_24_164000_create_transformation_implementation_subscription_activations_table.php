<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transformation_implementation_subscription_activations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transformation_implementation_capability_go_live_id');

            $table->foreign(
                'transformation_implementation_capability_go_live_id',
                'tip_sa_go_live_fk'
            )
                ->references('id')
                ->on('transformation_implementation_capability_go_lives')
                ->cascadeOnDelete();

            $table->foreignId('subscriber_id');

            $table->foreign(
                'subscriber_id',
                'tip_sa_subscriber_fk'
            )
                ->references('id')
                ->on('subscribers')
                ->restrictOnDelete();

            $table->foreignId('company_id');

            $table->foreign(
                'company_id',
                'tip_sa_company_fk'
            )
                ->references('id')
                ->on('companies')
                ->restrictOnDelete();

            $table->foreignId('subscription_id');

            $table->foreign(
                'subscription_id',
                'tip_sa_subscription_fk'
            )
                ->references('id')
                ->on('subscriptions')
                ->restrictOnDelete();

            $table->string('activation_type', 30);
            $table->string('status', 20)->default('active');

            $table->timestamp('go_live_at');
            $table->timestamp('subscription_started_at');
            $table->json('source_snapshot')->nullable();
            $table->text('internal_notes')->nullable();

            $table->foreignId('created_by_user_id')->nullable();

            $table->foreign(
                'created_by_user_id',
                'tip_sa_created_fk'
            )
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['transformation_implementation_capability_go_live_id'],
                'tip_sa_go_live_uq'
            );

            $table->index(
                ['subscriber_id', 'subscription_id'],
                'tip_sa_sub_subscription_idx'
            );

            $table->index(
                ['company_id', 'status'],
                'tip_sa_company_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transformation_implementation_subscription_activations');
    }
};
