<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiDiagnosticReportAdminUiContractTest
    extends TestCase
{
    private string $source;

    private string $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(
                __DIR__,
                3
            );

        $this->source =
            (string) file_get_contents(
                $root
                .'/resources/js/pages/Admin/'
                .'Transformation360/'
                .'ImplementationRequests/Show.vue'
            );

        $this->tenant =
            (string) file_get_contents(
                $root
                .'/resources/js/pages/App/'
                .'DataTransformationBi.vue'
            );
    }

    public function test_admin_workspace_types_diagnostic_report(): void
    {
        foreach ([
            'type DataBiDiagnosticReportContext = {',
            'type DataBiDiagnosticReportProfessionalFindings = {',
            'type DataBiDiagnosticReportInterpretationBoundaries = {',
            'type DataBiDiagnosticReport = {',
            'diagnostic_report: DataBiDiagnosticReport | null;',
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $this->source
            );
        }
    }

    public function test_report_is_read_only_presentation_authority(): void
    {
        foreach ([
            'function dataBiDiagnosticReport()',
            'function dataBiDiagnosticReportFindings()',
            '?.diagnostic_report',
            '?.structural_analysis',
            '?.professional_findings',
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $this->source
            );
        }
    }

    public function test_analysis_display_uses_report_structural_analysis(): void
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

        self::assertStringContainsString(
            '.diagnostic_report.structural_analysis',
            $block
        );

        self::assertStringNotContainsString(
            '.diagnostic_analysis',
            $block
        );
    }

    public function test_professional_finding_display_uses_report_items(): void
    {
        self::assertStringContainsString(
            'dataBiDiagnosticReportFindings()',
            $this->source
        );

        self::assertSame(
            0,
            preg_match(
                '/dataBiEvaluationWorkspace\s*'
                .'\.evaluation\s*'
                .'\.findings/',
                $this->source
            )
        );
    }

    public function test_compact_report_header_uses_report_contract(): void
    {
        $start =
            strpos(
                $this->source,
                '<!-- DATA_BI_DIAGNOSTIC_REPORT_ADMIN_UI -->'
            );

        $end =
            strpos(
                $this->source,
                '<!-- DATA_BI_DIAGNOSTIC_REPORT_ADMIN_UI_END -->'
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

        foreach ([
            '.report_context',
            '.scope_statement',
            '.structural_analysis',
            '.professional_findings',
            '.interpretation_boundaries',
            'Informe diagnóstico',
            'Resumen de la entrega evaluada',
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $block
            );
        }
    }

    public function test_finding_authoring_remains_evaluation_and_actions_driven(): void
    {
        foreach ([
            'id="data-bi-evaluation-finding-form"',
            '.can_manage_findings',
            'saveDataBiEvaluationFinding',
            'editDataBiEvaluationFinding',
            'resetDataBiEvaluationFindingForm',
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $this->source
            );
        }
    }

    public function test_d2e_context_flow_is_preserved(): void
    {
        foreach ([
            'useDataBiDiagnosticAnalysisAsFindingContext',
            'Usar como contexto para nuevo hallazgo',
            'D2E_DIAGNOSTIC_FINDING_CONTEXT_UI',
            'D2E_DIAGNOSTIC_FINDING_CONTEXT_PANEL',
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $this->source
            );
        }
    }

    public function test_report_body_is_not_duplicated(): void
    {
        self::assertSame(
            1,
            preg_match_all(
                '/v-for="\s*analysis in\s*'
                .'dataBiDiagnosticAnalyses\(\)\s*"/',
                $this->source
            )
        );

        self::assertSame(
            1,
            preg_match_all(
                '/v-for="\s*finding in\s*'
                .'dataBiDiagnosticReportFindings\(\)\s*"/',
                $this->source
            )
        );

        self::assertSame(
            1,
            substr_count(
                $this->source,
                '<!-- DATA_BI_DIAGNOSTIC_REPORT_ADMIN_UI -->'
            )
        );
    }

    public function test_tenant_ui_is_unchanged(): void
    {
        self::assertStringNotContainsString(
            'diagnostic_report:',
            $this->tenant
        );

        self::assertStringNotContainsString(
            'DATA_BI_DIAGNOSTIC_REPORT_ADMIN_UI',
            $this->tenant
        );
    }
}
