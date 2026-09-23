<?php

namespace Tests\Unit\Diagnosis;

use App\Jobs\DataTransformationBi\ProfileDataTransformationBiSourceAsset;
use App\Services\Diagnosis\DataTransformationBiSourceAssetProfilingDispatchService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class DataTransformationBiSourceAssetAsyncProfilingContractTest
    extends TestCase
{
    private function sourceFor(
        string $class
    ): string {
        $reflection =
            new ReflectionClass(
                $class
            );

        $path =
            $reflection->getFileName();

        self::assertIsString(
            $path
        );

        $source =
            file_get_contents(
                $path
            );

        self::assertIsString(
            $source
        );

        return $source;
    }

    public function test_async_job_has_explicit_runtime_boundary(): void
    {
        $source =
            $this->sourceFor(
                ProfileDataTransformationBiSourceAsset::class
            );

        self::assertStringContainsString(
            'implements ShouldQueue',
            $source
        );

        self::assertStringContainsString(
            'public int $tries = 1',
            $source
        );

        self::assertStringContainsString(
            'public int $timeout = 110',
            $source
        );

        self::assertStringContainsString(
            'public bool $failOnTimeout = true',
            $source
        );

        self::assertStringContainsString(
            "'memory_limit'",
            $source
        );

        self::assertStringContainsString(
            "'128M'",
            $source
        );
    }

    public function test_job_is_pinned_to_asset_file_sha_and_run_uuid(): void
    {
        $source =
            $this->sourceFor(
                ProfileDataTransformationBiSourceAsset::class
            );

        foreach (
            [
                '$sourceFileId',
                '$sourceSha256',
                '$runUuid',
                'profiling_job_uuid',
                'hash_equals',
                'markProcessingIfCurrent',
                'markCompletedIfCurrent',
                'markFailedIfCurrent',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_dispatch_blocks_duplicate_running_profile(): void
    {
        $source =
            $this->sourceFor(
                DataTransformationBiSourceAssetProfilingDispatchService::class
            );

        self::assertStringContainsString(
            '::PROFILING_QUEUED',
            $source
        );

        self::assertStringContainsString(
            '::PROFILING_PROCESSING',
            $source
        );

        self::assertStringContainsString(
            'La fuente ya tiene un profiling técnico en proceso.',
            $source
        );

        self::assertStringContainsString(
            'assertCanManage',
            $source
        );
    }

    public function test_async_layer_remains_source_centric(): void
    {
        $sources = [
            $this->sourceFor(
                ProfileDataTransformationBiSourceAsset::class
            ),
            $this->sourceFor(
                DataTransformationBiSourceAssetProfilingDispatchService::class
            ),
        ];

        foreach ($sources as $source) {
            foreach (
                [
                    'domain_key',
                    'DataTransformationBiStandardIntakeSchema',
                    'DataTransformationBiIntakeDomainDelivery',
                    'DataTransformationBiSourceDomainFile',
                ]
                as $forbidden
            ) {
                self::assertStringNotContainsString(
                    $forbidden,
                    $source
                );
            }
        }
    }

    public function test_replacing_file_invalidates_async_profile(): void
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
                .'DataTransformationBiSourceAssetDataUploadService.php'
            );

        self::assertIsString(
            $source
        );

        foreach (
            [
                "'profiling_status'",
                '::PROFILING_IDLE',
                "'profiling_job_uuid'",
                "'profiling_queued_at'",
                "'profiling_started_at'",
                "'profiling_finished_at'",
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_controller_dispatches_and_returns_202(): void
    {
        $root =
            dirname(
                __DIR__,
                3
            );

        $controller =
            file_get_contents(
                $root
                .'/app/Http/Controllers/Admin/'
                .'AdminDataTransformationBiIntakeV2Controller.php'
            );

        self::assertIsString(
            $controller
        );

        $start =
            strpos(
                $controller,
                'public function profileSourceAsset('
            );

        $end =
            strpos(
                $controller,
                'public function profileSourceAssetStatus(',
                $start
            );

        self::assertNotFalse(
            $start
        );

        self::assertNotFalse(
            $end
        );

        $action =
            substr(
                $controller,
                $start,
                $end - $start
            );

        self::assertStringContainsString(
            'DataTransformationBiSourceAssetProfilingDispatchService $service',
            $action
        );

        self::assertStringContainsString(
            '$service->dispatch(',
            $action
        );

        self::assertStringContainsString(
            '202',
            $action
        );

        self::assertStringNotContainsString(
            '$service->profile(',
            $action
        );
    }
}
