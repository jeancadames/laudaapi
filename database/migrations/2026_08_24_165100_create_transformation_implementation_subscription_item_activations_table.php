<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transformation_implementation_subscription_item_activations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transformation_implementation_capability_go_live_id');

            $table->foreign(
                'transformation_implementation_capability_go_live_id',
                'tip_sia_go_live_fk'
            )
                ->references('id')
                ->on('transformation_implementation_capability_go_lives')
                ->cascadeOnDelete();

            $table->foreignId('transformation_implementation_subscription_activation_id');

            $table->foreign(
                'transformation_implementation_subscription_activation_id',
                'tip_sia_sub_activation_fk'
            )
                ->references('id')
                ->on('transformation_implementation_subscription_activations')
                ->cascadeOnDelete();

            $table->foreignId('transformation_implementation_capability_service_mapping_id');

            $table->foreign(
                'transformation_implementation_capability_service_mapping_id',
                'tip_sia_mapping_fk'
            )
                ->references('id')
                ->on('transformation_implementation_capability_service_mappings')
                ->restrictOnDelete();

            $table->foreignId('service_id');

            $table->foreign(
                'service_id',
                'tip_sia_service_fk'
            )
                ->references('id')
                ->on('services')
                ->restrictOnDelete();

            $table->foreignId('subscription_item_id');

            $table->foreign(
                'subscription_item_id',
                'tip_sia_item_fk'
            )
                ->references('id')
                ->on('subscription_items')
                ->restrictOnDelete();

            $table->string('activation_type', 30);
            $table->string('status', 20)->default('active');
            $table->json('price_snapshot')->nullable();
            $table->timestamp('activated_at');
            $table->text('internal_notes')->nullable();

            $table->foreignId('created_by_user_id')->nullable();

            $table->foreign(
                'created_by_user_id',
                'tip_sia_created_fk'
            )
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['transformation_implementation_capability_go_live_id'],
                'tip_sia_go_live_uq'
            );

            $table->index(
                ['service_id', 'subscription_item_id'],
                'tip_sia_service_item_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transformation_implementation_subscription_item_activations');
    }
};
