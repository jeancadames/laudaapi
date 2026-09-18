<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiSourceAssetDataUploadHttpContractTest
    extends TestCase
{
    private string $controller;
    private string $routes;
    private string $service;

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

        $this->service =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiSourceAssetDataUploadService.php'
            );

        self::assertIsString(
            $this->controller
        );

        self::assertIsString(
            $this->routes
        );

        self::assertIsString(
            $this->service
        );
    }

    public function test_controller_exposes_dynamic_source_upload_action(): void
    {
        self::assertStringContainsString(
            'public function uploadSourceAssetData(',
            $this->controller
        );

        self::assertStringContainsString(
            'DataTransformationBiSourceAssetDataUploadService $service',
            $this->controller
        );

        self::assertStringContainsString(
            '$service->persist(',
            $this->controller
        );
    }

    public function test_upload_is_scoped_to_request_session_and_source_asset(): void
    {
        $block =
            $this->uploadAction();

        self::assertStringContainsString(
            '$this->assertRequest(',
            $block
        );

        self::assertStringContainsString(
            '$this->scopedSession(',
            $block
        );

        self::assertStringContainsString(
            '$this->scopedSourceAsset(',
            $block
        );
    }

    public function test_http_upload_uses_32mb_service_limit(): void
    {
        $block =
            $this->uploadAction();

        self::assertStringContainsString(
            '::MAX_UPLOAD_KILOBYTES',
            $block
        );

        self::assertStringContainsString(
            "'required'",
            $block
        );

        self::assertStringContainsString(
            "'file'",
            $block
        );
    }

    public function test_upload_response_returns_safe_asset_and_file_payloads(): void
    {
        $block =
            $this->uploadAction();

        self::assertStringContainsString(
            "'source_asset' =>",
            $block
        );

        self::assertStringContainsString(
            '$this->sourceAssetPayload(',
            $block
        );

        self::assertStringContainsString(
            "'data_file' =>",
            $block
        );

        self::assertStringNotContainsString(
            "'source_path'",
            $block
        );

        self::assertStringNotContainsString(
            "'source_disk'",
            $block
        );
    }

    public function test_upload_action_has_no_fixed_domain_dependency(): void
    {
        $block =
            $this->uploadAction();

        self::assertStringNotContainsString(
            'DataTransformationBiSourceDomainRegistry',
            $block
        );

        self::assertStringNotContainsString(
            'DataTransformationBiIntakeDomainDelivery',
            $block
        );

        self::assertStringNotContainsString(
            "'domain_key'",
            $block
        );
    }

    public function test_route_is_post_and_source_asset_scoped(): void
    {
        self::assertStringContainsString(
            "/source-assets/{sourceAssetId}/data-file",
            $this->routes
        );

        self::assertStringContainsString(
            "'uploadSourceAssetData'",
            $this->routes
        );

        self::assertStringContainsString(
            "source_assets.data_file.upload",
            $this->routes
        );

        self::assertStringContainsString(
            "->whereNumber('sessionId')",
            $this->routes
        );

        self::assertStringContainsString(
            "->whereNumber('sourceAssetId')",
            $this->routes
        );
    }

    public function test_service_still_owns_private_storage_contract(): void
    {
        self::assertStringContainsString(
            "SOURCE_DISK =\n        'private'",
            $this->service
        );

        self::assertStringContainsString(
            'DataTransformationBiSourceFileReader',
            $this->service
        );

        self::assertStringContainsString(
            '::DATA_RECEIVED',
            $this->service
        );

        self::assertStringNotContainsString(
            'DataTransformationBiSourceDomainRegistry',
            $this->service
        );
    }

    private function uploadAction(): string
    {
        $start =
            strpos(
                $this->controller,
                'public function uploadSourceAssetData('
            );

        self::assertNotFalse(
            $start
        );

        $end =
            strpos(
                $this->controller,
                'public function updateSourceAssetStructure(',
                $start
            );

        self::assertNotFalse(
            $end
        );

        return substr(
            $this->controller,
            $start,
            $end - $start
        );
    }
}
