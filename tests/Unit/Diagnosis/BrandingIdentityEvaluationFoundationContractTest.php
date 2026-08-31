<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class BrandingIdentityEvaluationFoundationContractTest extends TestCase
{
    private function root(string $path): string
    {
        return dirname(__DIR__, 3).'/'.$path;
    }

    public function test_evaluation_schema_separates_generated_draft_from_human_decision(): void
    {
        $source = file_get_contents(
            $this->root(
                'database/migrations/2026_08_31_014000_create_transformation_capability_need_evaluations_table.php'
            )
        );

        foreach ([
            "'transformation_capability_need_evaluations'",
            "'suggested_result'",
            "'suggested_findings'",
            "'suggested_recommendation'",
            "'suggested_priority'",
            "'suggested_questions'",
            "'generation_context'",
            "'generation_version'",
            "'generated_at'",
            "'result'",
            "'findings'",
            "'recommendation'",
            "'priority'",
            "'evaluated_by_user_id'",
            "'evaluated_at'",
            "'tcne_need_uq'",
            "'tcne_need_fk'",
            "'tcne_evaluated_by_fk'",
            'insertOrIgnore',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_model_keeps_insufficient_information_as_suggestion_only(): void
    {
        $source = file_get_contents(
            $this->root(
                'app/Models/TransformationCapabilityNeedEvaluation.php'
            )
        );

        foreach ([
            "STATUS_PENDING = 'pending'",
            "STATUS_DRAFT_GENERATED = 'draft_generated'",
            "STATUS_EVALUATED = 'evaluated'",
            "SUGGESTED_INSUFFICIENT_INFORMATION",
            "'insufficient_information'",
            "RESULT_REQUIRES_ATTENTION",
            "RESULT_ADEQUATE",
            "RESULT_NOT_APPLICABLE",
            "PRIORITY_HIGH",
            "PRIORITY_MEDIUM",
            "PRIORITY_LOW",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        $resultsStart = strpos(
            $source,
            'public const RESULTS'
        );

        $prioritiesStart = strpos(
            $source,
            'public const PRIORITY_HIGH'
        );

        $this->assertNotFalse($resultsStart);
        $this->assertNotFalse($prioritiesStart);

        $finalResultsBlock = substr(
            $source,
            $resultsStart,
            $prioritiesStart - $resultsStart
        );

        $this->assertStringNotContainsString(
            'SUGGESTED_INSUFFICIENT_INFORMATION',
            $finalResultsBlock
        );
    }

    public function test_need_has_one_evaluation_and_sync_initializes_it(): void
    {
        $model = file_get_contents(
            $this->root(
                'app/Models/TransformationCapabilityNeed.php'
            )
        );

        $service = file_get_contents(
            $this->root(
                'app/Services/Diagnosis/TransformationCapabilityNeedService.php'
            )
        );

        foreach ([
            'function evaluation(): HasOne',
            'TransformationCapabilityNeedEvaluation::class',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $model
            );
        }

        foreach ([
            'TransformationCapabilityNeedEvaluation::query()',
            '->firstOrCreate(',
            "'generation_version'",
            'STATUS_PENDING',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $service
            );
        }
    }

    public function test_generated_draft_is_advisory_and_audited(): void
    {
        $source = file_get_contents(
            $this->root(
                'app/Services/Diagnosis/TransformationCapabilityNeedEvaluationService.php'
            )
        );

        foreach ([
            'public function saveGeneratedDraft(',
            'SUGGESTED_RESULTS',
            'STATUS_DRAFT_GENERATED',
            "'suggested_result'",
            "'suggested_questions'",
            "'generation_context'",
            "'generation_version'",
            "'human_decision_changed'",
            'false',
            "'transformation_capability_need_evaluation_draft_generated'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_human_evaluation_requires_evidence_and_explicit_attention_priority(): void
    {
        $source = file_get_contents(
            $this->root(
                'app/Services/Diagnosis/TransformationCapabilityNeedEvaluationService.php'
            )
        );

        foreach ([
            'public function evaluate(',
            'TransformationCapabilityNeedEvaluation::RESULTS',
            'Registra los hallazgos que sustentan la evaluación.',
            'Una necesidad identificada requiere una recomendación.',
            'Una necesidad identificada requiere prioridad.',
            "'evaluated_by_user_id'",
            "'evaluated_at'",
            "'transformation_capability_need_evaluated'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_foundation_exposes_completeness_gate_without_advancing_activation(): void
    {
        $source = file_get_contents(
            $this->root(
                'app/Services/Diagnosis/TransformationCapabilityNeedEvaluationService.php'
            )
        );

        foreach ([
            'public function summaryForActivation(',
            "'requires_attention'",
            "'adequate'",
            "'not_applicable'",
            "'all_evaluated'",
            'public function assertAllEvaluated(',
            'Todas las áreas deben estar evaluadas antes de continuar.',
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
}
