<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class BrandingIdentityFreeRoadmapActivationContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_branding_activation_route_is_tenant_authenticated(): void
    {
        $source = file_get_contents(
            $this->root().'/routes/web.php'
        );

        foreach ([
            "'/{assessment}/capacidades/branding-identidad/activar'",
            'BrandingIdentityActivationController::class',
            "'store'",
            "capabilities.branding_identity.activate",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_controller_resolves_real_tenant_company_and_admin_access(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Diagnosis/'
            .'BrandingIdentityActivationController.php'
        );

        foreach ([
            "Gate::authorize('view', \$assessment)",
            'SubscriberResolver $subscriberResolver',
            'CompanyContextResolver $companyResolver',
            'TenantAccessService $tenantAccessService',
            'TenantAccessService::SUBSCRIBER_ADMIN',
            "'tenant_admin'",
            '$assessment->organization_id',
            '$companyResolver->resolve(',
            "'branding_identity'",
            'activateFromRoadmap(',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        foreach ([
            'TransformationImplementationSubscriptionService',
            'TransformationImplementationCapabilitySubscriptionService',
            'TransformationImplementationSubscriptionActivation',
            'TransformationImplementationSubscriptionItemActivation',
            'Order::',
            'Invoice::',
            'Payment::',
            'Subscription::',
            'SubscriptionItem::',
            'modality',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }
    }

    public function test_roadmap_exposes_activation_state_and_endpoint(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Diagnosis/'
            .'DiagnosisDetailedRoadmapController.php'
        );

        foreach ([
            'TransformationCapabilityActivation::query()',
            "'capability_key'",
            "'branding_identity'",
            "'branding_activation'",
            "'recommended'",
            "'available'",
            "'activated'",
            "'status'",
            "'activated_at'",
            "'endpoint'",
            "'diagnosis.capabilities.branding_identity.activate'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_tenant_ui_offers_free_activation_only_when_recommended(): void
    {
        $component = file_get_contents(
            $this->root()
            .'/resources/js/components/diagnosis/'
            .'DetailedRoadmapTransformationCapabilities.vue'
        );

        foreach ([
            'brandingActivation?.recommended',
            'brandingActivation?.available',
            'brandingActivation?.activated',
            'Activación gratuita disponible',
            'Activar gratis',
            'Branding e Identidad Digital activado',
            'router.post(',
            'no genera compra, pago ni contratación',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $component
            );
        }

        $page = file_get_contents(
            $this->root()
            .'/resources/js/pages/Diagnosis/'
            .'DetailedRoadmap.vue'
        );

        foreach ([
            'branding_activation:',
            ':branding-activation="branding_activation"',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $page
            );
        }
    }

    public function test_s12a_service_still_requires_recommended_professional_capability(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'TransformationCapabilityActivationService.php'
        );

        foreach ([
            "!== 'professional_service'",
            "'subscription_candidate'",
            "'recommended'",
            "'free_activation_contract'",
            "'commercial_acceptance' =>",
            "'requires_modality' =>",
            "'requires_payment' =>",
            "'creates_subscription' =>",
            "'creates_subscription_item' =>",
            "'creates_go_live' =>",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }
}
