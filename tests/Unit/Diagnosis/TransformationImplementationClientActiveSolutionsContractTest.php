<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationClientActiveSolutionsContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_free_plan_has_no_active_solution_activation_payload(): void
    {
        $controller = file_get_contents($this->root().'/app/Http/Controllers/Diagnosis/TransformationImplementationPlanController.php');
        $page = file_get_contents($this->root().'/resources/js/pages/Diagnosis/ImplementationPlan.vue');
        foreach (['recurring_solution', 'service_activation', 'subscription_item_id', 'entitlement_allowed', 'portal_url'] as $token) {
            $this->assertStringNotContainsString($token, $controller);
            $this->assertStringNotContainsString($token, $page);
        }
    }

    public function test_plan_only_exposes_professional_capabilities(): void
    {
        $controller = file_get_contents($this->root().'/app/Http/Controllers/Diagnosis/TransformationImplementationPlanController.php');
        foreach (["\$kind !== 'subscription_service'", '! $subscriptionCandidate', "'kind' => 'professional_service'"] as $token) {
            $this->assertStringContainsString($token, $controller);
        }
    }
}
