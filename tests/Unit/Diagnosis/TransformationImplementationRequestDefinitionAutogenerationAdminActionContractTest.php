<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationRequestDefinitionAutogenerationAdminActionContractTest
    extends TestCase
{
    private function project(
        string $path
    ): string {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/'
            .$path
        );
    }

    public function test_admin_route_exposes_separate_generate_action(): void
    {
        $routes =
            $this->project(
                'routes/admin.php'
            );

        $this->assertStringContainsString(
            '/transformation-360/implementation-requests/{implementationRequest}/definition/{definition}/generate',
            $routes
        );

        $this->assertStringContainsString(
            'transformation360.implementation_requests.definition.generate',
            $routes
        );
    }

    public function test_generate_action_uses_request_scoped_autogenerator(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestDefinitionActionController.php'
            );

        foreach ([
            'public function generate(',
            'TransformationImplementationDefinitionAutogenerator',
            '$autogenerator->generate(',
            'STATUS_DEFINITION_PREPARATION',
            'assertDefinitionContext(',
            'contentPrepared(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller
            );
        }
    }

    public function test_generate_does_not_transition_request(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestDefinitionActionController.php'
            );

        foreach ([
            'transitionByLauda(',
            'transitionByTenant(',
            'STATUS_AWAITING_TENANT_REVIEW',
            'STATUS_DEFINITION_AGREED',
            'STATUS_READY_FOR_COMMERCIAL',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $controller
            );
        }
    }

    public function test_request_detail_exposes_prepared_state_and_endpoint(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        foreach ([
            "'content_prepared'",
            "'deliverable_count'",
            "'dependency_count'",
            "'can_generate_definition'",
            "'definition_generate_endpoint'",
            "'prepared_for_review'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller
            );
        }
    }

    public function test_admin_ui_separates_create_from_prepare_content(): void
    {
        $ui =
            $this->project(
                'resources/js/pages/Admin/'
                .'Transformation360/'
                .'ImplementationRequests/Show.vue'
            );

        foreach ([
            'Crear borrador funcional de Definition',
            'Preparar contenido de la Definition',
            'generateImplementationDefinition',
            'content_prepared',
            'Contenido preparado para revisión',
            'Entregables preparados:',
            'Dependencias preparadas:',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $ui
            );
        }
    }

    public function test_f5e2_has_no_commercial_execution_or_activation_dependency(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestDefinitionActionController.php'
            );

        foreach ([
            'Invoice::create',
            'Payment::create',
            'Subscription::create',
            'TransformationImplementationExecutionService',
            'TransformationImplementationCommercialEngine',
            'TransformationImplementationPricingService',
            'TransformationCapabilityActivationService',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $controller
            );
        }
    }
}
