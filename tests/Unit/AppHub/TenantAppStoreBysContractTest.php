<?php

use PHPUnit\Framework\TestCase;

final class TenantAppStoreBysContractTest extends TestCase
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

    public function test_routes_allow_bys(): void
    {
        $routes = $this->read('routes/subscriber.php');

        $needle = "->whereIn('serviceKey', "
            ."['social', 'crm', 'pos', 'ecf', "
            ."'cumplimiento', 'bys'])";

        $this->assertSame(
            2,
            substr_count($routes, $needle)
        );
    }

    public function test_ecosystem_bys_identity_is_independent(): void
    {
        $config = $this->read(
            'config/ecosystem_hub.php'
        );

        foreach ([
            "'bys' => [",
            "'service_key' => 'bys'",
            "'target_url' => 'https://bys.laudaapi.com'",
            "'target_launch_path' => '/launch'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $config
            );
        }
    }

    public function test_controller_allows_bys(): void
    {
        $controller = $this->read(
            'app/Http/Controllers/Subscriber/'
            .'SubscriberAppStoreController.php'
        );

        $this->assertStringContainsString(
            "in_array(\$serviceKey, "
            ."['social', 'crm', 'pos', 'ecf', "
            ."'cumplimiento', 'bys'], true)",
            $controller
        );

        foreach ([
            'ServicePlan::query()',
            '->previewQuote(',
            '->checkout(',
            'featurePayload(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller
            );
        }
    }

    public function test_hub_routes_bys_to_modern_store(): void
    {
        $hub = $this->read(
            'resources/js/pages/App/Hub.vue'
        );

        $this->assertStringContainsString(
            "app.service_key === 'bys'",
            $hub
        );

        $this->assertStringContainsString(
            '`/subscriber/apps/${app.service_key}`',
            $hub
        );
    }

    public function test_ui_has_bys_limit_labels(): void
    {
        $page = $this->read(
            'resources/js/pages/App/Store/Show.vue'
        );

        foreach ([
            "users: 'Usuarios'",
            "api_requests: 'API requests / mes'",
            "companies: 'Empresas / tenants'",
            "purchases: 'Compras / mes'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $page
            );
        }
    }

    public function test_checkout_engine_remains_plan_aware(): void
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
