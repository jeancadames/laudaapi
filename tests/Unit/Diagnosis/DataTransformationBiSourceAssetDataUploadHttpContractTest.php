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
                .'/app/Http/Controllers/'
                .'AppHubDataTransformationBiSourceWorkspaceController.php'
            );

        $this->routes =
            file_get_contents(
                $root.'/routes/web.php'
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

    public function test_tenant_controller_exposes_dynamic_source_upload(): void
    {
        $action =
            $this->uploadAction();

        self::assertStringContainsString(
            'DataTransformationBiSourceAssetDataUploadService $service',
            $action
        );

        self::assertStringContainsString(
            '$service->persist(',
            $action
        );
    }

    public function test_upload_uses_controlled_file_limit(): void
    {
        $action =
            $this->uploadAction();

        self::assertStringContainsString(
            '::MAX_UPLOAD_KILOBYTES',
            $action
        );

        self::assertStringContainsString(
            "'required'",
            $action
        );

        self::assertStringContainsString(
            "'file'",
            $action
        );
    }

    public function test_upload_action_is_domain_agnostic(): void
    {
        $action =
            $this->uploadAction();

        foreach ([
            'DataTransformationBiSourceDomainRegistry',
            'DataTransformationBiIntakeDomainDelivery',
            "'domain_key'",
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $action
            );
        }
    }

    public function test_upload_route_belongs_to_tenant_source_workspace(): void
    {
        foreach ([
            "/sesiones/{sessionId}/fuentes/{sourceAssetId}/archivo",
            "'uploadSourceAssetData'",
            "->name('data_file.upload')",
            "->whereNumber('sessionId')",
            "->whereNumber('sourceAssetId')",
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $this->routes
            );
        }
    }

    public function test_upload_action_does_not_expose_private_storage(): void
    {
        $action =
            strtolower(
                $this->uploadAction()
            );

        self::assertStringNotContainsString(
            'source_path',
            $action
        );

        self::assertStringNotContainsString(
            'source_disk',
            $action
        );

        self::assertStringNotContainsString(
            'connection_string',
            $action
        );

        self::assertStringNotContainsString(
            'password',
            $action
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
        return $this->methodBlock(
            'public function uploadSourceAssetData(',
            'public function previewSourceAssetSqlServerExtraction('
        );
    }

    private function methodBlock(
        string $startNeedle,
        string $endNeedle
    ): string {
        $start =
            strpos(
                $this->controller,
                $startNeedle
            );

        self::assertNotFalse(
            $start
        );

        $end =
            strpos(
                $this->controller,
                $endNeedle,
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
