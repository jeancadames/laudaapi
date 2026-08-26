<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'service_bundle_discount_rules',
            function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger(
                    'bundle_service_id'
                );

                $table->foreign(
                    'bundle_service_id',
                    'sbdr_bundle_service_fk'
                )
                    ->references('id')
                    ->on('services')
                    ->cascadeOnDelete();

                $table->string('code')
                    ->unique();

                $table->string('name');

                $table->enum(
                    'discount_type',
                    [
                        'percentage',
                        'fixed_amount',
                    ]
                );

                $table->decimal(
                    'discount_value',
                    12,
                    4
                );

                $table->string(
                    'currency',
                    3
                )->nullable();

                $table->integer('priority')
                    ->default(0);

                $table->boolean('active')
                    ->default(true);

                $table->json('metadata')
                    ->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'active',
                        'priority',
                    ],
                    'bundle_discount_rules_active_priority_idx'
                );

                $table->index(
                    [
                        'bundle_service_id',
                        'active',
                    ],
                    'bundle_discount_rules_bundle_active_idx'
                );
            }
        );

        Schema::create(
            'subscription_bundle_discount_applications',
            function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger(
                    'subscription_id'
                );

                $table->foreign(
                    'subscription_id',
                    'sbda_subscription_fk'
                )
                    ->references('id')
                    ->on('subscriptions')
                    ->cascadeOnDelete();

                $table->unsignedBigInteger(
                    'rule_id'
                );

                $table->foreign(
                    'rule_id',
                    'sbda_rule_fk'
                )
                    ->references('id')
                    ->on(
                        'service_bundle_discount_rules'
                    )
                    ->cascadeOnDelete();

                $table->unsignedBigInteger(
                    'bundle_service_id'
                );

                $table->foreign(
                    'bundle_service_id',
                    'sbda_bundle_service_fk'
                )
                    ->references('id')
                    ->on('services')
                    ->cascadeOnDelete();

                $table->decimal(
                    'bundle_base_amount',
                    12,
                    2
                );

                $table->decimal(
                    'discount_amount',
                    12,
                    2
                );

                $table->string(
                    'currency',
                    3
                );

                $table->json(
                    'matched_service_ids'
                );

                $table->string(
                    'fingerprint',
                    64
                );

                $table->json('snapshot');

                $table->boolean('active')
                    ->default(true);

                $table->timestamp(
                    'applied_at'
                );

                $table->timestamp(
                    'superseded_at'
                )->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'subscription_id',
                        'active',
                    ],
                    'bundle_discount_apps_subscription_active_idx'
                );

                $table->index(
                    [
                        'rule_id',
                        'active',
                    ],
                    'bundle_discount_apps_rule_active_idx'
                );

                $table->index(
                    [
                        'subscription_id',
                        'fingerprint',
                    ],
                    'bundle_discount_apps_fingerprint_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'subscription_bundle_discount_applications'
        );

        Schema::dropIfExists(
            'service_bundle_discount_rules'
        );
    }
};
