<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiTenantImplementationRequestHttpContractTest
    extends TestCase
{
    private function routes(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3).'/routes/web.php'
        );
    }

    private function controller(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Http/Controllers/AppHubDataTransformationBiRequestController.php'
        );
    }

    private function page(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/resources/js/pages/App/DataTransformationBi.vue'
        );
    }

    public function test_request_route_has_no_tenant_controlled_ids(): void
    {
        $routes = $this->routes();

        $this->assertStringContainsString(
            '/app/transformacion-360/datos-bi/solicitar-implementacion',
            $routes
        );

        $this->assertStringContainsString(
            "app.transformation.data_bi.request",
            $routes
        );

        $this->assertStringNotContainsString(
            '/app/transformacion-360/datos-bi/{company}',
            $routes
        );

        $this->assertStringNotContainsString(
            '/app/transformacion-360/datos-bi/{plan}',
            $routes
        );
    }

    public function test_controller_resolves_tenant_context_server_side(): void
    {
        $source = $this->controller();

        foreach ([
            'SubscriberResolver',
            'CompanyContextResolver',
            'TenantAccessService',
            'SubscriberTransformation360DashboardService',
            'TransformationImplementationPlan::STATUS_PRESENTED',
            "'data_transformation_bi'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        $this->assertStringNotContainsString(
            "\$request->input('company_id')",
            $source
        );

        $this->assertStringNotContainsString(
            "\$request->input('plan_id')",
            $source
        );

        $this->assertStringNotContainsString(
            "\$request->input('assessment_id')",
            $source
        );

        $this->assertStringNotContainsString(
            "\$request->input('phase_capability_id')",
            $source
        );
    }

    public function test_only_tenant_admin_can_submit(): void
    {
        $source = $this->controller();

        $this->assertStringContainsString(
            "=== TenantAccessService::SUBSCRIBER_ADMIN",
            $source
        );

        $this->assertStringContainsString(
            "\$tenantAccess['tenant_admin']",
            $source
        );
    }

    public function test_post_calls_only_implementation_request_service(): void
    {
        $source = $this->controller();

        $this->assertStringContainsString(
            'requestFromTenantAdmin(',
            $source
        );

        foreach ([
            'TransformationCapabilityActivationService',
            'TransformationImplementationDefinitionService',
            'TransformationImplementationExecutionService',
            'TransformationImplementationCommercialEngine',
            'TransformationImplementationSubscriptionService',
            'CentralEntitlementActivationService',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_page_exposes_request_cta_and_progress(): void
    {
        $source = $this->page();

        foreach ([
            'Solicitar implementación',
            'Solicitud recibida',
            'Revisión LAUDA',
            'Definición',
            'Revisión de tu empresa',
            'Definición acordada',
            'implementation_request.request_endpoint',
            'router.post(',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_ui_preserves_non_commercial_boundary(): void
    {
        $source = $this->page();

        $this->assertStringContainsString(
            'La solicitud no activa el servicio ni genera',
            $source
        );

        $this->assertStringContainsString(
            'no inicia ejecución',
            $source
        );

        foreach ([
            'Activar BI',
            'Comprar ahora',
            'Pagar ahora',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }
}
