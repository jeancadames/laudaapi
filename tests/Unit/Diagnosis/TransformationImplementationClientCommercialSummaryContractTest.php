<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationClientCommercialSummaryContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_client_payload_is_consultive_only(): void
    {
        $controller = file_get_contents($this->root().'/app/Http/Controllers/Diagnosis/TransformationImplementationPlanController.php');
        foreach (["'horizon' =>", "'initiatives' =>", "'dependencies' =>", "'deliverables' =>", "'capabilities' =>"] as $token) {
            $this->assertStringContainsString($token, $controller);
        }
        foreach (['commercial_summary', 'selected_modality', 'recommended_modality', "'estimate' =>", "'estimates' =>", "'milestones' =>", 'billing_amount', 'price_amount'] as $token) {
            $this->assertStringNotContainsString($token, $controller);
        }
    }

    public function test_client_ui_has_no_prices_or_billing(): void
    {
        $page = file_get_contents($this->root().'/resources/js/pages/Diagnosis/ImplementationPlan.vue');
        foreach (['No selecciona modalidad', 'no contiene precios ni hitos de facturación', 'Iniciativas, actividades y responsables', 'Dependencias', 'Entregables'] as $token) {
            $this->assertStringContainsString($token, $page);
        }
        foreach (['money(', 'commercial_summary', 'billing_amount', 'milestoneStatusLabel', 'selected_modality_label'] as $token) {
            $this->assertStringNotContainsString($token, $page);
        }
    }
}
