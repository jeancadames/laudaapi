<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiSourceOwnerUiContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_admin_source_workspace_uses_owner_metadata(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/resources/js/pages/Admin/Transformation360/'
            .'ImplementationRequests/Show.vue'
        );

        self::assertStringContainsString(
            'owner?: string | null;',
            $source
        );

        self::assertStringContainsString(
            'owner: string;',
            $source
        );

        self::assertStringContainsString(
            'dynamicSourceForm.owner',
            $source
        );

        self::assertStringContainsString(
            'asset.owner',
            $source
        );

        self::assertStringContainsString(
            'Responsable de la fuente',
            $source
        );
    }

    public function test_tenant_source_workspace_uses_owner_metadata(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/resources/js/pages/App/DataTransformationBi.vue'
        );

        self::assertStringContainsString(
            'owner: string | null;',
            $source
        );

        self::assertStringContainsString(
            'createSourceForm.owner',
            $source
        );

        self::assertStringContainsString(
            'editSourceForm.owner',
            $source
        );

        self::assertStringContainsString(
            'sourceAsset.owner',
            $source
        );

        self::assertStringContainsString(
            'Responsable de la fuente',
            $source
        );
    }

    public function test_owner_does_not_reintroduce_canonical_source_selection(): void
    {
        foreach ([
            $this->root()
                .'/resources/js/pages/Admin/Transformation360/'
                .'ImplementationRequests/Show.vue',
            $this->root()
                .'/resources/js/pages/App/DataTransformationBi.vue',
        ] as $path) {
            $source = file_get_contents($path);

            self::assertStringNotContainsString(
                'owner_domain_key',
                $source
            );

            self::assertStringNotContainsString(
                'source_owner_domain',
                $source
            );
        }
    }
}
