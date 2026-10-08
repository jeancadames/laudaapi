<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DataTransformationBiImplementationMaterializationRun;
use PHPUnit\Framework\TestCase;

class DataTransformationBiImplementationMaterializationRunFoundationContractTest
    extends TestCase
{
    public function test_run_model_has_async_lifecycle_contract(): void
    {
        $this->assertSame(
            'queued',
            DataTransformationBiImplementationMaterializationRun
                ::STATUS_QUEUED
        );

        $this->assertSame(
            'processing',
            DataTransformationBiImplementationMaterializationRun
                ::STATUS_PROCESSING
        );

        $this->assertSame(
            'completed',
            DataTransformationBiImplementationMaterializationRun
                ::STATUS_COMPLETED
        );

        $this->assertSame(
            'failed',
            DataTransformationBiImplementationMaterializationRun
                ::STATUS_FAILED
        );

        $this->assertSame(
            [
                'queued',
                'processing',
                'completed',
                'failed',
            ],
            DataTransformationBiImplementationMaterializationRun
                ::STATUSES
        );
    }

    public function test_run_is_separate_from_intake_and_dataset_lifecycle(): void
    {
        $root =
            dirname(
                __DIR__,
                3
            );

        $migration =
            file_get_contents(
                $root
                .'/database/migrations/'
                .'2026_10_08_100500_create_data_transformation_bi_implementation_materialization_runs.php'
            );

        $this->assertIsString(
            $migration
        );

        foreach (
            [
                'run_uuid',
                'company_id',
                'transformation_implementation_request_id',
                'data_transformation_bi_intake_session_id',
                'requested_by_user_id',
                'selected_mapping_count',
                'materialized_dataset_count',
                'reused_dataset_count',
                'result_snapshot',
                'failure_code',
                'failure_message',
                'queued_at',
                'started_at',
                'finished_at',
            ]
            as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $migration
            );
        }

        foreach (
            [
                'ready_for_execution',
                'execution_started',
                'resulting_intake_batch_id',
                'processing_run_id',
                'intake_batch_id',
            ]
            as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $migration
            );
        }
    }

    public function test_run_does_not_store_canonical_row_payloads(): void
    {
        $root =
            dirname(
                __DIR__,
                3
            );

        $migration =
            file_get_contents(
                $root
                .'/database/migrations/'
                .'2026_10_08_100500_create_data_transformation_bi_implementation_materialization_runs.php'
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
                $migration
            );
        }
    }
}
