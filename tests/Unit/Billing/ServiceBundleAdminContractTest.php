<?php

namespace Tests\Unit\Billing;

use PHPUnit\Framework\TestCase;

class ServiceBundleAdminContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_bundle_configuration_service_owns_guards_and_sync(): void
    {
        $service = file_get_contents(
            $this->root()
            .'/app/Services/Billing/ServiceBundleConfigurationService.php'
        );

        foreach ([
            'Un bundle no puede incluirse a sí mismo.',
            'El Service está duplicado dentro del bundle.',
            'Una regla activa requiere al menos dos componentes required.',
            'Todos los componentes required deben estar activos.',
            'Los componentes required deben compartir moneda con el Service bundle.',
            'El porcentaje no puede superar 100.',
            'fixed_amount requiere moneda ISO de tres letras.',
            'La moneda fixed_amount debe coincidir con la moneda del Service bundle.',
            'La regla no pertenece a este bundle.',
            'El código de la regla ya existe.',
            'ServiceBundleItem::query()',
            'ServiceBundleDiscountRule::query()',
        ] as $required) {
            $this->assertStringContainsString($required, $service);
        }
    }

    public function test_disabling_bundle_preserves_rule_history(): void
    {
        $service = file_get_contents(
            $this->root()
            .'/app/Services/Billing/ServiceBundleConfigurationService.php'
        );

        $this->assertStringContainsString(
            "->where('active', true)",
            $service
        );

        $this->assertStringContainsString(
            "'active' => false",
            $service
        );

        $this->assertStringNotContainsString(
            'SubscriptionBundleDiscountApplication',
            $service
        );
    }

    public function test_admin_controller_reuses_existing_patch_and_transaction(): void
    {
        $controller = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Admin/AdminServiceController.php'
        );

        foreach ([
            'ServiceBundleConfigurationService',
            "'bundle_candidates' => \$bundleCandidates",
            "'bundle' => ['nullable', 'array']",
            "'bundle.items.*.service_id'",
            "'bundle.rules.*.discount_type'",
            "\$bundle = \$data['bundle'] ?? null",
            'DB::transaction',
            'ServicePricingTierService::class',
            'ServiceBundleConfigurationService::class',
        ] as $required) {
            $this->assertStringContainsString($required, $controller);
        }
    }

    public function test_admin_vue_has_bundle_editor_separate_from_pricing(): void
    {
        $vue = file_get_contents(
            $this->root()
            .'/resources/js/pages/Admin/Services/Index.vue'
        );

        foreach ([
            'type BundleConfig',
            'bundle_candidates: BundleCandidate[]',
            'function addBundleItem(',
            'function addBundleRule(',
            'function bundleSummary(',
            'const bundlePayload = {',
            '{ ...payload, bundle: bundlePayload }',
            'Bundle comercial',
            '+ Componente',
            'Reglas comerciales',
            '+ Regla',
            'No configurado',
            'pricing_tiers',
            'addPricingTier',
        ] as $required) {
            $this->assertStringContainsString($required, $vue);
        }
    }

    public function test_no_new_bundle_routes_are_required(): void
    {
        $routes = file_get_contents(
            $this->root()
            .'/routes/admin.php'
        );

        $this->assertStringContainsString(
            "Route::patch('/{service}'",
            $routes
        );

        $this->assertStringNotContainsString(
            "Route::patch('/bundles/",
            $routes
        );

        $this->assertStringNotContainsString(
            "Route::post('/bundles/",
            $routes
        );
    }
}
