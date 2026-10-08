<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DataTransformationBiImplementationMaterializationDispatchContractTest
    extends TestCase
{
    public function test_dispatch_reservation_is_session_locked_and_idempotent(): void
    {
        $source =
            $this->source();

        foreach (
            [
                'DB::transaction(',
                'assertActiveForRequest(',
                'lockForUpdate()',
                "'data_transformation_bi_intake_session_id'",
                '::STATUS_QUEUED',
                '::STATUS_PROCESSING',
                '->whereIn(',
                "'status'",
                "'reused'",
                'Str::uuid()',
            ]
            as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_queued_run_uses_at_least_once_delivery(): void
    {
        $source =
            $this->source();

        foreach (
            [
                'at-least-once',
                'STATUS_PROCESSING',
                'MaterializeDataTransformationBiImplementationSession',
                '::dispatch(',
                "->onConnection(\n                    'data_bi'",
                "->onQueue(\n                    'data-bi'",
                'markDispatchFailure(',
                'implementation_materialization_dispatch_failed',
            ]
            as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        /*
         * The old behavior caused the dispatch gap:
         *
         * reused QUEUED run => immediate return => no redispatch.
         */
        $this->assertStringNotContainsString(
            "if (\$reservation['reused']) {\n            return \$reservation;\n        }",
            $source
        );

        /*
         * We only suppress redispatch when a worker already owns the run.
         */
        $this->assertStringContainsString(
            "\$reservation['reused']\n            && (string) \$run->status",
            $source
        );

        $this->assertStringContainsString(
            '::STATUS_PROCESSING',
            $source
        );
    }

    public function test_dispatch_does_not_touch_legacy_or_execution_lifecycle(): void
    {
        $source =
            $this->source();

        foreach (
            [
                'TransformationImplementationExecutionService',
                'ready_for_execution',
                'execution_started',
                'DataTransformationBiProcessingRun',
                'DataTransformationBiIntakeBatch',
                'DataTransformationBiNormalizedRow',
                'StagingMaterialization',
            ]
            as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    private function source(): string
    {
        $root =
            dirname(
                __DIR__,
                3
            );

        $source =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiImplementationMaterializationDispatchService.php'
            );

        $this->assertIsString(
            $source
        );

        return $source;
    }
}
