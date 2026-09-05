<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationRequestDefinitionServiceContractTest
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

    public function test_service_creates_definition_from_request_only(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionService.php'
            );

        foreach ([
            'createOrGetDraftFromRequest',
            'transformation_implementation_request_id',
            'transformation_implementation_phase_capability_id',
            'capability_key',
            'REQUIRED_REQUEST_STATUS',
            'STATUS_PRESENTED',
            'implementation_only',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_definition_is_single_capability_scoped(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionService.php'
            );

        foreach ([
            'SCOPE_MODE',
            "'single_capability'",
            "'plan_wide_definition' =>",
            "'definition_scope_locked_to_request'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_service_is_idempotent_for_initial_request_definition(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionService.php'
            );

        $this->assertStringContainsString(
            "->where(\n"
            ."                            'transformation_implementation_request_id'",
            $source
        );

        $this->assertStringContainsString(
            'if ($existing)',
            $source
        );

        $this->assertStringContainsString(
            'return $existing->fresh();',
            $source
        );
    }

    public function test_service_does_not_transition_request(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionService.php'
            );

        foreach ([
            'transitionByLauda(',
            'transitionByTenant(',
            'STATUS_AWAITING_TENANT_REVIEW =>',
            "'status' =>\n"
            ."                                TransformationImplementationRequestContract",
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_definition_creation_is_traced(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionService.php'
            );

        $this->assertStringContainsString(
            "'definition_created'",
            $source
        );

        $this->assertStringContainsString(
            'transformation_implementation_definition_created_from_request',
            $source
        );
    }

    public function test_service_has_no_activation_execution_or_commercial_engine(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionService.php'
            );

        foreach ([
            'TransformationCapabilityActivationService',
            'TransformationImplementationExecutionService',
            'TransformationImplementationCommercialEngine',
            'TransformationImplementationPricingService',
            'TransformationImplementationSubscriptionService',
            'CentralEntitlementActivationService',
            'Invoice::',
            'Payment::',
            'Subscription::',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_human_review_and_downstream_steps_remain_pending(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionService.php'
            );

        foreach ([
            "'human_review_required' =>",
            "'human_review_completed' =>",
            "'ready_for_execution' =>",
            "'execution_started' =>",
            "'commercial_stage_started' =>",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }
}
