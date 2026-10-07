<?php

namespace Tests\Unit\Diagnosis;

use Tests\TestCase;

final class TransformationImplementationCommercialEngagementServiceContractTest
    extends TestCase
{
    public function test_draft_creation_is_request_scoped_and_pins_exact_ready_definition(): void
    {
        $service = file_get_contents(
            base_path(
                'app/Services/Diagnosis/TransformationImplementationCommercialEngagementService.php'
            )
        );

        foreach (
            [
                'STATUS_READY_FOR_COMMERCIAL',
                'request_ready_for_commercial_by_lauda',
                'definition_id',
                'definition_version',
                'functional_definition_ready',
                'technical_readiness',
                'lockForUpdate()',
                'TransformationImplementationCommercialEngagement::STATUS_DRAFT',
                'scope_snapshot',
                'deliverables_snapshot',
                'commercial_terms_snapshot',
            ]
            as $token
        ) {
            $this->assertStringContainsString(
                $token,
                $service
            );
        }
    }

    public function test_draft_creation_is_lauda_only_and_has_no_execution_side_effects(): void
    {
        $service = file_get_contents(
            base_path(
                'app/Services/Diagnosis/TransformationImplementationCommercialEngagementService.php'
            )
        );

        foreach (
            [
                "!== 'admin'",
                'commercial_acceptance',
                'implementation_authorized',
                'execution_started',
            ]
            as $token
        ) {
            $this->assertStringContainsString(
                $token,
                $service
            );
        }

        foreach (
            [
                'DataTransformationBiCanonicalModelService',
                'TransformationImplementationAuthorization::query()->create',
                'acceptPlan(',
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

    public function test_only_one_active_draft_or_presented_engagement_is_allowed(): void
    {
        $service = file_get_contents(
            base_path(
                'app/Services/Diagnosis/TransformationImplementationCommercialEngagementService.php'
            )
        );

        $this->assertStringContainsString(
            'STATUS_DRAFT',
            $service
        );

        $this->assertStringContainsString(
            'STATUS_PRESENTED',
            $service
        );

        $this->assertStringContainsString(
            'Ya existe una propuesta comercial activa para esta solicitud.',
            $service
        );
    }
}
