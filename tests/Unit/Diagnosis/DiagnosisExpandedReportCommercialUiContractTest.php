<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisExpandedReportCommercialUiContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_expanded_report_is_free_in_active_routes(): void
    {
        $web = file_get_contents(
            $this->root().'/routes/web.php'
        );

        $admin = file_get_contents(
            $this->root().'/routes/admin.php'
        );

        $this->assertStringNotContainsString(
            'expanded_report.request',
            $web
        );

        foreach ([
            'expanded_report.prepare_invoice',
            'expanded_report.record_payment',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $admin
            );
        }
    }

    public function test_report_controller_reads_published_report_without_paid_access(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Diagnosis/'
            .'DiagnosisExpandedReportController.php'
        );

        foreach ([
            'hasPaidAccess',
            'requiere confirmación de pago',
            'DiagnosisExpandedReportCommercialService',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }

        $this->assertStringContainsString(
            'DiagnosisExpandedReport::STATUS_PUBLISHED',
            $source
        );
    }

    public function test_active_client_and_admin_ui_have_no_purchase_flow(): void
    {
        $client = file_get_contents(
            $this->root()
            .'/resources/js/pages/Diagnosis/Show.vue'
        );

        $reportPage = file_get_contents(
            $this->root()
            .'/resources/js/pages/Diagnosis/ExpandedReport.vue'
        );

        $admin = file_get_contents(
            $this->root()
            .'/resources/js/pages/Admin/DiagnosisRequests/'
            .'ExpandedReport.vue'
        );

        foreach ([
            'ExpandedReportCommercialCard',
            'request_expanded_report',
            'expanded_report_commercial',
            'paid_access',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $client
            );
        }

        foreach ([
            'DetailedRoadmapCommercialCard',
            'detailed_roadmap_commercial',
            'detailed_roadmap_request_url',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $reportPage
            );
        }

        foreach ([
            'Estado comercial',
            'Preparar factura one-time',
            'Registrar pago completo',
            'Pago confirmado · acceso habilitado',
            'props.commercial?.paid_access',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $admin
            );
        }

        $this->assertStringContainsString(
            'Entregable gratuito del Diagnóstico 360',
            $admin
        );
    }

    public function test_historical_commercial_code_is_preserved(): void
    {
        foreach ([
            '/resources/js/components/diagnosis/ExpandedReportCommercialCard.vue',
            '/app/Http/Controllers/Diagnosis/DiagnosisExpandedReportCommercialController.php',
            '/app/Http/Controllers/Admin/AdminDiagnosisExpandedReportCommercialController.php',
            '/app/Services/Diagnosis/DiagnosisExpandedReportCommercialService.php',
        ] as $path) {
            $this->assertFileExists(
                $this->root().$path
            );
        }
    }
}
