<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DiagnosisCommercialControlCenterContractTest extends TestCase
{
    private function read(string $path): string
    {
        return file_get_contents(
            dirname(__DIR__, 3).'/'.$path
        );
    }

    public function test_admin_show_exposes_existing_commercial_states(): void
    {
        $source = $this->read(
            'app/Http/Controllers/Admin/AdminDiagnosisAccessRequestController.php'
        );

        foreach ([
            'DiagnosisExpandedReportCommercialService::class',
            'DiagnosisDetailedRoadmapCommercialService::class',
            "'expanded_report_commercial'",
            "'detailed_roadmap_commercial'",
            "'commercial_endpoints'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_admin_uses_existing_invoice_and_payment_routes(): void
    {
        $source = $this->read(
            'app/Http/Controllers/Admin/AdminDiagnosisAccessRequestController.php'
        );

        foreach ([
            'admin.diagnosis_requests.expanded_report.prepare_invoice',
            'admin.diagnosis_requests.expanded_report.record_payment',
            'admin.diagnosis_requests.detailed_roadmap.prepare_invoice',
            'admin.diagnosis_requests.detailed_roadmap.record_payment',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_client_main_diagnosis_exposes_both_request_routes(): void
    {
        $source = $this->read(
            'app/Http/Controllers/Diagnosis/DigitalDiagnosisController.php'
        );

        foreach ([
            "'request_expanded_report'",
            "'request_detailed_roadmap'",
            'diagnosis.expanded_report.request',
            'diagnosis.detailed_roadmap.request',
            "'detailed_roadmap_commercial'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_control_center_supports_client_requests(): void
    {
        $source = $this->read(
            'resources/js/components/diagnosis/TransformationQuickActions.vue'
        );

        foreach ([
            'Solicitar Informe Ampliado',
            'Solicitar Roadmap Detallado',
            'requestExpandedReport',
            'requestRoadmap',
            'requestExpandedReportUrl',
            'requestRoadmapUrl',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_control_center_supports_admin_invoice_and_payment(): void
    {
        $source = $this->read(
            'resources/js/components/diagnosis/TransformationQuickActions.vue'
        );

        foreach ([
            'Preparar factura',
            'Confirmar pago completo',
            'expanded_prepare_invoice',
            'expanded_record_payment',
            'roadmap_prepare_invoice',
            'roadmap_record_payment',
            "method: 'bank_transfer'",
            "value=\"cash\"",
            "value=\"check\"",
            "value=\"other\"",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_client_does_not_confirm_payments(): void
    {
        $source = $this->read(
            'resources/js/components/diagnosis/TransformationQuickActions.vue'
        );

        $this->assertStringContainsString(
            "<!-- ADMIN -->",
            $source
        );

        $this->assertStringContainsString(
            "v-if=\"mode === 'client'\"",
            $source
        );
    }

    public function test_implementation_plan_contract_remains_preserved(): void
    {
        $source = $this->read(
            'resources/js/components/diagnosis/TransformationQuickActions.vue'
        );

        foreach ([
            'v-if="implementationPlanUrl"',
            ':href="implementationPlanUrl"',
            'Continuar con mi transformación',
            'Plan de Implementación en preparación',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }
}
