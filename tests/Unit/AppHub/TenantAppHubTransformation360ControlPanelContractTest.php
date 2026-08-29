<?php

namespace Tests\Unit\AppHub;

use PHPUnit\Framework\TestCase;

class TenantAppHubTransformation360ControlPanelContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_gateway_exposes_transformation_360_snapshot(): void
    {
        $controller = file_get_contents(
            $this->root().'/app/Http/Controllers/AppGatewayController.php'
        );

        $this->assertStringContainsString(
            'TransformationControlPanelService $transformationControlPanelService',
            $controller
        );

        $this->assertStringContainsString(
            '$transformation360 = $transformationControlPanelService->forCompany($company);',
            $controller
        );

        $this->assertStringContainsString(
            "'transformation360' => \$transformation360",
            $controller
        );
    }

    public function test_snapshot_is_company_scoped_and_uses_real_t360_records(): void
    {
        $service = file_get_contents(
            $this->root().'/app/Services/Ecosystem/TransformationControlPanelService.php'
        );

        foreach ([
            "'company_id', \$company->id",
            'diagnosis_detailed_roadmap_orders',
            'diagnosis_expanded_report_orders',
            'transformation_implementation_plans',
            'transformation_implementation_phases',
            'transformation_implementation_phase_capabilities',
            'transformation_implementation_phase_estimates',
            'transformation_implementation_milestones',
            'transformation_implementation_phase_executions',
            'transformation_implementation_capability_executions',
            'transformation_implementation_capability_go_lives',
            'transformation_implementation_subscription_item_activations',
        ] as $token) {
            $this->assertStringContainsString($token, $service);
        }
    }

    public function test_catalog_is_not_exposed_and_draft_cancelled_plans_are_hidden(): void
    {
        $service = file_get_contents(
            $this->root().'/app/Services/Ecosystem/TransformationControlPanelService.php'
        );

        $this->assertStringContainsString(
            "->whereNotIn('status', ['draft', 'cancelled'])",
            $service
        );

        $this->assertStringNotContainsString(
            'TransformationServiceCapabilityCatalog',
            $service
        );
    }

    public function test_commercial_amounts_are_phase_scoped_not_duplicated_per_capability(): void
    {
        $service = file_get_contents(
            $this->root().'/app/Services/Ecosystem/TransformationControlPanelService.php'
        );

        $this->assertStringContainsString(
            "'estimate_amount' =>",
            $service
        );

        $this->assertStringContainsString(
            "'milestone_total' =>",
            $service
        );

        $this->assertStringContainsString(
            "'capabilities' => \$capabilities->all()",
            $service
        );

        $this->assertStringNotContainsString(
            "'price_amount' => \$capability",
            $service
        );
    }

    public function test_hub_renders_t360_as_separate_control_section_not_app_store(): void
    {
        $hub = file_get_contents(
            $this->root().'/resources/js/pages/App/Hub.vue'
        );

        foreach ([
            'type Transformation360ControlPanel =',
            'const hasTransformation360 = computed',
            'Transformación 360',
            'Servicios, ejecución y estado comercial',
            'v-for="phase in plan.phases"',
            'v-for="capability in phase.capabilities"',
            '{{ capability.label }}',
            'Estimado de la fase',
            'Estado comercial',
        ] as $token) {
            $this->assertStringContainsString($token, $hub);
        }

        $this->assertStringNotContainsString(
            "capability.key === 'branding_identity'",
            $hub
        );
    }

    public function test_b7_managed_visibility_contract_remains_intact(): void
    {
        $hub = file_get_contents(
            $this->root().'/resources/js/pages/App/Hub.vue'
        );

        $this->assertStringContainsString(
            "app.integration !== 'managed'",
            $hub
        );

        $this->assertStringContainsString(
            "app.state === 'active_managed'",
            $hub
        );
    }
}
