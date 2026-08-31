<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class BrandingIdentityEvaluationSummaryContractTest extends TestCase
{
    private function root(string $path): string
    {
        return dirname(__DIR__, 3).'/'.$path;
    }

    public function test_summary_schema_separates_generated_and_reviewed_content(): void
    {
        $source = file_get_contents(
            $this->root(
                'database/migrations/2026_08_31_120000_create_transformation_capability_evaluation_summaries_table.php'
            )
        );

        foreach ([
            "'transformation_capability_evaluation_summaries'",
            "'transformation_capability_activation_id'",
            "'generated_payload'",
            "'generation_context'",
            "'generation_version'",
            "'generated_at'",
            "'reviewed_payload'",
            "'reviewed_by_user_id'",
            "'reviewed_at'",
            "'tces_activation_uq'",
            "'tces_activation_fk'",
            "'tces_reviewed_by_fk'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_summary_model_is_one_per_activation(): void
    {
        $model = file_get_contents(
            $this->root(
                'app/Models/TransformationCapabilityEvaluationSummary.php'
            )
        );

        $activation = file_get_contents(
            $this->root(
                'app/Models/TransformationCapabilityActivation.php'
            )
        );

        foreach ([
            "STATUS_PENDING",
            "STATUS_DRAFT_GENERATED",
            "STATUS_REVIEWED",
            'hasGeneratedDraft()',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $model
            );
        }

        foreach ([
            'function evaluationSummary(): HasOne',
            'TransformationCapabilityEvaluationSummary::class',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $activation
            );
        }
    }

    public function test_generator_uses_only_confirmed_human_evaluations(): void
    {
        $source = file_get_contents(
            $this->root(
                'app/Services/Diagnosis/BrandingIdentityEvaluationSummaryGenerator.php'
            )
        );

        foreach ([
            'confirmed_area_evaluations_v1',
            'STATUS_EVALUATED',
            "'result'",
            "'findings'",
            "'recommendation'",
            "'priority'",
            "'human_evaluations_only'",
            'true',
            "'priority_order'",
            "'dependencies'",
            "'overall_recommendation'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        foreach ([
            'suggested_result',
            'suggested_findings',
            'suggested_recommendation',
            'suggested_priority',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_generator_builds_branding_sequence_only_for_confirmed_attention_areas(): void
    {
        $source = file_get_contents(
            $this->root(
                'app/Services/Diagnosis/BrandingIdentityEvaluationSummaryGenerator.php'
            )
        );

        foreach ([
            'positioning_refinement',
            'visual_identity_update',
            'brand_kit',
            'social_normalization',
            'commercial_documents',
            'web_application',
            'RESULT_REQUIRES_ATTENTION',
            'priorityRank(',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_summary_generation_is_audited_and_does_not_change_lifecycle(): void
    {
        $source = file_get_contents(
            $this->root(
                'app/Services/Diagnosis/TransformationCapabilityEvaluationSummaryService.php'
            )
        );

        foreach ([
            'saveGeneratedDraft(',
            "'transformation_capability_evaluation_summary_generated'",
            "'human_evaluations_only'",
            "'human_decision_changed'",
            'false',
            "'activation_status_changed'",
            "'commercial_acceptance'",
            'assertGeneratedForActivation(',
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

    public function test_admin_can_generate_summary_only_after_all_areas_are_evaluated(): void
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
            'public function generateSummary(',
            'BrandingIdentityEvaluationSummaryGenerator',
            'TransformationCapabilityEvaluationSummaryService',
            '$evaluations->assertAllEvaluated(',
            '$generator->generate(',
            '$summaries->saveGeneratedDraft(',
            '$summaries->assertGeneratedForActivation(',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $controller
            );
        }

        foreach ([
            '/branding-evaluations/{activation}/summary/generate',
            'branding_evaluations.summary.generate',
            "'generateSummary'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $routes
            );
        }
    }

    public function test_ready_for_review_requires_areas_and_generated_summary(): void
    {
        $controller = file_get_contents(
            $this->root(
                'app/Http/Controllers/Admin/AdminBrandingEvaluationController.php'
            )
        );

        $readyPosition =
            strpos(
                $controller,
                'public function markReadyForReview('
            );

        $this->assertNotFalse(
            $readyPosition
        );

        $ready =
            substr(
                $controller,
                $readyPosition
            );

        $this->assertStringContainsString(
            '$evaluations->assertAllEvaluated(',
            $ready
        );

        $this->assertStringContainsString(
            '$summaries->assertGeneratedForActivation(',
            $ready
        );

        $this->assertStringContainsString(
            '$activations->markReadyForReview(',
            $ready
        );
    }

    public function test_admin_ui_shows_summary_priorities_and_dependencies(): void
    {
        $source = file_get_contents(
            $this->root(
                'resources/js/pages/Admin/BrandingEvaluations/Show.vue'
            )
        );

        foreach ([
            'type EvaluationSummary',
            'evaluation_summary',
            'can_generate_summary',
            'function generateSummary()',
            '/summary/generate',
            'Síntesis de la Evaluación',
            'Generar síntesis',
            'Prioridades confirmadas',
            'Dependencias recomendadas',
            'Recomendación general',
            'Borrador automático',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_no_external_ai_or_commercial_side_effects_are_introduced(): void
    {
        $sources = implode(
            "\n",
            [
                file_get_contents(
                    $this->root(
                        'app/Services/Diagnosis/BrandingIdentityEvaluationSummaryGenerator.php'
                    )
                ),
                file_get_contents(
                    $this->root(
                        'app/Services/Diagnosis/TransformationCapabilityEvaluationSummaryService.php'
                    )
                ),
            ]
        );

        foreach ([
            'OpenAI',
            'Anthropic',
            'Gemini',
            'Invoice::',
            'Payment::',
            'Subscription::',
            'Order::',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $sources
            );
        }
    }
}
