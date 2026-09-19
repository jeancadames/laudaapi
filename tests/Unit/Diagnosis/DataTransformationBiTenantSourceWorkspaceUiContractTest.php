<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiTenantSourceWorkspaceUiContractTest
    extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(
                __DIR__,
                3
            );

        $this->source =
            file_get_contents(
                $root
                .'/resources/js/pages/App/'
                .'DataTransformationBi.vue'
            );

        self::assertIsString(
            $this->source
        );
    }

    public function test_tenant_receives_source_workspace_contract(): void
    {
        foreach (
            [
                'source_workspace: SourceWorkspace;',
                'source_assets: DynamicSourceAsset[];',
                'session:',
                'can_start_or_resume',
                'can_manage_sources',
                'readiness:',
                'inputs_validated',
                'source_count',
                'complete_source_count',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->source
            );
        }
    }

    public function test_workspace_has_only_tenant_tabs(): void
    {
        foreach (
            [
                'Información',
                'Estructura',
                'Extracción',
                'Archivo',
                'Resultado',
            ]
            as $label
        ) {
            self::assertStringContainsString(
                $label,
                $this->source
            );
        }

        self::assertStringNotContainsString(
            'Mapeo LAUDA',
            $this->source
        );
    }

    public function test_workspace_exposes_all_tenant_source_actions(): void
    {
        foreach (
            [
                'prepareSourceWorkspace',
                'createSourceAsset',
                'updateSourceAsset',
                'moveSourceAsset',
                'archiveSourceAsset',
                'saveSourceStructure',
                'previewSourceExtraction',
                'uploadSourceData',
            ]
            as $action
        ) {
            self::assertStringContainsString(
                $action,
                $this->source
            );
        }
    }

    public function test_workspace_uses_tenant_routes_not_admin_routes(): void
    {
        self::assertStringContainsString(
            "'/app/transformacion-360/datos-bi/fuentes'",
            $this->source
        );

        foreach (
            [
                '/preparar',
                '/sesiones/${sessionId}/fuentes',
                '/reordenar',
                '/archivar',
                '/estructura',
                '/archivo',
                '/extraccion-sql-server/previsualizar',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->source
            );
        }

        self::assertStringNotContainsString(
            '/admin/',
            $this->source
        );
    }

    public function test_reorder_sends_complete_source_id_list(): void
    {
        self::assertStringContainsString(
            'props.source_assets.map(',
            $this->source
        );

        self::assertStringContainsString(
            'source_asset_ids:',
            $this->source
        );
    }

    public function test_structure_is_optional_for_file_upload(): void
    {
        self::assertStringContainsString(
            'La estructura es opcional para subir',
            $this->source
        );

        self::assertStringContainsString(
            'accept=".csv,.xlsx"',
            $this->source
        );

        self::assertStringContainsString(
            'new FormData()',
            $this->source
        );
    }

    public function test_extraction_assistance_is_local_and_read_only(): void
    {
        self::assertStringContainsString(
            'LAUDA no se conecta a tu servidor.',
            $this->source
        );

        self::assertStringContainsString(
            'SELECT de solo lectura',
            $this->source
        );

        self::assertStringContainsString(
            'schema_name:',
            $this->source
        );

        self::assertStringContainsString(
            'table_name:',
            $this->source
        );
    }

    public function test_ui_collects_no_remote_credentials(): void
    {
        $lower =
            strtolower(
                $this->source
            );

        foreach (
            [
                'connection_string',
                'database_password',
                'db_password',
                'sqlsrv_connect',
                'odbc_connect',
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $lower
            );
        }
    }

    public function test_readiness_is_display_only(): void
    {
        self::assertStringContainsString(
            '.readiness',
            $this->source
        );

        self::assertStringContainsString(
            '.inputs_validated',
            $this->source
        );

        self::assertStringContainsString(
            '.complete_source_count',
            $this->source
        );

        foreach (
            [
                'v-model="source_workspace.readiness',
                "inputs_validated:",
                "accesses_validated:",
            ]
            as $editable
        ) {
            self::assertStringNotContainsString(
                $editable,
                $this->templateWorkspace()
            );
        }
    }

    public function test_json_endpoints_use_non_inertia_fetch_with_csrf(): void
    {
        foreach (
            [
                'fetch(',
                "credentials:\n                    'same-origin'",
                "'X-CSRF-TOKEN'",
                "'X-XSRF-TOKEN'",
                "'X-Requested-With'",
                "Accept: 'application/json'",
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->source
            );
        }
    }

    public function test_workspace_is_before_legacy_preparation_status(): void
    {
        $workspace =
            strpos(
                $this->source,
                '<!-- T1_TENANT_SOURCE_WORKSPACE -->'
            );

        $legacy =
            strpos(
                $this->source,
                '<!-- P7_DATA_PREPARATION_STATUS -->'
            );

        self::assertNotFalse(
            $workspace
        );

        self::assertNotFalse(
            $legacy
        );

        self::assertLessThan(
            $legacy,
            $workspace
        );
    }

    private function templateWorkspace(): string
    {
        $start =
            strpos(
                $this->source,
                '<!-- T1_TENANT_SOURCE_WORKSPACE -->'
            );

        self::assertNotFalse(
            $start
        );

        $end =
            strpos(
                $this->source,
                '<!-- P7_DATA_PREPARATION_STATUS -->',
                $start
            );

        self::assertNotFalse(
            $end
        );

        return substr(
            $this->source,
            $start,
            $end - $start
        );
    }
}
