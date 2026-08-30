<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisExpandedReportPublicationPaymentGateContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_admin_publication_no_longer_requires_paid_entitlement(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Admin/'
            .'AdminDiagnosisExpandedReportController.php'
        );

        foreach ([
            'DiagnosisExpandedReportCommercialService',
            'hasPaidAccess(',
            'solo puede publicarse después de confirmar el pago',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }

        $this->assertStringContainsString(
            '$reportService->publish(',
            $source
        );
    }

    public function test_publication_still_does_not_create_billing_or_subscription(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Admin/'
            .'AdminDiagnosisExpandedReportController.php'
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

    public function test_historical_commercial_service_remains_available(): void
    {
        $this->assertFileExists(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'DiagnosisExpandedReportCommercialService.php'
        );
    }
}
