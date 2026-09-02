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

    public function test_insufficient_information_has_area_specific_assistance(): void
    {
        $source = $this->source();

        foreach ([
            'manualResultAssistance',
            'positioning_refinement',
            'visual_identity_update',
            'brand_kit',
            'social_normalization',
            'commercial_documents',
            'web_application',
            'findingsPlaceholder',
            'assistFromSelectedResult',
            '@change="assistFromSelectedResult(need)"',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_requires_attention_only_proposes_support_fields(): void
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

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $block = substr(
            $source,
            $start,
            $end - $start
        );

        foreach ([
            "form.result === 'requires_attention'",
            'form.recommendation =',
            'form.priority =',
            'Campos de apoyo completados',
            'Documenta los hallazgos reales',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $block
            );
        }

        $this->assertStringNotContainsString(
            'router.post(',
            $block
        );

        $this->assertStringNotContainsString(
            'router.patch(',
            $block
        );
    }

    public function test_generic_insufficient_finding_is_not_used_as_human_evidence(): void
    {
        $source = $this->source();

        foreach ([
            'genericDraftFinding',
            "form.findings = '';",
            'no es evidencia profesional',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_changing_result_only_removes_automatic_support_values(): void
    {
        $source = $this->source();

        foreach ([
            'form.recommendation.trim()',
            '=== assistance.recommendation',
            'form.priority',
            '=== assistance.priority',
            "form.result === 'adequate'",
            "form.result === 'not_applicable'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_selection_assistance_never_saves_automatically(): void
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
            'router.',
            'saveEvaluation(',
            '/areas/',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $block
            );
        }
    }
}
