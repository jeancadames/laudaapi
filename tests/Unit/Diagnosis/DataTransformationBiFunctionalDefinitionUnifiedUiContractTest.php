<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiFunctionalDefinitionUnifiedUiContractTest
    extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/resources/js/pages/Admin/'
                .'Transformation360/'
                .'ImplementationRequests/'
                .'Show.vue'
            );
    }

    public function test_definition_is_not_raw_json_ui(): void
    {
        foreach ([
            'functionalScopeJson',
            'functionalDeliverablesJson',
            'functionalDependenciesJson',
            'parseFunctionalEditors',
            'El editor JSON permite',
        ] as $legacy) {
            self::assertStringNotContainsString(
                $legacy,
                $this->source
            );
        }

        foreach ([
            'Contenido funcional',
            'Alcance funcional',
            'Entregables',
            'Dependencias',
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $this->source
            );
        }
    }

    public function test_data_bi_uses_general_responsibilities(): void
    {
        foreach ([
            'data_transformation_bi:client_source_delivery',
            'data_transformation_bi:lauda_transformation',
            'Modelo general de responsabilidades',
            'Definir el responsable de cada',
            'SourceAsset.',
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $this->source
            );
        }
    }

    public function test_only_request_level_responsible_selector_remains(): void
    {
        self::assertSame(
            1,
            substr_count(
                $this->source,
                'Seleccionar responsable'
            )
        );

        self::assertStringContainsString(
            'v-model="assignedUserId"',
            $this->source
        );
    }

    public function test_sources_are_post_agreement(): void
    {
        self::assertStringContainsString(
            "'definition_agreed'",
            $this->source
        );

        self::assertStringContainsString(
            "'ready_for_commercial'",
            $this->source
        );

        self::assertStringContainsString(
            'Después del acuerdo funcional se',
            $this->source
        );
    }

    public function test_legacy_canonical_workbench_is_hidden(): void
    {
        self::assertStringContainsString(
            'LEGACY_CANONICAL_DOMAIN_WORKBENCH_HIDDEN',
            $this->source
        );

        self::assertStringContainsString(
            'v-if="false"',
            $this->source
        );
    }

    public function test_copy_does_not_mix_inputs_or_accesses(): void
    {
        self::assertStringNotContainsString(
            'dependencias, insumos y accesos',
            $this->source
        );

        self::assertStringContainsString(
            'modelo general de',
            $this->source
        );
    }
}
