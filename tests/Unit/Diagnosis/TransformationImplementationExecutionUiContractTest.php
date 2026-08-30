<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationExecutionUiContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_execution_subsystem_is_preserved_but_not_linked_from_free_plan(): void
    {
        $routes = file_get_contents($this->root().'/routes/admin.php');
        $page = file_get_contents($this->root().'/resources/js/pages/Admin/DiagnosisRequests/ImplementationPlan.vue');
        foreach (['implementation_execution.show', 'implementation_execution.phase.initialize', 'implementation_execution.go_live.create'] as $token) {
            $this->assertStringContainsString($token, $routes);
        }
        foreach (['Gestionar ejecución y Go-Live', 'implementation_execution', 'execution_url'] as $token) {
            $this->assertStringNotContainsString($token, $page);
        }
    }

    public function test_execution_page_and_controller_remain_historical_domain(): void
    {
        $this->assertFileExists($this->root().'/app/Http/Controllers/Admin/AdminTransformationImplementationExecutionController.php');
        $this->assertFileExists($this->root().'/resources/js/pages/Admin/DiagnosisRequests/ImplementationExecution.vue');
    }
}
