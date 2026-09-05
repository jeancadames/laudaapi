<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiTenantImplementationRequestReadModelContractTest
    extends TestCase
{
    private function source(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Http/Controllers/AppHubDataTransformationBiController.php'
        );
    }

    public function test_get_exposes_complete_implementation_request_prop(): void
    {
        $source = $this->source();

        foreach ([
            "'implementation_request' =>",
            'implementationRequestState(',
            "'id' =>",
            "'status' =>",
            "'status_label' =>",
            "'requested_at' =>",
            "'attempt' =>",
            "'can_request' =>",
            "'request_endpoint' =>",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_read_model_is_scoped_server_side(): void
    {
        $source = $this->source();

        foreach ([
            "'company_id'",
            "'diagnosis_assessment_id'",
            'TransformationImplementationPlan::STATUS_PRESENTED',
            "'data_transformation_bi'",
            "'transformation_implementation_plan_id'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_read_model_can_only_reopen_after_cancel(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            'STATUS_CANCELLED',
            $source
        );

        $this->assertStringContainsString(
            "'can_request'",
            $source
        );
    }

    public function test_read_model_has_no_activation_or_commercial_actions(): void
    {
        $source = $this->source();

        foreach ([
            'TransformationCapabilityActivationService',
            'TransformationImplementationDefinitionService',
            'TransformationImplementationExecutionService',
            'TransformationImplementationCommercialEngine',
            'TransformationImplementationSubscriptionService',
            'CentralEntitlementActivationService',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }
}
