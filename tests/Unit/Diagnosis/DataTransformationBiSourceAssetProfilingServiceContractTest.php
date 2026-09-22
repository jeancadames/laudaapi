<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataTransformationBiSourceAssetProfilingService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class DataTransformationBiSourceAssetProfilingServiceContractTest
    extends TestCase
{
    private function source(): string
    {
        $reflection =
            new ReflectionClass(
                DataTransformationBiSourceAssetProfilingService::class
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

    public function test_profiling_is_source_asset_centric_and_domain_agnostic(): void
    {
        $source =
            $this->source();

        self::assertStringContainsString(
            'DataTransformationBiSourceAsset',
            $source
        );

        self::assertStringContainsString(
            'DataTransformationBiSourceAssetFile',
            $source
        );

        self::assertStringContainsString(
            'DataTransformationBiSourceValueProfiler',
            $source
        );

        self::assertStringNotContainsString(
            'domain_key',
            $source
        );

        self::assertStringNotContainsString(
            'DataTransformationBiStandardIntakeSchema',
            $source
        );

        self::assertStringNotContainsString(
            'DataTransformationBiIntakeDomainDelivery',
            $source
        );

        self::assertStringNotContainsString(
            'DataTransformationBiSourceDomainFile',
            $source
        );
    }

    public function test_profile_is_admin_technical_work_and_marks_source_analyzed(): void
    {
        $source =
            $this->source();

        self::assertStringContainsString(
            'El profiling técnico de fuentes corresponde a Admin LAUDA.',
            $source
        );

        self::assertStringContainsString(
            '::DATA_ANALYZED',
            $source
        );

        self::assertStringContainsString(
            "'profiling_snapshot'",
            $source
        );

        self::assertStringContainsString(
            "'profiled_at'",
            $source
        );

        self::assertStringContainsString(
            'assertCanManage',
            $source
        );
    }

    public function test_profile_is_pinned_to_the_current_source_artifact(): void
    {
        $source =
            $this->source();

        self::assertStringContainsString(
            'hash_file',
            $source
        );

        self::assertStringContainsString(
            'hash_equals',
            $source
        );

        self::assertStringContainsString(
            'El archivo fuente fue reemplazado durante el profiling.',
            $source
        );

        self::assertStringContainsString(
            "'source_sha256'",
            $source
        );

        self::assertStringContainsString(
            "'source_file_id'",
            $source
        );
    }
}
