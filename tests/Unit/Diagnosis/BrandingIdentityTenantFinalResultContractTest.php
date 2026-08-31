<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class BrandingIdentityTenantFinalResultContractTest extends TestCase
{
    private function root(string $path): string
    {
        return dirname(__DIR__, 3).'/'.$path;
    }

    public function test_final_result_is_visible_only_after_validation(): void
    {
        $service = file_get_contents(
            $this->root(
                'app/Services/Ecosystem/BrandingIdentityWorkspaceService.php'
            )
        );

        foreach ([
            "'final_result' =>",
            'private function finalResult(',
            'STATUS_VALIDATED',
            'STATUS_COMPLETED',
            'hasReviewedSummary()',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $service
            );
        }
    }

    public function test_tenant_result_uses_reviewed_summary_not_generated_draft(): void
    {
        $service = file_get_contents(
            $this->root(
                'app/Services/Ecosystem/BrandingIdentityWorkspaceService.php'
            )
        );

        $start = strpos(
            $service,
            'private function finalResult('
        );

        $end = strpos(
            $service,
            'private function evaluationResultLabel(',
            $start
        );

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $section = substr(
            $service,
            $start,
            $end - $start
        );

        foreach ([
            'reviewed_payload',
            'executive_summary',
            'overall_recommendation',
            'priority_order',
            'dependencies',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $section
            );
        }

        $this->assertStringNotContainsString(
            '$summary->generated_payload',
            $section
        );
    }

    public function test_final_result_exposes_confirmed_human_area_evaluations(): void
    {
        $service = file_get_contents(
            $this->root(
                'app/Services/Ecosystem/BrandingIdentityWorkspaceService.php'
            )
        );

        foreach ([
            "->with('evaluation')",
            'STATUS_EVALUATED',
            "'result'",
            "'result_label'",
            "'findings'",
            "'recommendation'",
            "'priority'",
            "'priority_label'",
            "'evaluated_at'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $service
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
                $service
            );
        }
    }

    public function test_tenant_ui_shows_complete_professional_result(): void
    {
        $page = file_get_contents(
            $this->root(
                'resources/js/pages/App/BrandingIdentity.vue'
            )
        );

        foreach ([
            'type BrandingFinalResult',
            'final_result: BrandingFinalResult | null',
            'Resultado profesional de la evaluación',
            'Requieren atención',
            'Adecuadas',
            'No aplican',
            'Resultado por área',
            'Hallazgos',
            'Recomendación',
            'Prioridades',
            'Dependencias recomendadas',
            'Recomendación general',
            'Evaluación incluida',
            'definirán y cotizarán por separado',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $page
            );
        }
    }

    public function test_tenant_cannot_validate_or_complete_branding(): void
    {
        $routes = file_get_contents(
            $this->root(
                'routes/web.php'
            )
        );

        $controller = file_get_contents(
            $this->root(
                'app/Http/Controllers/AppHubBrandingLifecycleController.php'
            )
        );

        foreach ([
            "'app.branding.ready'",
            "'app.branding.validate'",
            "'app.branding.complete'",
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $routes
            );
        }

        foreach ([
            'function validate',
            'function complete',
            'markReadyForReview(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $controller
            );
        }
    }

    public function test_final_result_preserves_commercial_boundary(): void
    {
        $service = file_get_contents(
            $this->root(
                'app/Services/Ecosystem/BrandingIdentityWorkspaceService.php'
            )
        );

        foreach ([
            "'evaluation_included'",
            "'follow_up_requires_separate_quote'",
            "'automatic_commercial_execution'",
            'false',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $service
            );
        }

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
                $service
            );
        }
    }

    public function test_g5_copy_remains_correct(): void
    {
        $controller = file_get_contents(
            $this->root(
                'app/Http/Controllers/AppHubBrandingLifecycleController.php'
            )
        );

        $this->assertStringContainsString(
            'Evaluación de Branding e Identidad Digital en progreso.',
            $controller
        );

        $this->assertStringNotContainsString(
            'Branding e Identidad Digital está ahora en progreso.',
            $controller
        );
    }
}
