<?php

namespace Tests\Unit\AppHub;

use PHPUnit\Framework\TestCase;

class TenantAppStoreSocialContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function read(string $relative): string
    {
        $source = file_get_contents(
            $this->root().'/'.$relative
        );

        $this->assertIsString($source);

        return $source;
    }

    public function test_social_hub_cta_uses_modern_store_route(): void
    {
        $hub = $this->read(
            'resources/js/pages/App/Hub.vue'
        );

        $this->assertStringContainsString(
            '`/subscriber/apps/${app.service_key}`',
            $hub
        );

        $this->assertStringContainsString(
            "app.service_key === 'social'",
            $hub
        );
    }

    public function test_store_routes_exist_for_social_only_in_b1(): void
    {
        $routes = $this->read(
            'routes/subscriber.php'
        );

        foreach ([
            'SubscriberAppStoreController',
            "Route::prefix('apps')",
            "->whereIn('serviceKey', ['social', 'crm', 'pos'])",
            "->name('checkout')",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $routes
            );
        }
    }

    public function test_store_controller_is_tenant_admin_and_plan_aware(): void
    {
        $controller = $this->read(
            'app/Http/Controllers/Subscriber/SubscriberAppStoreController.php'
        );

        foreach ([
            "'subscriber.admin'",
            "'can_browse_store'",
            'ServicePlan::query()',
            '->previewQuote(',
            'service_plan_id',
            'billing_cycle',
            '->checkout(',
            'activeEntitlement(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller
            );
        }
    }

    public function test_customer_does_not_need_preexisting_activation_request(): void
    {
        $controller = $this->read(
            'app/Http/Controllers/Subscriber/SubscriberAppStoreController.php'
        );

        foreach ([
            'checkout_requires_activation_request',
            'false',
            'app_store_compatibility_bridge',
            'customer_facing_activation_request',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller
            );
        }

        $this->assertStringNotContainsString(
            'No tienes una solicitud de activación válida.',
            $controller
        );

        $this->assertStringNotContainsString(
            'Debes solicitar el servicio antes de continuar.',
            $controller
        );
    }

    public function test_starter_is_visible_but_deferred_until_f5(): void
    {
        $controller = $this->read(
            'app/Http/Controllers/Subscriber/SubscriberAppStoreController.php'
        );

        $page = $this->read(
            'resources/js/pages/App/Store/Show.vue'
        );

        foreach ([
            'Starter gratis · Próximamente',
            'starter_activation_deferred',
            'isFreePlan(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller.$page
            );
        }
    }

    public function test_ui_posts_plan_and_item_cycle_to_checkout(): void
    {
        $page = $this->read(
            'resources/js/pages/App/Store/Show.vue'
        );

        foreach ([
            "type BillingCycle = 'monthly' | 'yearly'",
            'service_plan_id: selectedPlan.value.id',
            'billing_cycle: billingCycle.value',
            'Continuar al pago',
            'Starter gratis · Próximamente',
            '/checkout',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $page
            );
        }
    }

    public function test_legacy_checkout_engine_is_not_replaced(): void
    {
        $checkout = $this->read(
            'app/Services/Billing/StandaloneServiceCheckoutService.php'
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
