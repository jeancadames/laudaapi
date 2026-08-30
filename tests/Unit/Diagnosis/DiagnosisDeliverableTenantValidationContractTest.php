<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisDeliverableTenantValidationContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_validation_foundation_is_version_specific_and_non_commercial(): void
    {
        $migration = file_get_contents(
            $this->root().'/database/migrations/2026_08_30_150000_create_diagnosis_deliverable_validations_table.php'
        );

        foreach ([
            'Schema::create(',
            "'diagnosis_deliverable_validations'",
            "'diagnosis_assessment_id'",
            "'deliverable_type'",
            "'deliverable_id'",
            "'deliverable_version'",
            "'reviewed_at'",
            "'validated_at'",
            "'adjustment_requested_at'",
            "'adjustment_note'",
            'diagnosis_deliverable_validation_unique',
        ] as $token) {
            $this->assertStringContainsString($token, $migration);
        }

        foreach (['invoice_id', 'payment_id', 'subscription_id', 'price'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $migration);
        }
    }

    public function test_tenant_routes_are_review_validate_and_adjustment_not_acceptance(): void
    {
        $routes = file_get_contents($this->root().'/routes/web.php');

        foreach ([
            'deliverable.review',
            'deliverable.validate',
            'deliverable.adjustment',
            '/entregables/{deliverable}/revisar',
            '/entregables/{deliverable}/validar',
            '/entregables/{deliverable}/solicitar-ajuste',
        ] as $token) {
            $this->assertStringContainsString($token, $routes);
        }

        $this->assertStringNotContainsString(
            'implementation_plan.accept',
            $routes
        );
    }

    public function test_validation_service_never_turns_validation_into_commercial_acceptance(): void
    {
        $source = file_get_contents(
            $this->root().'/app/Services/Diagnosis/DiagnosisDeliverableValidationService.php'
        );

        foreach ([
            'public function markReviewed(',
            'public function validate(',
            'public function requestAdjustment(',
            "'commercial_acceptance' => false",
            'reviewed_at',
            'validated_at',
            'adjustment_requested_at',
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }

        foreach ([
            'acceptPlan(',
            'accepted_at',
            "STATUS_ACCEPTED]",
            'Invoice::',
            'Payment::',
            'Subscription::',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }
    }

    public function test_client_documents_show_review_validation_and_adjustment_ui(): void
    {
        $component = file_get_contents(
            $this->root().'/resources/js/components/diagnosis/DiagnosisDeliverableValidationCard.vue'
        );

        foreach ([
            'Revisión y validación del tenant',
            'Marcar como revisado',
            'Validar documento',
            'Solicitar ajuste',
            'Esta validación no constituye contratación de servicios ni aceptación de una propuesta comercial.',
            'adjustment_note',
        ] as $token) {
            $this->assertStringContainsString($token, $component);
        }

        foreach ([
            'resources/js/pages/Diagnosis/ExpandedReport.vue',
            'resources/js/pages/Diagnosis/DetailedRoadmap.vue',
            'resources/js/pages/Diagnosis/ImplementationPlan.vue',
        ] as $relative) {
            $page = file_get_contents($this->root().'/'.$relative);
            $this->assertStringContainsString(
                'DiagnosisDeliverableValidationCard',
                $page
            );
            $this->assertStringContainsString(
                ':validation="validation"',
                $page
            );
            $this->assertStringContainsString(
                ':endpoints="validation_endpoints"',
                $page
            );
        }
    }

    public function test_validation_controller_is_tenant_only(): void
    {
        $controller = file_get_contents(
            $this->root().'/app/Http/Controllers/Diagnosis/DiagnosisDeliverableValidationController.php'
        );

        foreach ([
            'Gate::authorize(\'view\', $assessment)',
            '$request->user()?->isAdmin()',
            'La validación corresponde al tenant.',
            "'expanded-report' =>",
            "'roadmap' =>",
            "'implementation-plan' =>",
        ] as $token) {
            $this->assertStringContainsString($token, $controller);
        }
    }
}
