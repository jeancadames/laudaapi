<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationCommercialScenarioGenerationContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_admin_route_exposes_commercial_generation(): void
    {
        $routes =
            file_get_contents(
                $this->root()
                .'/routes/admin.php'
            );

        $this->assertStringContainsString(
            '/implementation-plan/commercial-scenarios',
            $routes
        );

        $this->assertStringContainsString(
            'diagnosis_requests.implementation_plan.commercial.generate',
            $routes
        );

        $this->assertStringContainsString(
            "'generateCommercialScenarios'",
            $routes
        );
    }

    public function test_controller_generates_through_commercial_engine(): void
    {
        $source =
            file_get_contents(
                $this->root()
                .'/app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationPlanController.php'
            );

        $this->assertStringContainsString(
            'public function generateCommercialScenarios(',
            $source
        );

        $this->assertStringContainsString(
            'TransformationImplementationCommercialEngine $commercialEngine',
            $source
        );

        $this->assertStringContainsString(
            '$commercialEngine->generate(',
            $source
        );

        $this->assertStringContainsString(
            'TransformationImplementationCommercialMatrixService $commercialMatrixService',
            $source
        );

        $this->assertStringContainsString(
            "STATUS_DRAFT",
            $source
        );
    }

    public function test_generation_action_does_not_select_modality_or_create_milestones(): void
    {
        $source =
            file_get_contents(
                $this->root()
                .'/app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationPlanController.php'
            );

        $start =
            strpos(
                $source,
                'public function generateCommercialScenarios('
            );

        $end =
            strpos(
                $source,
                'public function selectModality(',
                $start
            );

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $method =
            substr(
                $source,
                $start,
                $end - $start
            );

        foreach (
            [
                'modalityService->select',
                'upsertMilestone',
                'Invoice::',
                'Payment::',
                'Subscription::',
                'SubscriptionItem::',
                'selected_modality =',
            ] as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $method
            );
        }
    }

    public function test_ui_can_generate_and_recalculate_three_scenarios(): void
    {
        $source =
            file_get_contents(
                $this->root()
                .'/resources/js/pages/Admin/DiagnosisRequests/'
                .'ImplementationPlan.vue'
            );

        foreach (
            [
                'commercial_generate',
                'commercial_matrix_readiness',
                'generateCommercialScenarios',
                'Generar escenarios comerciales',
                'Recalcular escenarios comerciales',
                'commercialScenariosComplete',
                'Duración estimada total',
                'totalDurationDays',
            ] as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_ui_still_requires_milestones_before_presentation(): void
    {
        $source =
            file_get_contents(
                $this->root()
                .'/resources/js/pages/Admin/DiagnosisRequests/'
                .'ImplementationPlan.vue'
            );

        $this->assertStringContainsString(
            'selectedMilestones',
            $source
        );

        $this->assertStringContainsString(
            'commercialReady',
            $source
        );

        $this->assertStringContainsString(
            ':disabled="!commercialReady"',
            $source
        );
    }
}
