<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationClientGoLiveContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_free_plan_does_not_expose_go_live(): void
    {
        $controller = file_get_contents($this->root().'/app/Http/Controllers/Diagnosis/TransformationImplementationPlanController.php');
        $page = file_get_contents($this->root().'/resources/js/pages/Diagnosis/ImplementationPlan.vue');
        foreach (['go_live_summary', "'go_live' =>", 'solution_access_summary', 'recurring_solution'] as $token) {
            $this->assertStringNotContainsString($token, $controller);
        }
        foreach (['capability.go_live', 'Go-Live', 'Ir a mi portal'] as $token) {
            $this->assertStringNotContainsString($token, $page);
        }
    }

    public function test_go_live_domain_remains_preserved(): void
    {
        $routes = file_get_contents($this->root().'/routes/admin.php');
        $this->assertStringContainsString('implementation_execution.go_live.create', $routes);
        $this->assertStringContainsString('implementation_execution.go_live.live', $routes);
    }
}
