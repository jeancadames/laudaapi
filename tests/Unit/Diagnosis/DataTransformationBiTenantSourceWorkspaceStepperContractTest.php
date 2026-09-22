<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiTenantSourceWorkspaceStepperContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_source_workspace_exposes_the_five_step_sequence(): void
    {
        $ui = file_get_contents(
            $this->root()
            .'/resources/js/pages/App/DataTransformationBi.vue'
        );

        foreach ([
            "label: 'Información'",
            "label: 'Estructura'",
            "label: 'Extracción'",
            "label: 'Archivo'",
            "label: 'Resultado'",
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $ui
            );
        }

        self::assertStringContainsString(
            "helper: 'Opcional'",
            $ui
        );

        self::assertStringContainsString(
            'aria-label="Pasos de entrega de la fuente"',
            $ui
        );
    }

    public function test_stepper_exposes_current_and_next_step_navigation(): void
    {
        $ui = file_get_contents(
            $this->root()
            .'/resources/js/pages/App/DataTransformationBi.vue'
        );

        foreach ([
            'activeSourceStepIndex',
            'activeSourceStep',
            'nextSourceStep',
            'goToSourceStep',
            'goToNextSourceStep',
            'Paso actual',
            'Siguiente paso:',
            'Continuar a',
            'Último paso del flujo.',
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $ui
            );
        }
    }

    public function test_stepper_is_navigation_not_a_structure_gate(): void
    {
        $ui = file_get_contents(
            $this->root()
            .'/resources/js/pages/App/DataTransformationBi.vue'
        );

        self::assertStringContainsString(
            "@click=\"\n                                                        goToSourceStep(",
            $ui
        );

        self::assertStringNotContainsString(
            'canAdvanceSourceStep',
            $ui
        );

        self::assertStringNotContainsString(
            'structureRequiredForNextStep',
            $ui
        );
    }

    public function test_extraction_instruction_points_to_file_step(): void
    {
        $assistant = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'DataTransformationBiSqlServerExtractionAssistant.php'
        );

        self::assertStringNotContainsString(
            'Regresa a LAUDA',
            $assistant
        );

        self::assertSame(
            2,
            substr_count(
                $assistant,
                'Cuando tengas el archivo listo, continúa al paso Archivo.'
            )
        );
    }
}
