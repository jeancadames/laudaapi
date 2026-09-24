<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiSourceAssetMappingNullCanonicalRegistryUiContractTest extends TestCase
{
    private function source(): string
    {
        $path =
            dirname(__DIR__, 3)
            .'/resources/js/pages/Admin/Transformation360/ImplementationRequests/Show.vue';

        $source =
            file_get_contents($path);

        self::assertIsString($source);

        return $source;
    }

    public function test_mapping_workspace_contract_allows_null_canonical_registry(): void
    {
        $source =
            $this->source();

        self::assertStringContainsString(
            '} | null;',
            $source
        );

        self::assertStringContainsString(
            '|| ! workspace.canonical_registry',
            $source
        );

        self::assertStringContainsString(
            '&& workspace.canonical_registry',
            $source
        );
    }

    public function test_mapping_ui_shows_safe_empty_state_without_published_registry(): void
    {
        $source =
            $this->source();

        self::assertStringContainsString(
            '&& dynamicSourceMappingWorkspace.canonical_registry',
            $source
        );

        self::assertStringContainsString(
            '&& !dynamicSourceMappingWorkspace.canonical_registry',
            $source
        );

        self::assertStringContainsString(
            'Modelo canónico LAUDA pendiente',
            $source
        );

        self::assertStringContainsString(
            'No existe todavía una versión publicada del modelo canónico LAUDA.',
            $source
        );

        self::assertStringContainsString(
            'Prepara y publica el modelo antes de iniciar el mapeo.',
            $source
        );
    }
}
