<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationRequestAdminQueueContractTest
    extends TestCase
{
    private function project(
        string $path
    ): string {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/'
            .$path
        );
    }

    public function test_admin_queue_routes_exist(): void
    {
        $routes =
            $this->project(
                'routes/admin.php'
            );

        $this->assertStringContainsString(
            '/transformation-360/implementation-requests',
            $routes
        );

        $this->assertStringContainsString(
            'transformation360.implementation_requests.index',
            $routes
        );

        $this->assertStringContainsString(
            'transformation360.implementation_requests.show',
            $routes
        );
    }

    public function test_controller_is_admin_only(): void
    {
        $source =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        $this->assertStringContainsString(
            "=== 'admin'",
            $source
        );

        $this->assertStringContainsString(
            'authorizeAdmin(',
            $source
        );
    }

    public function test_queue_reads_request_and_event_history(): void
    {
        $source =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        foreach ([
            'transformation_implementation_requests',
            'transformation_implementation_request_events',
            'companies',
            'diagnosis_assessments',
            'transformation_implementation_plans',
            'transformation_implementation_phase_capabilities',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_queue_index_remains_read_only_and_detail_uses_controlled_actions(): void
    {
        $index =
            $this->project(
                'resources/js/pages/Admin/Transformation360/'
                .'ImplementationRequests/Index.vue'
            );

        $show =
            $this->project(
                'resources/js/pages/Admin/Transformation360/'
                .'ImplementationRequests/Show.vue'
            );

        foreach ([
            'router.post(',
            'router.patch(',
            'router.put(',
            'router.delete(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $index
            );
        }

        $this->assertStringContainsString(
            'router.patch(',
            $show
        );

        $this->assertStringContainsString(
            'router.post(',
            $show
        );
    }

    public function test_queue_has_no_definition_activation_or_commercial_side_effects(): void
    {
        $source =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        foreach ([
            'TransformationCapabilityActivationService',
            'TransformationImplementationDefinitionService',
            'TransformationImplementationExecutionService',
            'TransformationImplementationCommercialEngine',
            'TransformationImplementationSubscriptionService',
            'CentralEntitlementActivationService',
            'Invoice::',
            'Payment::',
            'Subscription::',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_navigation_exposes_admin_request_queue(): void
    {
        $navigation =
            $this->project(
                'resources/js/config/navigationByRole.ts'
            );

        $sidebar =
            $this->project(
                'resources/js/components/AppSidebar.vue'
            );

        foreach ([
            'Solicitudes de Implementación',
            '/admin/transformation-360/implementation-requests',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $navigation
            );
        }

        $this->assertStringContainsString(
            '/admin/transformation-360/implementation-requests',
            $sidebar
        );
    }

    public function test_detail_exposes_functional_context(): void
    {
        $show =
            $this->project(
                'resources/js/pages/Admin/Transformation360/'
                .'ImplementationRequests/Show.vue'
            );

        foreach ([
            'Contexto de la solicitud',
            'Diagnóstico',
            'Plan',
            'Fase',
            'Responsable LAUDA',
            'Historial',
            'Gestión de solicitud',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $show
            );
        }
    }
}
