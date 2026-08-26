<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationPlanAdminConfigurationUiContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_routes_cover_configuration(): void
    {
        $routes = file_get_contents(
            $this->root().'/routes/admin.php'
        );

        foreach ([
            'implementation_plan.phase.store',
            'implementation_plan.modality.select',
            'implementation_plan.estimate.upsert',
            'implementation_plan.milestone.upsert',
            'implementation_plan.present',
            'implementation_plan.accept',
        ] as $token) {
            $this->assertStringContainsString($token, $routes);
        }
    }

    public function test_controller_uses_domain_services(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Admin/AdminTransformationImplementationPlanController.php'
        );

        foreach ([
            'createPhaseFromRoadmap(',
            '->select(',
            'upsertEstimate(',
            'upsertMilestone(',
            'markPresented(',
            'acceptPlan(',
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }
    }

    public function test_dop_and_boundaries_are_preserved(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Admin/AdminTransformationImplementationPlanController.php'
        );

        $this->assertStringContainsString(
            "'currency' => 'DOP'",
            $source
        );

        foreach ([
            'Subscription::create(',
            'SubscriptionItem::create(',
            'activateFromGoLive(',
            'initializePhase(',
            'goLive(',
        ] as $token) {
            $this->assertStringNotContainsString($token, $source);
        }
    }

    public function test_page_has_four_steps(): void
    {
        $page = file_get_contents(
            $this->root()
            .'/resources/js/pages/Admin/DiagnosisRequests/ImplementationPlan.vue'
        );

        foreach ([
            '1. Fases y capabilities',
            '2. Modalidad',
            '3. Fases configuradas',
            '4. Presentación y aceptación',
            'Guardar precio/tiempo en DOP',
            'Presentar Plan',
            'Marcar como aceptado',
        ] as $token) {
            $this->assertStringContainsString($token, $page);
        }
    }
}
