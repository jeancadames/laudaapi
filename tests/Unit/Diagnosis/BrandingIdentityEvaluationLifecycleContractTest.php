<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class BrandingIdentityEvaluationLifecycleContractTest extends TestCase
{
    private function root(string $path): string
    {
        return dirname(__DIR__, 3).'/'.$path;
    }

    public function test_summary_review_is_human_and_audited(): void
    {
        $model = file_get_contents(
            $this->root(
                'app/Models/TransformationCapabilityEvaluationSummary.php'
            )
        );

        $service = file_get_contents(
            $this->root(
                'app/Services/Diagnosis/TransformationCapabilityEvaluationSummaryService.php'
            )
        );

        foreach ([
            'hasReviewedSummary()',
            'STATUS_REVIEWED',
            'reviewed_payload',
            'reviewed_by_user_id',
            'reviewed_at',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $model
            );
        }

        foreach ([
            'public function review(',
            'STATUS_READY_FOR_REVIEW',
            'STATUS_REVIEWED',
            "'transformation_capability_evaluation_summary_reviewed'",
            "'human_review'",
            'true',
            "'activation_status_changed'",
            'false',
            'assertReviewedForActivation(',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $service
            );
        }
    }

    public function test_validation_requires_reviewed_summary(): void
    {
        $controller = file_get_contents(
            $this->root(
                'app/Http/Controllers/Admin/AdminBrandingEvaluationController.php'
            )
        );

        $position = strpos(
            $controller,
            'public function validateEvaluation('
        );

        $this->assertNotFalse($position);

        $completePosition = strpos(
            $controller,
            'public function completeEvaluation(',
            $position
        );

        $this->assertNotFalse(
            $completePosition
        );

        $section = substr(
            $controller,
            $position,
            $completePosition - $position
        );

        foreach ([
            'STATUS_READY_FOR_REVIEW',
            '$summaries->assertReviewedForActivation(',
            '$activations->validate(',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $section
            );
        }

        $this->assertStringNotContainsString(
            '$activations->complete(',
            $section
        );
    }

    public function test_completion_requires_validated_state(): void
    {
        $controller = file_get_contents(
            $this->root(
                'app/Http/Controllers/Admin/AdminBrandingEvaluationController.php'
            )
        );

        $position = strpos(
            $controller,
            'public function completeEvaluation('
        );

        $this->assertNotFalse($position);

        $section = substr(
            $controller,
            $position,
            3000
        );

        foreach ([
            'STATUS_VALIDATED',
            '$summaries->assertReviewedForActivation(',
            '$activations->complete(',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $section
            );
        }
    }

    public function test_admin_routes_expose_review_validate_and_complete(): void
    {
        $routes = file_get_contents(
            $this->root(
                'routes/admin.php'
            )
        );

        foreach ([
            '/branding-evaluations/{activation}/summary/review',
            'branding_evaluations.summary.review',
            '/branding-evaluations/{activation}/validate',
            'branding_evaluations.validate',
            '/branding-evaluations/{activation}/complete',
            'branding_evaluations.complete',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $routes
            );
        }
    }

    public function test_admin_ui_exposes_review_validation_and_completion(): void
    {
        $page = file_get_contents(
            $this->root(
                'resources/js/pages/Admin/BrandingEvaluations/Show.vue'
            )
        );

        foreach ([
            'can_review_summary',
            'can_validate',
            'can_complete',
            'function reviewSummary()',
            '/summary/review',
            'function validateEvaluation()',
            '/validate',
            'function completeEvaluation()',
            '/complete',
            'Revisión y cierre profesional',
            'Confirmar revisión',
            'Validar evaluación',
            'Completar evaluación',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $page
            );
        }
    }

    public function test_g6e_does_not_create_commercial_objects(): void
    {
        $sources = implode(
            "\n",
            [
                file_get_contents(
                    $this->root(
                        'app/Services/Diagnosis/TransformationCapabilityEvaluationSummaryService.php'
                    )
                ),
                file_get_contents(
                    $this->root(
                        'app/Http/Controllers/Admin/AdminBrandingEvaluationController.php'
                    )
                ),
            ]
        );

        foreach ([
            'Invoice::',
            'Payment::',
            'Subscription::',
            'SubscriptionItem::',
            'Order::',
            'StandaloneServiceSettlement',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $sources
            );
        }
    }
}
