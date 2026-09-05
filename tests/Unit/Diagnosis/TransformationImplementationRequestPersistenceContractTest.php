<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationRequestPersistenceContractTest extends TestCase
{
    private function migration(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/database/migrations/2026_09_05_121500_create_transformation_implementation_requests_tables.php'
        );
    }

    private function service(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Services/Diagnosis/TransformationImplementationRequestService.php'
        );
    }

    public function test_request_and_history_tables_are_defined(): void
    {
        $source = $this->migration();

        $this->assertStringContainsString(
            "'transformation_implementation_requests'",
            $source
        );

        $this->assertStringContainsString(
            "'transformation_implementation_request_events'",
            $source
        );
    }

    public function test_request_is_scoped_to_company_plan_and_capability(): void
    {
        $source = $this->migration();

        foreach ([
            "'company_id'",
            "'diagnosis_assessment_id'",
            "'transformation_implementation_plan_id'",
            "'transformation_implementation_phase_capability_id'",
            "'capability_key'",
            "'attempt'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        $this->assertStringContainsString(
            "'tir_scope_attempt_unique'",
            $source
        );
    }

    public function test_request_has_auditable_status_history(): void
    {
        $source = $this->migration();

        foreach ([
            "'event_type'",
            "'from_status'",
            "'to_status'",
            "'actor_type'",
            "'actor_user_id'",
            "'occurred_at'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_service_is_idempotent_for_active_scope(): void
    {
        $source = $this->service();

        $this->assertStringContainsString(
            '->active()',
            $source
        );

        $this->assertStringContainsString(
            'lockForUpdate()',
            $source
        );

        $this->assertStringContainsString(
            'return $active->fresh()',
            $source
        );
    }

    public function test_cancelled_request_can_create_a_new_attempt(): void
    {
        $source = $this->service();

        $this->assertStringContainsString(
            '$latest->attempt + 1',
            $source
        );

        $this->assertStringContainsString(
            "'attempt' => \$attempt",
            $source
        );
    }

    public function test_request_requires_presented_plan_and_implementation_only_capability(): void
    {
        $source = $this->service();

        $this->assertStringContainsString(
            'TransformationImplementationPlan::STATUS_PRESENTED',
            $source
        );

        $this->assertStringContainsString(
            'REQUIRED_ACTIVATION_POLICY',
            $source
        );

        $this->assertStringContainsString(
            'REQUIRED_CAPABILITY_KIND',
            $source
        );
    }

    public function test_service_has_no_activation_commercial_or_execution_dependencies(): void
    {
        $source = $this->service();

        foreach ([
            'CentralEntitlementActivationService',
            'TransformationCapabilityActivationService',
            'TransformationCapabilityActivation::',
            'TransformationImplementationExecutionService',
            'TransformationImplementationCommercialEngine',
            'TransformationImplementationPricingService',
            'TransformationImplementationSubscriptionService',
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

    public function test_request_does_not_create_definition(): void
    {
        $source = $this->service();

        $this->assertStringNotContainsString(
            'TransformationImplementationDefinition::create',
            $source
        );

        $this->assertStringNotContainsString(
            'TransformationImplementationDefinitionService',
            $source
        );
    }

    public function test_tenant_and_lauda_transitions_remain_separate(): void
    {
        $source = $this->service();

        $this->assertStringContainsString(
            'transitionByTenant',
            $source
        );

        $this->assertStringContainsString(
            'transitionByLauda',
            $source
        );

        $this->assertStringContainsString(
            'canTenantTransition',
            $source
        );

        $this->assertStringContainsString(
            'canLaudaTransition',
            $source
        );
    }
}
