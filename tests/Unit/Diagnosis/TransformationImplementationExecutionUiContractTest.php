<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationExecutionUiContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_legacy_execution_routes_remain_preserved(): void
    {
        $routes =
            file_get_contents(
                $this->root()
                .'/routes/admin.php'
            );

        foreach ([
            'implementation_execution.show',
            'implementation_execution.phase.initialize',
            'implementation_execution.go_live.create',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $routes
            );
        }
    }

    public function test_current_plan_journey_does_not_expose_legacy_execution(): void
    {
        $controller =
            file_get_contents(
                $this->root()
                .'/app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationPlanController.php'
            );

        $page =
            file_get_contents(
                $this->root()
                .'/resources/js/pages/Admin/DiagnosisRequests/'
                .'ImplementationPlan.vue'
            );

        foreach ([
            'Gestionar ejecución y Go-Live',
            'execution_url',
            'implementation_execution',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $controller.$page
            );
        }

        $this->assertStringContainsString(
            'Definición de Implementación',
            $page
        );
    }

    public function test_execution_remains_a_separate_legacy_surface(): void
    {
        $this->assertFileExists(
            $this->root()
            .'/resources/js/pages/Admin/DiagnosisRequests/'
            .'ImplementationExecution.vue'
        );

        $this->assertFileExists(
            $this->root()
            .'/app/Http/Controllers/Admin/'
            .'AdminTransformationImplementationExecutionController.php'
        );
    }
}
