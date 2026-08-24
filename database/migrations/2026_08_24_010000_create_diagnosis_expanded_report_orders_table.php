<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'diagnosis_expanded_report_orders',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('diagnosis_assessment_id')
                    ->unique()
                    ->constrained('diagnosis_assessments')
                    ->cascadeOnDelete();

                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('contact_request_id')
                    ->nullable()
                    ->constrained('contact_requests')
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

                $table->decimal('subtotal', 12, 2)
                    ->default(0);

                $table->decimal('tax_rate', 6, 3)
                    ->default(0);

                $table->decimal('tax_amount', 12, 2)
                    ->default(0);

                $table->decimal('total', 12, 2)
                    ->default(0);

                $table->timestamp('requested_at')
                    ->nullable()
                    ->index();

                $table->timestamp('invoiced_at')
                    ->nullable();

                $table->timestamp('paid_at')
                    ->nullable()
                    ->index();

                $table->timestamp('cancelled_at')
                    ->nullable();

                $table->json('meta')
                    ->nullable();

                $table->timestamps();

                $table->index(
                    ['status', 'requested_at'],
                    'diag_exp_order_status_requested_idx'
                );

                $table->index(
                    ['company_id', 'status'],
                    'diag_exp_order_company_status_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'diagnosis_expanded_report_orders'
        );
    }
};
