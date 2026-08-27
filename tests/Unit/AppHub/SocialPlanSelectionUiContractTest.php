<?php

namespace Tests\Unit\AppHub;

use PHPUnit\Framework\TestCase;

class SocialPlanSelectionUiContractTest extends TestCase
{
    private function read(string $path): string
    {
        $source = file_get_contents(
            dirname(__DIR__, 3).'/'.$path
        );

        $this->assertIsString($source);

        return $source;
    }

    public function test_backend_exposes_commercial_plans_and_server_side_quotes(): void
    {
        $source = $this->read(
            'app/Http/Controllers/Subscriber/SubscriberMyServicesController.php'
        );

        foreach ([
            "'commercial_plans' =>",
            'private function commercialPlans(',
            'private function billingOptionsForPlan(',
            'ServicePlan::query()',
            ')->previewQuote(',
            "'is_featured'",
            "'is_free'",
            "'activation_available'",
            "'source_solution'",
            "'source_plan_key'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_free_plan_is_explicitly_visible_but_not_activatable(): void
    {
        $backend = $this->read(
            'app/Http/Controllers/Subscriber/SubscriberMyServicesController.php'
        );

        $ui = $this->read(
            'resources/js/pages/Subscriber/Services/My.vue'
        );

        foreach ([
            'upgrade/downgrade',
            "'activation_available' =>",
            '! $isFree',
            "'available' => false",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $backend
            );
        }

        foreach ([
            'Starter gratis · Próximamente',
            'selectedPlanIsFree(r)',
            '|| selectedPlanIsFree(r)',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $ui
            );
        }
    }

    public function test_ui_posts_plan_and_item_level_cycle(): void
    {
        $source = $this->read(
            'resources/js/pages/Subscriber/Services/My.vue'
        );

        foreach ([
            'type CommercialPlan = {',
            'planByService',
            'selectedPlanId(r)',
            'setSelectedPlan(',
            'Ciclo independiente para esta solución',
            'service_plan_id: servicePlanId',
            'billing_cycle: billingCycle',
            "mode: 'billed'",
            'Continuar al pago',
            'Solicitar activación',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        $this->assertStringNotContainsString(
            'La suscripción actual fija el ciclo',
            $source
        );
    }

    public function test_activation_requires_plan_when_catalog_exists(): void
    {
        $source = $this->read(
            'app/Http/Controllers/Subscriber/SubscriberServiceActivationController.php'
        );

        foreach ([
            'S10-F4 plan selection guard',
            '$hasCommercialPlans',
            '$mode === \'billed\'',
            '&& ! $servicePlan',
            'Selecciona un plan comercial',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_existing_payment_and_entitlement_chain_remains_intact(): void
    {
        $checkout = $this->read(
            'app/Services/Billing/StandaloneServiceCheckoutService.php'
        );

        $central = $this->read(
            'app/Services/Entitlements/CentralEntitlementActivationService.php'
        );

        foreach ([
            '?ServicePlan $servicePlan = null',
            '->quotePlan(',
            "'subscription_cycle_locked' => false",
            "'service_plan_id'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $checkout
            );
        }

        foreach ([
            'public function ensureSubscriptionForItem(',
            "'service_plan_id' =>",
            "'billing_cycle' =>",
            "'entitlement_claims'",
            'public function revokeCommercialItem(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $central
            );
        }
    }
}
