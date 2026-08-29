<?php

namespace Tests\Unit\AppHub;

use PHPUnit\Framework\TestCase;

final class AppHomeTransformation360JourneyContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_app_gateway_sends_t360_journey_to_app_home(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/AppGatewayController.php'
        );

        foreach ([
            'SubscriberTransformation360DashboardService',
            '$transformationJourney',
            '$transformation360Dashboard->forCompany(',
            "Inertia::render('App/Home'",
            "'transformation360'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_app_home_shows_full_five_stage_journey(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/resources/js/pages/App/Home.vue'
        );

        foreach ([
            'Transformación Digital 360',
            'transformation360.stages',
            'Etapa {{ index + 1 }}',
            'stage.optional',
            'Borrador privado de LAUDA',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_app_home_explains_optional_deliverables(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/resources/js/pages/App/Home.vue'
        );

        $normalized = preg_replace(
            '/\s+/u',
            ' ',
            $source
        );

        $this->assertIsString($normalized);

        $this->assertStringContainsString(
            'El Informe Ampliado y el Roadmap Detallado son opcionales.',
            $normalized
        );

        $this->assertStringContainsString(
            'El Plan de Implementación puede prepararse directamente desde el resultado oficial del Diagnóstico 360.',
            $normalized
        );
    }

    public function test_existing_control_panel_payload_remains_intact(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/AppGatewayController.php'
        );

        $this->assertStringContainsString(
            '$transformationControlPanelService->forCompany(',
            $source
        );

        $this->assertStringContainsString(
            "Inertia::render('App/Hub'",
            $source
        );

        $this->assertStringContainsString(
            "'transformation360' => \$transformation360",
            $source
        );
    }

    public function test_app_home_does_not_implement_commercial_mutations(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/resources/js/pages/App/Home.vue'
        );

        foreach ([
            'router.post(',
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
