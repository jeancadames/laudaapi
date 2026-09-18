<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiSqlServerExtractionUiContractTest
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

    public function test_it_exposes_sql_server_extraction_assistance(): void
    {
        self::assertStringContainsString(
            'Preparar extracción SQL Server',
            $this->view
        );

        self::assertStringContainsString(
            'Preparar extracción desde SQL Server',
            $this->view
        );

        self::assertStringContainsString(
            'sql-server-extraction/preview',
            $this->view
        );

        self::assertStringContainsString(
            'Generar consulta',
            $this->view
        );

        self::assertStringContainsString(
            'Copiar consulta',
            $this->view
        );
    }

    public function test_it_is_scoped_to_source_native_domains(): void
    {
        self::assertStringContainsString(
            'source_native_supported',
            $this->view
        );

        self::assertStringContainsString(
            'sqlServerExtractionForm(',
            $this->view
        );
    }

    public function test_it_requests_structure_not_credentials(): void
    {
        self::assertStringContainsString(
            'schema_name',
            $this->view
        );

        self::assertStringContainsString(
            'table_name',
            $this->view
        );

        self::assertStringContainsString(
            'structure_text',
            $this->view
        );

        self::assertStringContainsString(
            'No necesitamos acceso al servidor',
            $this->view
        );

        self::assertStringNotContainsString(
            'sql_server_password',
            $this->view
        );

        self::assertStringNotContainsString(
            'sql_server_username',
            $this->view
        );

        self::assertStringNotContainsString(
            'connection_string',
            $this->view
        );
    }

    public function test_it_supports_csv_and_xlsx_delivery_guidance(): void
    {
        self::assertStringContainsString(
            "export_format: 'csv'",
            $this->view
        );

        self::assertStringContainsString(
            'value="csv"',
            $this->view
        );

        self::assertStringContainsString(
            'value="xlsx"',
            $this->view
        );

        self::assertStringContainsString(
            'sqlServerExtractionInstructions(',
            $this->view
        );
    }
}
