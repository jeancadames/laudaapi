<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class BrandingIdentityWorkspaceContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_workspace_read_model_uses_only_free_activation_snapshot(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Ecosystem/'
            .'BrandingIdentityWorkspaceService.php'
        );

        foreach ([
            'TransformationCapabilityActivation $activation',
            "'branding_identity'",
            "\$activation->source_snapshot",
            "'catalog'",
            "'roadmap'",
            "'free_activation_contract'",
            "'scope'",
            "'recommendation'",
            "'progress'",
            "'next_action'",
            "'app.branding.start'",
            "'commercial_acceptance' =>",
            "'requires_modality' =>",
            "'requires_payment' =>",
            "'creates_subscription' =>",
            "'creates_subscription_item' =>",
            "'creates_go_live' =>",
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }

        foreach ([
            'TransformationImplementationExecutionService',
            'TransformationImplementationCapabilityExecution',
            'TransformationImplementationCapabilityGoLive',
            'TransformationImplementationSubscriptionService',
            'Order::',
            'Invoice::',
            'Payment::',
            'Subscription::',
            'SubscriptionItem::',
        ] as $token) {
            $this->assertStringNotContainsString($token, $source);
        }
    }

    public function test_workspace_has_real_status_tracker_without_fake_percentage(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Ecosystem/'
            .'BrandingIdentityWorkspaceService.php'
        );

        foreach ([
            'STATUS_ACTIVATED',
            'STATUS_IN_PROGRESS',
            'STATUS_READY_FOR_REVIEW',
            'STATUS_VALIDATED',
            'STATUS_COMPLETED',
            "'Activado'",
            "'En progreso'",
            "'Listo para revisión'",
            "'Validado'",
            "'Completado'",
            "'activated_at'",
            "'started_at'",
            "'ready_for_review_at'",
            "'validated_at'",
            "'completed_at'",
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }

        $this->assertStringNotContainsString(
            'progress_percentage',
            $source
        );
    }

    public function test_tenant_can_only_start_branding_in_s12d(): void
    {
        $routes = file_get_contents(
            $this->root().'/routes/web.php'
        );

        foreach ([
            "'/app/branding-identidad/iniciar'",
            'AppHubBrandingLifecycleController::class',
            "'start'",
            "'app.branding.start'",
        ] as $token) {
            $this->assertStringContainsString($token, $routes);
        }

        foreach ([
            "'app.branding.ready'",
            "'app.branding.validate'",
            "'app.branding.complete'",
        ] as $token) {
            $this->assertStringNotContainsString($token, $routes);
        }

        $controller = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/'
            .'AppHubBrandingLifecycleController.php'
        );

        foreach ([
            "=== 'subscriber'",
            'TenantAccessService::SUBSCRIBER_ADMIN',
            "'tenant_admin'",
            "\$companyResolver->resolve(",
            "'company_id',",
            "\$company->id",
            "'capability_key',",
            "'branding_identity'",
            'TransformationCapabilityActivation::STATUS_CANCELLED',
            '$activations->start(',
            "'success'",
        ] as $token) {
            $this->assertStringContainsString($token, $controller);
        }

        foreach ([
            'markReadyForReview(',
            '->validate(',
            '->complete(',
            'TransformationImplementationExecutionService',
            'Order::',
            'Invoice::',
            'Payment::',
            'Subscription::',
        ] as $token) {
            $this->assertStringNotContainsString($token, $controller);
        }
    }

    public function test_tenant_page_exposes_workspace_sections_and_start_action(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/resources/js/pages/App/BrandingIdentity.vue'
        );

        foreach ([
            'Workspace operacional',
            'Resumen',
            'Estado y progreso',
            'Alcance activado',
            'Origen',
            'Recomendación del Roadmap',
            'Próximo paso',
            'Revisión de LAUDA requerida',
            'startBranding',
            'router.post(',
            "next_action?.key === 'start'",
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }

        foreach ([
            'positioning_refinement',
            'visual_identity_update',
            'brand_kit',
            'social_normalization',
            'commercial_documents',
            'web_application',
            'progress_percentage',
            'precio',
            'DOP ',
        ] as $token) {
            $this->assertStringNotContainsString($token, $source);
        }
    }

    public function test_apphub_controller_uses_workspace_service_but_preserves_s12c_security(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/AppHubBrandingController.php'
        );

        foreach ([
            'BrandingIdentityWorkspaceService $workspace',
            'TransformationCapabilityActivation::query()',
            "->where('company_id', \$company->id)",
            "->where('capability_key', 'branding_identity')",
            'TransformationCapabilityActivation::STATUS_CANCELLED',
            '$workspace->forActivation($activation)',
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }
    }
}
