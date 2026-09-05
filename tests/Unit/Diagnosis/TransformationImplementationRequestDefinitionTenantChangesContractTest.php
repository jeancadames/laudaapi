<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationRequestDefinitionTenantChangesContractTest
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

    public function test_changes_are_only_allowed_from_tenant_review(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionTenantDecisionService.php'
            );

        foreach ([
            'STATUS_AWAITING_TENANT_REVIEW',
            'STATUS_CHANGES_REQUESTED',
            'tenant_review_requested_at',
            'transitionByTenant(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_reason_is_required_and_bounded(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionTenantDecisionService.php'
            );

        foreach ([
            'mb_strlen(',
            '$length < 10',
            '$length > 4000',
            "'reason'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_exact_latest_request_scoped_definition_is_required(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionTenantDecisionService.php'
            );

        foreach ([
            'transformation_implementation_request_id',
            'transformation_implementation_phase_capability_id',
            'transformation_implementation_plan_id',
            'diagnosis_assessment_id',
            'company_id',
            'capability_key',
            "'implementation_request'",
            'TransformationImplementationDefinitionRequestScopeContract::SCOPE_MODE',
            'definition_scope_locked_to_request',
            'orderByDesc(',
            "'version'",
            "'id'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_presented_definition_remains_under_review(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionTenantDecisionService.php'
            );

        $this->assertStringContainsString(
            'TransformationImplementationDefinition::STATUS_UNDER_REVIEW',
            $source
        );

        foreach ([
            '->markReady(',
            'TransformationImplementationDefinition::STATUS_READY',
            '$lockedDefinition->save(',
            '$lockedDefinition->update(',
            '$lockedDefinition->status =',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_v2_is_not_created_in_changes_request_step(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionTenantDecisionService.php'
            );

        foreach ([
            "'definition_modified' =>\n                            false",
            "'new_definition_version_created' =>\n                            false",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        foreach ([
            'TransformationImplementationRequestDefinitionService',
            'createOrGetDraftFromRequest(',
            'createRevision(',
            'createNewVersion(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_exact_definition_version_is_audited(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionTenantDecisionService.php'
            );

        foreach ([
            'AuditService::log(',
            'transformation_implementation_definition_changes_requested_by_tenant',
            "'definition_id'",
            "'definition_version'",
            "'tenant_reason'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_action_remains_precommercial_and_nonexecuting(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionTenantDecisionService.php'
            );

        foreach ([
            "'definition_ready' =>\n                            false",
            "'commercial_stage_started' =>\n                            false",
            "'execution_started' =>\n                            false",
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
}
