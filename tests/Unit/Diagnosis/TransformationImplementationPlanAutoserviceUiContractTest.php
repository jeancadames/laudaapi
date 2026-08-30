<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationPlanAutoserviceUiContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_admin_plan_is_consultive_supervisor_ui(): void
    {
        $page = file_get_contents($this->root().'/resources/js/pages/Admin/DiagnosisRequests/ImplementationPlan.vue');
        foreach (['1. Fuente del Plan', '2. Alcance consultivo', '3. Plan generado', '4. Presentación', 'Regenerar estructura desde la fuente', 'Presentar al tenant', 'Diagnóstico oficial · snapshot interno'] as $token) {
            $this->assertStringContainsString($token, $page);
        }
        foreach (['modality_options', 'commercial_matrix_readiness', 'estimateForms', 'milestoneForms', 'commercial_generate', 'Aceptar Plan'] as $token) {
            $this->assertStringNotContainsString($token, $page);
        }
    }
}
