<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class Transformation360AdminSupervisorContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function read(string $path): string
    {
        return file_get_contents(
            $this->root().'/'.$path
        );
    }

    public function test_sidebar_contains_supervisor_entries(): void
    {
        $source =
            $this->read(
                'resources/js/config/navigationByRole.ts'
            )
            .$this->read(
                'resources/js/components/AppSidebar.vue'
            );

        foreach ([
            'Transformación 360',
            '/admin/transformation-360',
            'Datos e Inteligencia BI',
            '/admin/transformation-360/data-bi',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_routes_use_existing_admin_prefix_contract(): void
    {
        $routes =
            $this->read(
                'routes/admin.php'
            );

        $this->assertStringContainsString(
            "->prefix('admin')",
            $routes
        );

        $this->assertStringContainsString(
            "->name('admin.')",
            $routes
        );

        $this->assertStringContainsString(
            "'/transformation-360'",
            $routes
        );

        $this->assertStringContainsString(
            "'transformation360.index'",
            $routes
        );

        $this->assertStringContainsString(
            "'transformation360.data_bi'",
            $routes
        );

        $this->assertStringNotContainsString(
            "'/admin/transformation-360'",
            $routes
        );
    }

    public function test_supervisor_reads_functional_sources(): void
    {
        $controller =
            $this->read(
                'app/Http/Controllers/Admin/'
                .'AdminTransformation360OverviewController.php'
            );

        foreach ([
            'diagnosis_access_requests',
            'transformation_implementation_plans',
            'transformation_implementation_definitions',
            'transformation_implementation_phases',
            'transformation_implementation_phase_capabilities',
            'data_transformation_bi',
            'TransformationProfessionalCapabilityCatalog',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $controller
            );
        }
    }

    public function test_new_layer_has_no_commercial_or_execution_actions(): void
    {
        $source =
            $this->read(
                'app/Http/Controllers/Admin/'
                .'AdminTransformation360OverviewController.php'
            )
            .$this->read(
                'resources/js/pages/Admin/'
                .'Transformation360/Index.vue'
            )
            .$this->read(
                'resources/js/pages/Admin/'
                .'Transformation360/DataBi.vue'
            );

        foreach ([
            'commercial_settings',
            'CommercialRate',
            'PricingService',
            'implementation_execution',
            'execution_url',
            'Subscription::',
            'Invoice::',
            'Payment::',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }
    }
}
