<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\TransformationImplementationRequestContract;
use App\Services\Diagnosis\TransformationProfessionalCapabilityCatalog;
use PHPUnit\Framework\TestCase;

final class TransformationImplementationRequestContractTest extends TestCase
{
    public function test_request_has_explicit_non_commercial_lifecycle(): void
    {
        $this->assertSame([
            'requested',
            'under_lauda_review',
            'definition_preparation',
            'awaiting_tenant_review',
            'changes_requested',
            'definition_agreed',
            'ready_for_commercial',
            'cancelled',
        ], TransformationImplementationRequestContract::STATUSES);
    }

    public function test_only_tenant_admin_initiates_normal_request_flow(): void
    {
        $rules =
            TransformationImplementationRequestContract::requestability();

        $this->assertSame(
            'tenant_admin',
            $rules['initiated_by']
        );

        $this->assertTrue(
            $rules['requires_company']
        );

        $this->assertTrue(
            $rules['requires_assessment']
        );

        $this->assertTrue(
            $rules['requires_presented_plan']
        );

        $this->assertTrue(
            $rules['requires_capability_in_plan']
        );

        $this->assertTrue(
            $rules[
                'requires_company_assessment_plan_alignment'
            ]
        );
    }

    public function test_request_targets_implementation_only_professional_capability(): void
    {
        $rules =
            TransformationImplementationRequestContract::requestability();

        $this->assertSame(
            'professional_service',
            $rules['required_capability_kind']
        );

        $this->assertSame(
            'implementation_only',
            $rules['required_activation_policy']
        );

        $bi = TransformationProfessionalCapabilityCatalog::get(
            'data_transformation_bi'
        );

        $this->assertNotNull($bi);

        $this->assertSame(
            'professional_service',
            $bi['kind']
        );

        $this->assertSame(
            'implementation_only',
            $bi['activation_policy']
        );

        $this->assertTrue(
            $bi['requires_lauda_review']
        );

        $this->assertNull(
            $bi['service_key']
        );

        $this->assertFalse(
            $bi['subscription_candidate']
        );
    }

    public function test_duplicate_active_request_is_idempotent_and_cancelled_can_be_resubmitted(): void
    {
        $rules =
            TransformationImplementationRequestContract::requestability();

        $this->assertSame(
            'company_plan_capability',
            $rules['active_request_idempotency_scope']
        );

        $this->assertTrue(
            $rules[
                'resubmission_after_cancel_creates_new_attempt'
            ]
        );
    }

    public function test_request_does_not_create_definition_automatically(): void
    {
        $rules =
            TransformationImplementationRequestContract::requestability();

        $this->assertTrue(
            $rules['definition_creation_is_lauda_action']
        );

        $this->assertTrue(
            $rules[
                'definition_must_be_scoped_to_requested_capability'
            ]
        );
    }

    public function test_request_has_zero_activation_commercial_or_execution_side_effects(): void
    {
        $boundary =
            TransformationImplementationRequestContract::sideEffectBoundary();

        foreach ($boundary as $key => $value) {
            $this->assertFalse(
                $value,
                "Boundary inválido: {$key} debe permanecer false."
            );
        }
    }

    public function test_lauda_controls_internal_review_and_definition_progress(): void
    {
        $c = TransformationImplementationRequestContract::class;

        $this->assertTrue(
            $c::canLaudaTransition(
                $c::STATUS_REQUESTED,
                $c::STATUS_UNDER_LAUDA_REVIEW
            )
        );

        $this->assertTrue(
            $c::canLaudaTransition(
                $c::STATUS_UNDER_LAUDA_REVIEW,
                $c::STATUS_DEFINITION_PREPARATION
            )
        );

        $this->assertTrue(
            $c::canLaudaTransition(
                $c::STATUS_DEFINITION_PREPARATION,
                $c::STATUS_AWAITING_TENANT_REVIEW
            )
        );

        $this->assertTrue(
            $c::canLaudaTransition(
                $c::STATUS_CHANGES_REQUESTED,
                $c::STATUS_DEFINITION_PREPARATION
            )
        );

        $this->assertTrue(
            $c::canLaudaTransition(
                $c::STATUS_DEFINITION_AGREED,
                $c::STATUS_READY_FOR_COMMERCIAL
            )
        );
    }

    public function test_tenant_controls_definition_feedback_and_agreement(): void
    {
        $c = TransformationImplementationRequestContract::class;

        $this->assertTrue(
            $c::canTenantTransition(
                $c::STATUS_AWAITING_TENANT_REVIEW,
                $c::STATUS_CHANGES_REQUESTED
            )
        );

        $this->assertTrue(
            $c::canTenantTransition(
                $c::STATUS_AWAITING_TENANT_REVIEW,
                $c::STATUS_DEFINITION_AGREED
            )
        );

        $this->assertFalse(
            $c::canTenantTransition(
                $c::STATUS_REQUESTED,
                $c::STATUS_UNDER_LAUDA_REVIEW
            )
        );

        $this->assertFalse(
            $c::canTenantTransition(
                $c::STATUS_DEFINITION_AGREED,
                $c::STATUS_READY_FOR_COMMERCIAL
            )
        );
    }

    public function test_terminal_states_are_explicit(): void
    {
        $c = TransformationImplementationRequestContract::class;

        $this->assertTrue(
            $c::isTerminal(
                $c::STATUS_READY_FOR_COMMERCIAL
            )
        );

        $this->assertTrue(
            $c::isTerminal(
                $c::STATUS_CANCELLED
            )
        );

        $this->assertFalse(
            $c::isTerminal(
                $c::STATUS_DEFINITION_AGREED
            )
        );
    }
}
