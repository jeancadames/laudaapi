<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class BrandingIdentityDynamicSidebarContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_global_lauda360_context_is_company_scoped_and_excludes_cancelled(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Middleware/HandleInertiaRequests.php'
        );

        foreach ([
            "'lauda360' => fn() => \$this->lauda360Payload(",
            'CompanyContextResolver::class',
            'TransformationCapabilityActivation::query()',
            "'company_id'",
            "'active_capabilities'",
            'TransformationCapabilityActivation::STATUS_CANCELLED',
            "\$request->is('app*')",
            "\$routeAssessment instanceof DiagnosisAssessment",
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }
    }

    public function test_sidebar_only_inserts_branding_when_capability_is_active(): void
    {
        $sidebar = file_get_contents(
            $this->root()
            .'/resources/js/components/AppSidebar.vue'
        );

        foreach ([
            'active_capabilities',
            "hasActiveCapability('branding_identity')",
            "lauda360Hrefs.push('/app/branding-identidad')",
            "'/app/diagnostico-360'",
            "'/app/transformacion-360'",
        ] as $token) {
            $this->assertStringContainsString($token, $sidebar);
        }

        $navigation = file_get_contents(
            $this->root()
            .'/resources/js/config/navigationByRole.ts'
        );

        foreach ([
            "title: 'Branding e Identidad Digital'",
            "href: '/app/branding-identidad'",
            "icon: 'Palette'",
        ] as $token) {
            $this->assertStringContainsString($token, $navigation);
        }

        $icons = file_get_contents(
            $this->root()
            .'/resources/js/utils/mapToNavItems.ts'
        );

        $this->assertStringContainsString('Palette,', $icons);
    }

    public function test_branding_shell_route_is_authenticated_and_verified(): void
    {
        $routes = file_get_contents(
            $this->root().'/routes/web.php'
        );

        foreach ([
            "Route::middleware(['auth', 'verified'])",
            "'/app/branding-identidad'",
            'AppHubBrandingController::class',
            "'app.branding.show'",
        ] as $token) {
            $this->assertStringContainsString($token, $routes);
        }
    }

    public function test_branding_shell_requires_tenant_admin_company_and_live_activation(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/AppHubBrandingController.php'
        );

        foreach ([
            "=== 'subscriber'",
            'SubscriberResolver $subscriberResolver',
            'CompanyContextResolver $companyResolver',
            'TenantAccessService $tenantAccessService',
            'TenantAccessService::SUBSCRIBER_ADMIN',
            "'tenant_admin'",
            "\$companyResolver->resolve(",
            "->where('company_id', \$company->id)",
            "->where('capability_key', 'branding_identity')",
            'TransformationCapabilityActivation::STATUS_CANCELLED',
            'abort_unless($activation, 404)',
            "'App/BrandingIdentity'",
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }

        foreach ([
            'Order::',
            'Invoice::',
            'Payment::',
            'Subscription::',
            'SubscriptionItem::',
            'TransformationImplementationSubscriptionService',
            'modality',
        ] as $token) {
            $this->assertStringNotContainsString($token, $source);
        }
    }

    public function test_branding_workspace_preserves_free_non_commercial_boundary(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/resources/js/pages/App/BrandingIdentity.vue'
        );

        foreach ([
            'Branding e Identidad Digital',
            'Workspace operacional',
            'Activación gratuita',
            'Alcance activado',
            'Estado y progreso',
            'Próximo paso',
            'no constituye compra',
            'pago, suscripción, modalidad comercial',
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }
    }
}
