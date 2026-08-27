<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'service_plans',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('service_id')
                    ->constrained('services')
                    ->restrictOnDelete();

                /*
                 * Identidad comercial local del espejo central.
                 */
                $table->string('code', 120);
                $table->string('name');
                $table->text('description')->nullable();

                /*
                 * Contrato de pricing que el Hub necesita para cotizar.
                 *
                 * La solución sigue siendo source-of-truth del producto.
                 * Estos campos son una copia/sincronización comercial.
                 */
                $table->string('currency', 3)->default('DOP');
                $table->string('billing_model', 30)->default('flat');

                $table->decimal(
                    'monthly_price',
                    12,
                    2
                )->nullable();

                $table->decimal(
                    'yearly_price',
                    12,
                    2
                )->nullable();

                $table->unsignedInteger(
                    'block_size'
                )->nullable();

                $table->unsignedInteger(
                    'included_units'
                )->nullable();

                $table->string(
                    'unit_name'
                )->nullable();

                $table->decimal(
                    'overage_unit_price',
                    12,
                    4
                )->nullable();

                /*
                 * Información de producto proveniente de la solución.
                 * El Hub puede mostrarla, pero no se convierte en
                 * source-of-truth operativo.
                 */
                $table->json('features')->nullable();
                $table->json('limits')->nullable();

                /*
                 * Trazabilidad al dueño real del plan.
                 */
                $table->string(
                    'source_solution',
                    80
                );

                $table->string(
                    'source_plan_key',
                    120
                );

                $table->string(
                    'source_revision'
                )->nullable();

                $table->json(
                    'source_snapshot'
                )->nullable();

                $table->timestamp(
                    'synced_at'
                )->nullable();

                $table->boolean(
                    'active'
                )->default(true);

                $table->unsignedInteger(
                    'sort_order'
                )->default(0);

                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(
                    [
                        'service_id',
                        'code',
                    ],
                    'service_plans_service_code_uq'
                );

                $table->unique(
                    [
                        'source_solution',
                        'source_plan_key',
                    ],
                    'service_plans_source_key_uq'
                );

                $table->index(
                    [
                        'service_id',
                        'active',
                        'sort_order',
                    ],
                    'service_plans_service_active_sort_idx'
                );
            }
        );

        /*
         * Tiers de cantidad POR PLAN.
         * No confundir con service_pricing_tiers legacy.
         */
        Schema::create(
            'service_plan_pricing_tiers',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'service_plan_id'
                )
                    ->constrained('service_plans')
                    ->cascadeOnDelete();

                $table->string(
                    'billing_cycle',
                    20
                );

                $table->unsignedInteger(
                    'min_quantity'
                );

                $table->unsignedInteger(
                    'max_quantity'
                )->nullable();

                $table->decimal(
                    'price',
                    12,
                    2
                );

                $table->string(
                    'currency',
                    3
                )->default('DOP');

                $table->boolean(
                    'active'
                )->default(true);

                $table->unsignedInteger(
                    'sort_order'
                )->default(0);

                $table->json(
                    'metadata'
                )->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'service_plan_id',
                        'billing_cycle',
                        'active',
                        'min_quantity',
                    ],
                    'sppt_plan_cycle_active_min_idx'
                );
            }
        );

        Schema::table(
            'activation_request_service',
            function (Blueprint $table): void {
                $table->foreignId(
                    'service_plan_id'
                )
                    ->nullable()
                    ->constrained('service_plans')
                    ->restrictOnDelete();
            }
        );

        Schema::table(
            'invoice_items',
            function (Blueprint $table): void {
                $table->foreignId(
                    'service_plan_id'
                )
                    ->nullable()
                    ->constrained('service_plans')
                    ->restrictOnDelete();
            }
        );

        Schema::table(
            'standalone_service_settlements',
            function (Blueprint $table): void {
                $table->foreignId(
                    'service_plan_id'
                )
                    ->nullable()
                    ->constrained('service_plans')
                    ->restrictOnDelete();
            }
        );

        Schema::table(
            'subscription_items',
            function (Blueprint $table): void {
                $table->foreignId(
                    'service_plan_id'
                )
                    ->nullable()
                    ->constrained('service_plans')
                    ->restrictOnDelete();

                /*
                 * Ciclo por solución/SubscriptionItem.
                 * Subscription.billing_cycle queda como legacy/default
                 * hasta S10-F2.
                 */
                $table->string(
                    'billing_cycle',
                    20
                )->nullable();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'subscription_items',
            function (Blueprint $table): void {
                $table->dropColumn(
                    'billing_cycle'
                );

                $table->dropConstrainedForeignId(
                    'service_plan_id'
                );
            }
        );

        Schema::table(
            'standalone_service_settlements',
            function (Blueprint $table): void {
                $table->dropConstrainedForeignId(
                    'service_plan_id'
                );
            }
        );

        Schema::table(
            'invoice_items',
            function (Blueprint $table): void {
                $table->dropConstrainedForeignId(
                    'service_plan_id'
                );
            }
        );

        Schema::table(
            'activation_request_service',
            function (Blueprint $table): void {
                $table->dropConstrainedForeignId(
                    'service_plan_id'
                );
            }
        );

        Schema::dropIfExists(
            'service_plan_pricing_tiers'
        );

        Schema::dropIfExists(
            'service_plans'
        );
    }
};
