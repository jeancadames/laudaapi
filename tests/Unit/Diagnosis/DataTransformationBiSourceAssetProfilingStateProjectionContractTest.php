<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiSourceAssetProfilingStateProjectionContractTest
    extends TestCase
{
    private string $service;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(
                __DIR__,
                3
            );

        $this->service =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiIntakeV2StateService.php'
            );

        self::assertIsString(
            $this->service
        );
    }

    public function test_dynamic_source_state_projects_async_profiling_status(): void
    {
        foreach (
            [
                "'profiling_snapshot'",
                "'profiling_status'",
                "'profiling_queued_at'",
                "'profiling_started_at'",
                "'profiling_finished_at'",
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->service
            );
        }
    }

    public function test_payload_exposes_server_owned_profiling_status(): void
    {
        self::assertStringContainsString(
            "'profiling_status' =>",
            $this->service
        );

        self::assertStringContainsString(
            '$asset->profiling_status',
            $this->service
        );

        self::assertStringContainsString(
            '::PROFILING_IDLE',
            $this->service
        );

        self::assertStringContainsString(
            '$asset->profiling_finished_at',
            $this->service
        );
    }
}
