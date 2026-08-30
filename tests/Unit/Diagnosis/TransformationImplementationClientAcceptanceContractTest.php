<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationClientAcceptanceContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_free_plan_has_no_active_acceptance_route_or_action(): void
    {
        $web = file_get_contents($this->root().'/routes/web.php');
        $controller = file_get_contents($this->root().'/app/Http/Controllers/Diagnosis/TransformationImplementationPlanController.php');
        $page = file_get_contents($this->root().'/resources/js/pages/Diagnosis/ImplementationPlan.vue');

        foreach (['implementation_plan.accept', 'plan-implementacion/aceptar'] as $token) {
            $this->assertStringNotContainsString($token, $web);
        }
        foreach (['function accept(', "'accept_url' =>", 'acceptPlan('] as $token) {
            $this->assertStringNotContainsString($token, $controller);
        }
        foreach (['accept_url', 'acceptPlan()', 'aceptas este Plan', 'Aceptar Plan'] as $token) {
            $this->assertStringNotContainsString($token, $page);
        }
        $this->assertStringContainsString('La validación del documento confirmará su revisión', $page);
    }

    public function test_historical_acceptance_service_is_preserved_but_quarantined(): void
    {
        $service = file_get_contents($this->root().'/app/Services/Diagnosis/TransformationImplementationPlanService.php');
        $this->assertStringContainsString('public function acceptPlan(', $service);
        $this->assertStringContainsString('private function commercialReadiness(', $service);
    }
}
