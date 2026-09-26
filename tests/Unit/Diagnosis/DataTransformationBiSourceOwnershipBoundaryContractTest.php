<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiSourceOwnershipBoundaryContractTest
    extends TestCase
{
    private string $adminRoutes;

    private string $tenantRoutes;

    private string $adminController;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(__DIR__, 3);

        $this->adminRoutes =
            file_get_contents(
                $root.'/routes/admin.php'
            );

        $this->tenantRoutes =
            file_get_contents(
                $root.'/routes/web.php'
            );

        $this->adminController =
            file_get_contents(
                $root
                .'/app/Http/Controllers/Admin/'
                .'AdminDataTransformationBiIntakeV2Controller.php'
            );

        self::assertIsString(
            $this->adminRoutes
        );

        self::assertIsString(
            $this->tenantRoutes
        );

        self::assertIsString(
            $this->adminController
        );
    }

    public function test_tenant_owns_source_delivery_mutations(): void
    {
        foreach ([
            "'prepareWorkspace'",
            "'createSourceAsset'",
            "'updateSourceAsset'",
            "'reorderSourceAssets'",
            "'archiveSourceAsset'",
            "'updateSourceAssetStructure'",
            "'uploadSourceAssetData'",
            "'previewSourceAssetSqlServerExtraction'",
        ] as $action) {
            self::assertStringContainsString(
                $action,
                $this->tenantRoutes
            );
        }
    }

    public function test_admin_cannot_mutate_tenant_source_delivery(): void
    {
        foreach ([
            "'startSession'",
            "'createSourceAsset'",
            "'updateSourceAsset'",
            "'reorderSourceAssets'",
            "'archiveSourceAsset'",
            "'updateSourceAssetStructure'",
            "'uploadSourceAssetData'",
            "'previewSourceAssetSqlServerExtraction'",
        ] as $action) {
            self::assertStringNotContainsString(
                $action,
                $this->adminRoutes
            );
        }
    }

    public function test_admin_keeps_evaluation_profiling_routes(): void
    {
        foreach ([
            "'profileSourceAsset'",
            "'profileSourceAssetStatus'",
            'source_assets.profile',
            'source_assets.profile.status',
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $this->adminRoutes
            );
        }
    }

    public function test_future_implementation_routes_are_preserved(): void
    {
        foreach ([
            '/canonical-model',
            "'sourceAssetMappingWorkspace'",
            "'startSourceAssetMapping'",
            "'replaceSourceAssetMappingFields'",
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $this->adminRoutes
            );
        }
    }

    public function test_admin_controller_does_not_own_tenant_source_delivery_mutations(): void
    {
        foreach ([
            'public function createSourceAsset(',
            'public function updateSourceAsset(',
            'public function reorderSourceAssets(',
            'public function archiveSourceAsset(',
            'public function updateSourceAssetStructure(',
            'public function uploadSourceAssetData(',
            'public function previewSourceAssetSqlServerExtraction(',
        ] as $method) {
            self::assertStringNotContainsString(
                $method,
                $this->adminController
            );
        }

        foreach ([
            'public function profileSourceAsset(',
            'public function profileSourceAssetStatus(',
        ] as $method) {
            self::assertStringContainsString(
                $method,
                $this->adminController
            );
        }
    }

}
