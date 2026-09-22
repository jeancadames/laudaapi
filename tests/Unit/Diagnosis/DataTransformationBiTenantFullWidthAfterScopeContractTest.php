<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiTenantFullWidthAfterScopeContractTest
    extends TestCase
{
    private function ui(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/resources/js/pages/App/DataTransformationBi.vue'
        );
    }

    public function test_content_after_potential_scope_uses_full_grid_width(): void
    {
        $ui = $this->ui();

        $scope = strpos(
            $ui,
            '<!-- Alcance -->'
        );

        $fullWidth = strpos(
            $ui,
            '<!-- Contenido posterior al alcance · ancho completo -->'
        );

        $definition = strpos(
            $ui,
            '<!-- Definition presentada al tenant -->'
        );

        self::assertNotFalse($scope);
        self::assertNotFalse($fullWidth);
        self::assertNotFalse($definition);

        self::assertGreaterThan(
            $scope,
            $fullWidth
        );

        self::assertGreaterThan(
            $fullWidth,
            $definition
        );

        self::assertStringContainsString(
            'class="space-y-6 xl:col-span-2"',
            $ui
        );
    }

    public function test_context_sidebar_stays_in_introductory_right_column(): void
    {
        $ui = $this->ui();

        self::assertStringContainsString(
            'class="space-y-6 xl:col-start-2 xl:row-start-1 xl:sticky xl:top-6"',
            $ui
        );
    }

    public function test_page_preserves_existing_content_width_container(): void
    {
        $ui = $this->ui();

        self::assertStringContainsString(
            'mx-auto w-full max-w-7xl',
            $ui
        );
    }

    public function test_source_workspace_remains_after_full_width_breakpoint(): void
    {
        $ui = $this->ui();

        $fullWidth = strpos(
            $ui,
            '<!-- Contenido posterior al alcance · ancho completo -->'
        );

        $sourceWorkspace = strpos(
            $ui,
            '<!-- T1_TENANT_SOURCE_WORKSPACE -->'
        );

        self::assertNotFalse($fullWidth);
        self::assertNotFalse($sourceWorkspace);

        self::assertGreaterThan(
            $fullWidth,
            $sourceWorkspace
        );
    }
}
