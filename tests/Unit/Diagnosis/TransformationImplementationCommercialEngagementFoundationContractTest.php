<?php

namespace Tests\Unit\Diagnosis;

use Tests\TestCase;

final class TransformationImplementationCommercialEngagementFoundationContractTest
    extends TestCase
{
    public function test_commercial_engagement_foundation_is_request_scoped_and_separate_from_execution(): void
    {
        $root = base_path();

        $migration = file_get_contents(
            $root
            .'/database/migrations/2026_10_07_183500_create_transformation_implementation_commercial_engagement_foundation.php'
        );

        $engagement = file_get_contents(
            $root
            .'/app/Models/TransformationImplementationCommercialEngagement.php'
        );

        $authorization = file_get_contents(
            $root
            .'/app/Models/TransformationImplementationAuthorization.php'
        );

        $request = file_get_contents(
            $root
            .'/app/Models/TransformationImplementationRequest.php'
        );

        foreach (
            [
                'transformation_implementation_commercial_engagements',
                'transformation_implementation_authorizations',
                'transformation_implementation_request_id',
                'transformation_implementation_definition_id',
                'transformation_implementation_phase_capability_id',
                'capability_key',
                'scope_snapshot',
                'commercial_terms_snapshot',
                'authorization_snapshot',
            ]
            as $token
        ) {
            $this->assertStringContainsString(
                $token,
                $migration
            );
        }

        $this->assertStringContainsString(
            "'tice_request_version_unique'",
            $migration
        );

        $this->assertStringContainsString(
            "'tia_engagement_unique'",
            $migration
        );

        foreach (
            [
                "STATUS_DRAFT = 'draft'",
                "STATUS_PRESENTED = 'presented'",
                "STATUS_ACCEPTED = 'accepted'",
                "STATUS_REJECTED = 'rejected'",
                "STATUS_SUPERSEDED = 'superseded'",
                "STATUS_CANCELLED = 'cancelled'",
            ]
            as $token
        ) {
            $this->assertStringContainsString(
                $token,
                $engagement
            );
        }

        foreach (
            [
                "STATUS_AUTHORIZED = 'authorized'",
                "STATUS_REVOKED = 'revoked'",
                'authorization_snapshot',
                'authorized_at',
                'revoked_at',
            ]
            as $token
        ) {
            $this->assertStringContainsString(
                $token,
                $authorization
            );
        }

        $this->assertStringContainsString(
            'function commercialEngagements(): HasMany',
            $request
        );

        $this->assertStringContainsString(
            'function implementationAuthorizations(): HasMany',
            $request
        );
    }

    public function test_foundation_does_not_reactivate_historical_plan_commercial_flow_or_unlock_data_bi(): void
    {
        $migration = file_get_contents(
            base_path(
                'database/migrations/2026_10_07_183500_create_transformation_implementation_commercial_engagement_foundation.php'
            )
        );

        $engagement = file_get_contents(
            base_path(
                'app/Models/TransformationImplementationCommercialEngagement.php'
            )
        );

        $authorization = file_get_contents(
            base_path(
                'app/Models/TransformationImplementationAuthorization.php'
            )
        );

        /*
         * Foundation only.
         *
         * No lifecycle service, Plan acceptance or Canonical mutation
         * may be introduced by this patch.
         */
        foreach (
            [
                'acceptPlan(',
                'DataTransformationBiCanonicalModelService',
                'ready_for_execution',
                'execution_started',
                'Subscription::',
                'Invoice::',
                'Payment::',
            ]
            as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $migration
                .$engagement
                .$authorization
            );
        }
    }
}
