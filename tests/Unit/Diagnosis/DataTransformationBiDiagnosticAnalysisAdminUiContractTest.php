<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiDiagnosticAnalysisAdminUiContractTest
    extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->source =
            (string) file_get_contents(
                dirname(__DIR__, 3)
                .'/resources/js/pages/Admin/'
                .'Transformation360/ImplementationRequests/'
                .'Show.vue'
            );
    }


    public function test_admin_ui_preserves_analysis_contract_and_uses_report_for_presentation(): void
    {
        /*
         * The raw diagnostic-analysis projection remains available in
         * the workspace for lifecycle compatibility and D2E context.
         */
        self::assertStringContainsString(
            'diagnostic_analysis: DataBiDiagnosticAnalysisProjection | null',
            $this->source
        );

        /*
         * D2G presentation is composed through the diagnostic report.
         */
        self::assertStringContainsString(
            'diagnostic_report: DataBiDiagnosticReport | null',
            $this->source
        );

        self::assertStringContainsString(
            'function dataBiDiagnosticReport()',
            $this->source
        );

        self::assertStringContainsString(
            'dataBiDiagnosticAnalysisUiSupported',
            $this->source
        );

        self::assertStringContainsString(
            'dataBiDiagnosticAnalyses',
            $this->source
        );

        self::assertStringContainsString(
            '?.structural_analysis',
            $this->source
        );
    }

    public function test_admin_ui_exposes_expected_business_labels(): void
    {
        foreach ([
            'Análisis BI sustentados por',
            'la entrega evaluada',
            'Sustentado por la evidencia evaluada',
            'Sustento parcial en la evidencia evaluada',
            'Evidencia insuficiente en esta entrega',
            'Vista previa · aún no congelada',
            'Resultado congelado de la evaluación',
        ] as $expected) {
            self::assertStringContainsString(
                $expected,
                $this->source
            );
        }
    }

    public function test_admin_ui_contains_required_interpretation_boundary(): void
    {
        foreach ([
            'únicamente la información',
            'contenida en la entrega',
            'No indican que la',
            'empresa carezca de esa',
            'porcentaje de preparación',
            'global para BI',
        ] as $expected) {
            self::assertStringContainsString(
                $expected,
                $this->source
            );
        }
    }


    public function test_analysis_block_uses_report_structural_analysis_snapshot_authority_for_conclusions(): void
    {
        $start =
            strpos(
                $this->source,
                '<!-- DATA_BI_DIAGNOSTIC_ANALYSIS_ADMIN_UI -->'
            );

        $end =
            strpos(
                $this->source,
                '<!-- DATA_BI_DIAGNOSTIC_ANALYSIS_ADMIN_UI_END -->'
            );

        self::assertNotFalse(
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

        /*
         * D2G presentation consumes the structural-analysis projection
         * already composed by the report.
         */
        self::assertStringContainsString(
            '.diagnostic_report.structural_analysis',
            $block
        );

        self::assertStringContainsString(
            'dataBiDiagnosticAnalyses()',
            $block
        );

        /*
         * The visible conclusions must not bypass the report and must
         * never be rebuilt from live descriptive workspace layers.
         */
        foreach ([
            '.diagnostic_analysis',
            '.sources',
            '.semantic_diagnostic',
            '.domain_coverage',
            '.cross_source_relationship_candidates',
            'readiness_score',
            'risk_score',
            'opportunity_score',
            'canonical',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                strtolower(
                    $block
                )
            );
        }
    }

    public function test_unknown_frozen_schema_is_not_silently_reinterpreted(): void
    {
        self::assertStringContainsString(
            'DATA_BI_DIAGNOSTIC_ANALYSIS_UI_SCHEMA_VERSIONS = [2, 3]',
            $this->source
        );

        self::assertStringContainsString(
            'dataBiDiagnosticAnalysisUiSchemaSupported',
            $this->source
        );

        self::assertStringNotContainsString(
            'projection.analysis_schema_version === 2',
            $this->source
        );

        self::assertStringNotContainsString(
            'projection.analysis_schema_version !== 2',
            $this->source
        );

        self::assertStringContainsString(
            'Se conserva sin recalcular ni reconstruir.',
            $this->source
        );
    }
}
