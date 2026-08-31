<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class BrandingIdentityAdminEvaluationWorkspaceContractTest extends TestCase
{
    private function root(string $path): string
    {
        return dirname(__DIR__, 3).'/'.$path;
    }

    public function test_admin_routes_are_protected_and_company_owned(): void
    {
        $routes = file_get_contents(
            $this->root('routes/admin.php')
        );

        foreach ([
            "middleware(['auth', 'verified', 'role:admin'])",
            "'/branding-evaluations'",
            "'/branding-evaluations/{activation}'",
            "'/branding-evaluations/{activation}/areas/{need}'",
            "'/branding-evaluations/{activation}/ready-for-review'",
            "'branding_evaluations.index'",
            "'branding_evaluations.show'",
            "'branding_evaluations.needs.evaluate'",
            "'branding_evaluations.ready_for_review'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $routes
            );
        }
    }

    public function test_controller_is_scoped_to_branding_and_does_not_require_diagnosis(): void
    {
        $controller = file_get_contents(
            $this->root(
                'app/Http/Controllers/Admin/AdminBrandingEvaluationController.php'
            )
        );

        foreach ([
            "'branding_identity'",
            'assertBrandingActivation(',
            'company:id,name,slug,subscriber_id',
            "'assessment' =>",
            '$activation->assessment',
            'summaryForActivation(',
            'evaluateNeed(',
            'markReadyForReview(',
            'assertAllEvaluated(',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $controller
            );
        }

        foreach ([
            'abort_unless(',
            'transformation_capability_activation_id',
            'STATUS_IN_PROGRESS',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $controller
            );
        }
    }

    public function test_human_evaluation_is_required_before_ready_for_review(): void
    {
        $controller = file_get_contents(
            $this->root(
                'app/Http/Controllers/Admin/AdminBrandingEvaluationController.php'
            )
        );

        $evaluation = file_get_contents(
            $this->root(
                'app/Services/Diagnosis/TransformationCapabilityNeedEvaluationService.php'
            )
        );

        foreach ([
            '$evaluations->evaluate(',
            '$evaluations->assertAllEvaluated(',
            '$activations->markReadyForReview(',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $controller
            );
        }

        $this->assertStringContainsString(
            'Todas las áreas deben estar evaluadas antes de continuar.',
            $evaluation
        );
    }

    public function test_admin_ui_exposes_six_area_evaluation_contract(): void
    {
        $page = file_get_contents(
            $this->root(
                'resources/js/pages/Admin/BrandingEvaluations/Show.vue'
            )
        );

        foreach ([
            'Evaluación profesional de Branding e Identidad',
            'branding.summary.evaluated',
            'branding.summary.pending',
            'v-for="need in branding.needs"',
            'Resultado *',
            'Hallazgos *',
            'Recomendación',
            'Prioridad',
            'Requiere atención',
            'Adecuado / no requiere intervención',
            'No aplica',
            'Guardar evaluación',
            'Marcar listo para revisión',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $page
            );
        }
    }

    public function test_admin_ui_is_ready_for_generated_advisory_drafts(): void
    {
        $page = file_get_contents(
            $this->root(
                'resources/js/pages/Admin/BrandingEvaluations/Show.vue'
            )
        );

        foreach ([
            'Borrador automático',
            'suggested_result',
            'suggested_findings',
            'suggested_recommendation',
            'suggested_priority',
            'suggested_questions',
            'Información insuficiente',
            'Usar como punto de partida',
            'constituye la evaluación profesional final',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $page
            );
        }
    }

    public function test_admin_sidebar_contains_branding_evaluations(): void
    {
        $navigation = file_get_contents(
            $this->root(
                'resources/js/config/navigationByRole.ts'
            )
        );

        $sidebar = file_get_contents(
            $this->root(
                'resources/js/components/AppSidebar.vue'
            )
        );

        foreach ([
            'Evaluaciones de Branding',
            '/admin/branding-evaluations',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $navigation
            );

            $this->assertStringContainsString(
                $token === 'Evaluaciones de Branding'
                    ? '/admin/branding-evaluations'
                    : $token,
                $sidebar
            );
        }
    }

    public function test_workspace_does_not_create_commercial_objects(): void
    {
        $controller = file_get_contents(
            $this->root(
                'app/Http/Controllers/Admin/AdminBrandingEvaluationController.php'
            )
        );

        foreach ([
            'Invoice::',
            'Payment::',
            'Subscription::',
            'Order::',
            'StandaloneServiceSettlement',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $controller
            );
        }
    }
}
