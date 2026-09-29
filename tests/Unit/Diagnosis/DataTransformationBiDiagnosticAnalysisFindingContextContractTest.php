<?php

namespace Tests\Unit\Diagnosis;

use Tests\TestCase;

final class DataTransformationBiDiagnosticAnalysisFindingContextContractTest
    extends TestCase
{
    private string $ui;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ui =
            file_get_contents(
                base_path(
                    'resources/js/pages/Admin/Transformation360/'
                    .'ImplementationRequests/Show.vue'
                )
            );
    }

    public function test_analysis_can_be_used_only_as_temporary_human_finding_context(): void
    {
        foreach (
            [
                'D2E_DIAGNOSTIC_FINDING_CONTEXT_TYPES',
                'D2E_DIAGNOSTIC_FINDING_CONTEXT_STATE',
                'D2E_DIAGNOSTIC_FINDING_CONTEXT_HELPERS',
                'D2E_DIAGNOSTIC_FINDING_CONTEXT_UI',
                'D2E_DIAGNOSTIC_FINDING_CONTEXT_PANEL',
                'Usar como contexto para nuevo hallazgo',
                'Contexto diagnóstico temporal',
                'Este contexto no se guarda como parte del',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->ui
            );
        }
    }

    public function test_context_preselects_only_supporting_sources_present_in_current_evaluation(): void
    {
        $helper =
            $this->block(
                'function useDataBiDiagnosticAnalysisAsFindingContext(',
                '// D2E_DIAGNOSTIC_FINDING_CONTEXT_HELPERS_END'
            );

        self::assertStringContainsString(
            'analysis.supporting_source_ids',
            $helper
        );

        self::assertStringContainsString(
            'workspace.sources',
            $helper
        );

        self::assertStringContainsString(
            'availableSourceIds.has(',
            $helper
        );

        self::assertStringContainsString(
            '.source_ids =',
            $helper
        );
    }

    public function test_context_does_not_auto_author_professional_fields(): void
    {
        $helper =
            $this->block(
                'function useDataBiDiagnosticAnalysisAsFindingContext(',
                '// D2E_DIAGNOSTIC_FINDING_CONTEXT_HELPERS_END'
            );

        foreach (
            [
                '.finding_type =',
                '.title =',
                '.details =',
                '.recommendation =',
                '.priority =',
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $helper
            );
        }

        self::assertStringContainsString(
            'It does NOT derive or assign:',
            $helper
        );

        self::assertStringContainsString(
            'finding type;',
            $helper
        );

        self::assertStringContainsString(
            'recommendation;',
            $helper
        );

        self::assertStringContainsString(
            'priority.',
            $helper
        );
    }

    public function test_analysis_context_is_not_persisted_in_finding_request(): void
    {
        $save =
            $this->block(
                'async function saveDataBiEvaluationFinding(',
                'async function deleteDataBiEvaluationFinding('
            );

        foreach (
            [
                'finding_type:',
                'title,',
                'details,',
                'recommendation:',
                'priority:',
                'source_ids:',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $save
            );
        }

        foreach (
            [
                'dataBiEvaluationFindingContext',
                'analysis_key',
                'diagnostic_analysis',
                'analysis_provenance',
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $save
            );
        }
    }

    public function test_reset_and_existing_finding_edit_clear_temporary_context(): void
    {
        $reset =
            $this->block(
                'function resetDataBiEvaluationFindingForm(): void {',
                'function editDataBiEvaluationFinding('
            );

        self::assertStringContainsString(
            'dataBiEvaluationFindingContext.value',
            $reset
        );

        self::assertStringContainsString(
            'null;',
            $reset
        );

        $edit =
            $this->block(
                'function editDataBiEvaluationFinding(',
                '// D2E_DIAGNOSTIC_FINDING_CONTEXT_HELPERS'
            );

        self::assertStringContainsString(
            'dataBiEvaluationFindingContext.value',
            $edit
        );

        self::assertStringContainsString(
            'null;',
            $edit
        );
    }

    public function test_context_button_uses_existing_diagnostic_analysis_object(): void
    {
        $analysis =
            $this->block(
                '<!-- DATA_BI_DIAGNOSTIC_ANALYSIS_ADMIN_UI -->',
                '<!-- DATA_BI_DIAGNOSTIC_ANALYSIS_ADMIN_UI_END -->'
            );

        self::assertStringContainsString(
            'dataBiDiagnosticAnalyses()',
            $analysis
        );

        self::assertStringContainsString(
            'useDataBiDiagnosticAnalysisAsFindingContext(',
            $analysis
        );

        self::assertStringContainsString(
            'analysis,',
            $analysis
        );
    }

    public function test_no_persisted_analysis_to_finding_provenance_is_added(): void
    {
        foreach (
            [
                'analysis_key:',
                'diagnostic_analysis_key:',
                'analysis_provenance:',
                'finding_analysis_id:',
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $this->findingWriteContract()
            );
        }
    }

    private function block(
        string $start,
        string $end
    ): string {
        $startAt =
            strpos(
                $this->ui,
                $start
            );

        self::assertNotFalse(
            $startAt
        );

        $endAt =
            strpos(
                $this->ui,
                $end,
                $startAt
            );

        self::assertNotFalse(
            $endAt
        );

        return substr(
            $this->ui,
            $startAt,
            $endAt - $startAt
        );
    }

    private function findingWriteContract(): string
    {
        return $this->block(
            'async function saveDataBiEvaluationFinding(',
            'async function deleteDataBiEvaluationFinding('
        );
    }
}
