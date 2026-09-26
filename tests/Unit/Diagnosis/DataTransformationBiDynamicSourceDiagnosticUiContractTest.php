<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiDynamicSourceDiagnosticUiContractTest
    extends TestCase
{
    private string $source;

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
            file_get_contents(
                $path
            );

        self::assertIsString(
            $this->source
        );
    }

    public function test_dynamic_source_exposes_diagnostic_summary_type(): void
    {
        self::assertStringContainsString(
            'type DynamicSourceDiagnosticSummary = {',
            $this->source
        );

        self::assertStringContainsString(
            'diagnostic_summary?: DynamicSourceDiagnosticSummary | null;',
            $this->source
        );
    }

    public function test_analysis_tab_renders_generic_diagnostic_metrics(): void
    {
        $analysis =
            $this->analysisBlock();

        foreach ([
            'Análisis de la fuente',
            'Filas de origen',
            'Columnas',
            'Completitud observada',
            'Celdas vacías',
            'Cobertura del profiling',
            'Celdas evaluadas',
            'Cobertura de los datos',
            'Señales estructurales observadas',
            'Con faltantes',
            'Totalmente vacías',
            'Tipos mixtos',
            'Tipo no reconocido',
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $analysis
            );
        }
    }

    public function test_analysis_tab_uses_diagnostic_summary(): void
    {
        $analysis =
            $this->analysisBlock();

        self::assertStringContainsString(
            'dynamicSourceDiagnosticSummary(',
            $analysis
        );

        self::assertStringContainsString(
            'coverage.completeness_percent',
            $analysis
        );

        self::assertStringContainsString(
            'coverage.empty_cell_count',
            $analysis
        );

        self::assertStringContainsString(
            'columns.mixed_type_count',
            $analysis
        );
    }

    public function test_analysis_does_not_overclaim_duplicates(): void
    {
        $analysis =
            mb_strtolower(
                $this->analysisBlock()
            );

        self::assertStringNotContainsString(
            'duplicado',
            $analysis
        );

        self::assertStringNotContainsString(
            'duplicados',
            $analysis
        );
    }

    public function test_analysis_remains_descriptive_not_scored(): void
    {
        $analysis =
            mb_strtolower(
                $this->analysisBlock()
            );

        foreach ([
            'score',
            'puntuación',
            'riesgo alto',
            'riesgo medio',
            'riesgo bajo',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $analysis
            );
        }

        self::assertStringContainsString(
            'estos conteos son descriptivos',
            $analysis
        );
    }

    private function analysisBlock(): string
    {
        $start =
            strpos(
                $this->source,
                '<!-- ANALYSIS -->'
            );

        self::assertNotFalse(
            $start
        );

        $end =
            strpos(
                $this->source,
                '<!-- MAPPING -->',
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
