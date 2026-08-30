<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationModalitySelectionUiContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_modality_selection_is_not_active_in_free_plan(): void
    {
        $routes = file_get_contents($this->root().'/routes/admin.php');
        $controller = file_get_contents($this->root().'/app/Http/Controllers/Admin/AdminTransformationImplementationPlanController.php');
        $page = file_get_contents($this->root().'/resources/js/pages/Admin/DiagnosisRequests/ImplementationPlan.vue');
        foreach (['implementation-plan/modality', 'implementation_plan.modality.select'] as $token) {
            $this->assertStringNotContainsString($token, $routes);
        }
        foreach (['selectModality(', 'modality_options', 'modality_select', 'selected_modality_label'] as $token) {
            $this->assertStringNotContainsString($token, $controller.$page);
        }
    }

    public function test_modality_catalog_and_service_are_preserved(): void
    {
        $this->assertFileExists($this->root().'/app/Services/Diagnosis/TransformationImplementationModalityCatalog.php');
        $this->assertFileExists($this->root().'/app/Services/Diagnosis/TransformationImplementationModalityService.php');
    }
}
