<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationDefinitionJourneyClosureContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function read(string $path): string
    {
        return file_get_contents(
            $this->root().'/'.$path
        );
    }

    public function test_definition_ready_is_explicit_end_of_current_stage(): void
    {
        $page =
            $this->read(
                'resources/js/pages/Admin/DiagnosisRequests/'
                .'ImplementationDefinition.vue'
            );

        foreach ([
            'Etapa de definición completada',
            'Esta etapa termina',
            'no inicia ejecución',
            'no activa',
            'Cierre de esta etapa',
            'No existe acción para',
            'iniciar ejecución desde aquí',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $page
            );
        }
    }

    public function test_plan_advances_to_definition_not_execution(): void
    {
        $page =
            $this->read(
                'resources/js/pages/Admin/DiagnosisRequests/'
                .'ImplementationPlan.vue'
            );

        $controller =
            $this->read(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationPlanController.php'
            );

        $this->assertStringContainsString(
            'Definición de Implementación',
            $page
        );

        foreach ([
            'execution_url',
            'implementation_execution',
            'Gestionar ejecución y Go-Live',
            '/admin/transformation-360/commercial-settings',
            'commercial_matrix_readiness',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $page.$controller
            );
        }
    }

    public function test_definition_surface_has_no_downstream_action(): void
    {
        $page =
            $this->read(
                'resources/js/pages/Admin/DiagnosisRequests/'
                .'ImplementationDefinition.vue'
            );

        foreach ([
            'implementation_execution',
            '/execution',
            'commercial_settings',
            'commercial-settings',
            'Iniciar ejecución',
            'Gestionar ejecución',
            'Go-Live',
            'Activar suscripción',
            'Facturar',
            'Cotizar',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $page
            );
        }
    }

    public function test_diagnosis_admin_show_does_not_skip_definition_into_execution(): void
    {
        $page =
            $this->read(
                'resources/js/pages/Admin/DiagnosisRequests/Show.vue'
            );

        foreach ([
            'implementation_execution',
            'execution_url',
            'Gestionar ejecución y Go-Live',
            '/admin/transformation-360/commercial-settings',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $page
            );
        }
    }

    public function test_legacy_infrastructure_is_preserved_but_separate(): void
    {
        $routes =
            $this->read(
                'routes/admin.php'
            );

        foreach ([
            'implementation_execution.show',
            'transformation360.commercial_settings.show',
        ] as $legacyRoute) {
            $this->assertStringContainsString(
                $legacyRoute,
                $routes
            );
        }

        $this->assertFileExists(
            $this->root()
            .'/resources/js/pages/Admin/DiagnosisRequests/'
            .'ImplementationExecution.vue'
        );

        $this->assertFileExists(
            $this->root()
            .'/resources/js/pages/Admin/Transformation360/'
            .'CommercialSettings.vue'
        );
    }
}
