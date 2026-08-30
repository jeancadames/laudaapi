<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationClientExecutionProgressContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_free_plan_does_not_expose_execution_progress(): void
    {
        $controller = file_get_contents($this->root().'/app/Http/Controllers/Diagnosis/TransformationImplementationPlanController.php');
        $page = file_get_contents($this->root().'/resources/js/pages/Diagnosis/ImplementationPlan.vue');
        foreach (['execution_summary', "'phases.execution'", "'phases.capabilities.execution'", 'progress_percentage'] as $token) {
            $this->assertStringNotContainsString($token, $controller);
        }
        foreach (['executionStatusLabel', 'phase.execution', 'Gestionar ejecución'] as $token) {
            $this->assertStringNotContainsString($token, $page);
        }
    }

    public function test_execution_domain_is_preserved_outside_free_plan(): void
    {
        $this->assertFileExists($this->root().'/app/Services/Diagnosis/TransformationImplementationExecutionService.php');
        $adminRoutes = file_get_contents($this->root().'/routes/admin.php');
        $this->assertStringContainsString('implementation_execution.show', $adminRoutes);
    }
}
