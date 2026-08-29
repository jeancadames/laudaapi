<?php

use PHPUnit\Framework\TestCase;

final class TenantAppStoreCrmContractTest extends TestCase
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

    public function test_routes_allow_social_and_crm(): void
    {
        $routes = $this->read('routes/subscriber.php');

        $this->assertSame(
            2,
            substr_count(
                $routes,
                "->whereIn('serviceKey', ['social', 'crm', 'pos', 'ecf', 'cumplimiento', 'bys'])"
            )
        );
    }

    public function test_controller_allows_crm_and_is_generic(): void
    {
        $controller = $this->read(
            'app/Http/Controllers/Subscriber/'
            .'SubscriberAppStoreController.php'
        );

        foreach ([
            "in_array(\$serviceKey, ['social', 'crm', 'pos', 'ecf', 'cumplimiento', 'bys'], true)",
            '$this->featurePayload($plan)',
            'array_is_list($features)',
            ".' ya está activo para esta empresa. '",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller
            );
        }

        $this->assertStringNotContainsString(
            'Social ya está activo para esta empresa.',
            $controller
        );
    }

    public function test_hub_routes_crm_to_modern_store(): void
    {
        $hub = $this->read(
            'resources/js/pages/App/Hub.vue'
        );

        $this->assertStringContainsString(
            "app.service_key === 'crm'",
            $hub
        );

        $this->assertStringContainsString(
            '`/subscriber/apps/${app.service_key}`',
            $hub
        );
    }

    public function test_ui_uses_service_name_and_hides_unavailable_cycles(): void
    {
        $page = $this->read(
            'resources/js/pages/App/Store/Show.vue'
        );

        foreach ([
            'Seleccione cómo quiere usar {{ service.title }}',
            'const availableCycles = computed<BillingCycle[]>',
            "availableCycles.includes('monthly')",
            "availableCycles.includes('yearly')",
            'selectedPlan.value?.billing_options?.[cycle]?.available',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $page
            );
        }

        $this->assertStringNotContainsString(
            'Seleccione cómo quiere usar Social',
            $page
        );
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
