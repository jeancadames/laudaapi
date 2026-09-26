<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiAdminTenantDeliveryUiContractTest
    extends TestCase
{
    private string $source;

    private string $cards;

    private string $detail;

    protected function setUp(): void
    {
        parent::setUp();

        $path =
            dirname(__DIR__, 3)
            .'/resources/js/pages/Admin/'
            .'Transformation360/'
            .'ImplementationRequests/'
            .'Show.vue';

        $this->source =
            file_get_contents($path);

        self::assertIsString(
            $this->source
        );

        $workspace =
            strpos(
                $this->source,
                '<!-- D17_DYNAMIC_SOURCE_WORKSPACE_UI -->'
            );

        $detail =
            strpos(
                $this->source,
                '<!-- D17_DYNAMIC_SOURCE_DETAIL_WORKSPACE -->',
                $workspace
            );

        self::assertNotFalse($workspace);
        self::assertNotFalse($detail);

        $this->cards =
            substr(
                $this->source,
                $workspace,
                $detail - $workspace
            );

        $this->detail =
            substr(
                $this->source,
                $detail
            );
    }

    public function test_admin_reviews_tenant_delivery(): void
    {
        self::assertStringContainsString(
            'Entrega de datos del tenant',
            $this->cards
        );

        self::assertStringContainsString(
            'pero no modificar la entrega',
            $this->cards
        );

        self::assertStringContainsString(
            'Revisar',
            $this->cards
        );
    }

    public function test_admin_no_longer_exposes_source_mutation_buttons(): void
    {
        foreach ([
            '@click="startStandardIntakeV2Session"',
            '@click="openDynamicSourceCreateForm"',
            'openDynamicSourceEditForm(',
            'archiveDynamicSourceAsset(',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $this->cards
            );
        }

        self::assertStringContainsString(
            'v-if="false" class="flex gap-1"',
            $this->cards
        );
    }

    public function test_visible_tabs_are_admin_review_tabs(): void
    {
        $tabs =
            $this->between(
                $this->detail,
                'v-for="tab in [',
                ':key="tab.key"'
            );

        foreach ([
            "label: 'Información'",
            "label: 'Estructura'",
            "label: 'Archivo recibido'",
            "label: 'Análisis'",
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $tabs
            );
        }

        foreach ([
            "label: 'Extracción'",
            "label: 'Mapeo LAUDA'",
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $tabs
            );
        }
    }

    public function test_structure_is_read_only(): void
    {
        $block =
            $this->between(
                $this->detail,
                '<!-- STRUCTURE -->',
                '<!-- EXTRACTION -->'
            );

        self::assertStringContainsString(
            'Estructura recibida',
            $block
        );

        self::assertStringContainsString(
            'solo lectura para LAUDA',
            $block
        );

        self::assertStringNotContainsString(
            '<textarea',
            $block
        );

        self::assertStringNotContainsString(
            'Guardar estructura',
            $block
        );
    }

    public function test_file_is_read_only(): void
    {
        $block =
            $this->between(
                $this->detail,
                '<!-- FILE -->',
                '<!-- ANALYSIS -->'
            );

        self::assertStringContainsString(
            'Archivo recibido',
            $block
        );

        $normalized =
            preg_replace(
                '/\\s+/',
                ' ',
                $block
            );

        self::assertIsString(
            $normalized
        );

        self::assertStringContainsString(
            'no reemplazarla desde este workspace',
            $normalized
        );

        foreach ([
            'type="file"',
            'uploadDynamicSourceData(',
            'Subir archivo',
            'Reemplazar archivo',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $block
            );
        }
    }

    public function test_admin_keeps_profiling_and_analysis(): void
    {
        self::assertStringContainsString(
            'async function profileDynamicSourceAsset(',
            $this->source
        );

        self::assertStringContainsString(
            'Perfilar fuente',
            $this->detail
        );

        self::assertStringContainsString(
            '<!-- ANALYSIS -->',
            $this->detail
        );

        self::assertStringContainsString(
            'Análisis de la fuente',
            $this->detail
        );
    }

    public function test_profiling_does_not_require_source_management_permission(): void
    {
        $block =
            $this->between(
                $this->source,
                'async function profileDynamicSourceAsset(',
                'async function saveDynamicSourceAsset('
            );

        self::assertStringNotContainsString(
            'dynamicSourceCanManage()',
            $block
        );

        self::assertStringContainsString(
            'No existe una entrega del tenant disponible para profiling.',
            $block
        );
    }

    private function between(
        string $source,
        string $start,
        string $end
    ): string {
        $startAt =
            strpos(
                $source,
                $start
            );

        self::assertNotFalse(
            $startAt
        );

        $endAt =
            strpos(
                $source,
                $end,
                $startAt + strlen($start)
            );

        self::assertNotFalse(
            $endAt
        );

        return substr(
            $source,
            $startAt,
            $endAt - $startAt
        );
    }
}
