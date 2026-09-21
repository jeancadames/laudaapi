<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DataTransformationBiSourceAsset;
use App\Models\DataTransformationBiSourceAssetFile;
use App\Services\Diagnosis\DataTransformationBiSourceAssetDataUploadService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class DataTransformationBiSourceAssetDataArtifactContractTest
    extends TestCase
{
    private string $migration;
    private string $model;
    private string $assetModel;
    private string $service;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(__DIR__, 3);

        $this->migration =
            file_get_contents(
                $root
                .'/database/migrations/'
                .'2026_09_18_153000_create_'
                .'data_transformation_bi_source_asset_files.php'
            );

        $this->model =
            file_get_contents(
                $root
                .'/app/Models/'
                .'DataTransformationBiSourceAssetFile.php'
            );

        $this->assetModel =
            file_get_contents(
                $root
                .'/app/Models/'
                .'DataTransformationBiSourceAsset.php'
            );

        $this->service =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiSourceAssetDataUploadService.php'
            );
    }

    public function test_artifact_is_separate_from_logical_source_asset(): void
    {
        self::assertStringContainsString(
            "'data_transformation_bi_source_asset_files'",
            $this->migration
        );

        self::assertStringContainsString(
            "'data_transformation_bi_source_asset_id'",
            $this->migration
        );

        self::assertStringContainsString(
            "public function dataFile(): HasOne",
            $this->assetModel
        );
    }

    public function test_one_current_artifact_exists_per_source_asset(): void
    {
        self::assertStringContainsString(
            "'dtbi_sa_files_asset_uniq'",
            $this->migration
        );

        self::assertStringContainsString(
            "->unique(",
            $this->migration
        );
    }

    public function test_artifact_preserves_private_storage_metadata(): void
    {
        foreach (
            [
                "'source_disk'",
                "'source_path'",
                "'original_filename'",
                "'source_format'",
                "'source_size_bytes'",
                "'source_sha256'",
                "'reader_configuration'",
                "'source_structure_snapshot'",
                "'source_row_count'",
            ]
            as $token
        ) {
            self::assertStringContainsString(
                $token,
                $this->migration
            );
        }

        self::assertStringContainsString(
            "'source_path'",
            $this->model
        );

        self::assertStringContainsString(
            'protected $hidden',
            $this->model
        );
    }

    public function test_upload_service_reuses_source_file_reader(): void
    {
        $reflection =
            new ReflectionClass(
                DataTransformationBiSourceAssetDataUploadService::class
            );

        self::assertTrue(
            $reflection->hasMethod(
                'persist'
            )
        );

        self::assertStringContainsString(
            'DataTransformationBiSourceFileReader',
            $this->service
        );

        self::assertStringContainsString(
            '->inspect(',
            $this->service
        );
    }

    public function test_upload_contract_accepts_only_csv_xlsx(): void
    {
        self::assertSame(
            'csv',
            DataTransformationBiSourceAssetFile::FORMAT_CSV
        );

        self::assertSame(
            'xlsx',
            DataTransformationBiSourceAssetFile::FORMAT_XLSX
        );

        self::assertStringContainsString(
            'MAX_UPLOAD_KILOBYTES',
            $this->service
        );

        self::assertStringContainsString(
            '32768',
            $this->service
        );
    }

    public function test_upload_is_not_bound_to_canonical_domain(): void
    {
        self::assertStringNotContainsString(
            'DataTransformationBiSourceDomainRegistry',
            $this->service
        );

        self::assertStringNotContainsString(
            'DataTransformationBiIntakeDomainDelivery',
            $this->service
        );

        self::assertStringNotContainsString(
            "'domain_key'",
            $this->service
        );
    }

    public function test_upload_does_not_store_raw_rows_or_credentials(): void
    {
        $lower =
            strtolower(
                $this->service
            );

        foreach (
            [
                'password',
                'connection_string',
                'access_token',
                'sqlsrv_connect',
                'odbc_connect',
                'raw_rows',
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $lower
            );
        }
    }

    public function test_safe_payload_does_not_expose_private_path(): void
    {
        $start =
            strpos(
                $this->service,
                'private function payload('
            );

        self::assertNotFalse(
            $start
        );

        $end =
            strpos(
                $this->service,
                'private function assertCanManage(',
                $start
            );

        self::assertNotFalse(
            $end
        );

        $payload =
            substr(
                $this->service,
                $start,
                $end - $start
            );

        self::assertStringNotContainsString(
            "'source_path'",
            $payload
        );

        self::assertStringNotContainsString(
            "'source_disk'",
            $payload
        );

        self::assertStringContainsString(
            "'original_filename'",
            $payload
        );

        self::assertStringContainsString(
            "'source_sha256'",
            $payload
        );
    }

    public function test_file_upload_updates_source_progress_without_profiling_values(): void
    {
        self::assertStringContainsString(
            '::STRUCTURE_ANALYZED',
            $this->service
        );

        self::assertStringContainsString(
            '::DATA_RECEIVED',
            $this->service
        );

        self::assertStringContainsString(
            "'profiling_snapshot' =>",
            $this->service
        );

        self::assertStringContainsString(
            "'profiled_at' =>",
            $this->service
        );

        self::assertStringContainsString(
            "'observed_file'",
            $this->service
        );
    }

    public function test_replacement_cleanup_is_supported(): void
    {
        self::assertStringContainsString(
            '$oldArtifactPath',
            $this->service
        );

        self::assertStringContainsString(
            '$disk->delete(',
            $this->service
        );

        self::assertStringContainsString(
            'lockForUpdate()',
            $this->service
        );
    }

    public function test_logical_source_still_stores_no_private_file_path(): void
    {
        self::assertStringNotContainsString(
            "'source_path'",
            $this->assetModel
        );

        self::assertStringNotContainsString(
            "'source_disk'",
            $this->assetModel
        );
    }
}
