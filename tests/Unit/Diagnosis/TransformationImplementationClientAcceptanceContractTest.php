<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationClientAcceptanceContractTest extends TestCase
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

    public function test_client_post_route_exists(): void
    {
        $routes = $this->read('routes/web.php');

        foreach ([
            "/{assessment}/plan-implementacion/aceptar",
            "TransformationImplementationPlanController::class, 'accept'",
            "implementation_plan.accept",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $routes
            );
        }
    }

    public function test_controller_reuses_policy_and_domain_service(): void
    {
        $controller = $this->read(
            'app/Http/Controllers/Diagnosis/TransformationImplementationPlanController.php'
        );

        foreach ([
            'public function accept(',
            "Gate::authorize('view', \$assessment)",
            'TransformationImplementationPlanService $planService',
            'TransformationImplementationPlan::STATUS_PRESENTED',
            'TransformationImplementationPlan::STATUS_ACCEPTED',
            "whereNotNull('presented_at')",
            '$planService->acceptPlan(',
            "'diagnosis.implementation_plan.show'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $controller
            );
        }
    }

    public function test_client_acceptance_does_not_own_recurring_activation(): void
    {
        $controller = $this->read(
            'app/Http/Controllers/Diagnosis/TransformationImplementationPlanController.php'
        );

        foreach ([
            'Subscriber::create(',
            'Company::create(',
            'Subscription::create(',
            'SubscriptionItem::create(',
            'activateFromGoLive(',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $controller
            );
        }
    }

    public function test_ui_only_offers_accept_on_presented(): void
    {
        $page = $this->read(
            'resources/js/pages/Diagnosis/ImplementationPlan.vue'
        );

        foreach ([
            'accept_url: string;',
            'function acceptPlan()',
            'router.post(',
            'props.accept_url',
            "plan.status === 'presented'",
            'Aceptar Plan de Implementación',
            'Tu aceptación quedó registrada.',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $page
            );
        }
    }

    public function test_domain_acceptance_remains_idempotent_and_readiness_guarded(): void
    {
        $service = $this->read(
            'app/Services/Diagnosis/TransformationImplementationPlanService.php'
        );

        foreach ([
            'public function acceptPlan(',
            'TransformationImplementationPlan::STATUS_ACCEPTED',
            'TransformationImplementationPlan::STATUS_PRESENTED',
            'private function commercialReadiness(',
            "'subscription_created' => false",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $service
            );
        }
    }
}
