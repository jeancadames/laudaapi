<?php

namespace Tests\Unit\AppHub;

use PHPUnit\Framework\TestCase;

class TenantAppHubManagedServiceVisibilityContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_managed_services_are_not_offered_as_available_apps(): void
    {
        $hub = file_get_contents(
            $this->root().'/resources/js/pages/App/Hub.vue'
        );

        $this->assertStringContainsString(
            "app.integration !== 'managed'",
            $hub
        );

        $this->assertStringContainsString(
            "&& !app.entitled",
            $hub
        );

        $this->assertStringContainsString(
            "&& app.state === 'available'",
            $hub
        );
    }

    public function test_managed_entitlements_can_still_render_in_my_apps(): void
    {
        $hub = file_get_contents(
            $this->root().'/resources/js/pages/App/Hub.vue'
        );

        $this->assertStringContainsString(
            "app.state === 'active_managed'",
            $hub
        );

        $this->assertStringContainsString(
            "Gestionada desde LAUDAAPI",
            $hub
        );
    }

    public function test_digital_presence_remains_managed_and_non_launchable(): void
    {
        $config = require $this->root().'/config/ecosystem_hub.php';

        $presence = null;

        foreach (($config['groups'] ?? []) as $group) {
            foreach (($group['solutions'] ?? []) as $solution) {
                if (
                    ($solution['service_key'] ?? null)
                    === 'digital_presence'
                ) {
                    $presence = $solution;
                    break 2;
                }
            }
        }

        $this->assertIsArray($presence);
        $this->assertSame('managed', $presence['integration']);
        $this->assertFalse($presence['launchable']);
        $this->assertNull($presence['target_url']);
    }

    public function test_existing_modern_app_store_routes_remain_present(): void
    {
        $hub = file_get_contents(
            $this->root().'/resources/js/pages/App/Hub.vue'
        );

        foreach ([
            'social',
            'crm',
            'pos',
            'ecf',
            'cumplimiento',
            'bys',
        ] as $key) {
            $this->assertStringContainsString(
                "app.service_key === '{$key}'",
                $hub
            );
        }
    }

    public function test_sidebar_navigation_uses_home_and_control_panel_without_redundant_store(): void
    {
        $navigation = file_get_contents(
            $this->root().'/resources/js/config/navigationByRole.ts'
        );

        $this->assertStringContainsString(
            "{ title: 'Inicio', href: '/app', icon: 'LayoutGrid' }",
            $navigation
        );

        $this->assertStringContainsString(
            "{ title: 'Control Panel', href: '/app/control', icon: 'Boxes' }",
            $navigation
        );

        $this->assertStringNotContainsString(
            "{ title: 'App Store', href: '/app#app-store'",
            $navigation
        );

        $this->assertStringNotContainsString(
            "{ title: 'Mis Apps', href: '/app'",
            $navigation
        );

        $this->assertMatchesRegularExpression(
            "/title:\s*'Mi suscripción',[\s\S]*?"
            ."href:\s*'\/subscriber\/subscription',[\s\S]*?"
            ."icon:\s*'CreditCard'/",
            $navigation
        );
    }

    public function test_pending_catalog_is_not_rendered_in_hub(): void
    {
        $hub = file_get_contents(
            $this->root().'/resources/js/pages/App/Hub.vue'
        );

        foreach ([
            'const pendingApps = computed',
            '{{ pendingApps.length }}',
            'v-for="app in pendingApps"',
            'Integración en preparación',
        ] as $removedToken) {
            $this->assertStringNotContainsString(
                $removedToken,
                $hub
            );
        }
    }

}
