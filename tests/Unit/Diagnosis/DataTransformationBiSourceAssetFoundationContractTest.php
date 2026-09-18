<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DataTransformationBiSourceAsset;
use PHPUnit\Framework\TestCase;

final class DataTransformationBiSourceAssetFoundationContractTest
    extends TestCase
{
    private string $migration;
    private string $sessionModel;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(__DIR__, 3);

        $this->migration =
            file_get_contents(
                $root
                .'/database/migrations/'
                .'2026_09_18_141500_create_'
                .'data_transformation_bi_source_assets.php'
            );

        $this->sessionModel =
            file_get_contents(
                $root
                .'/app/Models/'
                .'DataTransformationBiIntakeSession.php'
            );

        self::assertIsString(
            $this->migration
        );

        self::assertIsString(
            $this->sessionModel
        );
    }

    public function test_source_asset_is_not_bound_to_a_canonical_domain(): void
    {
        $model =
            new DataTransformationBiSourceAsset();

        self::assertSame(
            'data_transformation_bi_source_assets',
            $model->getTable()
        );

        $fillable =
            $model->getFillable();

        self::assertContains(
            'data_transformation_bi_intake_session_id',
            $fillable
        );

        self::assertContains(
            'display_name',
            $fillable
        );

        self::assertContains(
            'source_object_name',
            $fillable
        );

        self::assertContains(
            'description',
            $fillable
        );

        self::assertContains(
            'origin_system',
            $fillable
        );

        self::assertNotContains(
            'domain_key',
            $fillable
        );

        self::assertNotContains(
            'data_transformation_bi_intake_domain_delivery_id',
            $fillable
        );
    }

    public function test_origin_system_is_informational_free_text(): void
    {
        self::assertStringContainsString(
            "'origin_system'",
            $this->migration
        );

        self::assertStringNotContainsString(
            "enum(\n                    'origin_system'",
            $this->migration
        );

        self::assertStringNotContainsString(
            'DataTransformationBiSourceDomainRegistry',
            $this->migration
        );
    }

    public function test_actual_data_delivery_is_csv_or_xlsx_at_application_level(): void
    {
        self::assertSame(
            'csv',
            DataTransformationBiSourceAsset::DELIVERY_CSV
        );

        self::assertSame(
            'xlsx',
            DataTransformationBiSourceAsset::DELIVERY_XLSX
        );

        self::assertContains(
            'delivery_format',
            (
                new DataTransformationBiSourceAsset()
            )->getFillable()
        );
    }

    public function test_structure_and_data_progress_are_independent(): void
    {
        $fillable =
            (
                new DataTransformationBiSourceAsset()
            )->getFillable();

        self::assertContains(
            'structure_status',
            $fillable
        );

        self::assertContains(
            'data_status',
            $fillable
        );

        self::assertContains(
            'structure_snapshot',
            $fillable
        );

        self::assertContains(
            'profiling_snapshot',
            $fillable
        );
    }

    public function test_asset_belongs_directly_to_intake_session(): void
    {
        self::assertStringContainsString(
            "'data_transformation_bi_intake_session_id'",
            $this->migration
        );

        self::assertStringContainsString(
            "'data_transformation_bi_intake_sessions'",
            $this->migration
        );

        self::assertStringContainsString(
            'public function sourceAssets(): HasMany',
            $this->sessionModel
        );

        self::assertStringContainsString(
            'DataTransformationBiSourceAsset::class',
            $this->sessionModel
        );
    }

    public function test_asset_does_not_store_credentials_or_raw_rows(): void
    {
        $modelSource =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Models/'
                .'DataTransformationBiSourceAsset.php'
            );

        self::assertIsString(
            $modelSource
        );

        self::assertStringNotContainsString(
            'password',
            strtolower($modelSource)
        );

        self::assertStringNotContainsString(
            'connection_string',
            strtolower($modelSource)
        );

        self::assertStringNotContainsString(
            'source_path',
            $modelSource
        );

        self::assertStringNotContainsString(
            'domain_key',
            $modelSource
        );
    }

    public function test_horizontal_workspace_has_explicit_sort_order(): void
    {
        self::assertContains(
            'sort_order',
            (
                new DataTransformationBiSourceAsset()
            )->getFillable()
        );

        self::assertStringContainsString(
            "'sort_order'",
            $this->migration
        );
    }
}
