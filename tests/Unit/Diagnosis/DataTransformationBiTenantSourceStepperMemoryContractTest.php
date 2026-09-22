<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiTenantSourceStepperMemoryContractTest
    extends TestCase
{
    private function ui(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/resources/js/pages/App/DataTransformationBi.vue'
        );
    }

    public function test_stepper_memory_is_scoped_by_source(): void
    {
        $ui = $this->ui();

        self::assertStringContainsString(
            "'lauda:data-bi:source-step:'",
            $ui
        );

        self::assertStringContainsString(
            'sourceStepStorageKey',
            $ui
        );

        self::assertStringContainsString(
            '${sourceStepStoragePrefix}${sourceAssetId}',
            $ui
        );
    }

    public function test_only_valid_source_workspace_steps_can_be_restored(): void
    {
        $ui = $this->ui();

        self::assertStringContainsString(
            'function isSourceWorkspaceTab(',
            $ui
        );

        self::assertStringContainsString(
            'sourceTabs.some(',
            $ui
        );

        self::assertStringContainsString(
            "? stored\n            : 'information';",
            $ui
        );
    }

    public function test_step_navigation_remembers_the_current_source_step(): void
    {
        $ui = $this->ui();

        self::assertStringContainsString(
            'rememberSourceStep(',
            $ui
        );

        self::assertStringContainsString(
            'selectedSourceId.value',
            $ui
        );

        self::assertStringContainsString(
            'window.localStorage.setItem(',
            $ui
        );
    }

    public function test_source_selection_restores_instead_of_forcing_information(): void
    {
        $ui = $this->ui();

        $start = strpos(
            $ui,
            'function selectSource('
        );

        self::assertNotFalse($start);

        $block = substr(
            $ui,
            $start,
            700
        );

        self::assertStringContainsString(
            'readLastSourceStep(',
            $block
        );

        self::assertStringNotContainsString(
            "activeSourceTab.value =\n        'information';",
            $block
        );
    }

    public function test_selected_source_watcher_restores_the_saved_step(): void
    {
        $ui = $this->ui();

        self::assertStringContainsString(
            "sourceAsset\n                ? readLastSourceStep(",
            $ui
        );

        self::assertStringContainsString(
            "                : 'information';",
            $ui
        );
    }

    public function test_storage_failure_never_blocks_the_workspace(): void
    {
        $ui = $this->ui();

        self::assertGreaterThanOrEqual(
            2,
            substr_count(
                $ui,
                "typeof window === 'undefined'"
            )
        );

        self::assertStringContainsString(
            'catch {',
            $ui
        );
    }
}
