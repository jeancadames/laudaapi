<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DataTransformationBiPresentationGuardContractTest
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

    public function test_admin_plan_exposes_bi_context_metadata(): void
    {
        $source = $this->read(
            'app/Http/Controllers/Admin/'
            .'AdminTransformationImplementationPlanController.php'
        );

        foreach ([
            "'requires_lauda_review' =>",
            "'commercial_readiness' =>",
            "'activation_policy' =>",
            "'recommendation_basis' =>",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_client_plan_exposes_bi_context_metadata(): void
    {
        $source = $this->read(
            'app/Http/Controllers/Diagnosis/'
            .'TransformationImplementationPlanController.php'
        );

        foreach ([
            "'requires_lauda_review' =>",
            "'commercial_readiness' =>",
            "'activation_policy' =>",
            "'recommendation_basis' =>",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_app_hub_control_panel_preserves_bi_metadata(): void
    {
        $source = $this->read(
            'app/Services/Ecosystem/'
            .'TransformationControlPanelService.php'
        );

        foreach ([
            "'requires_lauda_review' =>",
            "'commercial_readiness' =>",
            "'activation_policy' =>",
            "'recommendation_basis' =>",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_execution_serializer_exposes_professional_kind(): void
    {
        $source = $this->read(
            'app/Http/Controllers/Admin/'
            .'AdminTransformationImplementationExecutionController.php'
        );

        foreach ([
            "'kind' => data_get(",
            "'activation_policy' => data_get(",
            "'commercial_readiness' => data_get(",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_execution_ui_detects_professional_capability_by_kind(): void
    {
        $source = $this->read(
            'resources/js/pages/Admin/DiagnosisRequests/'
            .'ImplementationExecution.vue'
        );

        $this->assertStringContainsString(
            "return capability.kind === 'professional_service';",
            $source
        );

        $this->assertStringNotContainsString(
            "return capability.capability_key === 'branding_identity';",
            $source
        );

        $this->assertStringContainsString(
            'servicio profesional dentro del',
            $source
        );
    }

    public function test_professional_capabilities_do_not_show_post_live_subscription_controls(): void
    {
        $source = $this->read(
            'resources/js/pages/Admin/DiagnosisRequests/'
            .'ImplementationExecution.vue'
        );

        $this->assertStringContainsString(
            '!isProfessionalCapability(capability)',
            $source
        );

        $this->assertStringContainsString(
            'Activar/vincular suscripción general',
            $source
        );

        $this->assertStringContainsString(
            'SubscriptionItem',
            $source
        );
    }

    public function test_backend_still_blocks_all_professional_post_go_live_activation(): void
    {
        foreach ([
            'app/Services/Diagnosis/'
            .'TransformationImplementationPostGoLiveServiceActivationService.php',
            'app/Services/Diagnosis/'
            .'TransformationImplementationPostGoLiveSubscriptionService.php',
        ] as $file) {
            $source = $this->read($file);

            $this->assertStringContainsString(
                'TransformationProfessionalCapabilityCatalog::isProfessional(',
                $source
            );

            $this->assertStringContainsString(
                'no genera',
                $source
            );
        }
    }

    public function test_bi_remains_implementation_only(): void
    {
        $source = $this->read(
            'app/Services/Diagnosis/'
            .'TransformationProfessionalCapabilityCatalog.php'
        );

        $this->assertStringContainsString(
            "'data_transformation_bi' => [",
            $source
        );

        $this->assertStringContainsString(
            "'activation_policy' =>",
            $source
        );

        $this->assertStringContainsString(
            "'implementation_only'",
            $source
        );
    }
}
