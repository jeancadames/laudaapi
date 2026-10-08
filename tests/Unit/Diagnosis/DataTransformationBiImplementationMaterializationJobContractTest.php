<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DataTransformationBiImplementationMaterializationJobContractTest
    extends TestCase
{
    public function test_job_uses_dedicated_worker_boundary(): void
    {
        $source =
            $this->source();

        foreach (
            [
                'implements ShouldQueue',
                'public int $tries = 1;',
                'public int $timeout = 840;',
                'public bool $failOnTimeout = true;',
                'markProcessingIfCurrent()',
                'markCompletedIfCurrent(',
                'markFailedIfCurrent(',
                'runUuid',
                '::STATUS_QUEUED',
                '::STATUS_PROCESSING',
                '::STATUS_COMPLETED',
                '::STATUS_FAILED',
            ]
            as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_job_executes_only_through_modern_orchestrator(): void
    {
        $source =
            $this->source();

        $this->assertStringContainsString(
            'DataTransformationBiImplementationDatasetOrchestrator',
            $source
        );

        $this->assertStringContainsString(
            '->materializeSession(',
            $source
        );

        foreach (
            [
                'DataTransformationBiIntakeV2StagingMaterializationService',
                'DataTransformationBiProcessingRun',
                'DataTransformationBiIntakeBatch',
                'DataTransformationBiNormalizedRow',
                'TransformationImplementationExecutionService',
            ]
            as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_stale_job_requires_matching_uuid_and_queued_state(): void
    {
        $source =
            $this->source();

        foreach (
            [
                "'run_uuid'",
                '$this->runUuid',
                '::STATUS_QUEUED',
                'DATA_BI_IMPLEMENTATION_MATERIALIZATION_STALE_JOB_SKIPPED',
            ]
            as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_completed_snapshot_contains_summary_not_row_payloads(): void
    {
        $source =
            $this->source();

        $this->assertStringContainsString(
            "'result_snapshot'",
            $source
        );

        foreach (
            [
                'canonical_payload',
                'source_row_number',
                'source_row_sha256',
                'canonical_payload_sha256',
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
                .'/app/Jobs/DataTransformationBi/'
                .'MaterializeDataTransformationBiImplementationSession.php'
            );

        $this->assertIsString(
            $source
        );

        return $source;
    }
}
