<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationCommercialScenarioGenerationContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_commercial_scenario_generation_is_not_an_active_plan_route(): void
    {
        $routes = file_get_contents($this->root().'/routes/admin.php');
        $controller = file_get_contents($this->root().'/app/Http/Controllers/Admin/AdminTransformationImplementationPlanController.php');
        $page = file_get_contents($this->root().'/resources/js/pages/Admin/DiagnosisRequests/ImplementationPlan.vue');
        foreach (['implementation-plan/commercial-scenarios', 'implementation_plan.commercial.generate'] as $token) {
            $this->assertStringNotContainsString($token, $routes);
        }
        foreach (['generateCommercialScenarios(', 'TransformationImplementationCommercialEngine', 'commercial_generate', 'commercial_matrix_readiness'] as $token) {
            $this->assertStringNotContainsString($token, $controller.$page);
        }
    }

    public function test_commercial_engine_is_preserved_for_future_external_flow(): void
    {
        $this->assertFileExists($this->root().'/app/Services/Diagnosis/TransformationImplementationCommercialEngine.php');
        $this->assertFileExists($this->root().'/app/Services/Diagnosis/TransformationImplementationCommercialMatrixService.php');
    }
}
