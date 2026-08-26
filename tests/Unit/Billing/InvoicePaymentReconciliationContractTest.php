<?php

namespace Tests\Unit\Billing;

use PHPUnit\Framework\TestCase;

class InvoicePaymentReconciliationContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function source(string $relative): string
    {
        return file_get_contents(
            $this->root().'/'.$relative
        );
    }

    public function test_invoice_item_delegates_to_central_reconciliation(): void
    {
        $source = $this->source(
            'app/Models/InvoiceItem.php'
        );

        foreach ([
            'InvoiceReconciliationService::class',
            'recalculateFromItems(',
            "getOriginal('invoice_id')",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        $this->assertStringNotContainsString(
            'recalcInvoiceTotals',
            $source
        );
    }

    public function test_payment_delegates_and_validates_before_save(): void
    {
        $source = $this->source(
            'app/Models/Payment.php'
        );

        foreach ([
            'static::saving(',
            'assertPaymentConsistency(',
            'InvoiceReconciliationService::class',
            'reconcilePayments(',
            "getOriginal('invoice_id')",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        foreach ([
            'afterPaymentChanged',
            'syncInvoiceFromPayments',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_invoice_items_are_the_source_of_invoice_totals(): void
    {
        $source = $this->source(
            'app/Services/Billing/InvoiceReconciliationService.php'
        );

        foreach ([
            "DB::table('invoice_items')",
            'SUM(line_subtotal)',
            'SUM(discount_amount)',
            'SUM(tax_amount)',
            'SUM(line_total)',
            "'subtotal' =>",
            "'discount_total' =>",
            "'tax_total' =>",
            "'total' =>",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_posted_payments_are_the_source_of_amount_paid_and_status(): void
    {
        $source = $this->source(
            'app/Services/Billing/InvoiceReconciliationService.php'
        );

        foreach ([
            "->where('invoice_id', \$invoice->id)",
            "->whereNotNull('paid_at')",
            "'amount_paid' =>",
            "'status' => 'paid'",
            "'overdue'",
            "'issued'",
            "status === 'void'",
            "status === 'draft'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_payment_currency_company_and_amount_are_guarded(): void
    {
        $source = $this->source(
            'app/Services/Billing/InvoiceReconciliationService.php'
        );

        foreach ([
            'assertPaymentConsistency(',
            'No se permite conversión FX implícita.',
            'La compañía del Payment debe coincidir con la compañía del Invoice.',
            'El monto del Payment debe ser mayor o igual a cero.',
            '$payment->currency = $paymentCurrency;',
            '$payment->company_id = $invoiceCompanyId;',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_item_recalculation_also_reconciles_payment_status(): void
    {
        $source = $this->source(
            'app/Services/Billing/InvoiceReconciliationService.php'
        );

        $method = explode(
            'public function recalculateFromItems(',
            $source,
            2
        )[1];

        $method = explode(
            'public function reconcilePayments(',
            $method,
            2
        )[0];

        $this->assertStringContainsString(
            'reconcilePaymentsLocked(',
            $method
        );
    }

    public function test_invoice_reconciliation_never_reprices_subscription(): void
    {
        $source = $this->source(
            'app/Services/Billing/InvoiceReconciliationService.php'
        );

        foreach ([
            'ServicePricingEngine',
            'SubscriptionTotalsService',
            'monthly_price',
            'yearly_price',
            'SubscriptionItem',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_lauda360_one_time_invoices_remain_decoupled_from_subscription(): void
    {
        foreach ([
            'app/Services/Diagnosis/DiagnosisExpandedReportCommercialService.php',
            'app/Services/Diagnosis/DiagnosisDetailedRoadmapCommercialService.php',
        ] as $relative) {
            $source = $this->source(
                $relative
            );

            $this->assertStringContainsString(
                "'subscription_id' => null",
                $source
            );

            $this->assertStringContainsString(
                "'currency' => \$invoice->currency",
                $source
            );
        }
    }
}
