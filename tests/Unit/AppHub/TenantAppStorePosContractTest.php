<?php

use PHPUnit\Framework\TestCase;

final class TenantAppStorePosContractTest extends TestCase
{
    private function read(string $relative): string
    {
        $root = dirname(__DIR__, 3);
        $path = $root.'/'.$relative;

        self::assertFileExists($path);

        $contents = file_get_contents($path);

        self::assertIsString($contents);

        return $contents;
    }

    public function test_routes_allow_pos(): void
    {
        $routes = $this->read('routes/subscriber.php');

        $this->assertSame(
            2,
            substr_count(
                $routes,
                "->whereIn('serviceKey', ['social', 'crm', 'pos', 'ecf', 'cumplimiento'])"
            )
        );
    }

    public function test_controller_allows_pos(): void
    {
        $controller = $this->read(
            'app/Http/Controllers/Subscriber/'
            .'SubscriberAppStoreController.php'
        );

        foreach ([
            "in_array(\$serviceKey, ['social', 'crm', 'pos', 'ecf', 'cumplimiento'], true)",
            "\$plan->name.' gratis · Próximamente'",
            'ServicePlan::query()',
            '->previewQuote(',
            '->checkout(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller
            );
        }
    }

    public function test_hub_routes_pos_to_modern_store(): void
    {
        $hub = $this->read(
            'resources/js/pages/App/Hub.vue'
        );

        $this->assertStringContainsString(
            "app.service_key === 'pos'",
            $hub
        );

        $this->assertStringContainsString(
            '`/subscriber/apps/${app.service_key}`',
            $hub
        );
    }

    public function test_ui_supports_pos_capacity_and_free_plan(): void
    {
        $page = $this->read(
            'resources/js/pages/App/Store/Show.vue'
        );

        foreach ([
            "branches: 'Sucursales'",
            "products: 'Productos'",
            "warehouses: 'Almacenes'",
            "leads: 'Leads'",
            'availableCycles.length > 0',
            "availableCycles.includes('monthly')",
            "availableCycles.includes('yearly')",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $page
            );
        }
    }

    public function test_checkout_engine_is_still_shared(): void
    {
        $checkout = $this->read(
            'app/Services/Billing/'
            .'StandaloneServiceCheckoutService.php'
        );

        foreach ([
            'public function previewQuote(',
            'public function checkout(',
            '?ServicePlan $servicePlan = null',
            "'service_plan_id'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $checkout
            );
        }
    }
}
