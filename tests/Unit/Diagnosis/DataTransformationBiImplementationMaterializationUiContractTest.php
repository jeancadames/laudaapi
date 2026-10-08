<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DataTransformationBiImplementationMaterializationUiContractTest
    extends TestCase
{
    public function test_ui_exposes_session_wide_materialization_boundary(): void
    {
        $source =
            $this->source();

        foreach (
            [
                'IMPLEMENTATION_MATERIALIZATION_ADMIN_UI',
                'Materialización de datasets',
                'Materializar datasets',
                'SERVER_AUTHORITATIVE_MATERIALIZATION_GATE',
                '/implementation-datasets/materialize',
                '/implementation-datasets/materialization-runs/${runUuid}',
                'selected_mapping_count',
                'materialized_dataset_count',
                'reused_dataset_count',
            ]
            as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_ui_reuses_async_polling_contract(): void
    {
        $source =
            $this->source();

        foreach (
            [
                'IMPLEMENTATION_MATERIALIZATION_POLL_INTERVAL_MS',
                '3000',
                'IMPLEMENTATION_MATERIALIZATION_POLL_ATTEMPTS',
                '320',
                "'queued'",
                "'processing'",
                "'completed'",
                "'failed'",
                'pollImplementationMaterialization(',
                'resumeImplementationMaterializationFromStorage',
                'window.sessionStorage',
            ]
            as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_browser_does_not_reimplement_session_mapping_gate(): void
    {
        $source =
            $this->source();

        $start =
            strpos(
                $source,
                '// IMPLEMENTATION_MATERIALIZATION_UI_STATE_START'
            );

        $end =
            strpos(
                $source,
                '// IMPLEMENTATION_MATERIALIZATION_UI_STATE_END'
            );

        $this->assertNotFalse(
            $start
        );

        $this->assertNotFalse(
            $end
        );

        $region =
            substr(
                $source,
                (int) $start,
                (int) $end - (int) $start
            );

        $this->assertStringNotContainsString(
            'dynamicSourceMappingWorkspace.value',
            $region
        );

        $this->assertStringNotContainsString(
            'mapping_version -',
            $region
        );

        $this->assertStringContainsString(
            'Do NOT reproduce currentMappings() in Vue.',
            $region
        );
    }

    public function test_modern_ui_remains_separate_from_legacy_staging(): void
    {
        $source =
            $this->source();

        $this->assertStringContainsString(
            'Preparar staging',
            $source
        );

        $this->assertStringContainsString(
            'materializeStandardIntakeV2Session',
            $source
        );

        $this->assertStringContainsString(
            'no reemplaza “Preparar staging”',
            $source
        );
    }

    public function test_materialization_ui_does_not_expose_raw_rows(): void
    {
        $source =
            $this->source();

        $start =
            strpos(
                $source,
                '// IMPLEMENTATION_MATERIALIZATION_UI_TYPES_START'
            );

        $end =
            strpos(
                $source,
                '// IMPLEMENTATION_MATERIALIZATION_UI_STATE_END'
            );

        $this->assertNotFalse(
            $start
        );

        $this->assertNotFalse(
            $end
        );

        $region =
            substr(
                $source,
                (int) $start,
                (int) $end - (int) $start
            );

        foreach (
            [
                'canonical_payload',
                'source_row_number',
                'source_row_sha256',
                'canonical_payload_sha256',
            ]
            as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $region
            );
        }
    }

    private function source(): string
    {
        $root =
            dirname(
                __DIR__,
                3
            );

        $source =
            file_get_contents(
                $root
                .'/resources/js/pages/Admin/Transformation360/'
                .'ImplementationRequests/Show.vue'
            );

        $this->assertIsString(
            $source
        );

        return $source;
    }
}
