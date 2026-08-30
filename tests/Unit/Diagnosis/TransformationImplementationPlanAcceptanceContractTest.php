<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationPlanAcceptanceContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_presentation_requires_consultive_content_not_commercial_readiness(): void
    {
        $service = file_get_contents($this->root().'/app/Services/Diagnosis/TransformationImplementationPlanService.php');
        $start = strpos($service, 'public function markPresented(');
        $end = strpos($service, 'public function acceptPlan(', $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $method = substr($service, $start, $end - $start);
        $this->assertStringContainsString('$this->consultiveReadiness($locked)', $method);
        foreach (['commercialReadiness', 'selected_modality', 'estimate', 'milestone'] as $token) {
            $this->assertStringNotContainsString($token, $method);
        }
        $this->assertStringContainsString("'commercial_requirements' => false", $service);
    }

    public function test_new_plan_does_not_attach_modality(): void
    {
        $service = file_get_contents($this->root().'/app/Services/Diagnosis/TransformationImplementationPlanService.php');
        preg_match_all("/'recommended_modality' =>\s*null,/", $service, $matches);
        $this->assertGreaterThanOrEqual(2, count($matches[0]));
        $this->assertStringContainsString("'commercial_context_attached' =>", $service);
    }
}
