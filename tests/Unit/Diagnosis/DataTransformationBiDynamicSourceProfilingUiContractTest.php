<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiDynamicSourceProfilingUiContractTest
    extends TestCase
{
    private string $admin;
    private string $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(
                __DIR__,
                3
            );

        $this->admin =
            file_get_contents(
                $root
                .'/resources/js/pages/Admin/'
                .'Transformation360/'
                .'ImplementationRequests/'
                .'Show.vue'
            );

        $this->tenant =
            file_get_contents(
                $root
                .'/resources/js/pages/App/'
                .'DataTransformationBi.vue'
            );

        self::assertIsString(
            $this->admin
        );

        self::assertIsString(
            $this->tenant
        );
    }

    public function test_admin_can_execute_source_asset_profiling(): void
    {
        foreach (
            [
                'async function profileDynamicSourceAsset(',
                '/source-assets/${asset.id}/profile',
                'Perfilar fuente',
                'Perfilando...',
                'Volver a perfilar',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->admin
            );
        }
    }

    public function test_admin_renders_safe_aggregate_profile_result(): void
    {
        foreach (
            [
                'Profiling técnico LAUDA',
                "'source_row_count'",
                "'profiled_row_count'",
                "'sheet_count'",
                'dynamicSourceProfileFullScanLabel(',
                'profiled_at',
                'No guarda muestras ni valores',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->admin
            );
        }
    }

    public function test_admin_profile_ui_does_not_assign_canonical_domain(): void
    {
        $start =
            strpos(
                $this->admin,
                'async function profileDynamicSourceAsset('
            );

        self::assertNotFalse(
            $start
        );

        $end =
            strpos(
                $this->admin,
                'async function saveDynamicSourceAsset(',
                $start
            );

        self::assertNotFalse(
            $end
        );

        $action =
            substr(
                $this->admin,
                $start,
                $end - $start
            );

        self::assertStringNotContainsString(
            'domain_key',
            $action
        );

        self::assertStringNotContainsString(
            'canonical',
            strtolower(
                $action
            )
        );
    }

    public function test_tenant_does_not_receive_admin_profiling_action(): void
    {
        self::assertStringNotContainsString(
            'profileDynamicSourceAsset(',
            $this->tenant
        );

        self::assertStringNotContainsString(
            'Perfilar fuente',
            $this->tenant
        );

        self::assertStringNotContainsString(
            'Profiling técnico LAUDA',
            $this->tenant
        );
    }
}
