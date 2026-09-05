<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationRequestDefinitionAdminActionContractTest
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

    public function test_admin_request_route_exposes_explicit_definition_creation(): void
    {
        $routes =
            $this->project(
                'routes/admin.php'
            );

        $this->assertStringContainsString(
            '/transformation-360/implementation-requests/{implementationRequest}/definition',
            $routes
        );

        $this->assertStringContainsString(
            'transformation360.implementation_requests.definition.create',
            $routes
        );

        $this->assertStringContainsString(
            'AdminTransformationImplementationRequestDefinitionActionController',
            $routes
        );
    }

    public function test_action_delegates_to_request_scoped_domain_service(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestDefinitionActionController.php'
            );

        $this->assertStringContainsString(
            'TransformationImplementationRequestDefinitionService',
            $controller
        );

        $this->assertStringContainsString(
            'createOrGetDraftFromRequest(',
            $controller
        );

        $this->assertStringContainsString(
            "!== 'admin'",
            $controller
        );
    }

    public function test_request_detail_exposes_definition_read_model(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        foreach ([
            "'definition' =>",
            "'can_create_definition'",
            "'definition_create_endpoint'",
            "'transformation_implementation_request_id'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller
            );
        }
    }

    public function test_create_action_is_only_available_in_definition_preparation(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        $this->assertStringContainsString(
            'STATUS_DEFINITION_PREPARATION',
            $controller
        );

        $this->assertStringContainsString(
            '$latestDefinition === null',
            $controller
        );
    }

    public function test_admin_ui_exposes_explicit_definition_action(): void
    {
        $ui =
            $this->project(
                'resources/js/pages/Admin/'
                .'Transformation360/'
                .'ImplementationRequests/Show.vue'
            );

        foreach ([
            'Crear borrador funcional de Definition',
            'createImplementationDefinition',
            'can_create_definition',
            'definition_create_endpoint',
            'Definition V',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $ui
            );
        }
    }

    public function test_f5d_does_not_autogenerate_or_send_to_tenant(): void
    {
        $action =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestDefinitionActionController.php'
            );

        $storeStart =
            strpos(
                $action,
                'public function store('
            );

        $generateStart =
            strpos(
                $action,
                'public function generate('
            );

        $this->assertNotFalse(
            $storeStart
        );

        $this->assertNotFalse(
            $generateStart
        );

        $this->assertTrue(
            $storeStart < $generateStart
        );

        /*
         * F5D certifica exclusivamente que CREAR
         * la Definition no autogenera contenido.
         *
         * Desde F5E existe deliberadamente otro
         * método generate(), por lo que no corresponde
         * inspeccionar todo el controller.
         */
        $store =
            substr(
                $action,
                $storeStart,
                $generateStart - $storeStart
            );

        $this->assertStringContainsString(
            'createOrGetDraftFromRequest(',
            $store
        );

        foreach ([
            'TransformationImplementationDefinitionAutogenerator',
            '$autogenerator->generate(',
            'transitionByLauda(',
            'transitionByTenant(',
            'STATUS_AWAITING_TENANT_REVIEW',
            'TransformationImplementationDefinitionReviewService',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $store
            );
        }
    }

    public function test_f5d_has_no_commercial_execution_or_activation_dependency(): void
    {
        $sources =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestDefinitionActionController.php'
            )
            ."\n"
            .$this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        foreach ([
            'TransformationCapabilityActivationService',
            'TransformationImplementationExecutionService',
            'TransformationImplementationCommercialEngine',
            'TransformationImplementationPricingService',
            'TransformationImplementationSubscriptionService',
            'CentralEntitlementActivationService',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $sources
            );
        }
    }
}
