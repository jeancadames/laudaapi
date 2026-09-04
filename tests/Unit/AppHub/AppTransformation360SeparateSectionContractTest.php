<?php

namespace Tests\Unit\AppHub;

use PHPUnit\Framework\TestCase;

final class AppTransformation360SeparateSectionContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_transformation_has_own_app_route(): void
    {
        $routes = file_get_contents(
            $this->root().'/routes/web.php'
        );

        $this->assertStringContainsString(
            "'/app/transformacion-360'",
            $routes
        );

        $this->assertStringContainsString(
            "->name('app.transformation.show')",
            $routes
        );

        $this->assertStringContainsString(
            'AppHubTransformationController::class',
            $routes
        );
    }

    public function test_controller_uses_existing_read_model(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/'
            .'AppHubTransformationController.php'
        );

        foreach ([
            'SubscriberTransformation360DashboardService',
            '$transformation360Dashboard->forCompany(',
            "'App/Transformation360'",
            "'transformation360'",
            "['visible']",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_home_is_again_app_launcher_only(): void
    {
        $home = file_get_contents(
            $this->root()
            .'/resources/js/pages/App/Home.vue'
        );

        foreach ([
            'Transformation360Journey',
            'transformation360.stages',
            'Borrador privado de LAUDA',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $home
            );
        }

        $this->assertStringContainsString(
            'Operación diaria',
            $home
        );

        $this->assertStringContainsString(
            'launchableApps',
            $home
        );
    }

    public function test_gateway_no_longer_sends_journey_to_home(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/'
            .'AppGatewayController.php'
        );

        $this->assertStringNotContainsString(
            '$transformationJourney',
            $source
        );

        $this->assertStringNotContainsString(
            'SubscriberTransformation360DashboardService '
            .'$transformation360Dashboard',
            $source
        );

        $this->assertStringContainsString(
            '$transformation360 = '
            .'$transformationControlPanelService'
            .'->forCompany($company);',
            $source
        );

        $this->assertStringContainsString(
            "Inertia::render('App/Hub'",
            $source
        );
    }

    public function test_sidebar_places_transformation_after_diagnosis(): void
    {
        $nav = file_get_contents(
            $this->root()
            .'/resources/js/config/navigationByRole.ts'
        );

        $diagnosis = strpos(
            $nav,
            "/app/diagnostico-360"
        );

        $transformation = strpos(
            $nav,
            "/app/transformacion-360"
        );

        $this->assertNotFalse($diagnosis);
        $this->assertNotFalse($transformation);

        $this->assertGreaterThan(
            $diagnosis,
            $transformation
        );

        $sidebar = file_get_contents(
            $this->root()
            .'/resources/js/components/AppSidebar.vue'
        );

        $this->assertStringContainsString(
            "'/app/diagnostico-360'",
            $sidebar
        );

        $this->assertStringContainsString(
            "'/app/transformacion-360'",
            $sidebar
        );
    }

    public function test_transformation_page_shows_five_stage_journey(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/resources/js/pages/App/'
            .'Transformation360.vue'
        );

        foreach ([
            'Transformación Digital 360',
            'props.transformation360',
            'Etapa {{ index + 1 }}',
            'optionalBranding',
            'Borrador privado de LAUDA',
            'Plan de Implementación',
            'Diagnóstico 360',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_new_page_has_no_commercial_mutations(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/resources/js/pages/App/'
            .'Transformation360.vue'
        );

        $this->assertStringContainsString(
            'router.post(',
            $source
        );

        $this->assertStringContainsString(
            'branding.activation_endpoint',
            $source
        );

        $this->assertStringContainsString(
            'branding.decline_endpoint',
            $source
        );

        foreach ([
            'axios.post(',
            'checkout',
            'confirmPayment',
            'SubscriptionItem',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }
}
