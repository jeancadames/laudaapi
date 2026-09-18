<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiSourceDomainUploadServiceContractTest
    extends TestCase
{
    private string $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiSourceDomainUploadService.php'
            );

        self::assertIsString(
            $this->service
        );
    }

    public function test_it_uses_existing_domain_delivery_as_anchor(): void
    {
        self::assertStringContainsString(
            'DataTransformationBiIntakeDomainDelivery::query()',
            $this->service
        );

        self::assertStringContainsString(
            "'data_transformation_bi_intake_domain_delivery_id'",
            $this->service
        );

        self::assertStringContainsString(
            "'domain_key'",
            $this->service
        );
    }

    public function test_it_only_accepts_registered_source_domains(): void
    {
        self::assertStringContainsString(
            'DataTransformationBiSourceDomainRegistry',
            $this->service
        );

        self::assertStringContainsString(
            '::assertSupported(',
            $this->service
        );
    }

    public function test_it_inspects_client_native_file_without_canonical_validation(): void
    {
        self::assertStringContainsString(
            'DataTransformationBiSourceFileReader',
            $this->service
        );

        self::assertStringContainsString(
            '->inspect(',
            $this->service
        );

        self::assertStringNotContainsString(
            'DataTransformationBiDomainIntakeValidationService',
            $this->service
        );

        self::assertStringNotContainsString(
            'DataTransformationBiStandardIntakeSchema::fields',
            $this->service
        );
    }

    public function test_it_persists_structure_metadata_not_raw_rows(): void
    {
        self::assertStringContainsString(
            "'source_structure_snapshot'",
            $this->service
        );

        self::assertStringContainsString(
            "'reader_configuration'",
            $this->service
        );

        self::assertStringContainsString(
            "'profiling_snapshot'",
            $this->service
        );

        self::assertStringNotContainsString(
            'DataTransformationBiIntakeRow',
            $this->service
        );

        self::assertStringNotContainsString(
            'raw_rows',
            $this->service
        );
    }

    public function test_it_does_not_promote_canonical_delivery(): void
    {
        self::assertStringNotContainsString(
            "DataTransformationBiIntakeDomainDelivery::STATUS_VALID",
            $this->service
        );

        self::assertStringNotContainsString(
            "'delivery_mode' =>",
            $this->service
        );

        self::assertStringNotContainsString(
            "'validation_snapshot' =>",
            $this->service
        );
    }

    public function test_it_uses_private_storage_and_hashed_path(): void
    {
        self::assertStringContainsString(
            "public const SOURCE_DISK = 'private';",
            $this->service
        );

        self::assertStringContainsString(
            "'source-native'",
            $this->service
        );

        self::assertStringContainsString(
            '$sourceSha256',
            $this->service
        );
    }

    public function test_replacement_invalidates_old_field_mappings(): void
    {
        self::assertStringContainsString(
            '->fieldMappings()',
            $this->service
        );

        self::assertStringContainsString(
            '->delete();',
            $this->service
        );
    }

    public function test_multi_sheet_row_count_remains_unresolved_until_selection(): void
    {
        self::assertStringContainsString(
            'count($sheets) !== 1',
            $this->service
        );

        self::assertStringContainsString(
            'return 0;',
            $this->service
        );
    }
}
