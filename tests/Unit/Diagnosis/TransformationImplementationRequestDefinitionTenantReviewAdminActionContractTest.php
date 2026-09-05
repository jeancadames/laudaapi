<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationRequestDefinitionTenantReviewAdminActionContractTest
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

    public function test_dedicated_submit_route_exists(): void
    {
        $routes =
            $this->project(
                'routes/admin.php'
            );

        foreach ([
            '/transformation-360/implementation-requests/{implementationRequest}/definition/{definition}/submit-tenant-review',
            'transformation360.implementation_requests.definition.submit_tenant_review',
            "'submitForTenantReview'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $routes
            );
        }
    }

    public function test_admin_action_delegates_to_request_scoped_domain_service(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestDefinitionActionController.php'
            );

        foreach ([
            'public function submitForTenantReview(',
            'TransformationImplementationRequestDefinitionTenantReviewService',
            '$tenantReview->submit(',
            "'notes'",
            "'nullable'",
            "'max:4000'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller
            );
        }
    }

    public function test_read_model_requires_completed_lauda_review(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        foreach ([
            '$definitionReadyForTenantReview =',
            'STATUS_DEFINITION_PREPARATION',
            'STATUS_UNDER_REVIEW',
            "'party_assignment_status'",
            "'confirmed'",
            'human_validation.scope_confirmed',
            'human_validation.deliverables_confirmed',
            'human_validation.dependencies_confirmed',
            'human_validation.inputs_validated',
            'human_validation.accesses_validated',
            'human_validation.responsibilities_confirmed',
            "'definition_ready'",
            "'can_submit_definition_for_tenant_review'",
            "'definition_submit_tenant_review_endpoint'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller
            );
        }
    }

    public function test_admin_ui_exposes_explicit_tenant_review_submission(): void
    {
        $ui =
            $this->project(
                'resources/js/pages/Admin/'
                .'Transformation360/'
                .'ImplementationRequests/Show.vue'
            );

        foreach ([
            'Enviar a revisión de la empresa',
            'submitDefinitionForTenantReview',
            'tenantReviewSubmissionForm',
            'can_submit_definition_for_tenant_review',
            'definition_submit_tenant_review_endpoint',
            'Nota para la empresa',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $ui
            );
        }
    }

    public function test_ui_explains_submission_is_not_tenant_agreement(): void
    {
        $ui =
            $this->project(
                'resources/js/pages/Admin/'
                .'Transformation360/'
                .'ImplementationRequests/Show.vue'
            );

        foreach ([
            'no significa que la empresa haya',
            'no la marca como ready',
            'no inicia contratación',
            'facturación',
            'activación ni ejecución',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $ui
            );
        }
    }

    public function test_generic_transition_ui_still_cannot_bypass_definition_gate(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        $start =
            strpos(
                $controller,
                'private function allowedAdminTransitions('
            );

        $end =
            strpos(
                $controller,
                'private function statusOptions(',
                $start
            );

        $this->assertNotFalse(
            $start
        );

        $this->assertNotFalse(
            $end
        );

        $chunk =
            substr(
                $controller,
                $start,
                $end - $start
            );

        $this->assertStringNotContainsString(
            'STATUS_AWAITING_TENANT_REVIEW',
            $chunk
        );
    }

    public function test_http_action_does_not_mark_ready_or_start_downstream_stages(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestDefinitionActionController.php'
            );

        foreach ([
            '->markReady(',
            'TransformationImplementationDefinition::STATUS_READY',
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
