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

    public function test_context_sidebar_is_sticky_except_when_the_request_form_is_available(): void
    {
        $ui = $this->ui();

        self::assertStringContainsString(
            "implementation_request.can_request ? 'xl:col-auto xl:row-auto xl:static xl:top-auto'"
            ." : 'xl:col-start-2 xl:row-start-1 xl:sticky xl:top-6'",
            preg_replace('/\s+/', ' ', $ui)
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
