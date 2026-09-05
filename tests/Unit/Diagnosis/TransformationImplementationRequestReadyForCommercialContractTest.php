<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationRequestReadyForCommercialContractTest
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
                .'TransformationImplementationRequestReadyForCommercialService.php'
            );

        $this->assertIsString(
            $this->service
        );
    }

    public function test_gate_is_lauda_only_and_requires_definition_agreed_request(): void
    {
        foreach ([
            "!== 'admin'",
            'AuthorizationException',
            'STATUS_DEFINITION_AGREED',
            'definition_agreed_at',
            'ready_for_commercial_at',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $this->service
            );
        }
    }

    public function test_exact_agreed_definition_is_source_of_truth_not_latest_definition(): void
    {
        foreach ([
            "'definition_agreed_by_tenant'",
            "'definition_id'",
            "'definition_version'",
            "'tenant_agreed'",
            'agreementEvents->count() !== 1',
            'whereKey(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $this->service
            );
        }

        $this->assertStringNotContainsString(
            "orderByDesc(\n                            'version'",
            $this->service
        );

        $this->assertStringNotContainsString(
            'latestDefinition',
            $this->service
        );
    }

    public function test_same_exact_definition_requires_explicit_functional_closure_evidence(): void
    {
        foreach ([
            "'definition_functionally_finalized_by_lauda'",
            'functionalClosureEvents->count() !== 1',
            "'agreement_event_id'",
            "'functional_closure_event_id'",
            "'definition_ready'",
            "'technical_readiness'",
            "'ready_for_execution'",
            "'execution_started'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $this->service
            );
        }
    }

    public function test_definition_must_already_be_functionally_ready_before_request_transition(): void
    {
        foreach ([
            'TransformationImplementationDefinition::STATUS_READY',
            "'state'",
            "'ready'",
            "'definition_ready'",
            "'technical_readiness'",
            "'ready_for_execution'",
            "'execution_started'",
            'ready_at',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $this->service
            );
        }

        $transitionPosition =
            strpos(
                $this->service,
                '->transitionByLauda('
            );

        $definitionGatePosition =
            strpos(
                $this->service,
                'assertExactReadyDefinition('
            );

        $closureGatePosition =
            strpos(
                $this->service,
                'assertFunctionalClosureEvidence('
            );

        $this->assertNotFalse(
            $transitionPosition
        );

        $this->assertNotFalse(
            $definitionGatePosition
        );

        $this->assertNotFalse(
            $closureGatePosition
        );

        $this->assertLessThan(
            $transitionPosition,
            $definitionGatePosition
        );

        $this->assertLessThan(
            $transitionPosition,
            $closureGatePosition
        );
    }

    public function test_only_request_lifecycle_transition_is_ready_for_commercial(): void
    {
        $this->assertSame(
            1,
            substr_count(
                $this->service,
                '->transitionByLauda('
            )
        );

        $this->assertStringContainsString(
            'STATUS_READY_FOR_COMMERCIAL',
            $this->service
        );

        $this->assertStringContainsString(
            "'request_ready_for_commercial_by_lauda'",
            $this->service
        );

        $this->assertStringContainsString(
            "'transformation_implementation_request_ready_for_commercial_by_lauda'",
            $this->service
        );
    }

    public function test_ready_for_commercial_does_not_mean_commercial_acceptance_or_execution(): void
    {
        foreach ([
            "'commercial_acceptance' =>\n                                false",
            "'commercial_proposal_created' =>\n                                false",
            "'pricing_created' =>\n                                false",
            "'contract_accepted' =>\n                                false",
            "'billing_started' =>\n                                false",
            "'invoice_created' =>\n                                false",
            "'payment_created' =>\n                                false",
            "'subscription_created' =>\n                                false",
            "'activation_started' =>\n                                false",
            "'service_active' =>\n                                false",
            "'execution_started' =>\n                                false",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $this->service
            );
        }

        foreach ([
            'Subscription::',
            'Invoice::',
            'Payment::',
            'TransformationCapabilityActivationService',
            'TransformationImplementationExecutionService',
            'CommercialProposal',
            'PricingService',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $this->service
            );
        }
    }
}
