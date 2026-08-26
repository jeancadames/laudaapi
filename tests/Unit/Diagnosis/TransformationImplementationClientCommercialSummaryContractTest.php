<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationClientCommercialSummaryContractTest extends TestCase
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

    public function test_controller_uses_existing_phase_relations(): void
    {
        $source = $this->read(
            'app/Http/Controllers/Diagnosis/TransformationImplementationPlanController.php'
        );

        foreach ([
            "'phases.estimates'",
            "'phases.milestones'",
            '$commercialPhases =',
            '$commercialSummary =',
            "'commercial_summary' => \$commercialSummary",
            "\$commercialPhases =",
            "'commercial_summary' => \$commercialSummary",
            "'phases' => \$clientPhases",
            "'total_price_amount' =>",
            "'estimate' =>",
            "'estimated_duration_value' =>",
            "'milestones' =>",
            "'billing_amount' =>",
            "'billing_status' =>",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_payload_does_not_expose_internal_fields(): void
    {
        $source = $this->read(
            'app/Http/Controllers/Diagnosis/TransformationImplementationPlanController.php'
        );

        foreach ([
            "'internal_notes' =>",
            "'source_snapshot' =>",
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }
    }

    public function test_client_page_keeps_record_payload_and_shows_summary(): void
    {
        $page = $this->read(
            'resources/js/pages/Diagnosis/ImplementationPlan.vue'
        );

        foreach ([
            'plan: Record<string, any>',
            'Resumen comercial',
            'Inversión de implementación',
            'Condiciones de esta fase',
            'Duración estimada',
            'Hitos de implementación',
            'plan.selected_modality_label',
            'plan.recommended_modality_label',
            'plan.commercial_summary',
            'phase.estimate',
            '.billing_amount',
            '.billing_status',
            'function money(',
            'function durationLabel(',
            'function milestoneStatusLabel(',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $page
            );
        }
    }

    public function test_recurring_remains_separate(): void
    {
        $page = $this->read(
            'resources/js/pages/Diagnosis/ImplementationPlan.vue'
        );

        $this->assertStringContainsString(
            'La suscripción recurrente no forma parte de este monto.',
            $page
        );
    }
}
