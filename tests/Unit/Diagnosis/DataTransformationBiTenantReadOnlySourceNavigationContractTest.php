<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiTenantReadOnlySourceNavigationContractTest
    extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();

        $path =
            dirname(__DIR__, 3)
            .'/resources/js/pages/App/'
            .'DataTransformationBi.vue';

        $this->source =
            file_get_contents($path);

        self::assertIsString(
            $this->source
        );
    }

    public function test_draft_preparation_workflow_remains_five_steps(): void
    {
        foreach ([
            "label: 'Información'",
            "label: 'Estructura'",
            "label: 'Extracción'",
            "helper: 'Generar archivo'",
            "label: 'Archivo'",
            "helper: 'Subir archivo'",
            "label: 'Resultado'",
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->source
            );
        }

        self::assertStringContainsString(
            'aria-label="Pasos de entrega de la fuente"',
            $this->source
        );

        self::assertStringContainsString(
            'previewSourceExtraction',
            $this->source
        );
    }

    public function test_submitted_delivery_has_read_only_navigation(): void
    {
        $block =
            $this->between(
                'const readOnlySourceTabs:',
                'const selectedSourceId ='
            );

        foreach ([
            "key: 'information'",
            "label: 'Información'",
            "key: 'structure'",
            "label: 'Estructura'",
            "key: 'file'",
            "label: 'Archivo recibido'",
            "key: 'result'",
            "label: 'Análisis'",
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $block
            );
        }

        foreach ([
            "'extraction'",
            'Extracción',
            'Generar consulta',
            'Subir archivo',
        ] as $token) {
            self::assertStringNotContainsString(
                $token,
                $block
            );
        }
    }

    public function test_read_only_navigation_replaces_stepper(): void
    {
        self::assertStringContainsString(
            'v-if="canManageSources"',
            $this->source
        );

        self::assertStringContainsString(
            'aria-label="Vistas de la entrega enviada"',
            $this->source
        );

        self::assertStringContainsString(
            'v-for="tab in readOnlySourceTabs"',
            $this->source
        );
    }

    public function test_saved_extraction_step_is_not_restored_read_only(): void
    {
        foreach ([
            'restoredStep',
            '.can_manage_sources',
            "restoredStep === 'extraction'",
            "return 'information';",
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->source
            );
        }
    }

    public function test_in_place_submission_exits_extraction(): void
    {
        $block =
            $this->between(
                'watch('."\n".'    canManageSources,',
                'const isSourceWorkspaceSubmitted ='
            );

        foreach ([
            '!canManage',
            "activeSourceTab.value",
            "=== 'extraction'",
            "goToSourceStep(",
            "'information'",
            'immediate: true',
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $block
            );
        }
    }

    private function between(
        string $start,
        string $end
    ): string {
        $startPosition =
            strpos(
                $this->source,
                $start
            );

        self::assertNotFalse(
            $startPosition
        );

        $endPosition =
            strpos(
                $this->source,
                $end,
                $startPosition + strlen($start)
            );

        self::assertNotFalse(
            $endPosition
        );

        return substr(
            $this->source,
            $startPosition,
            $endPosition - $startPosition
        );
    }
}
