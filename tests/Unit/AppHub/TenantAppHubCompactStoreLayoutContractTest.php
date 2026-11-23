<?php

namespace Tests\Unit\AppHub;

use PHPUnit\Framework\TestCase;

class TenantAppHubCompactStoreLayoutContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function hub(): string
    {
        return file_get_contents(
            $this->root().'/resources/js/pages/App/Hub.vue'
        );
    }

    public function test_company_and_billing_actions_live_in_header(): void
    {
        $hub = $this->hub();
        $actionsEnd = strpos($hub, '</template>', strpos($hub, '#actions'));

        $this->assertNotFalse($actionsEnd);

        foreach ([
            'href="/subscriber/company"',
            'href="/subscriber/subscription"',
        ] as $token) {
            $position = strpos($hub, $token);

            $this->assertNotFalse($position);
            $this->assertLessThan($actionsEnd, $position);
            $this->assertSame(1, substr_count($hub, $token));
        }
    }

    public function test_my_apps_then_available_apps_as_full_width_grids(): void
    {
        $hub = $this->hub();

        foreach ([
            'id="my-apps-panel"',
            'id="app-store"',
            'v-for="app in installedApps"',
            'v-for="app in availableApps"',
            'Apps disponibles',
        ] as $token) {
            $this->assertStringContainsString($token, $hub);
        }

        // Ambas listas usan la misma cuadrícula: nada de columnas desparejas.
        $this->assertSame(2, substr_count($hub, 'class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"'));
        $this->assertStringNotContainsString("'lg:grid-cols-2'", $hub);

        $this->assertLessThan(
            strpos($hub, 'id="app-store"'),
            strpos($hub, 'id="my-apps-panel"')
        );
    }

    public function test_cards_share_the_portal_tile(): void
    {
        $hub = $this->hub();

        $this->assertStringContainsString("import AppTile from '@/components/portal/AppTile.vue';", $hub);
        $this->assertStringContainsString(
            ':key="`installed-${app.key}`"',
            $hub
        );
        $this->assertStringContainsString(
            ':key="`available-${app.key}`"',
            $hub
        );
    }

    public function test_t360_remains_full_width_after_app_columns(): void
    {
        $hub = $this->hub();

        $store = strpos($hub, 'id="app-store"');
        $t360 = strpos($hub, 'Plan consultivo y prioridades de transformación');

        $this->assertNotFalse($store);
        $this->assertNotFalse($t360);
        $this->assertLessThan($t360, $store);

        foreach ([
            'v-if="hasTransformation360"',
            'v-for="phase in plan.phases"',
            'v-for="capability in phase.capabilities"',
        ] as $token) {
            $this->assertStringContainsString($token, $hub);
        }
    }

    public function test_no_roadmap_or_internal_language_for_clients(): void
    {
        $hub = $this->hub();

        foreach ([
            'Resumen administrativo',
            'En la siguiente etapa',
            'Administrador del tenant',
            'App Store',
            'Control Panel',
        ] as $removed) {
            $this->assertStringNotContainsString($removed, $hub);
        }
    }

    public function test_b7_and_no_pending_contracts_remain(): void
    {
        $hub = $this->hub();

        foreach ([
            "app.integration !== 'managed'",
            "app.state === 'active_managed'",
            'Gestionada por nuestro equipo',
        ] as $token) {
            $this->assertStringContainsString($token, $hub);
        }

        foreach ([
            'const pendingApps = computed',
            '{{ pendingApps.length }}',
            'v-for="app in pendingApps"',
            'Integración en preparación',
        ] as $removedToken) {
            $this->assertStringNotContainsString($removedToken, $hub);
        }
    }

    public function test_existing_modern_store_routes_are_preserved(): void
    {
        $hub = $this->hub();

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
}
