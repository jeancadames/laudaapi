<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationPostGoLiveServiceActivationUiContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_orchestrator_requires_r2i_before_r2j(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/TransformationImplementationPostGoLiveServiceActivationService.php'
        );

        $this->assertStringContainsString(
            'TransformationImplementationSubscriptionActivation::query()',
            $source
        );

        $this->assertStringContainsString(
            'Primero debe completarse R2-I',
            $source
        );

        $this->assertStringContainsString(
            'activateFromGoLive(',
            $source
        );
    }

    public function test_r2j_does_not_create_second_subscription(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/TransformationImplementationPostGoLiveServiceActivationService.php'
        );

        foreach ([
            'Subscription::create(',
            'Subscription::query()->create(',
            'Subscriber::create(',
            'Company::create(',
            'ensureForAssessment(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_admin_exposes_explicit_r2j_action(): void
    {
        $routes = file_get_contents(
            $this->root().'/routes/admin.php'
        );

        $controller = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Admin/AdminTransformationImplementationExecutionController.php'
        );

        $this->assertStringContainsString(
            'implementation_execution.service.activate',
            $routes
        );

        $this->assertStringContainsString(
            'public function activateService(',
            $controller
        );

        $this->assertStringContainsString(
            'activateServiceForGoLive(',
            $controller
        );
    }

    public function test_ui_shows_one_service_item_inside_general_subscription(): void
    {
        $page = file_get_contents(
            $this->root()
            .'/resources/js/pages/Admin/DiagnosisRequests/ImplementationExecution.vue'
        );

        $this->assertStringContainsString(
            'Activar solución mapeada',
            $page
        );

        $this->assertStringContainsString(
            'SubscriptionItem #',
            $page
        );

        $this->assertStringContainsString(
            'misma Subscription general',
            $page
        );

        $this->assertStringContainsString(
            'R2-J completado',
            $page
        );
    }
}
