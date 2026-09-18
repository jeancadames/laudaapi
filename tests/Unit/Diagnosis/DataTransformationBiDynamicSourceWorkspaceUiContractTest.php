<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiDynamicSourceWorkspaceUiContractTest
    extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(__DIR__, 3);

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
    }

    public function test_state_supports_dynamic_source_assets(): void
    {
        self::assertStringContainsString(
            'type DynamicSourceAsset =',
            $this->source
        );

        self::assertStringContainsString(
            'source_assets: DynamicSourceAsset[];',
            $this->source
        );

        self::assertStringContainsString(
            'source_asset?: DynamicSourceAsset;',
            $this->source
        );
    }

    public function test_workspace_is_source_first_not_domain_first(): void
    {
        self::assertStringContainsString(
            'Fuentes de datos',
            $this->source
        );

        self::assertStringContainsString(
            '+ Agregar fuente',
            $this->source
        );

        self::assertStringContainsString(
            'Tabla o archivo de origen',
            $this->source
        );

        self::assertStringContainsString(
            'Modelo objetivo LAUDA · procesamiento interno',
            $this->source
        );
    }

    public function test_origin_system_is_presented_as_informational(): void
    {
        self::assertStringContainsString(
            'Origen de los datos',
            $this->source
        );

        self::assertStringContainsString(
            'Solo informativo. No representa una conexión.',
            $this->source
        );

        self::assertStringContainsString(
            'SQL Server, FoxPro, Clarion, Mónica',
            $this->source
        );
    }

    public function test_delivery_selection_is_horizontal_csv_xlsx(): void
    {
        self::assertStringContainsString(
            'grid grid-cols-2 gap-2',
            $this->source
        );

        self::assertStringContainsString(
            "dynamicSourceForm.delivery_format =\n"
            ."                                                        'csv'",
            $this->source
        );

        self::assertStringContainsString(
            "dynamicSourceForm.delivery_format =\n"
            ."                                                        'xlsx'",
            $this->source
        );

        self::assertStringContainsString(
            'Excel (.xlsx)',
            $this->source
        );
    }

    public function test_workspace_supports_create_edit_archive_and_order(): void
    {
        foreach (
            [
                'saveDynamicSourceAsset(',
                'openDynamicSourceEditForm(',
                'archiveDynamicSourceAsset(',
                'moveDynamicSourceAsset(',
                '/source-assets',
                '/archive',
                '/reorder',
            ]
            as $token
        ) {
            self::assertStringContainsString(
                $token,
                $this->source
            );
        }
    }

    public function test_workspace_displays_independent_structure_and_data_progress(): void
    {
        self::assertStringContainsString(
            'dynamicSourceStructureLabel(',
            $this->source
        );

        self::assertStringContainsString(
            'dynamicSourceDataLabel(',
            $this->source
        );

        self::assertStringContainsString(
            'Información',
            $this->source
        );

        self::assertStringContainsString(
            'Estructura',
            $this->source
        );

        self::assertStringContainsString(
            'Extracción',
            $this->source
        );

        self::assertStringContainsString(
            'Archivo',
            $this->source
        );

        self::assertStringContainsString(
            'Análisis',
            $this->source
        );

        self::assertStringContainsString(
            'Mapeo',
            $this->source
        );
    }

    public function test_workspace_does_not_collect_credentials(): void
    {
        $start =
            strpos(
                $this->source,
                '<!-- D17_DYNAMIC_SOURCE_WORKSPACE_UI -->'
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

        $workspace =
            substr(
                $this->source,
                $start,
                $end - $start
            );

        $lower =
            strtolower(
                $workspace
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
