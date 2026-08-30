<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisExpandedReportVisualPaymentGateContractTest extends TestCase
{
    public function test_admin_publish_button_has_no_visual_payment_gate(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            .'/resources/js/pages/Admin/DiagnosisRequests/'
            .'ExpandedReport.vue'
        );

        $this->assertStringContainsString(
            'const canPublish = computed(() => canEdit.value);',
            $source
        );

        $this->assertStringContainsString(
            ':disabled="!canPublish"',
            $source
        );

        foreach ([
            'props.commercial?.paid_access === true',
            'Publicación bloqueada hasta pago confirmado.',
            'LockKeyhole',
            'Preparar factura one-time',
            'Registrar pago completo',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }
    }

    public function test_backend_payment_gate_is_removed(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            .'/app/Http/Controllers/Admin/'
            .'AdminDiagnosisExpandedReportController.php'
        );

        foreach ([
            '$commercialService->hasPaidAccess(',
            'solo puede publicarse después de confirmar el pago',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }
    }
}
