<?php

namespace Tests\Unit\AppHub;

use PHPUnit\Framework\TestCase;

class AppGatewayHubContractTest extends TestCase
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

    public function test_gateway_renders_generic_hub(): void
    {
        $source = $this->read(
            'app/Http/Controllers/AppGatewayController.php'
        );

        foreach ([
            'EcosystemHubService',
            "'App/Hub'",
            "'groups' =>",
            "'company' =>",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        $this->assertStringNotContainsString(
            "'erp.dashboard'",
            $source
        );
    }

    public function test_launch_url_requires_entitlement_and_ready_integration(): void
    {
        $source = $this->read(
            'app/Services/Ecosystem/EcosystemHubService.php'
        );

        foreach ([
            'ServiceAccessResolver',
            'userCanAccess(',
            '$entitled',
            '$ready',
            "'erp.services.open'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        foreach ([
            'Subscription::create',
            'SubscriptionItem::create',
            'Service::create',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_ui_exposes_installed_and_available_app_sections_without_pending_catalog(): void
    {
        $page = $this->read(
            'resources/js/pages/App/Hub.vue'
        );

        foreach ([
            'const installedApps = computed',
            'const availableApps = computed',
            'app.launch_url',
            'Apps disponibles',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $page
            );
        }

        foreach ([
            'const pendingApps = computed',
            '{ pendingApps.length }',
            'v-for="app in pendingApps"',
            'Integración en preparación',
        ] as $removedToken) {
            $this->assertStringNotContainsString(
                $removedToken,
                $page
            );
        }
    }

    public function test_add_to_package_opens_manage_for_an_active_package_and_keeps_the_app(): void
    {
        $controller = $this->read('app/Http/Controllers/AppGatewayController.php');
        $hub = $this->read('resources/js/pages/App/Hub.vue');

        // Con paquete activado, «Administrar mi paquete»; sin paquete, el configurador.
        $this->assertStringContainsString("'business_package' => [", $controller);
        $this->assertStringContainsString("route('business.package.manage', \$activated, false)", $controller);
        $this->assertStringContainsString("route('business.package.build', absolute: false)", $controller);

        // El botón lleva la aplicación elegida y nunca un enlace fijo al configurador.
        $this->assertStringContainsString(':href="addToPackageHref(app)"', $hub);
        $this->assertStringContainsString('props.business_package?.manage_url ??', $hub);
        $this->assertStringContainsString('?agregar=${encodeURIComponent(String(app.service_key))}', $hub);
        $this->assertStringNotContainsString('href="/app/business/self-service"', $hub);

        // Ambas pantallas leen la aplicación pedida.
        $this->assertStringContainsString("searchParams.getAll('agregar')", $this->read('resources/js/pages/Business/Package/Build.vue'));
        $this->assertStringContainsString("'agregar',", $this->read('resources/js/pages/Business/Package/Manage.vue'));
    }
}
