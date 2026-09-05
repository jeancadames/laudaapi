<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationRequestDefinitionHumanReviewContractTest
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

    public function test_request_review_wraps_existing_human_review_service(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionReviewService.php'
            );

        foreach ([
            'TransformationImplementationDefinitionReviewService',
            'public function saveReview(',
            '$this->reviews',
            '->saveReview(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_review_requires_exact_request_scoped_identity(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionReviewService.php'
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

    public function test_review_requires_definition_preparation(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionReviewService.php'
            );

        $this->assertStringContainsString(
            'STATUS_DEFINITION_PREPARATION',
            $source
        );
    }

    public function test_review_requires_prepared_editable_content(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionReviewService.php'
            );

        foreach ([
            'isEditable()',
            "'prepared_for_review'",
            "'under_review'",
            'implementation_scope',
            'deliverables',
            'dependencies',
            'responsibility_model',
            'readiness',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_review_is_added_to_request_history_without_transition(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionReviewService.php'
            );

        foreach ([
            'TransformationImplementationRequestEvent',
            "'definition_review_saved'",
            "'from_status'",
            "'to_status'",
            "'request_status_changed'",
            "'tenant_review_started'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        foreach ([
            'transitionByLauda(',
            'transitionByTenant(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_request_review_does_not_mark_definition_ready(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionReviewService.php'
            );

        $this->assertStringNotContainsString(
            '->markReady(',
            $source
        );

        $this->assertStringContainsString(
            "'definition_ready' =>",
            $source
        );

        $this->assertStringContainsString(
            "'definition_ready' =>\n                                false",
            $source
        );
    }

    public function test_request_review_has_no_commercial_execution_or_activation_service(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionReviewService.php'
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
                $source
            );
        }
    }
}
