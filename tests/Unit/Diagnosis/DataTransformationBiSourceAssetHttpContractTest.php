<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiSourceAssetHttpContractTest
    extends TestCase
{
    private string $controller;
    private string $routes;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(__DIR__, 3);

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

        self::assertIsString(
            $this->controller
        );

        self::assertIsString(
            $this->routes
        );
    }

    public function test_http_actions_cover_dynamic_source_lifecycle(): void
    {
        foreach (
            [
                'public function createSourceAsset(',
                'public function updateSourceAsset(',
                'public function reorderSourceAssets(',
                'public function archiveSourceAsset(',
            ]
            as $method
        ) {
            self::assertStringContainsString(
                $method,
                $this->controller
            );
        }
    }

    public function test_routes_are_scoped_to_intake_session(): void
    {
        self::assertStringContainsString(
            'standard-intake-v2/sessions/{sessionId}/source-assets',
            $this->routes
        );

        self::assertStringContainsString(
            "->whereNumber('sessionId')",
            $this->routes
        );

        self::assertStringContainsString(
            '{sourceAssetId}',
            $this->routes
        );

        self::assertStringContainsString(
            "->whereNumber('sourceAssetId')",
            $this->routes
        );
    }

    public function test_controller_uses_dynamic_source_service(): void
    {
        self::assertStringContainsString(
            'DataTransformationBiSourceAssetService',
            $this->controller
        );

        self::assertStringContainsString(
            '$service->create(',
            $this->controller
        );

        self::assertStringContainsString(
            '$service->update(',
            $this->controller
        );

        self::assertStringContainsString(
            '$service->reorder(',
            $this->controller
        );

        self::assertStringContainsString(
            '$service->archive(',
            $this->controller
        );
    }

    public function test_http_contract_has_no_canonical_domain_requirement(): void
    {
        $start =
            strpos(
                $this->controller,
                'public function createSourceAsset('
            );

        $end =
            strpos(
                $this->controller,
                'public function previewSqlServerExtraction('
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

        self::assertStringNotContainsString(
            'DataTransformationBiIntakeDomainDelivery',
            $block
        );
    }

    public function test_origin_is_free_text_and_delivery_is_csv_or_xlsx(): void
    {
        self::assertStringContainsString(
            "'origin_system'",
            $this->controller
        );

        self::assertStringContainsString(
            "'in:csv,xlsx'",
            $this->controller
        );

        self::assertStringContainsString(
            "'source_object_name'",
            $this->controller
        );

        self::assertStringContainsString(
            "'description'",
            $this->controller
        );
    }

    public function test_source_asset_is_scoped_to_company_and_session(): void
    {
        self::assertStringContainsString(
            'private function scopedSourceAsset(',
            $this->controller
        );

        self::assertStringContainsString(
            "'company_id'",
            $this->controller
        );

        self::assertStringContainsString(
            "'data_transformation_bi_intake_session_id'",
            $this->controller
        );
    }

    public function test_http_payload_exposes_no_private_storage_or_credentials(): void
    {
        $start =
            strpos(
                $this->controller,
                'private function sourceAssetPayload('
            );

        self::assertNotFalse(
            $start
        );

        $block =
            substr(
                $this->controller,
                $start
            );

        $lower =
            strtolower(
                $block
            );

        self::assertStringNotContainsString(
            'source_path',
            $lower
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
            'access_token',
            $lower
        );
    }

    public function test_archive_route_is_not_a_delete_endpoint(): void
    {
        self::assertStringContainsString(
            '/archive',
            $this->routes
        );

        self::assertStringNotContainsString(
            "Route::delete(\n"
            ."              '/transformation-360/"
            ."implementation-requests/{implementationRequest}/"
            ."standard-intake-v2/sessions/{sessionId}/"
            ."source-assets",
            $this->routes
        );
    }
}
