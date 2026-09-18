<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiDynamicSourceDataFileStateContractTest
    extends TestCase
{
    private string $state;
    private string $fileModel;
    private string $method;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(__DIR__, 3);

        $this->state =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiIntakeV2StateService.php'
            );

        $this->fileModel =
            file_get_contents(
                $root
                .'/app/Models/'
                .'DataTransformationBiSourceAssetFile.php'
            );

        $start =
            strpos(
                $this->state,
                'private function sourceAssetsPayload('
            );

        self::assertNotFalse(
            $start
        );

        $this->method =
            substr(
                $this->state,
                $start
            );
    }

    public function test_state_remains_safe_before_file_artifact_migration(): void
    {
        self::assertStringContainsString(
            "Schema::hasTable(\n"
            ."                'data_transformation_bi_source_assets'",
            $this->method
        );

        self::assertStringContainsString(
            "Schema::hasTable(\n"
            ."                'data_transformation_bi_source_asset_files'",
            $this->method
        );
    }

    public function test_current_data_file_is_nested_under_source_asset(): void
    {
        self::assertStringContainsString(
            "'dataFile'",
            $this->method
        );

        self::assertStringContainsString(
            "'data_file' =>",
            $this->method
        );

        self::assertStringContainsString(
            "\$asset->relationLoaded(\n"
            ."                            'dataFile'",
            $this->method
        );
    }

    public function test_state_exposes_only_safe_file_metadata(): void
    {
        foreach (
            [
                "'original_filename'",
                "'source_format'",
                "'source_mime_type'",
                "'source_size_bytes'",
                "'source_sha256'",
                "'reader_configuration'",
                "'source_structure_snapshot'",
                "'source_row_count'",
                "'uploaded_at'",
            ]
            as $token
        ) {
            self::assertStringContainsString(
                $token,
                $this->method
            );
        }

        self::assertStringNotContainsString(
            "'source_path'",
            $this->method
        );

        self::assertStringNotContainsString(
            "'source_disk'",
            $this->method
        );
    }

    public function test_file_model_hides_private_storage_fields(): void
    {
        self::assertStringContainsString(
            "protected \$hidden",
            $this->fileModel
        );

        self::assertStringContainsString(
            "'source_disk'",
            $this->fileModel
        );

        self::assertStringContainsString(
            "'source_path'",
            $this->fileModel
        );
    }

    public function test_state_does_not_expose_raw_rows_or_credentials(): void
    {
        $lower =
            strtolower(
                $this->method
            );

        foreach (
            [
                'raw_rows',
                'connection_string',
                'access_token',
                'password',
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $lower
            );
        }
    }
}
