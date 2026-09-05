<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationRequestDefinitionRevisionAdminActionContractTest
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

    public function test_admin_revision_has_dedicated_route_without_definition_parameter(): void
    {
        $routes =
            $this->project(
                'routes/admin.php'
            );

        foreach ([
            '/transformation-360/implementation-requests/{implementationRequest}/definition/revision',
            'AdminTransformationImplementationRequestDefinitionActionController',
            "'createRevision'",
            'transformation360.implementation_requests.definition.revision.create',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $routes
            );
        }

        $this->assertStringNotContainsString(
            '/definition/{definition}/revision',
            $routes
        );
    }

    public function test_action_resolves_latest_definition_server_side(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestDefinitionActionController.php'
            );

        foreach ([
            'public function createRevision(',
            'STATUS_CHANGES_REQUESTED',
            'TransformationImplementationDefinition::query()',
            'transformation_implementation_request_id',
            'company_id',
            'diagnosis_assessment_id',
            'transformation_implementation_plan_id',
            'transformation_implementation_phase_capability_id',
            'capability_key',
            "'version'",
            "'id'",
            'TransformationImplementationRequestDefinitionRevisionService',
            '->createRevision(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller
            );
        }
    }

    public function test_action_does_not_accept_definition_identity_from_browser(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestDefinitionActionController.php'
            );

        $start =
            strpos(
                $controller,
                'public function createRevision('
            );

        $end =
            strpos(
                $controller,
                'private function authorizeAdmin(',
                $start
            );

        $this->assertNotFalse(
            $start
        );

        $this->assertNotFalse(
            $end
        );

        $method =
            substr(
                $controller,
                $start,
                $end - $start
            );

        $this->assertStringNotContainsString(
            'TransformationImplementationDefinition $definition',
            $method
        );

        $this->assertStringNotContainsString(
            "'definition_id'",
            $method
        );
    }

    public function test_read_model_exposes_revision_only_for_changes_requested(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        foreach ([
            '$definitionRevisionAvailable',
            'STATUS_CHANGES_REQUESTED',
            'STATUS_UNDER_REVIEW',
            "'implementation_request'",
            "'single_capability'",
            "'definition_scope_locked_to_request'",
            "'definition_revision_context'",
            "'can_create_definition_revision'",
            "'definition_revision_endpoint'",
            'admin.transformation360.implementation_requests.definition.revision.create',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller
            );
        }
    }

    public function test_read_model_preserves_tenant_change_reason(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        foreach ([
            '$tenantChangesEvent',
            "'status_transition'",
            'STATUS_AWAITING_TENANT_REVIEW',
            'STATUS_CHANGES_REQUESTED',
            "'tenant_change_reason'",
            "['notes']",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller
            );
        }
    }

    public function test_ui_has_explicit_revision_action_and_boundary_copy(): void
    {
        $ui =
            $this->project(
                'resources/js/pages/Admin/'
                .'Transformation360/ImplementationRequests/Show.vue'
            );

        foreach ([
            'definition_revision_context',
            'can_create_definition_revision',
            'definition_revision_endpoint',
            'createImplementationDefinitionRevision',
            'Cambios solicitados por la empresa',
            'Preparar nueva versión',
            'se conservará sin cambios',
            'reinicia las confirmaciones humanas',
            'no se envía automáticamente',
            'contratación',
            'facturación',
            'activación',
            'suscripción',
            'ejecución',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $ui
            );
        }
    }

    public function test_http_action_has_no_downstream_or_ready_side_effect(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestDefinitionActionController.php'
            );

        $start =
            strpos(
                $controller,
                'public function createRevision('
            );

        $end =
            strpos(
                $controller,
                'private function authorizeAdmin(',
                $start
            );

        $method =
            substr(
                $controller,
                $start,
                $end - $start
            );

        foreach ([
            '->markReady(',
            'STATUS_DEFINITION_AGREED',
            'STATUS_READY_FOR_COMMERCIAL',
            'submitForTenantReview(',
            'TransformationImplementationExecutionService',
            'TransformationImplementationPricingService',
            'TransformationCapabilityActivationService',
            'Subscription::create',
            'Invoice::create',
            'Payment::create',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $method
            );
        }
    }
}
