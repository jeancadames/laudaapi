<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiDynamicSourceDataUploadUiContractTest
    extends TestCase
{
    private string $view;
    private string $routes;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(
                __DIR__,
                3
            );

        $this->view =
            file_get_contents(
                $root
                .'/resources/js/pages/App/'
                .'DataTransformationBi.vue'
            );

        $this->routes =
            file_get_contents(
                $root
                .'/routes/web.php'
            );

        self::assertIsString(
            $this->view
        );

        self::assertIsString(
            $this->routes
        );
    }

    public function test_tenant_owns_dynamic_source_file_surface(): void
    {
        foreach ([
            'type DynamicSourceAssetFile =',
            'data_file: DynamicSourceAssetFile | null;',
            'function selectSourceFile(',
            'async function uploadSourceData(): Promise<void>',
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->view
            );
        }

        self::assertStringNotContainsString(
            'uploadDynamicSourceData(',
            $this->tenantFileTab()
        );
    }

    public function test_upload_uses_form_data_and_tenant_source_endpoint(): void
    {
        $block =
            $this->uploadLogic();

        foreach ([
            'new FormData()',
            'formData.append(',
            "'file'",
            'sourceFile.value',
            "'POST'",
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $block
            );
        }

        self::assertStringContainsString(
            '${sourceWorkspaceBase}/sesiones/${sessionId}/fuentes/${sourceAsset.id}/archivo',
            $block
        );
    }

    public function test_file_tab_exposes_csv_xlsx_upload_only_while_tenant_can_manage(): void
    {
        $block =
            $this->tenantFileTab();

        foreach ([
            'accept=".csv,.xlsx"',
            'v-if="canManageSources"',
            '@change="selectSourceFile"',
            '@click="uploadSourceData"',
            'Subir archivo',
            'Reemplazar archivo',
            'Tamaño máximo: 32 MB.',
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $block
            );
        }
    }

    public function test_file_tab_displays_only_safe_received_file_metadata(): void
    {
        $block =
            $this->tenantFileTab();

        foreach ([
            'original_filename',
            'source_format',
            'source_size_bytes',
            'source_row_count',
            'filas detectadas',
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $block
            );
        }

        foreach ([
            'source_path',
            'source_disk',
            'source_sha256',
            'SHA-256',
            'reader_configuration',
            'source_structure_snapshot',
        ] as $privateToken) {
            self::assertStringNotContainsString(
                $privateToken,
                $block
            );
        }
    }

    public function test_upload_does_not_require_structure_before_file_delivery(): void
    {
        $block =
            $this->uploadLogic();

        self::assertStringContainsString(
            'Selecciona un archivo CSV o XLSX.',
            $block
        );

        self::assertStringNotContainsString(
            'structure_status',
            $block
        );

        self::assertStringNotContainsString(
            'structure_text',
            $block
        );
    }

    public function test_tenant_route_is_post_and_scoped_to_session_and_source(): void
    {
        self::assertStringContainsString(
            "'/sesiones/{sessionId}/fuentes/{sourceAssetId}/archivo'",
            $this->routes
        );

        self::assertStringContainsString(
            "'uploadSourceAssetData'",
            $this->routes
        );

        self::assertStringContainsString(
            "->name('data_file.upload')",
            $this->routes
        );

        self::assertStringContainsString(
            "->whereNumber('sessionId')",
            $this->routes
        );

        self::assertStringContainsString(
            "->whereNumber('sourceAssetId')",
            $this->routes
        );
    }

    public function test_submitted_workspace_cannot_replace_file_from_ui(): void
    {
        $block =
            $this->tenantFileTab();

        self::assertStringContainsString(
            'v-if="canManageSources"',
            $block
        );

        self::assertStringContainsString(
            '@click="uploadSourceData"',
            $block
        );

        self::assertStringContainsString(
            'const canManageSources =',
            $this->view
        );

        self::assertStringContainsString(
            '.can_manage_sources',
            $this->view
        );
    }

    private function uploadLogic(): string
    {
        $start =
            strpos(
                $this->view,
                'async function uploadSourceData(): Promise<void>'
            );

        self::assertNotFalse(
            $start
        );

        $end =
            strpos(
                $this->view,
                '<template>',
                $start
            );

        /*
         * The first template starts after the complete script block.
         * This deliberately avoids coupling the test to whichever
         * helper function happens to follow uploadSourceData().
         */
        self::assertNotFalse(
            $end
        );

        return substr(
            $this->view,
            $start,
            $end - $start
        );
    }

    private function tenantFileTab(): string
    {
        $start =
            strpos(
                $this->view,
                '<!-- Archivo -->'
            );

        self::assertNotFalse(
            $start
        );

        $end =
            strpos(
                $this->view,
                '<!-- Resultado -->',
                $start
            );

        self::assertNotFalse(
            $end
        );

        return substr(
            $this->view,
            $start,
            $end - $start
        );
    }
}
