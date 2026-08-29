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
        $headerEnd = strpos($hub, '</header>');

        $this->assertNotFalse($headerEnd);

        foreach ([
            'href="/subscriber/company"',
            'href="/subscriber/subscription"',
        ] as $token) {
            $position = strpos($hub, $token);

            $this->assertNotFalse($position);
            $this->assertLessThan($headerEnd, $position);
            $this->assertSame(1, substr_count($hub, $token));
        }
    }

    public function test_admin_sees_left_my_apps_and_right_app_store_columns(): void
    {
        $hub = $this->hub();

        foreach ([
            'id="my-apps-panel"',
            'id="app-store"',
            ":class=\"isTenantAdmin ? 'lg:grid-cols-2' : 'grid-cols-1'\"",
            'v-for="app in installedApps"',
            'v-for="app in availableApps"',
            'Apps disponibles',
        ] as $token) {
            $this->assertStringContainsString($token, $hub);
        }

        $this->assertLessThan(
            strpos($hub, 'id="app-store"'),
            strpos($hub, 'id="my-apps-panel"')
        );
    }

    public function test_cards_are_compact(): void
    {
        $hub = $this->hub();

        $this->assertStringNotContainsString('min-h-56', $hub);
        $this->assertStringContainsString(
            ':key="`installed-${app.key}`"',
            $hub
        );
        $this->assertStringContainsString(
            ':key="`available-${app.key}`"',
            $hub
        );

        $this->assertGreaterThanOrEqual(
            2,
            substr_count(
                $hub,
                'rounded-2xl border border-slate-100 bg-white p-4'
            )
        );
    }

    public function test_t360_remains_full_width_after_app_columns(): void
    {
        $hub = $this->hub();

        $store = strpos($hub, 'id="app-store"');
        $t360 = strpos($hub, 'Servicios, ejecución y estado comercial');

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

    public function test_admin_summary_remains_after_t360(): void
    {
        $hub = $this->hub();

        $t360 = strpos($hub, 'Servicios, ejecución y estado comercial');
        $summary = strpos($hub, 'Resumen administrativo');

        $this->assertNotFalse($t360);
        $this->assertNotFalse($summary);
        $this->assertLessThan($summary, $t360);
    }

    public function test_b7_and_no_pending_contracts_remain(): void
    {
        $hub = $this->hub();

        foreach ([
            "app.integration !== 'managed'",
            "app.state === 'active_managed'",
            'Gestionada desde LAUDAAPI',
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
