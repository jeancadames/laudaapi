<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationRequestDefinitionHumanReviewAdminActionContractTest
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

    public function test_request_definition_review_route_exists(): void
    {
        $routes =
            $this->project(
                'routes/admin.php'
            );

        $this->assertStringContainsString(
            '/transformation-360/implementation-requests/{implementationRequest}/definition/{definition}/review',
            $routes
        );

        $this->assertStringContainsString(
            'transformation360.implementation_requests.definition.review',
            $routes
        );

        $this->assertStringContainsString(
            'Route::patch(',
            $routes
        );
    }

    public function test_admin_action_uses_request_scoped_review_service(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestDefinitionActionController.php'
            );

        foreach ([
            'public function review(',
            'TransformationImplementationRequestDefinitionReviewService',
            '$reviews->saveReview(',
            "'in:lauda,client,shared'",
            "'readiness.scope_confirmed'",
            "'readiness.deliverables_confirmed'",
            "'readiness.dependencies_confirmed'",
            "'readiness.inputs_validated'",
            "'readiness.accesses_validated'",
            "'readiness.responsibilities_confirmed'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller
            );
        }
    }

    public function test_request_detail_exposes_human_review_read_model(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        foreach ([
            "'definition_review' =>",
            "'responsibility_model'",
            "'readiness'",
            "'can_review_definition'",
            "'definition_review_endpoint'",
            'STATUS_DEFINITION_PREPARATION',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller
            );
        }
    }

    public function test_admin_ui_exposes_responsibility_and_six_human_confirmations(): void
    {
        $ui =
            $this->project(
                'resources/js/pages/Admin/'
                .'Transformation360/'
                .'ImplementationRequests/Show.vue'
            );

        foreach ([
            'Guardar revisión humana',
            'saveImplementationDefinitionHumanReview',
            'humanReviewForm',
            'value="lauda"',
            'value="client"',
            'value="shared"',
            'scope_confirmed',
            'deliverables_confirmed',
            'dependencies_confirmed',
            'inputs_validated',
            'accesses_validated',
            'responsibilities_confirmed',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $ui
            );
        }
    }

    public function test_review_ui_explains_that_it_does_not_send_or_mark_ready(): void
    {
        $ui =
            $this->project(
                'resources/js/pages/Admin/'
                .'Transformation360/'
                .'ImplementationRequests/Show.vue'
            );

        $this->assertStringContainsString(
            'no marca la Definition',
            $ui
        );

        $this->assertStringContainsString(
            'no la envía al tenant',
            $ui
        );

        $this->assertStringContainsString(
            'permanece en preparación de definición',
            $ui
        );
    }

    public function test_f5f2_does_not_expose_ready_or_tenant_transition(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestDefinitionActionController.php'
            );

        foreach ([
            '->markReady(',
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

    public function test_f5f2_has_no_commercial_execution_or_activation_dependency(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestDefinitionActionController.php'
            );

        foreach ([
            'TransformationImplementationExecutionService',
            'TransformationImplementationCommercialEngine',
            'TransformationImplementationPricingService',
            'TransformationCapabilityActivationService',
            'TransformationImplementationSubscriptionService',
            'CentralEntitlementActivationService',
            'Invoice::create',
            'Payment::create',
            'Subscription::create',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $controller
            );
        }
    }
}
