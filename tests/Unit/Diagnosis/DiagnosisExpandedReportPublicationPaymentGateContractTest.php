<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisExpandedReportPublicationPaymentGateContractTest extends TestCase
{
    public function test_admin_publication_requires_paid_entitlement(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/app/Http/Controllers/Admin/AdminDiagnosisExpandedReportController.php'
        );

        foreach ([
            'DiagnosisExpandedReportCommercialService',
            'hasPaidAccess(',
            'El Informe Ampliado solo puede publicarse después de confirmar el pago.',
            '$reportService->publish(',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_payment_gate_executes_before_report_publication(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/app/Http/Controllers/Admin/AdminDiagnosisExpandedReportController.php'
        );

        $publishStart = strpos(
            $source,
            'public function publish('
        );

        $gate = strpos(
            $source,
            '$commercialService->hasPaidAccess(',
            $publishStart
        );

        $publish = strpos(
            $source,
            '$reportService->publish(',
            $publishStart
        );

        $this->assertNotFalse(
            $publishStart
        );

        $this->assertNotFalse(
            $gate
        );

        $this->assertNotFalse(
            $publish
        );

        $this->assertLessThan(
            $publish,
            $gate,
            'El gate de pago debe ejecutarse antes de publicar.'
        );
    }

    public function test_gate_does_not_create_billing_or_subscription(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/app/Http/Controllers/Admin/AdminDiagnosisExpandedReportController.php'
        );

        foreach ([
            'Invoice::create',
            'Payment::create',
            'PaymentTransaction::create',
            'Subscription::create',
            'ActivationRequest::',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }
}
