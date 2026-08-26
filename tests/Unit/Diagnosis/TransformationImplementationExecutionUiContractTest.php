<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationExecutionUiContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_routes_cover_execution_and_go_live(): void
    {
        $routes = file_get_contents(
            $this->root().'/routes/admin.php'
        );

        foreach ([
            'implementation_execution.show',
            'implementation_execution.phase.initialize',
            'implementation_execution.capability.start',
            'implementation_execution.capability.progress',
            'implementation_execution.capability.complete',
            'implementation_execution.go_live.create',
            'implementation_execution.go_live.ready',
            'implementation_execution.go_live.live',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $routes
            );
        }
    }

    public function test_controller_uses_r2g_and_r2h_services(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Admin/AdminTransformationImplementationExecutionController.php'
        );

        foreach ([
            'initializePhase(',
            'startCapability(',
            'updateCapabilityProgress(',
            'completeCapability(',
            'createAttempt(',
            'markReady(',
            '->goLive(',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_execution_requires_accepted_plan(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Admin/AdminTransformationImplementationExecutionController.php'
        );

        $this->assertStringContainsString(
            'STATUS_ACCEPTED',
            $source
        );

        $this->assertStringContainsString(
            '$plan->accepted_at !== null',
            $source
        );

        $this->assertStringContainsString(
            'El Plan debe estar aceptado antes de iniciar ejecución.',
            $source
        );
    }

    public function test_readiness_requires_three_explicit_confirmations(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Admin/AdminTransformationImplementationExecutionController.php'
        );

        foreach ([
            'technical_readiness',
            'operational_readiness',
            'client_readiness',
            "'accepted'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_go_live_ui_does_not_activate_subscription(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Admin/AdminTransformationImplementationExecutionController.php'
        );

        foreach ([
            'TransformationImplementationSubscriptionService',
            'TransformationImplementationCapabilitySubscriptionService',
            'activateFromGoLive(',
            'Subscriber::create(',
            'Company::create(',
            'Subscription::create(',
            'SubscriptionItem::create(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_plan_links_to_operational_screen_after_acceptance(): void
    {
        $page = file_get_contents(
            $this->root()
            .'/resources/js/pages/Admin/DiagnosisRequests/ImplementationPlan.vue'
        );

        $this->assertStringContainsString(
            'Gestionar ejecución y Go-Live',
            $page
        );

        $this->assertStringContainsString(
            "['accepted', 'active', 'completed']",
            $page
        );
    }

    public function test_execution_page_explains_commercial_boundary(): void
    {
        $page = file_get_contents(
            $this->root()
            .'/resources/js/pages/Admin/DiagnosisRequests/ImplementationExecution.vue'
        );

        foreach ([
            'Inicializar fase',
            'Completar al 100%',
            'Checklist de readiness',
            'Confirmar LIVE',
            'No crea Subscriber, Company, Subscription ni',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $page
            );
        }
    }
}
