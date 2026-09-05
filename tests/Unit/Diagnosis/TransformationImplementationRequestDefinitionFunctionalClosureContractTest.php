<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationRequestDefinitionFunctionalClosureContractTest
    extends TestCase
{
    private string $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service =
            file_get_contents(
                dirname(
                    __DIR__,
                    3
                )
                .'/app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionFunctionalClosureService.php'
            );

        $this->assertIsString(
            $this->service
        );
    }

    public function test_closure_is_lauda_only_and_requires_definition_agreed_request(): void
    {
        $this->assertStringContainsString(
            "!== 'admin'",
            $this->service
        );

        $this->assertStringContainsString(
            'AuthorizationException',
            $this->service
        );

        $this->assertStringContainsString(
            'STATUS_DEFINITION_AGREED',
            $this->service
        );

        $this->assertStringContainsString(
            'definition_agreed_at',
            $this->service
        );

        $this->assertStringContainsString(
            'ready_for_commercial_at',
            $this->service
        );
    }

    public function test_exact_agreed_definition_is_resolved_from_specific_event_not_latest_version(): void
    {
        $this->assertStringContainsString(
            "'definition_agreed_by_tenant'",
            $this->service
        );

        $this->assertStringContainsString(
            "'definition_id'",
            $this->service
        );

        $this->assertStringContainsString(
            "'definition_version'",
            $this->service
        );

        $this->assertStringContainsString(
            "'tenant_agreed'",
            $this->service
        );

        $this->assertStringContainsString(
            'agreementEvents->count() !== 1',
            $this->service
        );

        $this->assertStringContainsString(
            'whereKey(',
            $this->service
        );

        $this->assertStringNotContainsString(
            "orderByDesc(\n                            'version'",
            $this->service
        );
    }

    public function test_exact_request_scope_is_revalidated_before_mark_ready(): void
    {
        foreach ([
            'transformation_implementation_request_id',
            'company_id',
            'diagnosis_assessment_id',
            'transformation_implementation_plan_id',
            'transformation_implementation_phase_capability_id',
            'capability_key',
            "'implementation_request'",
            "'single_capability'",
            "'definition_scope_locked_to_request'",
            'STATUS_UNDER_REVIEW',
            "'under_review'",
            "'definition_ready'",
            'ready_at',
            "'party_assignment_status'",
            "'confirmed'",
            "'scope_confirmed'",
            "'deliverables_confirmed'",
            "'dependencies_confirmed'",
            "'inputs_validated'",
            "'accesses_validated'",
            "'responsibilities_confirmed'",
        ] as $needle) {
            $this->assertStringContainsString(
                $needle,
                $this->service
            );
        }
    }

    public function test_generic_mark_ready_is_wrapped_only_after_exact_agreement_checks(): void
    {
        $agreementPosition =
            strpos(
                $this->service,
                'assertExactAgreedDefinition('
            );

        $markReadyPosition =
            strpos(
                $this->service,
                '->markReady('
            );

        $this->assertNotFalse(
            $agreementPosition
        );

        $this->assertNotFalse(
            $markReadyPosition
        );

        $this->assertLessThan(
            $markReadyPosition,
            $agreementPosition
        );

        $this->assertSame(
            1,
            substr_count(
                $this->service,
                '->markReady('
            )
        );
    }

    public function test_functional_closure_keeps_request_out_of_commercial_and_execution(): void
    {
        $this->assertStringContainsString(
            "'definition_functionally_finalized_by_lauda'",
            $this->service
        );

        $this->assertStringContainsString(
            "'transformation_implementation_definition_functionally_finalized_by_lauda'",
            $this->service
        );

        foreach ([
            "'definition_ready' =>\n                                true",
            "'technical_readiness' =>\n                                true",
            "'ready_for_execution' =>\n                                false",
            "'execution_started' =>\n                                false",
            "'commercial_acceptance' =>\n                                false",
            "'commercial_stage_started' =>\n                                false",
            "'ready_for_commercial' =>\n                                false",
            "'activation_started' =>\n                                false",
            "'subscription_created' =>\n                                false",
        ] as $needle) {
            $this->assertStringContainsString(
                $needle,
                $this->service
            );
        }

        $this->assertStringNotContainsString(
            'transitionByLauda(',
            $this->service
        );

        $this->assertStringNotContainsString(
            'STATUS_READY_FOR_COMMERCIAL,',
            $this->service
        );

        $this->assertStringNotContainsString(
            'TransformationCapabilityActivationService',
            $this->service
        );

        $this->assertStringNotContainsString(
            'TransformationImplementationExecutionService',
            $this->service
        );

        $this->assertStringNotContainsString(
            'Subscription::',
            $this->service
        );
    }
}
