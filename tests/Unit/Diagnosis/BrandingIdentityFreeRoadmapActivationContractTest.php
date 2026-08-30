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

    public function test_branding_activation_and_decline_routes_are_tenant_authenticated(): void
    {
        $source = file_get_contents(
            $this->root().'/routes/web.php'
        );

        foreach ([
            "'/{assessment}/capacidades/branding-identidad/activar'",
            'BrandingIdentityActivationController::class',
            "'store'",
            "capabilities.branding_identity.activate",
            "'/{assessment}/capacidades/branding-identidad/ahora-no'",
            'BrandingIdentityDecisionController::class',
            "'decline'",
            "capabilities.branding_identity.decline",
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }
    }

    public function test_controller_resolves_real_tenant_company_without_requiring_assessment_organization_id(): void
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
            '$companyResolver->resolve(',
            "'branding_identity'",
            'activateFromRoadmap(',
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }

        $this->assertStringNotContainsString(
            '$assessment->organization_id',
            $source
        );

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
            $this->assertStringNotContainsString($token, $source);
        }
    }

    public function test_roadmap_exposes_recommendation_decision_and_optional_activation(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Diagnosis/'
            .'DiagnosisDetailedRoadmapController.php'
        );

        foreach ([
            'TransformationCapabilityActivation::query()',
            'TransformationCapabilityDecision::query()',
            "'company_id'",
            "'capability_key'",
            "'branding_identity'",
            "'branding_activation'",
            "'recommended'",
            "'decision'",
            "'available'",
            "'activated'",
            "'endpoint'",
            "'decline_endpoint'",
            "'diagnosis.capabilities.branding_identity.activate'",
            "'diagnosis.capabilities.branding_identity.decline'",
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }

        $this->assertStringContainsString(
            '$available = $brandingActivation === null;',
            $source
        );
    }

    public function test_tenant_ui_allows_activation_even_when_not_recommended_and_decline_when_recommended(): void
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
            "brandingActivation?.decision !== 'declined'",
            'brandingActivation?.decline_endpoint',
            'Activación gratuita disponible',
            'Recomendado por tu Diagnóstico 360',
            'Activar gratis',
            'Ahora no',
            'puedes activar Branding cuando decidas',
            'router.post(',
            'no genera compra, pago ni contratación',
        ] as $token) {
            $this->assertStringContainsString($token, $component);
        }

        $activateStart = strpos(
            $component,
            'function activateBranding(): void'
        );
        $declineStart = strpos(
            $component,
            'function declineBranding(): void'
        );

        $this->assertNotFalse($activateStart);
        $this->assertNotFalse($declineStart);

        $activateFunction = substr(
            $component,
            $activateStart,
            $declineStart - $activateStart
        );

        $this->assertStringNotContainsString(
            'activation.recommended',
            $activateFunction
        );

        $page = file_get_contents(
            $this->root()
            .'/resources/js/pages/Diagnosis/'
            .'DetailedRoadmap.vue'
        );

        foreach ([
            'branding_activation:',
            ':branding-activation="branding_activation"',
        ] as $token) {
            $this->assertStringContainsString($token, $page);
        }
    }

    public function test_service_preserves_recommendation_as_context_not_gate(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'TransformationCapabilityActivationService.php'
        );

        foreach ([
            'activateFromRoadmap(',
            'activateManually(',
            "SOURCE_MANUAL",
            "!== 'professional_service'",
            "'subscription_candidate'",
            "'activation_origin'",
            "'roadmap' => \$roadmapDefinition ?? []",
            "'free_activation_contract'",
            "'commercial_acceptance' => false",
            "'requires_modality' => false",
            "'requires_payment' => false",
            "'creates_subscription' => false",
            "'creates_subscription_item' => false",
            "'creates_go_live' => false",
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }

        $this->assertStringNotContainsString(
            'La capacidad debe estar recomendada en el Roadmap publicado antes de activarse.',
            $source
        );
    }
}
