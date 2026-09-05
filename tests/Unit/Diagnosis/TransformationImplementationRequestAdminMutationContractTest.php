<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationRequestAdminMutationContractTest
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

    public function test_admin_mutation_routes_exist(): void
    {
        $routes =
            $this->project(
                'routes/admin.php'
            );

        foreach ([
            'transformation360.implementation_requests.assign',
            'transformation360.implementation_requests.transition',
            '/assign',
            '/transition',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $routes
            );
        }
    }

    public function test_controller_delegates_to_domain_service(): void
    {
        $source =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        $this->assertStringContainsString(
            'TransformationImplementationRequestService',
            $source
        );

        $this->assertStringContainsString(
            '->assignTo(',
            $source
        );

        $this->assertStringContainsString(
            '->transitionByLauda(',
            $source
        );
    }

    public function test_f4c_only_exposes_first_two_lauda_transitions(): void
    {
        $source =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        $this->assertStringContainsString(
            'STATUS_REQUESTED',
            $source
        );

        $this->assertStringContainsString(
            'STATUS_UNDER_LAUDA_REVIEW',
            $source
        );

        $this->assertStringContainsString(
            'STATUS_DEFINITION_PREPARATION',
            $source
        );

        $this->assertStringNotContainsString(
            "STATUS_AWAITING_TENANT_REVIEW => [",
            $source
        );
    }

    public function test_assignment_only_accepts_lauda_admin(): void
    {
        $source =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        $this->assertStringContainsString(
            "!== 'admin'",
            $source
        );

        $this->assertStringContainsString(
            'El responsable debe ser un usuario Admin LAUDA.',
            $source
        );
    }

    public function test_ui_exposes_receive_assign_and_definition_preparation(): void
    {
        $source =
            $this->project(
                'resources/js/pages/Admin/Transformation360/'
                .'ImplementationRequests/Show.vue'
            );

        foreach ([
            'Asignar responsable',
            'Recibir e iniciar revisión',
            'Iniciar preparación de definición',
            'assignResponsible',
            'transitionRequest',
            'under_lauda_review',
            'definition_preparation',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_definition_is_not_created_in_f4c(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        foreach ([
            'TransformationImplementationDefinitionService',
            'createOrGetDraftFromPresentedPlan',
            'TransformationImplementationDefinition::create',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $controller
            );
        }
    }

    public function test_no_activation_commercial_or_execution_dependency(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        foreach ([
            'TransformationCapabilityActivationService',
            'TransformationImplementationExecutionService',
            'TransformationImplementationCommercialEngine',
            'TransformationImplementationSubscriptionService',
            'CentralEntitlementActivationService',
            'Invoice::',
            'Payment::',
            'Subscription::',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $controller
            );
        }
    }

    public function test_assignment_is_recorded_in_request_history(): void
    {
        $service =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestService.php'
            );

        $this->assertStringContainsString(
            "'request_assigned'",
            $service
        );

        $this->assertStringContainsString(
            "'assigned_to_user_id' =>",
            $service
        );

        $this->assertStringContainsString(
            'transformation_implementation_request_assigned',
            $service
        );
    }


}
