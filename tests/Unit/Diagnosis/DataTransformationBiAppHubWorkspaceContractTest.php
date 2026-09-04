<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiAppHubWorkspaceContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_bi_has_authenticated_app_route(): void
    {
        $routes = file_get_contents(
            $this->root().'/routes/web.php'
        );

        foreach ([
            "Route::middleware(['auth', 'verified'])",
            "'/app/transformacion-360/datos-bi'",
            'AppHubDataTransformationBiController::class',
            "'app.transformation.data_bi.show'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $routes
            );
        }
    }

    public function test_controller_is_company_and_tenant_admin_scoped(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/'
            .'AppHubDataTransformationBiController.php'
        );

        foreach ([
            'SubscriberResolver $subscriberResolver',
            'CompanyContextResolver $companyResolver',
            'TenantAccessService $tenantAccessService',
            'TenantAccessService::SUBSCRIBER_ADMIN',
            "'tenant_admin'",
            '$companyResolver->resolve(',
            'SubscriberTransformation360DashboardService',
            '$dashboard->forCompany(',
            "'professional_capabilities.data_transformation_bi'",
            'TransformationProfessionalCapabilityCatalog::get(',
            "'data_transformation_bi'",
            "'implementation_only'",
            "'recommended_in_plan'",
            "'recommendation_status'",
            "'App/DataTransformationBi'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        foreach ([
            'Order::',
            'Invoice::',
            'Payment::',
            'Subscription::',
            'SubscriptionItem::',
            'TransformationCapabilityActivation::',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_sidebar_places_bi_after_branding(): void
    {
        $sidebar = file_get_contents(
            $this->root()
            .'/resources/js/components/'
            .'AppSidebar.vue'
        );

        $branding = strpos(
            $sidebar,
            "lauda360Hrefs.push('/app/branding-identidad')"
        );

        $bi = strpos(
            $sidebar,
            "'/app/transformacion-360/datos-bi'"
        );

        $this->assertNotFalse($branding);
        $this->assertNotFalse($bi);

        $this->assertGreaterThan(
            $branding,
            $bi
        );

        /*
         * Branding sigue condicionado por activación.
         * BI no usa hasActiveCapability.
         */
        $this->assertStringContainsString(
            "hasActiveCapability('branding_identity')",
            $sidebar
        );

        $this->assertStringNotContainsString(
            "hasActiveCapability('data_transformation_bi')",
            $sidebar
        );
    }

    public function test_navigation_contains_bi_after_branding(): void
    {
        $navigation = file_get_contents(
            $this->root()
            .'/resources/js/config/'
            .'navigationByRole.ts'
        );

        foreach ([
            "title: 'Transformación e Inteligencia de Datos para BI'",
            "href: '/app/transformacion-360/datos-bi'",
            "icon: 'Layers'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $navigation
            );
        }

        $branding = strpos(
            $navigation,
            "title: 'Branding e Identidad Digital'"
        );

        $bi = strpos(
            $navigation,
            "title: 'Transformación e Inteligencia de Datos para BI'"
        );

        $this->assertNotFalse($branding);
        $this->assertNotFalse($bi);

        $this->assertGreaterThan(
            $branding,
            $bi
        );
    }

    public function test_transformation_card_links_to_detail_page_generically(): void
    {
        $service = file_get_contents(
            $this->root()
            .'/app/Services/Ecosystem/'
            .'SubscriberTransformation360DashboardService.php'
        );

        foreach ([
            'PROFESSIONAL_CAPABILITY_DETAIL_ROUTES',
            "'data_transformation_bi'",
            "'app.transformation.data_bi.show'",
            "'detail_url'",
            'professionalCapabilityDetailUrl(',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $service
            );
        }

        $page = file_get_contents(
            $this->root()
            .'/resources/js/pages/App/'
            .'Transformation360.vue'
        );

        foreach ([
            'detail_url: string | null',
            'v-if="capability.detail_url"',
            ':href="capability.detail_url"',
            'Ver capacidad',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $page
            );
        }

        $this->assertStringNotContainsString(
            "capability.capability_key === 'data_transformation_bi'",
            $page
        );
    }

    public function test_bi_page_is_informational_and_non_commercial(): void
    {
        $page = file_get_contents(
            $this->root()
            .'/resources/js/pages/App/'
            .'DataTransformationBi.vue'
        );

        foreach ([
            'Transformación e Inteligencia',
            'de Datos para BI',
            'Servicio profesional',
            'Recomendado por tu Diagnóstico 360',
            'No recomendado actualmente',
            'Pendiente de Diagnóstico 360',
            'Datos e Inteligencia',
            'Alcance considerado',
            'Recomendado en tu Plan de Implementación',
            'Se define y cotiza en',
            'Implementación.',
            'Ver Plan de Implementación',
            'Ver Roadmap Detallado',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $page
            );
        }

        foreach ([
            'router.post(',
            'axios.post(',
            'Activar',
            'Contratar',
            'checkout',
            'Subscription',
            'selected_modality',
            'recommended_modality',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $page
            );
        }
    }
}
