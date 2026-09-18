<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DataTransformationBiSourceAsset;
use App\Services\Diagnosis\DataTransformationBiSourceAssetStructureService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class DataTransformationBiSourceAssetStructureContractTest
    extends TestCase
{
    private string $migration;
    private string $model;
    private string $service;
    private string $controller;
    private string $routes;
    private string $state;

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

        $this->model =
            file_get_contents(
                $root
                .'/app/Models/'
                .'DataTransformationBiSourceAsset.php'
            );

        $this->service =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiSourceAssetStructureService.php'
            );

        $this->controller =
            file_get_contents(
                $root
                .'/app/Http/Controllers/Admin/'
                .'AdminDataTransformationBiIntakeV2Controller.php'
            );

        $this->routes =
            file_get_contents(
                $root
                .'/routes/admin.php'
            );

        $this->state =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiIntakeV2StateService.php'
            );
    }

    public function test_structure_text_is_persisted_on_dynamic_source_asset(): void
    {
        self::assertStringContainsString(
            "'structure_text'",
            $this->migration
        );

        self::assertContains(
            'structure_text',
            (
                new DataTransformationBiSourceAsset()
            )->getFillable()
        );
    }

    public function test_structure_formats_are_explicit_but_not_origin_gated(): void
    {
        self::assertSame(
            'field_type_list',
            DataTransformationBiSourceAsset
                ::STRUCTURE_FORMAT_FIELD_TYPE_LIST
        );

        self::assertSame(
            'sql_server_ddl',
            DataTransformationBiSourceAsset
                ::STRUCTURE_FORMAT_SQL_SERVER_DDL
        );

        self::assertSame(
            'other',
            DataTransformationBiSourceAsset
                ::STRUCTURE_FORMAT_OTHER
        );

        self::assertStringNotContainsString(
            'origin_system',
            $this->service
        );
    }

    public function test_structure_service_has_controlled_save_contract(): void
    {
        $reflection =
            new ReflectionClass(
                DataTransformationBiSourceAssetStructureService::class
            );

        self::assertTrue(
            $reflection->hasMethod(
                'save'
            )
        );

        self::assertStringContainsString(
            'MAX_STRUCTURE_LENGTH = 50000',
            $this->service
        );

        self::assertStringContainsString(
            '::STRUCTURE_PROVIDED',
            $this->service
        );

        self::assertStringContainsString(
            "'structure_snapshot'",
            $this->service
        );
    }

    public function test_structure_is_treated_as_inert_text(): void
    {
        $lower =
            strtolower(
                $this->service
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

        self::assertStringNotContainsString(
            'statement(',
            $lower
        );

        self::assertStringNotContainsString(
            'unprepared(',
            $lower
        );
    }

    public function test_http_contract_is_source_asset_scoped(): void
    {
        self::assertStringContainsString(
            'public function updateSourceAssetStructure(',
            $this->controller
        );

        self::assertStringContainsString(
            'public function previewSourceAssetSqlServerExtraction(',
            $this->controller
        );

        self::assertStringContainsString(
            '/source-assets/{sourceAssetId}/structure',
            $this->routes
        );

        self::assertStringContainsString(
            '/source-assets/{sourceAssetId}/sql-server-extraction/preview',
            $this->routes
        );
    }

    public function test_new_sql_preview_has_no_fixed_domain_registry_dependency(): void
    {
        $start =
            strpos(
                $this->controller,
                'public function previewSourceAssetSqlServerExtraction('
            );

        $end =
            strpos(
                $this->controller,
                'public function previewSqlServerExtraction(',
                $start
            );

        self::assertNotFalse(
            $start
        );

        self::assertNotFalse(
            $end
        );

        $block =
            substr(
                $this->controller,
                $start,
                $end - $start
            );

        self::assertStringNotContainsString(
            'DataTransformationBiSourceDomainRegistry',
            $block
        );

        self::assertStringNotContainsString(
            "'domain_key'",
            $block
        );

        self::assertStringContainsString(
            '$asset->structure_text',
            $block
        );

        self::assertStringContainsString(
            '$asset->source_object_name',
            $block
        );
    }

    public function test_structure_text_is_available_to_ui_state(): void
    {
        self::assertStringContainsString(
            "'structure_text'",
            $this->state
        );

        self::assertStringContainsString(
            "'structure_text'",
            $this->controller
        );
    }
}
