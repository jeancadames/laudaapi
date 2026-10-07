<?php

namespace Tests\Unit\Diagnosis;

use Tests\TestCase;

final class TransformationImplementationAuthorizationServiceContractTest
    extends TestCase
{
    private function source(): string
    {
        return file_get_contents(
            base_path(
                'app/Services/Diagnosis/TransformationImplementationAuthorizationService.php'
            )
        );
    }

    public function test_authorization_requires_exact_accepted_commercial_boundary(): void
    {
        $service =
            $this->source();

        foreach (
            [
                'public function authorize(',
                'assertLaudaAdmin(',
                'assertAcceptedEngagement(',
                'STATUS_ACCEPTED',
                'accepted_at',
                'accepted_by_user_id',
                'presented_at',
                'presented_by_user_id',
                'assertRequestState(',
                'STATUS_READY_FOR_COMMERCIAL',
                'resolveReadyForCommercialEvidence(',
                'assertDefinitionContext(',
                'assertEngagementContext(',
                'assertCommercialEvidence(',
            ]
            as $token
        ) {
            $this->assertStringContainsString(
                $token,
                $service
            );
        }
    }

    public function test_authorization_pins_exact_scope_and_commercial_evidence(): void
    {
        $service =
            $this->source();

        foreach (
            [
                'authorization_snapshot',
                "'request' =>",
                "'ready_for_commercial_evidence' =>",
                "'definition' =>",
                "'commercial_engagement' =>",
                "'scope' =>",
                "'authorization_boundary' =>",
                'definition_version',
                'scope_snapshot',
                'deliverables_snapshot',
                'commercial_terms_snapshot',
                'price_amount',
                'currency',
                'duration_days',
                'accepted_by_user_id',
                'accepted_at',
            ]
            as $token
        ) {
            $this->assertStringContainsString(
                $token,
                $service
            );
        }
    }

    public function test_authorization_is_one_per_exact_commercial_version(): void
    {
        $service =
            $this->source();

        foreach (
            [
                'TransformationImplementationAuthorization::query()',
                "'transformation_implementation_commercial_engagement_id'",
                'lockForUpdate()',
                'STATUS_AUTHORIZED',
                'authorized_by_user_id',
                'authorized_at',
            ]
            as $token
        ) {
            $this->assertStringContainsString(
                $token,
                $service
            );
        }
    }

    public function test_authorization_is_lauda_only(): void
    {
        $service =
            $this->source();

        $this->assertStringContainsString(
            "!== 'admin'",
            $service
        );

        $this->assertStringContainsString(
            'AuthorizationException',
            $service
        );
    }

    public function test_authorization_does_not_start_execution_or_mutate_canonical(): void
    {
        $service =
            $this->source();

        foreach (
            [
                'DataTransformationBiCanonicalModelService',
                'TransformationImplementationExecutionService',
                'TransformationImplementationCapabilityExecution::',
                'TransformationImplementationPhaseExecution::',
                'Subscription::',
                'Invoice::',
                'Payment::',
                'acceptPlan(',
            ]
            as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $service
            );
        }

        foreach (
            [
                "'ready_for_execution_mutated' =>\n                            false",
                "'execution_started' =>\n                            false",
                "'canonical_write' =>\n                            false",
            ]
            as $token
        ) {
            $this->assertStringContainsString(
                $token,
                $service
            );
        }
    }

    public function test_authorization_does_not_mutate_request_definition_or_engagement(): void
    {
        $service =
            $this->source();

        foreach (
            [
                '$request->forceFill(',
                '$definition->forceFill(',
                '$lockedEngagement->forceFill(',
                '$request->update(',
                '$definition->update(',
                '$lockedEngagement->update(',
            ]
            as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $service
            );
        }
    }
}
