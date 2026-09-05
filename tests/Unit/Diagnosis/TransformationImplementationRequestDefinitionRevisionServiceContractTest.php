<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationRequestDefinitionRevisionServiceContractTest
    extends TestCase
{
    private function source(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Services/Diagnosis/'
            .'TransformationImplementationRequestDefinitionRevisionService.php'
        );
    }

    public function test_revision_requires_changes_requested_and_lauda_admin(): void
    {
        $source =
            $this->source();

        foreach ([
            'assertLaudaAdmin(',
            "'admin'",
            'AuthorizationException',
            'STATUS_CHANGES_REQUESTED',
            'changes_requested_at',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_latest_request_scoped_definition_is_version_authority(): void
    {
        $source =
            $this->source();

        foreach ([
            'transformation_implementation_request_id',
            "orderByDesc(\n                            'version'",
            "orderByDesc(\n                            'id'",
            '$nextVersion',
            '+ 1',
            'TransformationImplementationDefinitionRequestScopeContract::SCOPE_MODE',
            'definition_scope_locked_to_request',
            "'implementation_request'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_revision_copies_functional_baseline_but_resets_approval(): void
    {
        $source =
            $this->source();

        foreach ([
            "'implementation_scope'",
            "'deliverables'",
            "'dependencies'",
            "'responsibility_model'",
            "'party_assignment_status'",
            "'to_be_defined'",
            "'prepared_for_review'",
            "'definition_ready'",
            "'technical_readiness'",
            "'human_review_required'",
            "'human_review_completed'",
            "'human_validation'",
            'scope_confirmed',
            'deliverables_confirmed',
            'dependencies_confirmed',
            'inputs_validated',
            'accesses_validated',
            'responsibilities_confirmed',
            'STATUS_DRAFT',
            "'reviewed_by_user_id'",
            "'reviewed_at'",
            "'ready_at'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_revision_preserves_exact_tenant_change_provenance(): void
    {
        $source =
            $this->source();

        foreach ([
            'tenantChangesEvent(',
            'STATUS_AWAITING_TENANT_REVIEW',
            'STATUS_CHANGES_REQUESTED',
            "'tenant_change_event_id'",
            "'tenant_change_reason'",
            "'revision_of_definition_id'",
            "'revision_of_definition_version'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_request_moves_back_to_definition_preparation(): void
    {
        $source =
            $this->source();

        foreach ([
            '->transitionByLauda(',
            'STATUS_DEFINITION_PREPARATION',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_previous_definition_is_not_mutated(): void
    {
        $source =
            $this->source();

        foreach ([
            '$latestDefinition->save(',
            '$latestDefinition->update(',
            '$latestDefinition->forceFill(',
            '$previousDefinition->save(',
            '$previousDefinition->update(',
            '$previousDefinition->forceFill(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }

        $this->assertStringContainsString(
            "'previous_definition_modified' =>\n                            false",
            $source
        );
    }

    public function test_revision_does_not_reuse_initial_v1_creation_contract(): void
    {
        $source =
            $this->source();

        foreach ([
            'createOrGetDraftFromRequest(',
            'TransformationImplementationRequestDefinitionService',
            '->generate(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_revision_has_no_ready_commercial_or_execution_side_effect(): void
    {
        $source =
            $this->source();

        foreach ([
            "'definition_ready' =>\n                            false",
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
            '->markReady(',
            'STATUS_READY_FOR_COMMERCIAL',
            'TransformationImplementationExecutionService',
            'TransformationImplementationPricingService',
            'TransformationCapabilityActivationService',
            'TransformationImplementationSubscriptionService',
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

    public function test_revision_creation_is_audited(): void
    {
        $source =
            $this->source();

        foreach ([
            'AuditService::log(',
            'transformation_implementation_definition_revision_created',
            "'previous_definition_id'",
            "'previous_definition_version'",
            "'revision_definition_id'",
            "'revision_definition_version'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }
}
