<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiDiagnosisWorkspaceAlignmentContractTest
    extends TestCase
{
    private string $admin;
    private string $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(__DIR__, 3);

        $this->admin =
            file_get_contents(
                $root
                .'/resources/js/pages/Admin/Transformation360/'
                .'ImplementationRequests/Show.vue'
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

    public function test_submitted_state_is_presented_as_evaluation(): void
    {
        self::assertStringContainsString(
            "submitted_for_evaluation: 'En evaluación'",
            $this->admin
        );
    }

    public function test_canonical_workspace_is_hidden_but_preserved(): void
    {
        $block =
            $this->between(
                $this->admin,
                '<!-- CANONICAL_MODEL_V2_ADMIN_UI -->',
                '<!-- DATA_BI_DIAGNOSIS_EVALUATION_WORKSPACE -->'
            );

        self::assertStringContainsString(
            'v-if="false"',
            $block
        );

        self::assertStringContainsString(
            'FUTURE_IMPLEMENTATION_CANONICAL_MODEL_'
            .'HIDDEN_DURING_DIAGNOSIS',
            $block
        );

        foreach ([
            'Modelo canónico LAUDA',
            'Nueva entidad canónica',
            'Relaciones canónicas',
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $block
            );
        }
    }

    public function test_admin_has_diagnostic_workspace(): void
    {
        foreach ([
            'Evaluación diagnóstica',
            'Revisión técnica de la entrega',
            'Esta etapa no ejecuta transformación,',
            'normalización, mapeo canónico ni',
            'materialización de datos.',
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->admin
            );
        }
    }

    public function test_admin_review_tabs_remain_diagnostic_only(): void
    {
        $workspace =
            $this->between(
                $this->admin,
                '<!-- D17_DYNAMIC_SOURCE_WORKSPACE_UI -->',
                '<!-- D15C_INTAKE_V2_UI -->'
            );

        foreach ([
            'Entrega de datos del tenant',
            "label: 'Información'",
            "label: 'Estructura'",
            "label: 'Archivo recibido'",
            "label: 'Análisis'",
            'Profiling técnico LAUDA',
            'Análisis de la fuente',
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $workspace
            );
        }

        $tabsStart =
            strpos(
                $workspace,
                'v-for="tab in ['
            );

        self::assertNotFalse(
            $tabsStart
        );

        $tabsEnd =
            strpos(
                $workspace,
                ']"',
                $tabsStart
            );

        self::assertNotFalse(
            $tabsEnd
        );

        $tabs =
            substr(
                $workspace,
                $tabsStart,
                $tabsEnd - $tabsStart
            );

        self::assertStringNotContainsString(
            "key: 'extraction'",
            $tabs
        );

        self::assertStringNotContainsString(
            "key: 'mapping'",
            $tabs
        );
    }

    public function test_visible_admin_flow_ends_in_diagnostic_analysis(): void
    {
        $workspace =
            $this->between(
                $this->admin,
                '<!-- D17_DYNAMIC_SOURCE_WORKSPACE_UI -->',
                '<!-- D15C_INTAKE_V2_UI -->'
            );

        foreach ([
            'Flujo de evaluación:',
            'Información',
            'Estructura observada',
            'Archivo recibido',
            'Profiling',
            'Análisis diagnóstico',
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $workspace
            );
        }

        self::assertStringNotContainsString(
            'Mapeo al modelo LAUDA.',
            $workspace
        );
    }

    public function test_tenant_copy_represents_diagnostic_delivery(): void
    {
        foreach ([
            'Entrega de fuentes',
            'Estado de la entrega',
            'para que LAUDA evalúe su estructura,',
            'evaluación diagnóstica',
            'una etapa independiente',
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->tenant
            );
        }

        foreach ([
            'CSV o XLSX que LAUDA transformará para BI.',
            'Readiness derivado',
            'LAUDA conserva el profiling técnico, normalización,',
        ] as $token) {
            self::assertStringNotContainsString(
                $token,
                $this->tenant
            );
        }
    }

    public function test_tenant_future_implementation_panels_are_hidden(): void
    {
        foreach ([
            '<!-- P7_DATA_PREPARATION_STATUS -->',
            '<!-- P13_USABLE_DATASET_STATUS -->',
            '<!-- P12_PROCESSING_HISTORY -->',
        ] as $marker) {
            $position =
                strpos(
                    $this->tenant,
                    $marker
                );

            self::assertNotFalse(
                $position
            );

            $fragment =
                substr(
                    $this->tenant,
                    $position,
                    1200
                );

            self::assertStringContainsString(
                'v-if="false && (',
                $fragment
            );
        }
    }

    private function between(
        string $source,
        string $start,
        string $end
    ): string {
        $startPosition =
            strpos(
                $source,
                $start
            );

        self::assertNotFalse(
            $startPosition
        );

        $endPosition =
            strpos(
                $source,
                $end,
                $startPosition + strlen($start)
            );

        self::assertNotFalse(
            $endPosition
        );

        return substr(
            $source,
            $startPosition,
            $endPosition - $startPosition
        );
    }
}
