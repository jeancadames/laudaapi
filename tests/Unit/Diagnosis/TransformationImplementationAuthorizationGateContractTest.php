<?php

namespace Tests\Unit\Diagnosis;

use Tests\TestCase;

final class TransformationImplementationAuthorizationGateContractTest
    extends TestCase
{
    private function source(): string
    {
        return file_get_contents(
            base_path(
                'app/Services/Diagnosis/TransformationImplementationAuthorizationGate.php'
            )
        );
    }

    public function test_gate_requires_exactly_one_active_authorization_for_request(): void
    {
        $service =
            $this->source();

        foreach (
            [
                'public function assertActiveForRequest(',
                'TransformationImplementationAuthorization::query()',
                "'transformation_implementation_request_id'",
                "'company_id'",
                "'transformation_implementation_phase_capability_id'",
                "'capability_key'",
                'STATUS_AUTHORIZED',
                "whereNotNull(\n                    'authorized_at'",
                "whereNull(\n                    'revoked_at'",
                '$authorizations->count() !== 1',
                '$authorization->isActive()',
            ]
            as $token
        ) {
            $this->assertStringContainsString(
                $token,
                $service
            );
        }
    }

    public function test_gate_requires_exact_accepted_engagement(): void
    {
        $service =
            $this->source();

        foreach (
            [
                'assertEngagement(',
                'STATUS_ACCEPTED',
                'accepted_at',
                'accepted_by_user_id',
                'presented_at',
                'presented_by_user_id',
                'transformation_implementation_request_id',
                'transformation_implementation_phase_capability_id',
            ]
            as $token
        ) {
            $this->assertStringContainsString(
                $token,
                $service
            );
        }
    }

    public function test_gate_requires_exact_ready_definition_without_starting_execution(): void
    {
        $service =
            $this->source();

        foreach (
            [
                'assertDefinition(',
                'TransformationImplementationDefinition::STATUS_READY',
                "'definition_ready'",
                "'technical_readiness'",
                "'ready_for_execution'",
                "'execution_started'",
            ]
            as $token
        ) {
            $this->assertStringContainsString(
                $token,
                $service
            );
        }
    }

    public function test_gate_validates_frozen_authorization_snapshot(): void
    {
        $service =
            $this->source();

        foreach (
            [
                'assertAuthorizationSnapshot(',
                "'request.id'",
                "'definition.id'",
                "'definition.version'",
                "'commercial_engagement.id'",
                "'commercial_engagement.version'",
                "'scope.company_id'",
                "'scope.phase_capability_id'",
                "'scope.capability_key'",
                "'authorization_boundary.commercial_acceptance'",
                "'authorization_boundary.implementation_authorized'",
                "'authorization_boundary.ready_for_execution_mutated'",
                "'authorization_boundary.execution_started'",
                "'authorization_boundary.canonical_write'",
            ]
            as $token
        ) {
            $this->assertStringContainsString(
                $token,
                $service
            );
        }
    }

    public function test_gate_is_read_assert_only_and_does_not_start_execution(): void
    {
        $service =
            $this->source();

        foreach (
            [
                '->create([',
                '->save()',
                '->update([',
                'forceFill([',
                'TransformationImplementationExecutionService',
                'DataTransformationBiCanonicalModelService',
                'DataTransformationBiSourceAssetMappingService',
                'Subscription::',
                'Invoice::',
                'Payment::',
            ]
            as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $service
            );
        }
    }

    public function test_gate_supports_row_lock_for_future_write_transactions(): void
    {
        $service =
            $this->source();

        $this->assertStringContainsString(
            'bool $lockForUpdate = false',
            $service
        );

        $this->assertStringContainsString(
            'if ($lockForUpdate)',
            $service
        );

        $this->assertStringContainsString(
            '$query->lockForUpdate();',
            $service
        );
    }
}
