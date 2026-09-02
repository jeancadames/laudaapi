<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class BrandingIdentityAdminNeedResetContractTest extends TestCase
{
    private function root(string $path): string
    {
        return dirname(__DIR__, 3).'/'.$path;
    }

    public function test_admin_has_individual_reset_route(): void
    {
        $routes = file_get_contents(
            $this->root('routes/admin.php')
        );

        foreach ([
            "Route::delete(",
            "/branding-evaluations/{activation}/areas/{need}/reset",
            "branding_evaluations.needs.reset",
            "'resetNeed'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $routes
            );
        }
    }

    public function test_controller_scopes_reset_to_in_progress_activation(): void
    {
        $source = file_get_contents(
            $this->root(
                'app/Http/Controllers/Admin/AdminBrandingEvaluationController.php'
            )
        );

        $start = strpos(
            $source,
            'public function resetNeed('
        );

        $end = strpos(
            $source,
            'public function generateDrafts(',
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
            'assertBrandingActivation(',
            'transformation_capability_activation_id',
            'STATUS_IN_PROGRESS',
            'resetHumanEvaluation(',
            'request->user()',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $block
            );
        }
    }

    public function test_reset_clears_only_human_decision(): void
    {
        $source = file_get_contents(
            $this->root(
                'app/Services/Diagnosis/TransformationCapabilityNeedEvaluationService.php'
            )
        );

        $start = strpos(
            $source,
            'public function resetHumanEvaluation('
        );

        $end = strpos(
            $source,
            'public function summaryForActivation(',
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
            "'result' =>",
            "'findings' =>",
            "'recommendation' =>",
            "'priority' =>",
            "'evaluated_by_user_id' =>",
            "'evaluated_at' =>",
            'null',
            'STATUS_DRAFT_GENERATED',
            'STATUS_PENDING',
            'transformation_capability_need_evaluation_reset',
            'generated_draft_preserved',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $block
            );
        }

        foreach ([
            "'suggested_result' => null",
            "'suggested_findings' => null",
            "'suggested_recommendation' => null",
            "'suggested_priority' => null",
            "'generation_version' => 0",
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $block
            );
        }
    }

    public function test_ui_has_local_clear_and_persistent_reset(): void
    {
        $source = file_get_contents(
            $this->root(
                'resources/js/pages/Admin/BrandingEvaluations/Show.vue'
            )
        );

        foreach ([
            'function clearNeedForm(',
            'function resetEvaluation(',
            'Campos limpiados',
            'Limpiar campos',
            'Restablecer evaluación',
            'window.confirm(',
            'router.delete(',
            '/reset',
            'resetting[need.id]',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_confirmed_area_must_be_reset_before_editing_again(): void
    {
        $source = file_get_contents(
            $this->root(
                'resources/js/pages/Admin/BrandingEvaluations/Show.vue'
            )
        );

        foreach ([
            "need.evaluation.status === 'evaluated'",
            "need.evaluation.status !== 'evaluated'",
            "|| need.evaluation.status === 'evaluated'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_reset_does_not_touch_commercial_or_lifecycle_objects(): void
    {
        $service = file_get_contents(
            $this->root(
                'app/Services/Diagnosis/TransformationCapabilityNeedEvaluationService.php'
            )
        );

        $start = strpos(
            $service,
            'public function resetHumanEvaluation('
        );

        $end = strpos(
            $service,
            'public function summaryForActivation(',
            $start
        );

        $block = substr(
            $service,
            $start,
            $end - $start
        );

        foreach ([
            'Invoice::',
            'Payment::',
            'Subscription::',
            'markReadyForReview(',
            '->validate(',
            '->complete(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $block
            );
        }
    }
}
