<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationModalitySelectionUiContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_existing_backend_route_is_used_for_selection(): void
    {
        $vue = file_get_contents(
            $this->root()
            .'/resources/js/pages/Admin/DiagnosisRequests/'
            .'ImplementationPlan.vue'
        );

        $this->assertStringContainsString(
            'modality_select: string;',
            $vue
        );

        $this->assertStringContainsString(
            'props.endpoints.modality_select',
            $vue
        );

        $this->assertStringContainsString(
            'selectCommercialModality',
            $vue
        );
    }

    public function test_selection_is_only_offered_for_complete_draft_scenarios(): void
    {
        $vue = file_get_contents(
            $this->root()
            .'/resources/js/pages/Admin/DiagnosisRequests/'
            .'ImplementationPlan.vue'
        );

        $this->assertStringContainsString(
            'v-if="isDraft && scenario.complete"',
            $vue
        );

        $this->assertStringContainsString(
            '!scenario?.complete',
            $vue
        );
    }

    public function test_ui_exposes_each_scenario_as_selectable(): void
    {
        $vue = file_get_contents(
            $this->root()
            .'/resources/js/pages/Admin/DiagnosisRequests/'
            .'ImplementationPlan.vue'
        );

        $this->assertStringContainsString(
            '`Seleccionar ${scenario.label}`',
            $vue
        );

        $this->assertStringContainsString(
            "'Modalidad seleccionada'",
            $vue
        );
    }

    public function test_selection_does_not_generate_commercial_side_effects(): void
    {
        $vue = file_get_contents(
            $this->root()
            .'/resources/js/pages/Admin/DiagnosisRequests/'
            .'ImplementationPlan.vue'
        );

        $start = strpos(
            $vue,
            'function selectCommercialModality('
        );

        $end = strpos(
            $vue,
            'function generateCommercialScenarios()',
            $start
        );

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $method = substr(
            $vue,
            $start,
            $end - $start
        );

        foreach ([
            'milestone',
            'invoice',
            'payment',
            'subscription',
            'presentPlan',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                strtolower($method)
            );
        }
    }

    public function test_draft_selection_is_not_called_contracted(): void
    {
        $vue = file_get_contents(
            $this->root()
            .'/resources/js/pages/Admin/DiagnosisRequests/'
            .'ImplementationPlan.vue'
        );

        $this->assertStringContainsString(
            'Modalidad seleccionada',
            $vue
        );

        $this->assertStringNotContainsString(
            'Modalidad contratada',
            $vue
        );
    }
}
