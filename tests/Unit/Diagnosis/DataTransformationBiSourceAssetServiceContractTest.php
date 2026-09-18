<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataTransformationBiSourceAssetService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class DataTransformationBiSourceAssetServiceContractTest
    extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(__DIR__, 3);

        $path =
            $root
            .'/app/Services/Diagnosis/'
            .'DataTransformationBiSourceAssetService.php';

        $this->source =
            file_get_contents(
                $path
            );

        self::assertIsString(
            $this->source
        );
    }

    public function test_service_exposes_controlled_source_asset_lifecycle(): void
    {
        $reflection =
            new ReflectionClass(
                DataTransformationBiSourceAssetService::class
            );

        self::assertTrue(
            $reflection->hasMethod(
                'create'
            )
        );

        self::assertTrue(
            $reflection->hasMethod(
                'update'
            )
        );

        self::assertTrue(
            $reflection->hasMethod(
                'reorder'
            )
        );

        self::assertTrue(
            $reflection->hasMethod(
                'archive'
            )
        );
    }

    public function test_service_does_not_require_a_canonical_domain(): void
    {
        self::assertStringNotContainsString(
            'DataTransformationBiSourceDomainRegistry',
            $this->source
        );

        self::assertStringNotContainsString(
            'DataTransformationBiIntakeDomainDelivery',
            $this->source
        );

        self::assertStringNotContainsString(
            "'domain_key'",
            $this->source
        );
    }

    public function test_origin_system_is_metadata_not_a_gate(): void
    {
        self::assertStringContainsString(
            "'origin_system'",
            $this->source
        );

        self::assertStringNotContainsString(
            'SOURCE_TYPES',
            $this->source
        );

        self::assertStringNotContainsString(
            'assertOrigin',
            $this->source
        );
    }

    public function test_delivery_preference_is_limited_to_csv_and_xlsx(): void
    {
        self::assertStringContainsString(
            '::DELIVERY_CSV',
            $this->source
        );

        self::assertStringContainsString(
            '::DELIVERY_XLSX',
            $this->source
        );

        self::assertStringContainsString(
            'El formato de entrega debe ser CSV o XLSX.',
            $this->source
        );
    }

    public function test_service_does_not_collect_credentials_or_connect_remotely(): void
    {
        $lower =
            strtolower(
                $this->source
            );

        self::assertStringNotContainsString(
            'password',
            $lower
        );

        self::assertStringNotContainsString(
            'connection_string',
            $lower
        );

        self::assertStringNotContainsString(
            'sqlsrv_connect',
            $lower
        );

        self::assertStringNotContainsString(
            'odbc_connect',
            $lower
        );

        self::assertStringNotContainsString(
            'db::connection',
            $lower
        );
    }

    public function test_archive_is_soft_business_archive_not_delete(): void
    {
        self::assertStringContainsString(
            '::STATUS_ARCHIVED',
            $this->source
        );

        self::assertStringContainsString(
            "'archived_at'",
            $this->source
        );

        self::assertStringNotContainsString(
            '->delete(',
            $this->source
        );

        self::assertStringNotContainsString(
            'forceDelete',
            $this->source
        );
    }

    public function test_reorder_is_scoped_to_active_sources_in_session(): void
    {
        self::assertStringContainsString(
            "'data_transformation_bi_intake_session_id'",
            $this->source
        );

        self::assertStringContainsString(
            "->whereNull(\n                            'archived_at'",
            $this->source
        );

        self::assertStringContainsString(
            "'sort_order'",
            $this->source
        );

        self::assertStringContainsString(
            'lockForUpdate()',
            $this->source
        );
    }

    public function test_session_must_remain_editable(): void
    {
        self::assertStringContainsString(
            '::STATUS_DRAFT',
            $this->source
        );

        self::assertStringContainsString(
            '::STATUS_READY',
            $this->source
        );

        self::assertStringContainsString(
            'assertEditableSession',
            $this->source
        );
    }
}
