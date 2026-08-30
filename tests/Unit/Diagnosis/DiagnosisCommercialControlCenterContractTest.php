<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisCommercialControlCenterContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function read(string $path): string
    {
        return file_get_contents(
            $this->root().'/'.$path
        );
    }

    public function test_active_routes_have_no_report_purchase_or_billing_endpoints(): void
    {
        $web = $this->read('routes/web.php');
        $admin = $this->read('routes/admin.php');

        foreach ([
            'expanded_report.request',
            'detailed_roadmap.request',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $web
            );
        }

        foreach ([
            'expanded_report.prepare_invoice',
            'expanded_report.record_payment',
            'detailed_roadmap.prepare_invoice',
            'detailed_roadmap.record_payment',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $admin
            );
        }
    }

    public function test_main_client_and_admin_diagnosis_have_no_commercial_state(): void
    {
        $client = $this->read(
            'app/Http/Controllers/Diagnosis/DigitalDiagnosisController.php'
        );

        $admin = $this->read(
            'app/Http/Controllers/Admin/AdminDiagnosisAccessRequestController.php'
        );

        foreach ([
            'expanded_report_commercial',
            'detailed_roadmap_commercial',
            'request_expanded_report',
            'request_detailed_roadmap',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $client
            );
        }

        foreach ([
            'expanded_report_commercial',
            'detailed_roadmap_commercial',
            'commercial_endpoints',
            '$expandedCommercial',
            '$roadmapCommercial',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $admin
            );
        }
    }

    public function test_quick_actions_are_consultive_not_commercial(): void
    {
        $source = $this->read(
            'resources/js/components/diagnosis/TransformationQuickActions.vue'
        );

        foreach ([
            'CommercialState',
            'AdminCommercialEndpoints',
            'expandedReportCommercial',
            'roadmapCommercial',
            'requestExpandedReportUrl',
            'requestRoadmapUrl',
            'commercialEndpoints',
            'paid_access',
            'Preparar factura',
            'Confirmar pago completo',
            'Factura preparada',
            'Pago confirmado',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }

        foreach ([
            'Informe Ampliado',
            'Roadmap Detallado',
            'Gestionar Informe Ampliado',
            'Gestionar Roadmap Detallado',
            'Continuar con mi transformación',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_historical_commercial_code_is_preserved_without_active_routes(): void
    {
        foreach ([
            'app/Services/Diagnosis/DiagnosisExpandedReportCommercialService.php',
            'app/Services/Diagnosis/DiagnosisDetailedRoadmapCommercialService.php',
            'app/Http/Controllers/Diagnosis/DiagnosisExpandedReportCommercialController.php',
            'app/Http/Controllers/Diagnosis/DiagnosisDetailedRoadmapCommercialController.php',
            'app/Http/Controllers/Admin/AdminDiagnosisExpandedReportCommercialController.php',
            'app/Http/Controllers/Admin/AdminDiagnosisDetailedRoadmapCommercialController.php',
        ] as $path) {
            $this->assertFileExists(
                $this->root().'/'.$path
            );
        }
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
