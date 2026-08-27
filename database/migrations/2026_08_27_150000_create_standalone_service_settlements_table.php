<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'standalone_service_settlements',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'activation_request_service_id'
                );

                $table->unsignedBigInteger(
                    'activation_request_id'
                );

                $table->unsignedBigInteger(
                    'subscriber_id'
                );

                $table->unsignedBigInteger(
                    'company_id'
                );

                $table->unsignedBigInteger(
                    'service_id'
                );

                $table->unsignedBigInteger(
                    'invoice_id'
                );

                $table->unsignedBigInteger(
                    'invoice_item_id'
                );

                $table->unsignedBigInteger(
                    'subscription_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'subscription_item_id'
                )->nullable();

                $table->string(
                    'status',
                    32
                )->default('pending_payment');

                $table->string(
                    'billing_cycle',
                    20
                );

                $table->char(
                    'currency',
                    3
                );

                $table->decimal(
                    'amount_due',
                    12,
                    2
                );

                $table->decimal(
                    'amount_paid',
                    12,
                    2
                )->default(0);

                $table->timestamp(
                    'settled_at'
                )->nullable();

                $table->timestamp(
                    'activated_at'
                )->nullable();

                $table->timestamp(
                    'revoked_at'
                )->nullable();

                $table->text(
                    'failure_reason'
                )->nullable();

                $table->json(
                    'evidence_snapshot'
                )->nullable();

                $table->timestamps();

                /*
                 * Idempotencia:
                 * una request row solo puede producir un settlement.
                 */
                $table->unique(
                    'activation_request_service_id',
                    'sss_request_row_uq'
                );

                /*
                 * Un InvoiceItem concreto no puede conceder
                 * entitlement a dos solicitudes.
                 */
                $table->unique(
                    'invoice_item_id',
                    'sss_invoice_item_uq'
                );

                $table->index(
                    ['invoice_id', 'status'],
                    'sss_invoice_status_idx'
                );

                $table->index(
                    ['subscriber_id', 'service_id'],
                    'sss_subscriber_service_idx'
                );

                $table->foreign(
                    'activation_request_service_id',
                    'sss_reqrow_fk'
                )
                    ->references('id')
                    ->on('activation_request_service')
                    ->restrictOnDelete();

                $table->foreign(
                    'activation_request_id',
                    'sss_request_fk'
                )
                    ->references('id')
                    ->on('activation_requests')
                    ->restrictOnDelete();

                $table->foreign(
                    'subscriber_id',
                    'sss_subscriber_fk'
                )
                    ->references('id')
                    ->on('subscribers')
                    ->restrictOnDelete();

                $table->foreign(
                    'company_id',
                    'sss_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->restrictOnDelete();

                $table->foreign(
                    'service_id',
                    'sss_service_fk'
                )
                    ->references('id')
                    ->on('services')
                    ->restrictOnDelete();

                $table->foreign(
                    'invoice_id',
                    'sss_invoice_fk'
                )
                    ->references('id')
                    ->on('invoices')
                    ->restrictOnDelete();

                $table->foreign(
                    'invoice_item_id',
                    'sss_invoice_item_fk'
                )
                    ->references('id')
                    ->on('invoice_items')
                    ->restrictOnDelete();

                $table->foreign(
                    'subscription_id',
                    'sss_subscription_fk'
                )
                    ->references('id')
                    ->on('subscriptions')
                    ->restrictOnDelete();

                $table->foreign(
                    'subscription_item_id',
                    'sss_sub_item_fk'
                )
                    ->references('id')
                    ->on('subscription_items')
                    ->restrictOnDelete();
            }
        );
    }

    public function down(): void
    {
        if (
            Schema::hasTable(
                'standalone_service_settlements'
            )
            && DB::table(
                'standalone_service_settlements'
            )
                ->where(
                    'status',
                    'activated'
                )
                ->exists()
        ) {
            throw new RuntimeException(
                'No se puede eliminar el ledger standalone: '
                .'existen activaciones comerciales reales.'
            );
        }

        Schema::dropIfExists(
            'standalone_service_settlements'
        );
    }
};
