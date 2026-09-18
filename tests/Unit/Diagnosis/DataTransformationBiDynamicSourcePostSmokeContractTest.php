<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiDynamicSourcePostSmokeContractTest
    extends TestCase
{
    private string $state;
    private string $controller;
    private string $view;
    private string $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(__DIR__, 3);

        $this->state =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiIntakeV2StateService.php'
            );

        $this->controller =
            file_get_contents(
                $root
                .'/app/Http/Controllers/Admin/'
                .'AdminDataTransformationBiIntakeV2Controller.php'
            );

        $this->view =
            file_get_contents(
                $root
                .'/resources/js/pages/Admin/'
                .'Transformation360/ImplementationRequests/Show.vue'
            );

        $this->migration =
            file_get_contents(
                $root
                .'/database/migrations/'
                .'2026_09_18_141500_create_data_transformation_bi_source_assets.php'
            );
    }

    public function test_dynamic_sources_have_their_own_permission(): void
    {
        self::assertSame(
            2,
            substr_count(
                $this->state,
                "'can_manage_sources' =>"
            )
        );

        self::assertStringContainsString(
            'can_manage_sources?: boolean;',
            $this->view
        );

        $start =
            strpos(
                $this->view,
                'function dynamicSourceCanManage(): boolean'
            );

        self::assertNotFalse($start);

        $end =
            strpos(
                $this->view,
                "\n}",
                $start
            );

        self::assertNotFalse($end);

        $block =
            substr(
                $this->view,
                $start,
                $end - $start + 2
            );

        self::assertStringContainsString(
            '?.can_manage_sources',
            $block
        );

        self::assertStringNotContainsString(
            'standardIntakeV2CanEditDomains()',
            $block
        );
    }

    public function test_http_source_payload_contains_structure_text(): void
    {
        $start =
            strpos(
                $this->controller,
                'private function sourceAssetPayload('
            );

        self::assertNotFalse($start);

        $block =
            substr(
                $this->controller,
                $start
            );

        self::assertStringContainsString(
            "'structure_format' =>",
            $block
        );

        self::assertStringContainsString(
            "'structure_text' =>",
            $block
        );

        self::assertStringContainsString(
            '$asset->structure_text',
            $block
        );

        self::assertStringContainsString(
            "'delivery_format' =>",
            $block
        );
    }

    public function test_internal_canonical_body_is_collapsible(): void
    {
        self::assertStringContainsString(
            'const standardIntakeV2CanonicalOpen =',
            $this->view
        );

        self::assertStringContainsString(
            'v-show="standardIntakeV2CanonicalOpen"',
            $this->view
        );

        self::assertStringContainsString(
            'data-d17-canonical-body',
            $this->view
        );

        self::assertStringContainsString(
            'Mostrar modelo interno',
            $this->view
        );

        self::assertStringContainsString(
            'Ocultar modelo interno',
            $this->view
        );

        self::assertStringContainsString(
            'Modelo objetivo LAUDA · procesamiento interno',
            $this->view
        );

        self::assertStringContainsString(
            'startStandardIntakeV2Session',
            $this->view
        );
    }

    public function test_migration_comment_marks_ddl_as_inert(): void
    {
        self::assertStringNotContainsString(
            'No executable SQL is stored here.',
            $this->migration
        );

        self::assertStringContainsString(
            'Any supplied DDL lives in structure_text as inert text;',
            $this->migration
        );

        self::assertStringContainsString(
            'LAUDA never executes that client-provided definition.',
            $this->migration
        );
    }

    public function test_detail_close_remains_intact(): void
    {
        self::assertStringContainsString(
            'Cerrar detalle',
            $this->view
        );

        self::assertStringContainsString(
            'dynamicSourceSelectedId.value === null',
            $this->view
        );
    }
}
