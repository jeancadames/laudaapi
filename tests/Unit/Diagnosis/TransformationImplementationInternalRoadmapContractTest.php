<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationInternalRoadmapContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_both_sources_remain_supported_and_visible(): void
    {
        $service = file_get_contents($this->root().'/app/Services/Diagnosis/TransformationImplementationPlanService.php');
        $admin = file_get_contents($this->root().'/resources/js/pages/Admin/DiagnosisRequests/ImplementationPlan.vue');
        $client = file_get_contents($this->root().'/app/Http/Controllers/Diagnosis/TransformationImplementationPlanController.php');
        foreach (['createDraftFromPublishedRoadmap(', 'createDraftFromAssessment(', "'published_roadmap'", "'internal_assessment'"] as $token) {
            $this->assertStringContainsString($token, $service);
        }
        $this->assertStringContainsString('Diagnóstico oficial · snapshot interno', $admin);
        $this->assertStringContainsString('roadmap_url', $client);
    }

    public function test_internal_source_has_no_new_commercial_attachment(): void
    {
        $service = file_get_contents($this->root().'/app/Services/Diagnosis/TransformationImplementationPlanService.php');
        $this->assertStringContainsString("'commercial_context_attached' =>", $service);
        $this->assertStringContainsString("'recommended_modality' =>\n                      null", $service);
    }
}
