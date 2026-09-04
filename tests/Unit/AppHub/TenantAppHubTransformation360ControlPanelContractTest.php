<?php

namespace Tests\Unit\AppHub;

use PHPUnit\Framework\TestCase;

class TenantAppHubTransformation360ControlPanelContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_gateway_exposes_transformation_360_snapshot(): void
    {
        $controller = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/AppGatewayController.php'
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

    public function test_snapshot_is_company_scoped_and_uses_real_consultive_t360_records(): void
    {
        $service = file_get_contents(
            $this->root()
            .'/app/Services/Ecosystem/'
            .'TransformationControlPanelService.php'
        );

        foreach ([
            'diagnosis_access_requests',
            'diagnosis_detailed_roadmap_orders',
            'diagnosis_expanded_report_orders',
            'transformation_implementation_plans',
            'transformation_implementation_phases',
            'transformation_implementation_phase_capabilities',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $service
            );
        }

        foreach ([
            'transformation_implementation_phase_estimates',
            'transformation_implementation_milestones',
            'transformation_implementation_phase_executions',
            'transformation_implementation_capability_executions',
            'transformation_implementation_capability_go_lives',
            'transformation_implementation_subscription_item_activations',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $service
            );
        }
    }

    public function test_catalog_is_not_exposed_and_private_plans_are_hidden(): void
    {
        $service = file_get_contents(
            $this->root()
            .'/app/Services/Ecosystem/'
            .'TransformationControlPanelService.php'
        );

        $this->assertStringContainsString(
            "->whereNotIn('status', ['draft', 'cancelled'])",
            $service
        );

        $this->assertStringContainsString(
            "->whereNotNull('presented_at')",
            $service
        );

        $this->assertStringNotContainsString(
            'TransformationServiceCapabilityCatalog',
            $service
        );

        $this->assertStringNotContainsString(
            'TransformationProfessionalCapabilityCatalog',
            $service
        );
    }

    public function test_capabilities_are_consultive_and_preserve_professional_context(): void
    {
        $service = file_get_contents(
            $this->root()
            .'/app/Services/Ecosystem/'
            .'TransformationControlPanelService.php'
        );

        foreach ([
            "'capabilities' => \$capabilities->all()",
            "'kind' => 'professional_service'",
            "'includes' =>",
            "'requires_lauda_review' =>",
            "'commercial_readiness' =>",
            "'activation_policy' =>",
            "'recommendation_basis' =>",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $service
            );
        }

        foreach ([
            "'estimate_amount' =>",
            "'milestone_total' =>",
            "'paid_total' =>",
            "'billing_status' =>",
            "'subscription_item_id' =>",
            "'commercial' =>",
            "'execution' =>",
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $service
            );
        }
    }

    public function test_hub_renders_t360_as_separate_consultive_control_section(): void
    {
        $hub = file_get_contents(
            $this->root()
            .'/resources/js/pages/App/Hub.vue'
        );

        foreach ([
            'type Transformation360ControlPanel =',
            'const hasTransformation360 = computed',
            'Transformación 360',
            'Plan consultivo y prioridades de transformación',
            'v-for="phase in plan.phases"',
            'v-for="capability in phase.capabilities"',
            '{{ capability.label }}',
            'Dependencias',
            'Entregables',
            'La contratación comercial se gestiona fuera del Plan.',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $hub
            );
        }

        foreach ([
            'Servicios, ejecución y estado comercial',
            'Estimado de la fase',
            'Estado comercial',
            'estimated_total',
            'milestone_total',
            'paid_total',
            'billingStatusLabel',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $hub
            );
        }

        $this->assertStringNotContainsString(
            "capability.key === 'branding_identity'",
            $hub
        );

        $this->assertStringNotContainsString(
            "capability.key === 'data_transformation_bi'",
            $hub
        );
    }

    public function test_b7_managed_visibility_contract_remains_intact(): void
    {
        $hub = file_get_contents(
            $this->root()
            .'/resources/js/pages/App/Hub.vue'
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
