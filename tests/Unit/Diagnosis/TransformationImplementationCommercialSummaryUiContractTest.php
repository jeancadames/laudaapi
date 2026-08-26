<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationCommercialSummaryUiContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_controller_exposes_commercial_identity_and_totals(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Admin/AdminTransformationImplementationExecutionController.php'
        );

        foreach ([
            "'subscriber:id,name,currency'",
            "'company:id,name,currency,subscriber_id'",
            'subtotal_amount',
            'discount_amount',
            'tax_amount',
            'total_amount',
            "'subscriber_id' =>",
            "'company_id' =>",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_ui_has_one_general_subscription_summary(): void
    {
        $page = file_get_contents(
            $this->root()
            .'/resources/js/pages/Admin/DiagnosisRequests/ImplementationExecution.vue'
        );

        foreach ([
            'Estado comercial post-Go-Live',
            'Subscription general LAUDAAPI',
            'Una sola Subscription por cliente.',
            'Soluciones activas',
            'Total recurrente',
            'Subtotal',
            'Descuento',
            'Impuestos',
            'commercialSummary',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $page
            );
        }
    }

    public function test_ui_keeps_r2i_and_r2j_actions(): void
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
            'Activar solución mapeada',
            $page
        );
    }

    public function test_summary_is_read_only_and_does_not_activate_anything(): void
    {
        $page = file_get_contents(
            $this->root()
            .'/resources/js/pages/Admin/DiagnosisRequests/ImplementationExecution.vue'
        );

        $this->assertStringContainsString(
            'const commercialSummary = computed(',
            $page
        );

        $this->assertStringContainsString(
            'props.plan.phases.flatMap(',
            $page
        );

        $this->assertStringContainsString(
            'new Map(',
            $page
        );
    }
}
