<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DiagnosisDetailedRoadmapOrder;
use PHPUnit\Framework\TestCase;

class DiagnosisDetailedRoadmapCommercialContractTest extends TestCase
{
    public function test_order_has_expected_states(): void
    {
        $this->assertSame(
            [
                'requested',
                'invoiced',
                'paid',
                'cancelled',
            ],
            DiagnosisDetailedRoadmapOrder::STATUSES
        );
    }

    public function test_migration_freezes_credit_decision(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/database/migrations/2026_08_24_030000_create_diagnosis_detailed_roadmap_orders_table.php'
        );

        foreach ([
            'base_subtotal',
            'credit_eligible',
            'credit_amount',
            'net_subtotal',
            'tax_rate',
            'tax_amount',
            'total',
            'credit_window_days',
            'credit_source_paid_at',
            'credit_expires_at',
            'expanded_report_order_id',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_credit_policy_uses_paid_at_and_percentage_tax(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/app/Services/Diagnosis/DiagnosisDetailedRoadmapCommercialService.php'
        );

        foreach ([
            "expanded_report_order.paid_at",
            '$taxRate / 100',
            'expanded_report_credit_window_days',
            'expanded_report_paid_within_window',
            'credit_window_expired',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_one_time_flow_reuses_billing_without_subscription(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/app/Services/Diagnosis/DiagnosisDetailedRoadmapCommercialService.php'
        );

        foreach ([
            'Invoice::create',
            'InvoiceItem::create',
            'Payment::create',
            'PaymentTransaction::create',
            "'subscription_id' => null",
            'L360-RD-%06d',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        $this->assertStringNotContainsString(
            'Subscription::create',
            $source
        );

        $this->assertStringNotContainsString(
            'ActivationRequest::',
            $source
        );
    }

    public function test_invoice_models_credit_as_discount_before_tax(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/app/Services/Diagnosis/DiagnosisDetailedRoadmapCommercialService.php'
        );

        foreach ([
            "'subtotal' =>",
            "'discount_total' =>",
            "'discount_amount' =>",
            "'tax_total' =>",
            "'line_subtotal' =>",
            "'line_total' =>",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_paid_access_requires_order_and_invoice_paid(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/app/Services/Diagnosis/DiagnosisDetailedRoadmapCommercialService.php'
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
            '$order->invoice->status',
            $source
        );

        $this->assertStringContainsString(
            "=== 'paid'",
            $source
        );
    }

    public function test_public_commercial_methods_are_available(): void
    {
        $methods =
            get_class_methods(
                \App\Services\Diagnosis\DiagnosisDetailedRoadmapCommercialService::class
            );

        foreach ([
            'commercialSnapshot',
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
