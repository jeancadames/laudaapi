<?php

use PHPUnit\Framework\TestCase;

final class TenantAppStoreEcfContractTest extends TestCase
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

    public function test_routes_allow_ecf(): void
    {
        $routes = $this->read('routes/subscriber.php');

        $this->assertSame(
            2,
            substr_count(
                $routes,
                "->whereIn('serviceKey', "
                ."['social', 'crm', 'pos', 'ecf', 'cumplimiento', 'bys'])"
            )
        );
    }

    public function test_ecosystem_uses_independent_ecf_identity(): void
    {
        $config = $this->read(
            'config/ecosystem_hub.php'
        );

        $this->assertStringContainsString(
            "'ecf' => [",
            $config
        );

        $this->assertStringContainsString(
            "'service_key' => 'ecf'",
            $config
        );

        $this->assertStringContainsString(
            "'target_url' => 'https://ecf.laudaapi.com'",
            $config
        );

        $this->assertStringContainsString(
            "'target_launch_path' => '/launch'",
            $config
        );
    }

    public function test_controller_allows_ecf(): void
    {
        $controller = $this->read(
            'app/Http/Controllers/Subscriber/'
            .'SubscriberAppStoreController.php'
        );

        $this->assertStringContainsString(
            "in_array(\$serviceKey, "
            ."['social', 'crm', 'pos', 'ecf', 'cumplimiento', 'bys'], true)",
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

    public function test_hub_routes_ecf_to_modern_store(): void
    {
        $hub = $this->read(
            'resources/js/pages/App/Hub.vue'
        );

        $this->assertStringContainsString(
            "app.service_key === 'ecf'",
            $hub
        );

        $this->assertStringContainsString(
            '`/subscriber/apps/${app.service_key}`',
            $hub
        );
    }

    public function test_ui_supports_ecf_limits(): void
    {
        $page = $this->read(
            'resources/js/pages/App/Store/Show.vue'
        );

        foreach ([
            "ecfs: 'e-CF / mes'",
            "webhooks: 'Webhooks'",
            "users: 'Usuarios'",
            "availableCycles.includes('monthly')",
            "availableCycles.includes('yearly')",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $page
            );
        }
    }

    public function test_checkout_engine_remains_shared(): void
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
