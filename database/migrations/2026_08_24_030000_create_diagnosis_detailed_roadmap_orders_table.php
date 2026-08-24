<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'diagnosis_detailed_roadmap_orders',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'diagnosis_assessment_id'
                )
                    ->unique(
                        'diag_rdmap_orders_assessment_uq'
                    );

                $table->foreign(
                    'diagnosis_assessment_id',
                    'diag_rdmap_orders_assessment_fk'
                )
                    ->references('id')
                    ->on('diagnosis_assessments')
                    ->cascadeOnDelete();

                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId(
                    'contact_request_id'
                )
                    ->nullable()
                    ->constrained(
                        'contact_requests'
                    )
                    ->nullOnDelete();

                $table->foreignId(
                    'expanded_report_order_id'
                )
                    ->nullable();

                $table->foreign(
                    'expanded_report_order_id',
                    'diag_rdmap_orders_exp_report_fk'
                )
                    ->references('id')
                    ->on(
                        'diagnosis_expanded_report_orders'
                    )
                    ->nullOnDelete();

                $table->foreignId('subscriber_id')
                    ->nullable()
                    ->constrained('subscribers')
                    ->nullOnDelete();

                $table->foreignId('company_id')
                    ->nullable()
                    ->constrained('companies')
                    ->nullOnDelete();

                $table->foreignId('invoice_id')
                    ->nullable()
                    ->unique()
                    ->constrained('invoices')
                    ->nullOnDelete();

                $table->string('status', 30)
                    ->default('requested')
                    ->index();

                $table->string('currency', 3)
                    ->default('DOP');

                $table->decimal(
                    'base_subtotal',
                    12,
                    2
                );

                $table->boolean(
                    'credit_eligible'
                )->default(false);

                $table->decimal(
                    'credit_amount',
                    12,
                    2
                )->default(0);

                $table->decimal(
                    'net_subtotal',
                    12,
                    2
                );

                $table->decimal(
                    'tax_rate',
                    7,
                    3
                )->default(0);

                $table->decimal(
                    'tax_amount',
                    12,
                    2
                )->default(0);

                $table->decimal(
                    'total',
                    12,
                    2
                );

                $table->unsignedSmallInteger(
                    'credit_window_days'
                )->default(30);

                $table->timestamp(
                    'credit_source_paid_at'
                )->nullable();

                $table->timestamp(
                    'credit_expires_at'
                )->nullable();

                $table->timestamp(
                    'requested_at'
                )->nullable();

                $table->timestamp(
                    'invoiced_at'
                )->nullable();

                $table->timestamp(
                    'paid_at'
                )->nullable();

                $table->timestamp(
                    'cancelled_at'
                )->nullable();

                $table->json('meta')->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'diagnosis_assessment_id',
                        'status',
                    ],
                    'diag_roadmap_order_status_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'diagnosis_detailed_roadmap_orders'
        );
    }
};
