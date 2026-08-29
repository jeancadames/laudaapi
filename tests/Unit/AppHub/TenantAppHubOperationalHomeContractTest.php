<?php

namespace Tests\Unit\AppHub;

use PHPUnit\Framework\TestCase;

class TenantAppHubOperationalHomeContractTest extends TestCase
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

    public function test_app_is_home_and_control_panel_has_own_route(): void
    {
        $routes = $this->read('routes/web.php');

        foreach ([
            "->get('/app',",
            "->name('app.gateway')",
            "->get('/app/control',",
            "->name('app.control')",
        ] as $token) {
            $this->assertStringContainsString($token, $routes);
        }
    }

    public function test_gateway_renders_home_by_default_and_hub_only_for_control(): void
    {
        $gateway = $this->read(
            'app/Http/Controllers/AppGatewayController.php'
        );

        foreach ([
            "\$request->routeIs('app.control')",
            "Inertia::render('App/Home'",
            "Inertia::render('App/Hub'",
            "redirect()->route('app.gateway')",
        ] as $token) {
            $this->assertStringContainsString($token, $gateway);
        }
    }

    public function test_home_only_launches_entitled_active_solutions(): void
    {
        $home = $this->read('resources/js/pages/App/Home.vue');

        foreach ([
            '!!app.entitled',
            '!!app.launch_url',
            "app.state === 'active'",
            ':href="app.launch_url!"',
            'Solo aparecen las soluciones que tienes asignadas.',
        ] as $token) {
            $this->assertStringContainsString($token, $home);
        }
    }

    public function test_admin_home_has_operational_panel_and_control_panel_link(): void
    {
        $home = $this->read('resources/js/pages/App/Home.vue');

        foreach ([
            'Paneles administrativos',
            'Resumen administrativo:',
            'Estado del ecosistema',
            'href="/app/control"',
            'v-if="isTenantAdmin"',
        ] as $token) {
            $this->assertStringContainsString($token, $home);
        }

        foreach ([
            'App Store',
            'Facturas pendientes',
            'Cuentas por pagar',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $home);
        }
    }

    public function test_home_does_not_create_cross_solution_database_coupling(): void
    {
        $home = $this->read('resources/js/pages/App/Home.vue');
        $gateway = $this->read(
            'app/Http/Controllers/AppGatewayController.php'
        );

        foreach ([
            'DB::connection(',
            'social.laudaapi.com/api',
            'crm.laudaapi.com/api',
            'pos.laudaapi.com/api',
            'ecf.laudaapi.com/api',
            'cumplimiento.laudaapi.com/api',
            'bys.laudaapi.com/api',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $home);
            $this->assertStringNotContainsString($forbidden, $gateway);
        }
    }

    public function test_navigation_places_home_first_and_removes_mis_apps_redundancy(): void
    {
        $navigation = $this->read(
            'resources/js/config/navigationByRole.ts'
        );

        $home = strpos(
            $navigation,
            "{ title: 'Inicio', href: '/app', icon: 'LayoutGrid' }"
        );

        $control = strpos(
            $navigation,
            "{ title: 'Control Panel', href: '/app/control', icon: 'Boxes' }"
        );

        $this->assertNotFalse($home);
        $this->assertNotFalse($control);
        $this->assertLessThan($control, $home);

        $this->assertStringNotContainsString(
            "{ title: 'Mis Apps', href: '/app'",
            $navigation
        );

        $this->assertStringNotContainsString(
            "{ title: 'App Store', href: '/app#app-store'",
            $navigation
        );
    }

    public function test_control_panel_keeps_store_t360_and_managed_contracts(): void
    {
        $hub = $this->read('resources/js/pages/App/Hub.vue');

        foreach ([
            'id="app-store"',
            'Mis Apps',
            'Apps disponibles',
            'Servicios, ejecución y estado comercial',
            "app.integration !== 'managed'",
            "app.state === 'active_managed'",
            "href=\"/subscriber/company\"",
            "href=\"/subscriber/subscription\"",
        ] as $token) {
            $this->assertStringContainsString($token, $hub);
        }
    }
}
