<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiDynamicSourceStructureUiContractTest
    extends TestCase
{
    private string $source;
    private string $workspace;

    private string $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(__DIR__, 3);

        $this->tenant = file_get_contents($root.'/resources/js/pages/App/DataTransformationBi.vue');

        $this->source =
            file_get_contents(
                $root
                .'/resources/js/pages/Admin/'
                .'Transformation360/'
                .'ImplementationRequests/'
                .'Show.vue'
            );

        self::assertIsString(
            $this->source
        );

        $start =
            strpos(
                $this->source,
                '<!-- D17_DYNAMIC_SOURCE_DETAIL_WORKSPACE -->'
            );

        self::assertNotFalse(
            $start
        );

        $end =
            strpos(
                $this->source,
                '<!-- D15C_INTAKE_V2_UI -->',
                $start
            );

        self::assertNotFalse(
            $end
        );

        $this->workspace =
            substr(
                $this->source,
                $start,
                $end - $start
            );
    }

    public function test_dynamic_source_state_contains_structure_text(): void
    {
        self::assertStringContainsString(
            'structure_text?: string | null;',
            $this->source
        );
    }

    public function test_source_workspace_has_horizontal_process_tabs(): void
    {
        foreach (
            [
                'Información',
                'Estructura',
                'Archivo recibido',
                'Análisis',
                'Mapeo al modelo LAUDA',
            ]
            as $label
        ) {
            self::assertStringContainsString(
                $label,
                $this->workspace
            );
        }

        self::assertStringContainsString(
            'overflow-x-auto',
            $this->workspace
        );

        self::assertStringContainsString(
            'min-w-max',
            $this->workspace
        );
    }

    public function test_tenant_selects_the_format_of_the_source_structure(): void
    {
        foreach (['v-model="structureForm.structure_format"', 'value="field_type_list"', 'value="sql_server_ddl"', 'value="other"'] as $field) {
            self::assertStringContainsString($field, $this->tenant);
        }
    }

    public function test_tenant_saves_the_structure_for_the_selected_source(): void
    {
        foreach (['@click="saveSourceStructure"', 'Guardar estructura', '/fuentes/${sourceAsset.id}/estructura', "'PATCH'"] as $token) {
            self::assertStringContainsString($token, $this->tenant);
        }
    }

    public function test_sql_server_assistant_uses_dynamic_source_asset_endpoint(): void
    {
        self::assertStringContainsString(
            'generateDynamicSourceSqlServerPreview(',
            $this->source
        );

        self::assertStringContainsString(
            '/source-assets/${asset.id}/sql-server-extraction/preview',
            $this->source
        );

        self::assertStringContainsString(
            'Preparar extracción SQL Server',
            $this->workspace
        );

        self::assertStringContainsString(
            'SELECT solamente',
            $this->workspace
        );

        self::assertStringContainsString(
            'Sin conexión remota',
            $this->workspace
        );
    }

    public function test_sql_assistance_is_not_gated_by_origin_system(): void
    {
        $start =
            strpos(
                $this->source,
                'async function generateDynamicSourceSqlServerPreview('
            );

        self::assertNotFalse(
            $start
        );

        $end =
            strpos(
                $this->source,
                'async function copyDynamicSourceSqlServerQuery(',
                $start
            );

        self::assertNotFalse(
            $end
        );

        $block =
            substr(
                $this->source,
                $start,
                $end - $start
            );

        self::assertStringNotContainsString(
            'origin_system',
            $block
        );
    }

    public function test_csv_xlsx_export_choice_is_horizontal(): void
    {
        self::assertStringContainsString(
            'grid max-w-sm grid-cols-2 gap-2',
            $this->workspace
        );

        self::assertStringContainsString(
            "export_format =\n"
            ."                                                                    'csv'",
            $this->workspace
        );

        self::assertStringContainsString(
            "export_format =\n"
            ."                                                                    'xlsx'",
            $this->workspace
        );
    }

    public function test_tenant_uploads_csv_or_xlsx_to_the_selected_source(): void
    {
        foreach (['type="file"', 'accept=".csv,.xlsx"', '@click="uploadSourceData"', '/fuentes/${sourceAsset.id}/archivo'] as $token) {
            self::assertStringContainsString($token, $this->tenant);
        }
        self::assertStringContainsString('Archivo recibido', $this->workspace);
    }

    public function test_dynamic_workspace_collects_no_credentials(): void
    {
        $lower =
            strtolower(
                $this->workspace
            );

        self::assertStringNotContainsString(
            'type="password"',
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
}
