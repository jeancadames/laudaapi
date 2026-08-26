<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationPostGoLiveSubscriptionUiContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_orchestrator_runs_provisioning_before_r2i(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/TransformationImplementationPostGoLiveSubscriptionService.php'
        );

        $provisioning = strpos(
            $source,
            'ensureForAssessment('
        );

        $r2i = strpos(
            $source,
            'activateFromGoLive('
        );

        $this->assertNotFalse($provisioning);
        $this->assertNotFalse($r2i);
        $this->assertLessThan($r2i, $provisioning);
    }

    public function test_orchestrator_requires_live(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/TransformationImplementationPostGoLiveSubscriptionService.php'
        );

        $this->assertStringContainsString(
            'STATUS_LIVE',
            $source
        );

        $this->assertStringContainsString(
            '$goLive->went_live_at',
            $source
        );
    }

    public function test_step_two_does_not_run_r2j(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/TransformationImplementationPostGoLiveSubscriptionService.php'
        );

        foreach ([
            'TransformationImplementationCapabilitySubscriptionService',
            'SubscriptionItem::',
            'upsertMapping(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_admin_action_is_explicit(): void
    {
        $routes = file_get_contents(
            $this->root().'/routes/admin.php'
        );

        $controller = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Admin/AdminTransformationImplementationExecutionController.php'
        );

        $this->assertStringContainsString(
            'implementation_execution.subscription.activate',
            $routes
        );

        $this->assertStringContainsString(
            'public function activateSubscription(',
            $controller
        );

        $this->assertStringContainsString(
            'activateSubscriptionForGoLive(',
            $controller
        );
    }

    public function test_ui_exposes_general_subscription_without_service_item(): void
    {
        $page = file_get_contents(
            $this->root()
            .'/resources/js/pages/Admin/DiagnosisRequests/ImplementationExecution.vue'
        );

        $this->assertStringContainsString(
            'Activar/vincular suscripción general',
            $page
        );

        $this->assertStringContainsString(
            'No crea ningún',
            $page
        );

        $this->assertStringContainsString(
            'eso corresponde a R2-J',
            $page
        );
    }
}
