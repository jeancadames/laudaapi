<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiSourceAssetHttpContractTest
    extends TestCase
{
    private string $controller;

    private string $routes;

    private string $sourceRoutes;

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

        self::assertIsString(
            $this->controller
        );

        self::assertIsString(
            $this->routes
        );

        $start =
            strpos(
                $this->routes,
                "->prefix('/app/transformacion-360/datos-bi/fuentes')"
            );

        self::assertNotFalse(
            $start
        );

        $end =
            strpos(
                $this->routes,
                '| Tenant Definition review',
                $start
            );

        self::assertNotFalse(
            $end
        );

        $this->sourceRoutes =
            substr(
                $this->routes,
                $start,
                $end - $start
            );
    }

    public function test_tenant_controller_owns_dynamic_source_lifecycle(): void
    {
        foreach ([
            'public function createSourceAsset(',
            'public function updateSourceAsset(',
            'public function reorderSourceAssets(',
            'public function archiveSourceAsset(',
        ] as $method) {
            self::assertStringContainsString(
                $method,
                $this->controller
            );
        }
    }

    public function test_routes_are_tenant_and_session_scoped(): void
    {
        foreach ([
            '/sesiones/{sessionId}/fuentes',
            '/sesiones/{sessionId}/fuentes/reordenar',
            '/sesiones/{sessionId}/fuentes/{sourceAssetId}',
            '/sesiones/{sessionId}/fuentes/{sourceAssetId}/archivar',
            "->whereNumber('sessionId')",
            "->whereNumber('sourceAssetId')",
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $this->sourceRoutes
            );
        }

        self::assertStringContainsString(
            "->prefix('/app/transformacion-360/datos-bi/fuentes')",
            $this->sourceRoutes
        );
    }

    public function test_controller_reuses_dynamic_source_service(): void
    {
        self::assertStringContainsString(
            'DataTransformationBiSourceAssetService',
            $this->controller
        );

        foreach ([
            '$service->create(',
            '$service->update(',
            '$service->reorder(',
            '$service->archive(',
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $this->controller
            );
        }
    }

    public function test_tenant_source_lifecycle_is_domain_agnostic(): void
    {
        foreach ([
            'DataTransformationBiSourceDomainRegistry',
            'DataTransformationBiIntakeDomainDelivery',
            "'domain_key'",
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $this->controller
            );
        }
    }

    public function test_source_metadata_is_native_and_dynamic(): void
    {
        foreach ([
            "'origin_system'",
            "'source_object_name'",
            "'description'",
            "'delivery_format'",
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $this->controller
            );
        }
    }

    public function test_source_scope_remains_company_and_session_bound(): void
    {
        foreach ([
            "'company_id'",
            "'data_transformation_bi_intake_session_id'",
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $this->controller
            );
        }
    }

    public function test_archive_is_patch_not_delete(): void
    {
        self::assertStringContainsString(
            "Route::patch(",
            $this->sourceRoutes
        );

        self::assertStringContainsString(
            '/archivar',
            $this->sourceRoutes
        );

        self::assertStringNotContainsString(
            'Route::delete(',
            $this->sourceRoutes
        );
    }
}
