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

    public function test_admin_ui_consumes_diagnostic_analysis_projection(): void
    {
        self::assertStringContainsString(
            'diagnostic_analysis: DataBiDiagnosticAnalysisProjection | null',
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
            '.diagnostic_analysis',
            $this->source
        );

        self::assertStringContainsString(
            '.snapshot',
            $this->source
        );

        self::assertStringContainsString(
            '.analyses',
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

    public function test_analysis_block_uses_only_snapshot_authority_for_conclusions(): void
    {
        $start = strpos(
            $this->source,
            '<!-- DATA_BI_DIAGNOSTIC_ANALYSIS_ADMIN_UI -->'
        );

        $end = strpos(
            $this->source,
            '<!-- DATA_BI_DIAGNOSTIC_ANALYSIS_ADMIN_UI_END -->'
        );

        self::assertNotFalse($start);
        self::assertNotFalse($end);
        self::assertGreaterThan($start, $end);

        $block = substr(
            $this->source,
            $start,
            $end - $start
        );

        self::assertStringContainsString(
            'diagnostic_analysis',
            $block
        );

        self::assertStringContainsString(
            'supporting_evidence',
            $block
        );

        self::assertStringNotContainsString(
            '.sources',
            $block
        );

        self::assertStringNotContainsString(
            '.semantic_diagnostic',
            $block
        );

        self::assertStringNotContainsString(
            '.domain_coverage',
            $block
        );

        self::assertStringNotContainsString(
            '.cross_source_relationship_candidates',
            $block
        );

        self::assertStringNotContainsString(
            'readiness_score',
            $block
        );

        self::assertStringNotContainsString(
            'canonical',
            strtolower($block)
        );
    }

    public function test_unknown_frozen_schema_is_not_silently_reinterpreted(): void
    {
        self::assertStringContainsString(
            'projection.analysis_schema_version === 2',
            $this->source
        );

        self::assertStringContainsString(
            'Se conserva sin recalcular ni reconstruir.',
            $this->source
        );
    }
}
