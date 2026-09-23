<?php

namespace Tests\Unit\Diagnosis;

use App\Jobs\DataTransformationBi\ProfileDataTransformationBiSourceAsset;
use App\Services\Diagnosis\DataTransformationBiSourceAssetProfilingDispatchService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class DataTransformationBiDedicatedProfilingQueueContractTest
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

    public function test_data_bi_has_dedicated_database_queue_connection(): void
    {
        $root =
            dirname(
                __DIR__,
                3
            );

        $config =
            file_get_contents(
                $root
                .'/config/queue.php'
            );

        self::assertIsString(
            $config
        );

        foreach (
            [
                "'data_bi' => [",
                "'driver' => 'database'",
                "env('DATA_BI_QUEUE', 'data-bi')",
                "'DATA_BI_QUEUE_RETRY_AFTER'",
                '900',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $config
            );
        }
    }

    public function test_profile_dispatch_uses_dedicated_connection_and_queue(): void
    {
        $source =
            $this->sourceFor(
                DataTransformationBiSourceAssetProfilingDispatchService::class
            );

        self::assertStringContainsString(
            "->onConnection(\n                    'data_bi'",
            $source
        );

        self::assertStringContainsString(
            "->onQueue(\n                    'data-bi'",
            $source
        );

        self::assertStringNotContainsString(
            "->onQueue(\n                    'default'",
            $source
        );
    }

    public function test_job_timeout_remains_below_retry_after_boundary(): void
    {
        $job =
            new ReflectionClass(
                ProfileDataTransformationBiSourceAsset::class
            );

        $defaults =
            $job->getDefaultProperties();

        self::assertSame(
            840,
            $defaults['timeout']
        );

        self::assertSame(
            1,
            $defaults['tries']
        );

        self::assertTrue(
            $defaults['failOnTimeout']
        );

        self::assertLessThan(
            900,
            $defaults['timeout']
        );
    }

    public function test_env_example_documents_dedicated_queue(): void
    {
        $root =
            dirname(
                __DIR__,
                3
            );

        $env =
            file_get_contents(
                $root
                .'/.env.example'
            );

        self::assertIsString(
            $env
        );

        self::assertStringContainsString(
            'DATA_BI_QUEUE=data-bi',
            $env
        );

        self::assertStringContainsString(
            'DATA_BI_QUEUE_RETRY_AFTER=900',
            $env
        );
    }
}
