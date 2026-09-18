<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataTransformationBiSqlServerExtractionAssistant;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class DataTransformationBiSqlServerExtractionAssistantTest
    extends TestCase
{
    public function test_it_generates_select_from_create_table(): void
    {
        $service =
            new DataTransformationBiSqlServerExtractionAssistant();

        $result =
            $service->preview(
                'dbo',
                'CTES',
                <<<'SQL'
CREATE TABLE dbo.CTES (
    [CODIGO] varchar(20) NOT NULL,
    [NOMBRE] varchar(150) NULL,
    [RNC] varchar(20) NULL,
    [LIMCRED] decimal(18,2) NULL,
    CONSTRAINT [PK_CTES] PRIMARY KEY ([CODIGO])
);
SQL
            );

        self::assertSame(
            'sql_server',
            $result['source_type']
        );

        self::assertSame(
            4,
            $result['field_count']
        );

        self::assertSame(
            [
                'CODIGO',
                'NOMBRE',
                'RNC',
                'LIMCRED',
            ],
            array_column(
                $result['fields'],
                'name'
            )
        );

        self::assertSame(
            <<<'SQL'
SELECT
    [CODIGO],
    [NOMBRE],
    [RNC],
    [LIMCRED]
FROM [dbo].[CTES];
SQL,
            $result['query']
        );
    }

    public function test_it_accepts_simple_field_type_lines(): void
    {
        $service =
            new DataTransformationBiSqlServerExtractionAssistant();

        $result =
            $service->preview(
                'dbo',
                'CTES',
                <<<'TEXT'
CODIGO varchar(20)
NOMBRE nvarchar(150)
RNC varchar(20)
BALANCE decimal(18,2)
TEXT
            );

        self::assertSame(
            4,
            $result['field_count']
        );

        self::assertStringContainsString(
            '[BALANCE]',
            $result['query']
        );
    }

    public function test_it_deduplicates_fields_case_insensitively(): void
    {
        $service =
            new DataTransformationBiSqlServerExtractionAssistant();

        $result =
            $service->preview(
                'dbo',
                'CTES',
                <<<'TEXT'
CODIGO varchar(20)
codigo varchar(20)
NOMBRE varchar(100)
TEXT
            );

        self::assertSame(
            2,
            $result['field_count']
        );
    }

    public function test_it_quotes_sql_server_identifiers(): void
    {
        $service =
            new DataTransformationBiSqlServerExtractionAssistant();

        $result =
            $service->preview(
                'ventas',
                'Clientes Activos',
                <<<'TEXT'
Código varchar(20)
Nombre nvarchar(100)
TEXT
            );

        self::assertStringContainsString(
            'FROM [ventas].[Clientes Activos];',
            $result['query']
        );
    }

    public function test_it_rejects_executable_mutation_sql(): void
    {
        $service =
            new DataTransformationBiSqlServerExtractionAssistant();

        $this->expectException(
            ValidationException::class
        );

        $service->preview(
            'dbo',
            'CTES',
            <<<'SQL'
CODIGO varchar(20)
DELETE FROM CTES;
SQL
        );
    }

    public function test_it_rejects_empty_structure(): void
    {
        $service =
            new DataTransformationBiSqlServerExtractionAssistant();

        $this->expectException(
            ValidationException::class
        );

        $service->preview(
            'dbo',
            'CTES',
            '   '
        );
    }

    public function test_it_returns_csv_and_xlsx_export_guidance(): void
    {
        $service =
            new DataTransformationBiSqlServerExtractionAssistant();

        $result =
            $service->preview(
                'dbo',
                'CTES',
                'CODIGO varchar(20)'
            );

        self::assertSame(
            '.csv',
            $result['export']['csv']['extension']
        );

        self::assertSame(
            '.xlsx',
            $result['export']['xlsx']['extension']
        );

        self::assertNotEmpty(
            $result['export']['csv']['instructions']
        );

        self::assertNotEmpty(
            $result['export']['xlsx']['instructions']
        );
    }
}
