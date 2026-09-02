<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class BrandingIdentityAdminSelectionAssistanceContractTest extends TestCase
{
    private function source(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/resources/js/pages/Admin/BrandingEvaluations/Show.vue'
        );
    }

    public function test_all_six_areas_have_all_result_assistance(): void
    {
        $source = $this->source();

        foreach ([
            'positioning_refinement',
            'visual_identity_update',
            'brand_kit',
            'social_normalization',
            'commercial_documents',
            'web_application',
        ] as $area) {
            $this->assertStringContainsString(
                $area,
                $source
            );
        }

        foreach ([
            'requires_attention',
            'adequate',
            'not_applicable',
            'findings:',
            'recommendation:',
            'priority:',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_selecting_result_fills_support_fields(): void
    {
        $source = $this->source();

        foreach ([
            'function assistFromSelectedResult',
            'canUseAutomaticSuggestion',
            'suggested_findings',
            'suggested_recommendation',
            'suggested_priority',
            'fallback.findings',
            'fallback.recommendation',
            'fallback.priority',
            'Campos completados automáticamente',
            '@change="assistFromSelectedResult(need)"',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_generation_clears_only_unsaved_editable_forms(): void
    {
        $source = $this->source();

        foreach ([
            'function resetFormsAfterDraftGeneration',
            "need.evaluation.status === 'evaluated'",
            "result: ''",
            "findings: ''",
            "recommendation: ''",
            "priority: ''",
            'onSuccess: () => {',
            'resetFormsAfterDraftGeneration();',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_confirmed_human_evaluations_are_preserved(): void
    {
        $source = $this->source();

        $start = strpos(
            $source,
            'function resetFormsAfterDraftGeneration'
        );

        $end = strpos(
            $source,
            'function assistFromSelectedResult',
            $start
        );

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $block = substr(
            $source,
            $start,
            $end - $start
        );

        foreach ([
            "need.evaluation.status === 'evaluated'",
            'need.evaluation.result',
            'need.evaluation.findings',
            'need.evaluation.recommendation',
            'need.evaluation.priority',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $block
            );
        }
    }

    public function test_selection_assistance_never_autosaves(): void
    {
        $source = $this->source();

        $start = strpos(
            $source,
            'function assistFromSelectedResult'
        );

        $end = strpos(
            $source,
            'function useSuggestion',
            $start
        );

        $block = substr(
            $source,
            $start,
            $end - $start
        );

        foreach ([
            'router.post(',
            'router.patch(',
            'saveEvaluation(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $block
            );
        }
    }
}
