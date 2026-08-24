<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DiagnosisExpandedReportOrder;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Services\Diagnosis\DiagnosisExpandedReportCommercialService;
use PHPUnit\Framework\TestCase;

class DiagnosisExpandedReportCommercialContractTest extends TestCase
{
    public function test_order_has_expected_states(): void
    {
        $this->assertSame(
            ['requested', 'invoiced', 'paid', 'cancelled'],
            DiagnosisExpandedReportOrder::STATUSES
        );
    }

    public function test_payment_accepts_company_id(): void
    {
        $payment = new Payment();

        $this->assertTrue(
            $payment->isFillable('company_id')
        );

        $this->assertTrue(
            $payment->isFillable('invoice_id')
        );
    }

    public function test_payment_transaction_maps_existing_table(): void
    {
        $transaction = new PaymentTransaction();

        $this->assertSame(
            'payment_transactions',
            $transaction->getTable()
        );
    }

    public function test_one_time_flow_never_creates_subscription_or_activation(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/app/Services/Diagnosis/DiagnosisExpandedReportCommercialService.php'
        );

        $this->assertStringContainsString(
            "'subscription_id' => null",
            $source
        );

        $this->assertStringNotContainsString(
            'Subscription::create',
            $source
        );

        $this->assertStringNotContainsString(
            'ActivationRequest::',
            $source
        );

        $this->assertStringContainsString(
            'Invoice::create',
            $source
        );

        $this->assertStringContainsString(
            'InvoiceItem::create',
            $source
        );

        $this->assertStringContainsString(
            'Payment::create',
            $source
        );

        $this->assertStringContainsString(
            'PaymentTransaction::create',
            $source
        );
    }

    public function test_access_requires_paid_order_and_invoice(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/app/Services/Diagnosis/DiagnosisExpandedReportCommercialService.php'
        );

        $this->assertStringContainsString(
            'function hasPaidAccess(',
            $source
        );

        $this->assertStringContainsString(
            '$order->isPaid()',
            $source
        );

        $this->assertStringContainsString(
            '$order->invoice->status === \'paid\'',
            $source
        );
    }

    public function test_public_flow_is_explicit(): void
    {
        $methods = get_class_methods(
            DiagnosisExpandedReportCommercialService::class
        );

        foreach ([
            'requestPurchase',
            'prepareInvoice',
            'recordFullPayment',
            'hasPaidAccess',
            'state',
        ] as $method) {
            $this->assertContains(
                $method,
                $methods
            );
        }
    }
}
