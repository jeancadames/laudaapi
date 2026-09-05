<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationRequestDefinitionTenantReviewContractTest
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

    public function test_service_submits_only_from_definition_preparation(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionTenantReviewService.php'
            );

        $this->assertStringContainsString(
            'STATUS_DEFINITION_PREPARATION',
            $source
        );

        $this->assertStringContainsString(
            'STATUS_AWAITING_TENANT_REVIEW',
            $source
        );

        $this->assertStringContainsString(
            'transitionByLauda(',
            $source
        );
    }

    public function test_service_requires_exact_request_definition_identity(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionTenantReviewService.php'
            );

        foreach ([
            'transformation_implementation_request_id',
            'transformation_implementation_phase_capability_id',
            'transformation_implementation_plan_id',
            'diagnosis_assessment_id',
            'company_id',
            'capability_key',
            "'implementation_request'",
            'definition_scope_locked_to_request',
            'TransformationImplementationDefinitionRequestScopeContract::SCOPE_MODE',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_submission_requires_completed_lauda_human_review(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionTenantReviewService.php'
            );

        foreach ([
            'STATUS_UNDER_REVIEW',
            "'party_assignment_status'",
            "'confirmed'",
            "'scope_confirmed'",
            "'deliverables_confirmed'",
            "'dependencies_confirmed'",
            "'inputs_validated'",
            "'accesses_validated'",
            "'responsibilities_confirmed'",
            "'human_validation'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_submission_does_not_mark_definition_ready(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionTenantReviewService.php'
            );

        foreach ([
            '->markReady(',
            'TransformationImplementationDefinition::STATUS_READY',
            "'definition_ready' =>\n                            true",
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }

        $this->assertStringContainsString(
            "'definition_ready' =>\n                            false",
            $source
        );

        $this->assertStringContainsString(
            "'tenant_agreed' =>\n                            false",
            $source
        );
    }

    public function test_submission_preserves_precommercial_boundary(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionTenantReviewService.php'
            );

        foreach ([
            "'ready_for_execution' =>\n                            false",
            "'execution_started' =>\n                            false",
            "'commercial_stage_started' =>\n                            false",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

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
                $source
            );
        }
    }

    public function test_submission_records_definition_specific_audit(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionTenantReviewService.php'
            );

        foreach ([
            'AuditService::log(',
            'transformation_implementation_definition_submitted_for_tenant_review',
            "'definition_version'",
            "'request_id'",
            "'capability_key'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_generic_admin_transition_ui_still_cannot_bypass_definition_gate(): void
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

        $allowedTransitions =
            substr(
                $controller,
                $start,
                $end - $start
            );

        /*
         * F5G debe usar un endpoint dedicado que valide
         * la Definition. No habilitamos el salto genérico.
         */
        $this->assertStringNotContainsString(
            'STATUS_AWAITING_TENANT_REVIEW',
            $allowedTransitions
        );
    }
}
