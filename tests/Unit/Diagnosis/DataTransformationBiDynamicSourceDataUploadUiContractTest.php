<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiDynamicSourceDataUploadUiContractTest
    extends TestCase
{
    private string $view;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(__DIR__, 3);

        $this->view =
            file_get_contents(
                $root
                .'/resources/js/pages/Admin/'
                .'Transformation360/ImplementationRequests/Show.vue'
            );

        self::assertIsString(
            $this->view
        );
    }

    public function test_dynamic_source_has_safe_data_file_type(): void
    {
        self::assertStringContainsString(
            'type DynamicSourceDataFile =',
            $this->view
        );

        self::assertStringContainsString(
            'data_file?: DynamicSourceDataFile | null;',
            $this->view
        );

        self::assertStringContainsString(
            'data_file?: DynamicSourceDataFile;',
            $this->view
        );
    }

    public function test_upload_uses_form_data_and_source_asset_endpoint(): void
    {
        $block =
            $this->uploadLogic();

        self::assertStringContainsString(
            'new FormData()',
            $block
        );

        self::assertStringContainsString(
            "body.append(\n        'file'",
            $block
        );

        self::assertStringContainsString(
            '/source-assets/${asset.id}/data-file',
            $block
        );

        self::assertStringContainsString(
            "'POST'",
            $block
        );
    }

    public function test_frontend_rejects_non_csv_xlsx_and_files_over_32mb(): void
    {
        $block =
            $this->uploadLogic();

        self::assertStringContainsString(
            "'.csv'",
            $block
        );

        self::assertStringContainsString(
            "'.xlsx'",
            $block
        );

        self::assertStringContainsString(
            '32 * 1024 * 1024',
            $block
        );
    }

    public function test_success_merges_safe_file_into_dynamic_asset(): void
    {
        $block =
            $this->uploadLogic();

        self::assertStringContainsString(
            '!payload.data_file',
            $block
        );

        self::assertStringContainsString(
            'data_file:',
            $block
        );

        self::assertStringContainsString(
            'payload.data_file',
            $block
        );

        self::assertStringContainsString(
            'upsertDynamicSourceAsset(',
            $block
        );
    }

    public function test_file_tab_is_no_longer_placeholder(): void
    {
        $block =
            $this->fileTab();

        self::assertStringContainsString(
            'accept=".csv,.xlsx"',
            $block
        );

        self::assertStringContainsString(
            'uploadDynamicSourceData(',
            $block
        );

        self::assertStringContainsString(
            'Reemplazar archivo',
            $block
        );

        self::assertStringContainsString(
            'Filas detectadas',
            $block
        );

        self::assertStringContainsString(
            'Hojas detectadas',
            $block
        );

        self::assertStringContainsString(
            'SHA-256',
            $block
        );

        self::assertStringNotContainsString(
            'La carga se habilitará en el siguiente paso.',
            $block
        );
    }

    public function test_ui_does_not_reference_private_storage_path(): void
    {
        $block =
            $this->fileTab();

        self::assertStringNotContainsString(
            'source_path',
            $block
        );

        self::assertStringNotContainsString(
            'source_disk',
            $block
        );
    }

    public function test_structure_is_not_required_before_file_upload(): void
    {
        $block =
            $this->uploadLogic();

        self::assertStringNotContainsString(
            'structure_status',
            $block
        );

        self::assertStringNotContainsString(
            'structure_text',
            $block
        );

        self::assertStringContainsString(
            'Selecciona un archivo CSV o XLSX.',
            $block
        );
    }

    public function test_detail_can_really_be_closed(): void
    {
        $start =
            strpos(
                $this->view,
                'function dynamicSourceSelectedAsset()'
            );

        self::assertNotFalse(
            $start
        );

        $end =
            strpos(
                $this->view,
                'function openDynamicSourceWorkspace(',
                $start
            );

        self::assertNotFalse(
            $end
        );

        $block =
            substr(
                $this->view,
                $start,
                $end - $start
            );

        self::assertStringContainsString(
            'dynamicSourceSelectedId.value === null',
            $block
        );

        self::assertStringNotContainsString(
            'assets[0]',
            $block
        );
    }

    private function uploadLogic(): string
    {
        $start =
            strpos(
                $this->view,
                'async function uploadDynamicSourceData('
            );

        self::assertNotFalse(
            $start
        );

        $end =
            strpos(
                $this->view,
                'function dynamicSourceStructureForm(',
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

    private function fileTab(): string
    {
        $start =
            strpos(
                $this->view,
                '<!-- FILE -->'
            );

        self::assertNotFalse(
            $start
        );

        $end =
            strpos(
                $this->view,
                '<!-- ANALYSIS -->',
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
