<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationRequestDefinitionTenantAgreementContractTest
    extends TestCase
{
    private function service(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Services/Diagnosis/'
            .'TransformationImplementationRequestDefinitionTenantDecisionService.php'
        );
    }

    public function test_agreement_is_an_explicit_tenant_domain_action(): void
    {
        $service =
            $this->service();

        foreach ([
            'public function agree(',
            '$this->assertTenantActor(',
            '$this->assertAgreementContext(',
            '->transitionByTenant(',
            'STATUS_DEFINITION_AGREED',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $service
            );
        }
    }

    public function test_agreement_requires_exact_latest_request_scoped_definition(): void
    {
        $service =
            $this->service();

        foreach ([
            'private function assertAgreementContext(',
            'STATUS_AWAITING_TENANT_REVIEW',
            "'transformation_implementation_request_id'",
            "'company_id'",
            "'diagnosis_assessment_id'",
            "'transformation_implementation_plan_id'",
            "'transformation_implementation_phase_capability_id'",
            "'capability_key'",
            'orderByDesc(',
            "'version'",
            "'id'",
            'lockForUpdate()',
            'Solo puede acordarse la versión más reciente presentada.',
            "'source_type'",
            "'implementation_request'",
            "'scope_mode'",
            'TransformationImplementationDefinitionRequestScopeContract::SCOPE_MODE',
            "'definition_scope_locked_to_request'",
            'STATUS_UNDER_REVIEW',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $service
            );
        }
    }

    public function test_agreement_requires_completed_lauda_human_review(): void
    {
        $service =
            $this->service();

        foreach ([
            "'state'",
            "'under_review'",
            "'scope_confirmed'",
            "'deliverables_confirmed'",
            "'dependencies_confirmed'",
            "'inputs_validated'",
            "'accesses_validated'",
            "'responsibilities_confirmed'",
            "'party_assignment_status'",
            "'confirmed'",
            "'confirmation_required'",
            "'unresolved'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $service
            );
        }
    }

    public function test_agreement_pins_exact_version_in_specific_event_and_audit(): void
    {
        $service =
            $this->service();

        foreach ([
            'TransformationImplementationRequestEvent::query()',
            "'definition_agreed_by_tenant'",
            "'transformation_implementation_definition_agreed_by_tenant'",
            "'request_id'",
            "'definition_id'",
            "'definition_version'",
            "'definition_status'",
            "'company_id'",
            "'capability_key'",
            "'request_from_status'",
            "'request_to_status'",
            "'actor_user_id'",
            "'tenant_agreed'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $service
            );
        }
    }

    public function test_agreement_does_not_finalize_definition_or_start_commercial_execution(): void
    {
        $service =
            $this->service();

        $agreeStart =
            strpos(
                $service,
                'public function agree('
            );

        $agreeEnd =
            strpos(
                $service,
                'private function assertTenantActor(',
                $agreeStart
            );

        $this->assertNotFalse(
            $agreeStart
        );

        $this->assertNotFalse(
            $agreeEnd
        );

        $agree =
            substr(
                $service,
                $agreeStart,
                $agreeEnd - $agreeStart
            );

        foreach ([
            '->markReady(',
            'STATUS_READY_FOR_COMMERCIAL',
            'STATUS_READY',
            '->save()',
            '->update(',
            'TransformationCapabilityActivation',
            'TransformationImplementationExecution',
            'Subscription::',
            'Invoice::',
            'Payment::',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $agree
            );
        }

        foreach ([
            "'definition_modified' =>\n                                false",
            "'definition_ready' =>\n                                false",
            "'mark_ready_used' =>\n                                false",
            "'commercial_acceptance' =>\n                                false",
            "'commercial_stage_started' =>\n                                false",
            "'ready_for_commercial' =>\n                                false",
            "'activation_started' =>\n                                false",
            "'execution_started' =>\n                                false",
            "'subscription_created' =>\n                                false",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $agree
            );
        }
    }

    public function test_existing_changes_requested_action_remains_present(): void
    {
        $service =
            $this->service();

        foreach ([
            'public function requestChanges(',
            'STATUS_CHANGES_REQUESTED',
            'transformation_implementation_definition_changes_requested_by_tenant',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $service
            );
        }
    }
}
