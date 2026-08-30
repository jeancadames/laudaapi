<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationPostGoLiveGatewayContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_free_plan_has_no_post_go_live_gateway_cta(): void
    {
        $controller = file_get_contents($this->root().'/app/Http/Controllers/Diagnosis/TransformationImplementationPlanController.php');
        $page = file_get_contents($this->root().'/resources/js/pages/Diagnosis/ImplementationPlan.vue');
        foreach (['portal_url', 'solution_access_summary', 'Ir a mi portal', 'entitlement_allowed'] as $token) {
            $this->assertStringNotContainsString($token, $controller.$page);
        }
    }

    public function test_app_gateway_remains_independent_from_plan(): void
    {
        $routes = file_get_contents($this->root().'/routes/web.php');
        $this->assertStringContainsString("->get('/app'", $routes);
        $this->assertStringContainsString("->name('app.gateway')", $routes);
    }
}
