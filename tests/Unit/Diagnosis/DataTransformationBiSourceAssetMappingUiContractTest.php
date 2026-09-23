<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiSourceAssetMappingUiContractTest
    extends TestCase
{
    private string $view;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(
                __DIR__,
                3
            );

        $this->view =
            file_get_contents(
                $root
                .'/resources/js/pages/Admin/'
                .'Transformation360/'
                .'ImplementationRequests/'
                .'Show.vue'
            );

        self::assertIsString(
            $this->view
        );
    }

    public function test_mapping_tab_loads_read_only_workspace(): void
    {
        $block =
            $this->methodBlock(
                'async function loadDynamicSourceMappingWorkspace(',
                'async function startDynamicSourceMapping('
            );

        foreach (
            [
                '/mapping-workspace',
                "method:\n                        'GET'",
                'payload.workspace',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $block
            );
        }

        self::assertStringNotContainsString(
            '/mappings`,',
            $block
        );

        self::assertStringNotContainsString(
            "method:\n                        'POST'",
            $block
        );

        self::assertStringNotContainsString(
            "method:\n                        'PUT'",
            $block
        );
    }

    public function test_mapping_creation_is_explicit_post(): void
    {
        $block =
            $this->methodBlock(
                'async function startDynamicSourceMapping(',
                'async function saveDynamicSourceMappingFields('
            );

        self::assertStringContainsString(
            '/mappings`',
            $block
        );

        self::assertStringContainsString(
            "method:\n                        'POST'",
            $block
        );

        self::assertStringContainsString(
            'canonical_entity_key',
            $block
        );

        self::assertStringContainsString(
            'source_sheet_index',
            $block
        );
    }

    public function test_field_decisions_are_saved_explicitly_by_put(): void
    {
        $block =
            $this->methodBlock(
                'async function saveDynamicSourceMappingFields(',
                '/*'."\n"
                .' * TRANSFORM_CATALOG_PENDING'
            );

        self::assertStringContainsString(
            '/fields`',
            $block
        );

        self::assertStringContainsString(
            "method:\n                        'PUT'",
            $block
        );

        foreach (
            [
                'canonical_field_key',
                'mapping_type',
                'source_column_key',
                'default_value',
                'transformation_key',
                'configuration_snapshot',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $block
            );
        }
    }

    public function test_ui_exposes_entity_sheet_and_field_mapping_workflow(): void
    {
        foreach (
            [
                'Mapeo al modelo LAUDA',
                'Entidad objetivo LAUDA',
                'Hoja de origen',
                'Decisiones por campo',
                'Columna de origen',
                'Valor fijo',
                'No mapear',
                'Guardar decisiones',
                'Iniciar mapeo',
                'Abrir nueva versión',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->view
            );
        }
    }

    public function test_mapping_tab_uses_explicit_loader_instead_of_implicit_write(): void
    {
        self::assertStringContainsString(
            'selectDynamicSourceWorkspaceTab(',
            $this->view
        );

        self::assertStringContainsString(
            "if (tab === 'mapping')",
            $this->view
        );

        self::assertStringContainsString(
            'void loadDynamicSourceMappingWorkspace(',
            $this->view
        );
    }

    public function test_historical_mapping_selection_is_pinned_to_exact_mapping_id(): void
    {
        self::assertStringContainsString(
            'dynamicSourceMappingSelectedMappingId',
            $this->view
        );

        $current =
            $this->methodBlock(
                'function dynamicSourceMappingCurrent()',
                'function dynamicSourceMappingDecision('
            );

        self::assertStringContainsString(
            'mapping.id',
            $current
        );

        self::assertStringContainsString(
            'dynamicSourceMappingSelectedMappingId.value',
            $current
        );

        $selection =
            $this->methodBlock(
                'function selectDynamicSourceExistingMapping(',
                'function dynamicSourceMappingEditable('
            );

        self::assertStringContainsString(
            'dynamicSourceMappingSelectedMappingId.value =',
            $selection
        );

        self::assertStringContainsString(
            'mapping.id',
            $selection
        );

        $targetChange =
            $this->methodBlock(
                'function dynamicSourceMappingSelectionChanged()',
                'function selectDynamicSourceExistingMapping('
            );

        self::assertStringContainsString(
            'dynamicSourceMappingSelectedMappingId.value =',
            $targetChange
        );

        self::assertStringContainsString(
            'null',
            $targetChange
        );
    }

    public function test_free_form_transform_creation_remains_disabled_until_catalog_exists(): void
    {
        self::assertStringContainsString(
            'TRANSFORM_CATALOG_PENDING',
            $this->view
        );

        self::assertStringContainsString(
            'Transformación LAUDA existente',
            $this->view
        );

        self::assertStringNotContainsString(
            'Crear transformación libre',
            $this->view
        );

        self::assertStringNotContainsString(
            'Nueva transformación',
            $this->view
        );
    }

    public function test_tenant_mapping_ui_is_not_added_here(): void
    {
        self::assertStringNotContainsString(
            'resources/js/pages/App/DataTransformationBi.vue',
            $this->view
        );
    }

    private function methodBlock(
        string $startNeedle,
        string $endNeedle
    ): string {
        $start =
            strpos(
                $this->view,
                $startNeedle
            );

        self::assertNotFalse(
            $start
        );

        $end =
            strpos(
                $this->view,
                $endNeedle,
                $start
            );

        self::assertNotFalse(
            $end
        );

        return substr(
            $this->view,
            $start,
            $end - $start
        );
    }
}
