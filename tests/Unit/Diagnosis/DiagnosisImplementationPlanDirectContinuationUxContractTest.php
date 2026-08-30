<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DiagnosisImplementationPlanDirectContinuationUxContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_admin_plan_is_available_after_official_diagnosis(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/resources/js/components/diagnosis/'
            .'TransformationQuickActions.vue'
        );

        foreach ([
            'Crear Plan de Implementación',
            'diagnosisPublished && adminPlanUrl',
            'Gestionar Plan de Implementación',
            'DIAGNOSIS360_DIRECT_IMPLEMENTATION_PLAN',
            'DIAGNOSIS360_IMPLEMENTATION_PLAN_BLOCKED',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_reports_are_presented_as_free_consultive_deliverables(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/resources/js/components/diagnosis/'
            .'TransformationQuickActions.vue'
        );

        $normalized = preg_replace(
            '/\\s+/u',
            ' ',
            $source
        );

        $this->assertIsString(
            $normalized
        );

        foreach ([
            'El Informe Ampliado y el Roadmap Detallado forman parte del flujo gratuito del Diagnóstico 360.',
            'Se preparan como entregables consultivos',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $normalized
            );
        }

        $this->assertStringNotContainsString(
            'son opcionales para iniciar esta fase',
            $normalized
        );
    }

    public function test_backend_supports_both_plan_sources(): void
    {
        $service = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'TransformationImplementationPlanService.php'
        );

        $controller = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Admin/'
            .'AdminTransformationImplementationPlanController.php'
        );

        foreach ([
            'createDraftFromPublishedRoadmap(',
            'createDraftFromAssessment(',
            "'published_roadmap'",
            "'internal_assessment'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $service
            );
        }

        $this->assertStringContainsString(
            'createDraftFromPublishedRoadmap(',
            $controller
        );

        $this->assertStringContainsString(
            'createDraftFromAssessment(',
            $controller
        );
    }

    public function test_client_still_only_sees_presented_plan(): void
    {
        $controller = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Diagnosis/'
            .'DigitalDiagnosisController.php'
        );

        foreach ([
            'TransformationImplementationPlan::STATUS_PRESENTED',
            'TransformationImplementationPlan::STATUS_ACCEPTED',
            'TransformationImplementationPlan::STATUS_ACTIVE',
            'TransformationImplementationPlan::STATUS_COMPLETED',
            "->whereNotNull('presented_at')",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $controller
            );
        }
    }

    public function test_this_continuation_does_not_activate_subscription(): void
    {
        $sources = implode("\n", [
            file_get_contents(
                $this->root()
                .'/app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationPlanController.php'
            ),
            file_get_contents(
                $this->root()
                .'/resources/js/components/diagnosis/'
                .'TransformationQuickActions.vue'
            ),
        ]);

        foreach ([
            'Subscription::create(',
            'SubscriptionItem::create(',
            'activateFromGoLive(',
            'activateServiceForGoLive(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $sources
            );
        }
    }
}
