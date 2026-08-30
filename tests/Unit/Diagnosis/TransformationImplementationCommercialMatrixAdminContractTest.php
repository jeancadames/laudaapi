<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationCommercialMatrixAdminContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_commercial_settings_remain_standalone_and_preserved(): void
    {
        $routes = file_get_contents($this->root().'/routes/admin.php');
        $this->assertStringContainsString('transformation360.commercial_settings.show', $routes);
        $this->assertStringContainsString('transformation360.commercial_settings.update', $routes);
        $this->assertFileExists($this->root().'/app/Services/Diagnosis/TransformationImplementationCommercialMatrixService.php');
    }

    public function test_free_plan_does_not_link_or_read_commercial_matrix(): void
    {
        $controller = file_get_contents($this->root().'/app/Http/Controllers/Admin/AdminTransformationImplementationPlanController.php');
        $page = file_get_contents($this->root().'/resources/js/pages/Admin/DiagnosisRequests/ImplementationPlan.vue');
        foreach (['TransformationImplementationCommercialMatrixService', 'commercial_matrix_readiness', '/admin/transformation-360/commercial-settings'] as $token) {
            $this->assertStringNotContainsString($token, $controller.$page);
        }
    }
}
