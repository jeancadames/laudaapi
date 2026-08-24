<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisExpandedReportVisualPaymentGateContractTest extends TestCase
{
    public function test_admin_publish_button_is_visually_gated(): void
    {
        $root = dirname(__DIR__, 3);

        $source =
            file_get_contents(
                $root
                . '/resources/js/pages/Admin/DiagnosisRequests/ExpandedReport.vue'
            );

        foreach ([
            'const canPublish = computed(',
            'props.commercial?.paid_access === true',
            ':disabled="!canPublish"',
            'Publicación bloqueada hasta pago confirmado.',
            'LockKeyhole',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_backend_gate_remains_authoritative(): void
    {
        $root = dirname(__DIR__, 3);

        $source =
            file_get_contents(
                $root
                . '/app/Http/Controllers/Admin/AdminDiagnosisExpandedReportController.php'
            );

        $this->assertStringContainsString(
            '$commercialService->hasPaidAccess(',
            $source
        );

        $this->assertStringContainsString(
            'solo puede publicarse después de confirmar el pago',
            $source
        );
    }
}
