<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\TransformationProfessionalCapabilityCatalog;
use App\Services\Diagnosis\TransformationServiceCapabilityCatalog;
use PHPUnit\Framework\TestCase;

final class BrandingIdentityProfessionalServiceContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_branding_is_professional_service(): void
    {
        $branding =
            TransformationProfessionalCapabilityCatalog::get(
                'branding_identity'
            );

        $this->assertNotNull($branding);

        $this->assertSame(
            'Branding e Identidad Digital',
            $branding['title']
        );

        $this->assertSame(
            'professional_service',
            $branding['kind']
        );

        $this->assertNull(
            $branding['service_key']
        );

        $this->assertFalse(
            $branding['subscription_candidate']
        );

        $this->assertFalse(
            $branding['commercial_model']['recurring']
        );

        $this->assertFalse(
            $branding['execution']['creates_subscription']
        );

        $this->assertFalse(
            $branding['execution']['creates_subscription_item']
        );
    }

    public function test_branding_remains_outside_recurring_service_catalog(): void
    {
        $this->assertNotContains(
            'branding_identity',
            TransformationServiceCapabilityCatalog::keys()
        );
    }

    public function test_branding_scope_is_defined(): void
    {
        $branding =
            TransformationProfessionalCapabilityCatalog::get(
                'branding_identity'
            );

        $this->assertContains(
            'Diagnóstico de marca y consistencia.',
            $branding['includes']
        );

        $this->assertContains(
            'Brand Kit Digital.',
            $branding['includes']
        );

        $this->assertContains(
            'Aplicación a web, ecommerce, redes y documentos.',
            $branding['includes']
        );
    }

    public function test_admin_plan_merges_professional_catalog(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Admin/'
            .'AdminTransformationImplementationPlanController.php'
        );

        foreach ([
            'TransformationProfessionalCapabilityCatalog::all()',
            "'kind' =>",
            "'subscription_candidate' =>",
            "'capability_summary' =>",
            "'source_snapshot' => [",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_plan_ui_identifies_professional_service(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/resources/js/pages/Admin/DiagnosisRequests/'
            .'ImplementationPlan.vue'
        );

        foreach ([
            'professional_service',
            'Apoyo profesional sugerido',
            'Las soluciones LAUDAAPI se contratan y activan fuera de este Plan',
            'no cotiza la ejecución',
            'no crea hitos de facturación',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_post_go_live_services_reject_professional_capabilities(): void
    {
        foreach ([
            'TransformationImplementationPostGoLiveSubscriptionService.php',
            'TransformationImplementationPostGoLiveServiceActivationService.php',
        ] as $file) {
            $source = file_get_contents(
                $this->root()
                .'/app/Services/Diagnosis/'
                .$file
            );

            foreach ([
                'TransformationProfessionalCapabilityCatalog::isProfessional(',
                'ValidationException::withMessages',
                'servicio profesional',
                'Subscription ni SubscriptionItem.',
            ] as $token) {
                $this->assertStringContainsString(
                    $token,
                    $source
                );
            }
        }
    }

    public function test_execution_ui_hides_commercial_activation_for_branding(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/resources/js/pages/Admin/DiagnosisRequests/'
            .'ImplementationExecution.vue'
        );

        $normalized = preg_replace(
            '/\\s+/u',
            ' ',
            $source
        );

        $this->assertIsString($normalized);

        foreach ([
            'function isProfessionalCapability(',
            "capability.capability_key === 'branding_identity'",
            '!isProfessionalCapability(capability)',
            'Branding e Identidad Digital',
            'Subscription ni SubscriptionItem',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $normalized
            );
        }
    }
}
