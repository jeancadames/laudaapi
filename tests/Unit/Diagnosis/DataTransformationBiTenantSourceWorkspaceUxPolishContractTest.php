<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiTenantSourceWorkspaceUxPolishContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_copy_query_has_visible_contextual_feedback(): void
    {
        $ui = file_get_contents(
            $this->root()
            .'/resources/js/pages/App/DataTransformationBi.vue'
        );

        self::assertStringContainsString(
            "ref<'copied' | 'error' | null>",
            $ui
        );

        self::assertStringContainsString(
            "extractionCopyState.value =\n            'copied';",
            $ui
        );

        self::assertStringContainsString(
            '✓ Consulta copiada',
            $ui
        );

        self::assertStringContainsString(
            'No se pudo copiar',
            $ui
        );

        self::assertStringContainsString(
            'Consulta copiada al portapapeles.',
            $ui
        );
    }

    public function test_all_native_buttons_expose_pointer_affordance(): void
    {
        $ui = file_get_contents(
            $this->root()
            .'/resources/js/pages/App/DataTransformationBi.vue'
        );

        preg_match_all(
            '/<button\b.*?>/s',
            $ui,
            $matches
        );

        self::assertNotEmpty(
            $matches[0]
        );

        foreach (
            $matches[0]
            as $button
        ) {
            self::assertStringContainsString(
                'cursor-pointer',
                $button,
                $button
            );

            if (
                str_contains(
                    $button,
                    ':disabled='
                )
            ) {
                self::assertStringContainsString(
                    'disabled:cursor-not-allowed',
                    $button,
                    $button
                );
            }
        }
    }

    public function test_extraction_instructions_follow_the_source_stepper(): void
    {
        $assistant = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'DataTransformationBiSqlServerExtractionAssistant.php'
        );

        foreach ([
            'archivo fuente del dominio',
            'archivo de esta fuente',
            'Regresa a LAUDA',
        ] as $obsolete) {
            self::assertStringNotContainsString(
                $obsolete,
                $assistant
            );
        }

        self::assertSame(
            2,
            substr_count(
                $assistant,
                'Cuando tengas el archivo listo, continúa al paso Archivo.'
            )
        );
    }
}
