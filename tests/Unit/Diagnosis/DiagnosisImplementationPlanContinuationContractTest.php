<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisImplementationPlanContinuationContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_diagnosis_exposes_only_presented_or_later_plan(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Diagnosis/DigitalDiagnosisController.php'
        );

        foreach ([
            'TransformationImplementationPlan::STATUS_PRESENTED',
            'TransformationImplementationPlan::STATUS_ACCEPTED',
            'TransformationImplementationPlan::STATUS_ACTIVE',
            'TransformationImplementationPlan::STATUS_COMPLETED',
            "->whereNotNull('presented_at')",
            "'implementation_plan_url' =>",
            "'diagnosis.implementation_plan.show'",
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }
    }

    public function test_client_quick_action_uses_backend_plan_visibility(): void
    {
        $quick = file_get_contents(
            $this->root()
            .'/resources/js/components/diagnosis/TransformationQuickActions.vue'
        );

        $show = file_get_contents(
            $this->root()
            .'/resources/js/pages/Diagnosis/Show.vue'
        );

        foreach ([
            'implementationPlanUrl?: string | null',
            'v-if="implementationPlanUrl"',
            ':href="implementationPlanUrl"',
            'Continuar con mi transformación',
            'Plan de Implementación en preparación',
        ] as $token) {
            $this->assertStringContainsString($token, $quick);
        }

        $this->assertStringContainsString(
            ':implementation-plan-url="endpoints.implementation_plan_url"',
            $show
        );
    }

    public function test_continuation_does_not_activate_recurring_commerce(): void
    {
        $sources = implode("\n", [
            file_get_contents(
                $this->root()
                .'/app/Http/Controllers/Diagnosis/DigitalDiagnosisController.php'
            ),
            file_get_contents(
                $this->root()
                .'/resources/js/components/diagnosis/TransformationQuickActions.vue'
            ),
        ]);

        foreach ([
            'Subscription::create',
            'SubscriptionItem::create',
            'activateFromGoLive(',
            'activateServiceForGoLive(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $sources
            );
        }
    }
}
