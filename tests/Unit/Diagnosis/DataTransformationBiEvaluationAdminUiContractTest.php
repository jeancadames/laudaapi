<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiEvaluationAdminUiContractTest
    extends TestCase
{
    private string $show;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(
                __DIR__,
                3
            );

        $this->show =
            file_get_contents(
                $root
                .'/resources/js/pages/Admin/'
                .'Transformation360/'
                .'ImplementationRequests/'
                .'Show.vue'
            );

        self::assertIsString(
            $this->show
        );
    }

    public function test_evaluation_workspace_is_session_level_inside_delivery_workspace(): void
    {
        $sourceWorkspace =
            strpos(
                $this->show,
                '<!-- D17_DYNAMIC_SOURCE_WORKSPACE_UI -->'
            );

        $evaluationWorkspace =
            strpos(
                $this->show,
                '<!-- DATA_BI_DIAGNOSIS_EVALUATION_WORKSPACE -->'
            );

        $sourceDetail =
            strpos(
                $this->show,
                '<!-- D17_DYNAMIC_SOURCE_DETAIL_WORKSPACE -->'
            );

        self::assertNotFalse(
            $sourceWorkspace
        );

        self::assertNotFalse(
            $evaluationWorkspace
        );

        self::assertNotFalse(
            $sourceDetail
        );

        self::assertLessThan(
            $evaluationWorkspace,
            $sourceWorkspace
        );

        self::assertLessThan(
            $sourceDetail,
            $evaluationWorkspace
        );

        $block =
            substr(
                $this->show,
                $evaluationWorkspace,
                $sourceDetail
                    - $evaluationWorkspace
            );

        self::assertStringNotContainsString(
            'dynamicSourceSelectedAsset()',
            $block
        );

        self::assertStringContainsString(
            "=== 'submitted_for_evaluation'",
            $block
        );
    }

    public function test_get_workspace_is_read_only_and_prepare_is_explicit(): void
    {
        $loadStart =
            strpos(
                $this->show,
                'async function loadDataBiEvaluationWorkspace'
            );

        $prepareStart =
            strpos(
                $this->show,
                'async function prepareDataBiEvaluation',
                $loadStart
            );

        self::assertNotFalse(
            $loadStart
        );

        self::assertNotFalse(
            $prepareStart
        );

        $loadMethod =
            substr(
                $this->show,
                $loadStart,
                $prepareStart
                    - $loadStart
            );

        self::assertStringContainsString(
            '`${baseUrl}/workspace`',
            $loadMethod
        );

        self::assertStringContainsString(
            "'GET'",
            $loadMethod
        );

        self::assertStringNotContainsString(
            '/prepare',
            $loadMethod
        );

        self::assertStringContainsString(
            '`${baseUrl}/prepare`',
            $this->show
        );
    }

    public function test_ui_supports_complete_professional_finding_lifecycle(): void
    {
        foreach ([
            'saveDataBiEvaluationFinding',
            'editDataBiEvaluationFinding',
            'deleteDataBiEvaluationFinding',
            'reconfirmDataBiEvaluationFinding',
            'markDataBiEvaluationReady',
            'publishDataBiEvaluation',
            '/findings',
            '/ready-for-review',
            '/publish',
            'Guardar hallazgo',
            'Reconfirmar',
            'Enviar a revisión',
            'Publicar evaluación',
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->show
            );
        }
    }

    public function test_findings_are_evidence_version_aware(): void
    {
        foreach ([
            'evidence_version',
            'evidence_current',
            'stale_finding_count',
            'Por reconfirmar',
            'dataBiEvaluationCanMarkReady',
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->show
            );
        }

        $normalized =
            preg_replace(
                '/\\s+/',
                ' ',
                $this->show
            );

        self::assertIsString(
            $normalized
        );

        self::assertStringContainsString(
            'Requiere reconfirmación',
            $normalized
        );
    }

    public function test_edit_controls_depend_on_backend_draft_action(): void
    {
        $marker =
            strpos(
                $this->show,
                '<!-- DATA_BI_DIAGNOSIS_EVALUATION_WORKSPACE -->'
            );

        $end =
            strpos(
                $this->show,
                '<!-- D17_DYNAMIC_SOURCE_DETAIL_WORKSPACE -->',
                $marker
            );

        self::assertNotFalse(
            $marker
        );

        self::assertNotFalse(
            $end
        );

        $block =
            substr(
                $this->show,
                $marker,
                $end - $marker
            );

        self::assertStringContainsString(
            '.can_manage_findings',
            $block
        );

        self::assertStringContainsString(
            '.can_mark_ready_for_review',
            $block
        );

        self::assertStringContainsString(
            '.can_publish',
            $block
        );

        self::assertStringContainsString(
            'La evaluación publicada es inmutable.',
            $block
        );
    }

    public function test_ui_never_auto_prepares_evaluation_on_load(): void
    {
        $watchStart =
            strpos(
                $this->show,
                '// GET only. Loading this workspace must never create an evaluation.'
            );

        $watchEnd =
            strpos(
                $this->show,
                '// DATA_BI_DIAGNOSIS_EVALUATION_UI_END',
                $watchStart
            );

        self::assertNotFalse(
            $watchStart
        );

        self::assertNotFalse(
            $watchEnd
        );

        $watchBlock =
            substr(
                $this->show,
                $watchStart,
                $watchEnd - $watchStart
            );

        self::assertStringContainsString(
            'loadDataBiEvaluationWorkspace',
            $watchBlock
        );

        self::assertStringNotContainsString(
            'prepareDataBiEvaluation',
            $watchBlock
        );
    }

    public function test_new_ui_does_not_start_future_implementation_pipeline(): void
    {
        $scriptStart =
            strpos(
                $this->show,
                '// DATA_BI_DIAGNOSIS_EVALUATION_UI_STATE'
            );

        $scriptEnd =
            strpos(
                $this->show,
                '// DATA_BI_DIAGNOSIS_EVALUATION_UI_END',
                $scriptStart
            );

        $templateStart =
            strpos(
                $this->show,
                '<!-- DATA_BI_DIAGNOSIS_EVALUATION_WORKSPACE -->'
            );

        $templateEnd =
            strpos(
                $this->show,
                '<!-- D17_DYNAMIC_SOURCE_DETAIL_WORKSPACE -->',
                $templateStart
            );

        self::assertNotFalse(
            $scriptStart
        );

        self::assertNotFalse(
            $scriptEnd
        );

        self::assertNotFalse(
            $templateStart
        );

        self::assertNotFalse(
            $templateEnd
        );

        $newUi =
            substr(
                $this->show,
                $scriptStart,
                $scriptEnd - $scriptStart
            )
            .substr(
                $this->show,
                $templateStart,
                $templateEnd - $templateStart
            );

        foreach ([
            'DataTransformationBiCanonical',
            'DataTransformationBiStaging',
            'DataTransformationBiNormalizedRow',
            'DataTransformationBiProcessingRun',
            'DataTransformationBiIntakeBatch',
            'normalized_payload',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $newUi
            );
        }
    }
}
