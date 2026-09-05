<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationRequestDefinitionTenantReviewLatestVersionContractTest
    extends TestCase
{
    private function service(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Services/Diagnosis/'
            .'TransformationImplementationRequestDefinitionTenantReviewService.php'
        );
    }

    public function test_submit_requires_latest_definition_before_readiness_gate(): void
    {
        $service =
            $this->service();

        $submitStart =
            strpos(
                $service,
                'public function submit('
            );

        $submitEnd =
            strpos(
                $service,
                'private function assertLaudaAdmin(',
                $submitStart
            );

        $this->assertNotFalse(
            $submitStart
        );

        $this->assertNotFalse(
            $submitEnd
        );

        $submit =
            substr(
                $service,
                $submitStart,
                $submitEnd - $submitStart
            );

        $contextPos =
            strpos(
                $submit,
                '$this->assertRequestContext('
            );

        $latestPos =
            strpos(
                $submit,
                '$this->assertLatestDefinition('
            );

        $readinessPos =
            strpos(
                $submit,
                '$this->assertReadyForTenantReview('
            );

        $this->assertNotFalse(
            $contextPos
        );

        $this->assertNotFalse(
            $latestPos
        );

        $this->assertNotFalse(
            $readinessPos
        );

        $this->assertLessThan(
            $latestPos,
            $contextPos
        );

        $this->assertLessThan(
            $readinessPos,
            $latestPos
        );
    }

    public function test_latest_definition_is_server_resolved_with_exact_request_scope(): void
    {
        $service =
            $this->service();

        foreach ([
            'private function assertLatestDefinition(',
            'TransformationImplementationDefinition::query()',
            "'transformation_implementation_request_id'",
            "'company_id'",
            "'diagnosis_assessment_id'",
            "'transformation_implementation_plan_id'",
            "'transformation_implementation_phase_capability_id'",
            "'capability_key'",
            "'version'",
            "'id'",
            'lockForUpdate()',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $service
            );
        }
    }

    public function test_stale_definition_is_explicitly_rejected(): void
    {
        $service =
            $this->service();

        foreach ([
            '(int) $latest->id',
            '(int) $definition->id',
            'Solo la versión más reciente de la Definition puede enviarse',
            'ValidationException::withMessages',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $service
            );
        }
    }

    public function test_existing_v1_only_flow_remains_compatible(): void
    {
        $service =
            $this->service();

        /*
         * No existe requisito de version >= 2.
         * Si V1 es la única Definition del Request, es la latest y pasa.
         */
        foreach ([
            '$latest->id',
            '$definition->id',
            'orderByDesc(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $service
            );
        }

        foreach ([
            'version >= 2',
            'version > 1',
            '$definition->version >= 2',
            '$definition->version > 1',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $service
            );
        }
    }

    public function test_submit_still_has_no_ready_or_commercial_side_effect(): void
    {
        $service =
            $this->service();

        foreach ([
            '->markReady(',
            'STATUS_DEFINITION_AGREED',
            'STATUS_READY_FOR_COMMERCIAL',
            'TransformationImplementationExecutionService',
            'TransformationImplementationPricingService',
            'TransformationCapabilityActivationService',
            'Subscription::create',
            'Invoice::create',
            'Payment::create',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $service
            );
        }

        foreach ([
            'STATUS_AWAITING_TENANT_REVIEW',
            "'definition_ready' =>",
            "'tenant_agreed' =>",
            "'ready_for_execution' =>",
            "'execution_started' =>",
            "'commercial_stage_started' =>",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $service
            );
        }
    }
}
