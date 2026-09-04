<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DataTransformationBiConsultivePresentationContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function read(string $path): string
    {
        return file_get_contents(
            $this->root().'/'.$path
        );
    }

    public function test_admin_plan_presents_professional_context(): void
    {
        $source = $this->read(
            'resources/js/pages/Admin/DiagnosisRequests/'
            .'ImplementationPlan.vue'
        );

        foreach ([
            'requires_lauda_review: boolean;',
            'commercial_readiness: string | null;',
            'activation_policy: string | null;',
            'recommendation_basis: string | null;',
            'Servicio profesional',
            'Fundamento de recomendación',
            'Alcance incluido',
            'Se define y cotiza en Implementación',
            'Esta capacidad no se activa desde el ciclo consultivo gratuito.',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_tenant_plan_explains_bi_without_commercial_activation(): void
    {
        $source = $this->read(
            'resources/js/pages/Diagnosis/'
            .'ImplementationPlan.vue'
        );

        foreach ([
            'activation_policy: string | null;',
            'recommendation_basis: string | null;',
            'Servicio profesional',
            '¿Por qué se recomienda?',
            'Alcance incluido',
            'Se define y cotiza en Implementación',
            'Esta recomendación no activa ningún servicio ni genera una compra.',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        foreach ([
            'money(',
            'billing_amount',
            'price_amount',
            'selected_modality_label',
            'SubscriptionItem',
            'Activar servicio',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_app_hub_shows_professional_context_generically(): void
    {
        $source = $this->read(
            'resources/js/pages/App/Hub.vue'
        );

        foreach ([
            'activation_policy: string | null;',
            'recommendation_basis: string | null;',
            'Servicio profesional',
            'Se define y cotiza en Implementación',
            'capability.recommendation_basis',
            'capability.includes.slice(0, 4)',
            'elementos adicionales en el Plan',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        $this->assertStringNotContainsString(
            "capability.key === 'data_transformation_bi'",
            $source
        );

        $this->assertStringNotContainsString(
            "capability.key === 'branding_identity'",
            $source
        );
    }

    public function test_generic_fallback_basis_is_not_rendered_to_users(): void
    {
        foreach ([
            'resources/js/pages/Admin/DiagnosisRequests/'
                .'ImplementationPlan.vue',
            'resources/js/pages/Diagnosis/'
                .'ImplementationPlan.vue',
            'resources/js/pages/App/Hub.vue',
        ] as $file) {
            $source = $this->read($file);

            $this->assertStringContainsString(
                "capability.recommendation_basis !== 'professional_transformation_capability'",
                $source
            );
        }
    }

    public function test_consultive_boundary_remains_intact(): void
    {
        $client = $this->read(
            'resources/js/pages/Diagnosis/'
            .'ImplementationPlan.vue'
        );

        $hub = $this->read(
            'resources/js/pages/App/Hub.vue'
        );

        $this->assertStringContainsString(
            'no contiene precios ni hitos de facturación',
            $client
        );

        $this->assertStringContainsString(
            'La contratación comercial se gestiona fuera del Plan.',
            $hub
        );

        foreach ([
            'Servicios, ejecución y estado comercial',
            'Estimado de la fase',
            'Estado comercial',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $hub
            );
        }
    }
}
