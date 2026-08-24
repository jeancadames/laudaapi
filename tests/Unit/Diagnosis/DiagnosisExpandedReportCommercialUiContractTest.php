<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisExpandedReportCommercialUiContractTest extends TestCase
{
    public function test_routes_exist_in_code(): void
    {
        $root = dirname(__DIR__, 3);
        $web = file_get_contents($root . '/routes/web.php');
        $admin = file_get_contents($root . '/routes/admin.php');

        $this->assertStringContainsString('expanded_report.request', $web);
        $this->assertStringContainsString('expanded_report.prepare_invoice', $admin);
        $this->assertStringContainsString('expanded_report.record_payment', $admin);
    }

    public function test_report_controller_requires_paid_access(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents($root . '/app/Http/Controllers/Diagnosis/DiagnosisExpandedReportController.php');
        $this->assertStringContainsString('hasPaidAccess', $source);
        $this->assertStringContainsString('requiere confirmación de pago', $source);
    }

    public function test_client_ui_supports_commercial_states(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents($root . '/resources/js/components/diagnosis/ExpandedReportCommercialCard.vue');
        foreach (['Solicitar Informe Ampliado', 'Solicitud recibida', 'Factura preparada', 'Pago confirmado'] as $token) {
            $this->assertStringContainsString($token, $source);
        }
    }

    public function test_admin_ui_supports_invoice_and_payment(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents($root . '/resources/js/pages/Admin/DiagnosisRequests/ExpandedReport.vue');
        foreach (['Estado comercial', 'Preparar factura one-time', 'Registrar pago completo', 'Pago confirmado · acceso habilitado'] as $token) {
            $this->assertStringContainsString($token, $source);
        }
    }

    public function test_controllers_do_not_create_subscription(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents($root . '/app/Http/Controllers/Diagnosis/DiagnosisExpandedReportCommercialController.php')
            . file_get_contents($root . '/app/Http/Controllers/Admin/AdminDiagnosisExpandedReportCommercialController.php');

        $this->assertStringNotContainsString('Subscription::create', $source);
        $this->assertStringNotContainsString('ActivationRequest::', $source);
    }
}
