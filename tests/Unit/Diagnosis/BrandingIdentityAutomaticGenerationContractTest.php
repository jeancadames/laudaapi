<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class BrandingIdentityAutomaticGenerationContractTest extends TestCase
{
    private function root(string $path): string
    {
        return dirname(__DIR__, 3).'/'.$path;
    }

    public function test_context_uses_company_and_optional_lauda360_sources(): void
    {
        $source = file_get_contents(
            $this->root(
                'app/Services/Ecosystem/BrandingIdentityEvaluationContextService.php'
            )
        );

        foreach ([
            'deterministic_context_v1',
            "'company'",
            "'assessment'",
            "'roadmap'",
            "'plan_context'",
            "'Perfil de empresa'",
            "'Diagnóstico 360'",
            "'Contexto de Roadmap'",
            "'Plan de Implementación'",
            'BrandingIdentityPlanContextService',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_generator_is_fail_closed_without_evidence(): void
    {
        $source = file_get_contents(
            $this->root(
                'app/Services/Diagnosis/BrandingIdentityEvaluationDraftGenerator.php'
            )
        );

        foreach ([
            "'insufficient_information'",
            'La ausencia de una señal NO significa',
            "'suggested_questions'",
            "'generation_context'",
            "'requires_attention'",
            'matchedSignals(',
            'positioning_refinement',
            'visual_identity_update',
            'brand_kit',
            'social_normalization',
            'commercial_documents',
            'web_application',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        $this->assertStringNotContainsString(
            "'suggested_result' =>\n                    'adequate'",
            $source
        );
    }

    public function test_generation_never_changes_human_decision_or_lifecycle(): void
    {
        $source = file_get_contents(
            $this->root(
                'app/Services/Diagnosis/BrandingIdentityAutomaticEvaluationService.php'
            )
        );

        foreach ([
            'STATUS_IN_PROGRESS',
            'STATUS_EVALUATED',
            'saveGeneratedDraft(',
            "'human_decision_changed'",
            'false',
            "'activation_status_changed'",
            "'commercial_acceptance'",
            "'branding_identity_automatic_drafts_generated'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        foreach ([
            'markReadyForReview(',
            '->validate(',
            '->complete(',
            'Invoice::',
            'Payment::',
            'Subscription::',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_admin_has_explicit_generation_action(): void
    {
        $controller = file_get_contents(
            $this->root(
                'app/Http/Controllers/Admin/AdminBrandingEvaluationController.php'
            )
        );

        $routes = file_get_contents(
            $this->root(
                'routes/admin.php'
            )
        );

        foreach ([
            'BrandingIdentityAutomaticEvaluationService',
            'public function generateDrafts(',
            'generatePending(',
            'Borradores automáticos generados',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $controller
            );
        }

        foreach ([
            '/branding-evaluations/{activation}/generate-drafts',
            'branding_evaluations.generate_drafts',
            "'generateDrafts'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $routes
            );
        }
    }

    public function test_admin_ui_exposes_generation_and_sources(): void
    {
        $source = file_get_contents(
            $this->root(
                'resources/js/pages/Admin/BrandingEvaluations/Show.vue'
            )
        );

        foreach ([
            'generation_context',
            'generatingDrafts',
            'function generateDrafts()',
            'Generar borradores automáticos',
            '/generate-drafts',
            'Fuentes consideradas',
            'Borrador automático',
            'Usar como punto de partida',
            'Información insuficiente',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_no_external_ai_vendor_is_introduced(): void
    {
        $sources = implode(
            "\n",
            [
                file_get_contents(
                    $this->root(
                        'app/Services/Ecosystem/BrandingIdentityEvaluationContextService.php'
                    )
                ),
                file_get_contents(
                    $this->root(
                        'app/Services/Diagnosis/BrandingIdentityEvaluationDraftGenerator.php'
                    )
                ),
                file_get_contents(
                    $this->root(
                        'app/Services/Diagnosis/BrandingIdentityAutomaticEvaluationService.php'
                    )
                ),
            ]
        );

        foreach ([
            'OpenAI',
            'Anthropic',
            'Gemini',
            'api.openai',
            'chatCompletion',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $sources
            );
        }
    }
}
