<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DiagnosisExpandedReportOrder;
use App\Models\Invoice;
use App\Services\Diagnosis\DiagnosisDetailedRoadmapCommercialService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class DiagnosisDetailedRoadmapCommercialServiceTest extends TestCase
{
    public function test_full_price_uses_percentage_tax_rate(): void
    {
        $snapshot = (
            new DiagnosisDetailedRoadmapCommercialService()
        )->commercialSnapshot(
            null,
            CarbonImmutable::parse(
                '2026-08-23 12:00:00',
                config('app.timezone', 'UTC')
            )
        );

        $this->assertSame(
            95000.0,
            $snapshot['base_subtotal']
        );

        $this->assertFalse(
            $snapshot['credit_eligible']
        );

        $this->assertSame(
            0.0,
            $snapshot['credit_amount']
        );

        $this->assertSame(
            95000.0,
            $snapshot['net_subtotal']
        );

        $this->assertSame(
            18.0,
            $snapshot['tax_rate']
        );

        $this->assertSame(
            17100.0,
            $snapshot['tax_amount']
        );

        $this->assertSame(
            112100.0,
            $snapshot['total']
        );
    }

    public function test_paid_report_gets_full_credit_inside_30_day_window(): void
    {
        $paidAt = CarbonImmutable::parse(
            '2026-08-01 10:00:00',
            config('app.timezone', 'UTC')
        );

        $source =
            $this->paidExpandedOrder(
                $paidAt
            );

        $snapshot = (
            new DiagnosisDetailedRoadmapCommercialService()
        )->commercialSnapshot(
            $source,
            $paidAt->addDays(30)
        );

        $this->assertTrue(
            $snapshot['credit_eligible']
        );

        $this->assertSame(
            'expanded_report_paid_within_window',
            $snapshot['credit_reason']
        );

        $this->assertSame(
            29900.0,
            $snapshot['credit_amount']
        );

        $this->assertSame(
            65100.0,
            $snapshot['net_subtotal']
        );

        $this->assertSame(
            11718.0,
            $snapshot['tax_amount']
        );

        $this->assertSame(
            76818.0,
            $snapshot['total']
        );
    }

    public function test_credit_expires_after_30_day_window(): void
    {
        $paidAt = CarbonImmutable::parse(
            '2026-08-01 10:00:00',
            config('app.timezone', 'UTC')
        );

        $source =
            $this->paidExpandedOrder(
                $paidAt
            );

        $snapshot = (
            new DiagnosisDetailedRoadmapCommercialService()
        )->commercialSnapshot(
            $source,
            $paidAt
                ->addDays(30)
                ->addSecond()
        );

        $this->assertFalse(
            $snapshot['credit_eligible']
        );

        $this->assertSame(
            'credit_window_expired',
            $snapshot['credit_reason']
        );

        $this->assertSame(
            0.0,
            $snapshot['credit_amount']
        );

        $this->assertSame(
            112100.0,
            $snapshot['total']
        );
    }

    public function test_order_without_paid_invoice_cannot_generate_credit(): void
    {
        $paidAt = CarbonImmutable::parse(
            '2026-08-01 10:00:00',
            config('app.timezone', 'UTC')
        );

        $source =
            new DiagnosisExpandedReportOrder();

        $source->forceFill([
            'status' =>
                DiagnosisExpandedReportOrder::STATUS_PAID,
            'paid_at' => $paidAt,
        ]);

        $source->setAttribute(
            'id',
            45
        );

        $source->setRelation(
            'invoice',
            new Invoice([
                'status' => 'issued',
            ])
        );

        $snapshot = (
            new DiagnosisDetailedRoadmapCommercialService()
        )->commercialSnapshot(
            $source,
            $paidAt->addDay()
        );

        $this->assertFalse(
            $snapshot['credit_eligible']
        );

        $this->assertSame(
            'expanded_report_not_paid',
            $snapshot['credit_reason']
        );
    }

    private function paidExpandedOrder(
        CarbonImmutable $paidAt
    ): DiagnosisExpandedReportOrder {
        $source =
            new DiagnosisExpandedReportOrder();

        $source->forceFill([
            'status' =>
                DiagnosisExpandedReportOrder::STATUS_PAID,
            'paid_at' => $paidAt,
        ]);

        $source->setAttribute(
            'id',
            33
        );

        $source->setRelation(
            'invoice',
            new Invoice([
                'status' => 'paid',
            ])
        );

        $this->assertSame(
            $paidAt->getTimestamp(),
            $source->paid_at->getTimestamp(),
            'El fixture debe usar el mismo timezone que Eloquent para no desplazar el instante de paid_at.'
        );

        return $source;
    }
}
