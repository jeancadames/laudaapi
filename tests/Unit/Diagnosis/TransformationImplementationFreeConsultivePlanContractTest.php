<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationFreeConsultivePlanContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_active_plan_routes_are_consultive_only(): void
    {
        $admin = file_get_contents($this->root().'/routes/admin.php');
        $web = file_get_contents($this->root().'/routes/web.php');
        foreach (['implementation_plan.show', 'implementation_plan.create', 'implementation_plan.regenerate', 'implementation_plan.present'] as $token) {
            $this->assertStringContainsString($token, $admin);
        }
        foreach (['commercial-scenarios', '/modality', '/estimate', '/milestones', 'implementation_plan.accept'] as $token) {
            $this->assertStringNotContainsString($token, $admin);
        }
        $this->assertStringNotContainsString('implementation_plan.accept', $web);
    }

    public function test_plan_documents_have_no_active_commercial_semantics(): void
    {
        $sources = implode("\n", [
            file_get_contents($this->root().'/app/Http/Controllers/Admin/AdminTransformationImplementationPlanController.php'),
            file_get_contents($this->root().'/app/Http/Controllers/Diagnosis/TransformationImplementationPlanController.php'),
            file_get_contents($this->root().'/resources/js/pages/Admin/DiagnosisRequests/ImplementationPlan.vue'),
            file_get_contents($this->root().'/resources/js/pages/Diagnosis/ImplementationPlan.vue'),
        ]);
        foreach (['commercial_matrix_readiness', 'modality_select', 'commercial_generate', 'price_amount', 'billing_amount', 'accept_url', 'execution_summary', 'go_live_summary', 'solution_access_summary'] as $token) {
            $this->assertStringNotContainsString($token, $sources);
        }
        foreach (['horizon', 'initiatives', 'dependencies', 'deliverables', 'professional_service'] as $token) {
            $this->assertStringContainsString($token, $sources);
        }
    }

    public function test_transformation_journey_has_four_non_optional_free_stages(): void
    {
        $service = file_get_contents($this->root().'/app/Services/Ecosystem/SubscriberTransformation360DashboardService.php');
        $page = file_get_contents($this->root().'/resources/js/pages/App/Transformation360.vue');
        foreach (["'key' => 'diagnosis'", "'key' => 'expanded_report'", "'key' => 'roadmap'", "'key' => 'implementation_plan'"] as $token) {
            $this->assertStringContainsString($token, $service);
        }
        foreach ([
            "'key' => 'diagnosis'",
            "'key' => 'expanded_report'",
            "'key' => 'roadmap'",
            "'key' => 'implementation_plan'",
        ] as $token) {
            $this->assertSame(2, substr_count($service, $token));
        }

        $this->assertSame(8, substr_count($service, "'optional' => false"));
        $this->assertStringNotContainsString("'key' => 'execution'", $service);
        $this->assertStringNotContainsString('xl:grid-cols-5', $page);
        $this->assertStringContainsString('xl:grid-cols-4', $page);
    }

    public function test_control_panel_is_consultive_not_commercial(): void
    {
        $service = file_get_contents($this->root().'/app/Services/Ecosystem/TransformationControlPanelService.php');
        $page = file_get_contents($this->root().'/resources/js/pages/App/Hub.vue');
        foreach (['transformation_implementation_phase_estimates', 'transformation_implementation_milestones', 'selected_modality', "'commercial' =>", "'execution' =>"] as $token) {
            $this->assertStringNotContainsString($token, $service);
        }
        foreach (['Plan consultivo y prioridades de transformación', 'Dependencias', 'Entregables'] as $token) {
            $this->assertStringContainsString($token, $page);
        }
        foreach (['Servicios, ejecución y estado comercial', 'estimated_total', 'milestone_total', 'paid_total', 'billingStatusLabel'] as $token) {
            $this->assertStringNotContainsString($token, $page);
        }
    }
}
