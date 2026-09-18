<?php

use App\Services\Diagnosis\DataTransformationBiSourceFileReader;
use App\Services\Diagnosis\DataTransformationBiStandardIntakeResourceGuard;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function dtbiSourceReader(): DataTransformationBiSourceFileReader
{
    return new DataTransformationBiSourceFileReader(
        new DataTransformationBiStandardIntakeResourceGuard()
    );
}

function dtbiTempSourceFile(
    string $suffix
): string {
    $base =
        tempnam(
            sys_get_temp_dir(),
            'dtbi-source-'
        );

    if ($base === false) {
        throw new RuntimeException(
            'No se pudo crear archivo temporal.'
        );
    }

    $path =
        $base.$suffix;

    if (! rename($base, $path)) {
        @unlink($base);

        throw new RuntimeException(
            'No se pudo preparar archivo temporal.'
        );
    }

    return $path;
}

it(
    'inspects utf8 csv while preserving duplicate and blank headers',
    function (): void {
        $path =
            dtbiTempSourceFile(
                '.csv'
            );

        try {
            file_put_contents(
                $path,
                "CODIGO,NOMBRE,NOMBRE,\n"
                ."001,Ana,Cliente A,\n"
                ."002,Luis,Cliente B,\n"
            );

            $result =
                dtbiSourceReader()->inspect(
                    $path,
                    'clientes.csv'
                );

            expect($result['format'])
                ->toBe('csv');

            expect(
                $result['reader_configuration']['encoding']
            )->toBe('UTF-8');

            expect(
                $result['reader_configuration']['delimiter']
            )->toBe('comma');

            expect($result['sheets'])
                ->toHaveCount(1);

            $sheet =
                $result['sheets'][0];

            expect($sheet['row_count'])
                ->toBe(2);

            expect($sheet['column_count'])
                ->toBe(4);

            expect($sheet['headers'])
                ->toBe([
                    'CODIGO',
                    'NOMBRE',
                    'NOMBRE',
                    '',
                ]);

            expect($sheet['columns'])
                ->toBe([
                    [
                        'index' => 1,
                        'key' => 'column_1',
                        'header' => 'CODIGO',
                    ],
                    [
                        'index' => 2,
                        'key' => 'column_2',
                        'header' => 'NOMBRE',
                    ],
                    [
                        'index' => 3,
                        'key' => 'column_3',
                        'header' => 'NOMBRE',
                    ],
                    [
                        'index' => 4,
                        'key' => 'column_4',
                        'header' => '',
                    ],
                ]);

            expect(
                array_key_exists(
                    'rows',
                    $sheet
                )
            )->toBeFalse();
        } finally {
            @unlink($path);
        }
    }
);

it(
    'detects semicolon csv',
    function (): void {
        $path =
            dtbiTempSourceFile(
                '.csv'
            );

        try {
            file_put_contents(
                $path,
                "CODIGO;NOMBRE;RNC\n"
                ."1;Cliente Uno;101010101\n"
                ."2;Cliente Dos;202020202\n"
            );

            $result =
                dtbiSourceReader()->inspect(
                    $path,
                    'clientes.csv'
                );

            expect(
                $result['reader_configuration']['delimiter']
            )->toBe('semicolon');

            expect(
                $result['reader_configuration']['delimiter_character']
            )->toBe(';');

            expect(
                $result['sheets'][0]['column_count']
            )->toBe(3);

            expect(
                $result['sheets'][0]['row_count']
            )->toBe(2);
        } finally {
            @unlink($path);
        }
    }
);

it(
    'detects tab delimited csv',
    function (): void {
        $path =
            dtbiTempSourceFile(
                '.csv'
            );

        try {
            file_put_contents(
                $path,
                "CODIGO\tNOMBRE\tMONTO\n"
                ."1\tCliente Uno\t100.00\n"
                ."2\tCliente Dos\t200.00\n"
            );

            $result =
                dtbiSourceReader()->inspect(
                    $path,
                    'ventas.csv'
                );

            expect(
                $result['reader_configuration']['delimiter']
            )->toBe('tab');

            expect(
                $result['reader_configuration']['delimiter_character']
            )->toBe("\t");

            expect(
                $result['sheets'][0]['column_count']
            )->toBe(3);
        } finally {
            @unlink($path);
        }
    }
);

it(
    'detects pipe delimited csv',
    function (): void {
        $path =
            dtbiTempSourceFile(
                '.csv'
            );

        try {
            file_put_contents(
                $path,
                "CODIGO|NOMBRE|BALANCE\n"
                ."1|Cliente Uno|50\n"
                ."2|Cliente Dos|75\n"
            );

            $result =
                dtbiSourceReader()->inspect(
                    $path,
                    'cuentas.csv'
                );

            expect(
                $result['reader_configuration']['delimiter']
            )->toBe('pipe');

            expect(
                $result['reader_configuration']['delimiter_character']
            )->toBe('|');
        } finally {
            @unlink($path);
        }
    }
);

it(
    'detects utf8 bom without exposing bom in first header',
    function (): void {
        $path =
            dtbiTempSourceFile(
                '.csv'
            );

        try {
            file_put_contents(
                $path,
                "\xEF\xBB\xBFCODIGO,NOMBRE\n"
                ."1,Cliente Uno\n"
            );

            $result =
                dtbiSourceReader()->inspect(
                    $path,
                    'clientes.csv'
                );

            expect(
                $result['reader_configuration']['encoding']
            )->toBe('UTF-8-BOM');

            expect(
                $result['reader_configuration']['has_bom']
            )->toBeTrue();

            expect(
                $result['sheets'][0]['headers'][0]
            )->toBe('CODIGO');
        } finally {
            @unlink($path);
        }
    }
);

it(
    'supports windows 1252 legacy csv headers',
    function (): void {
        $path =
            dtbiTempSourceFile(
                '.csv'
            );

        try {
            $utf8 =
                "CÓDIGO;RAZÓN SOCIAL\n"
                ."1;José Pérez\n";

            $legacy =
                mb_convert_encoding(
                    $utf8,
                    'Windows-1252',
                    'UTF-8'
                );

            file_put_contents(
                $path,
                $legacy
            );

            $result =
                dtbiSourceReader()->inspect(
                    $path,
                    'clientes.csv'
                );

            expect(
                $result['reader_configuration']['encoding']
            )->toBe('Windows-1252');

            expect(
                $result['reader_configuration']['delimiter']
            )->toBe('semicolon');

            expect(
                $result['sheets'][0]['headers']
            )->toBe([
                'CÓDIGO',
                'RAZÓN SOCIAL',
            ]);
        } finally {
            @unlink($path);
        }
    }
);

it(
    'discovers all xlsx sheets without requiring canonical sheet names',
    function (): void {
        $path =
            dtbiTempSourceFile(
                '.xlsx'
            );

        $spreadsheet =
            new Spreadsheet();

        try {
            $first =
                $spreadsheet->getActiveSheet();

            $first->setTitle(
                'Maestro Clientes'
            );

            $first->setCellValue(
                'A1',
                'COD_CTE'
            );

            $first->setCellValue(
                'B1',
                'RAZON'
            );

            $first->setCellValue(
                'A2',
                '001'
            );

            $first->setCellValue(
                'B2',
                'Cliente Uno'
            );

            $first->setCellValue(
                'A3',
                '002'
            );

            $first->setCellValue(
                'B3',
                'Cliente Dos'
            );

            $second =
                $spreadsheet->createSheet();

            $second->setTitle(
                'Catálogos'
            );

            $second->setCellValue(
                'A1',
                'TIPO'
            );

            $second->setCellValue(
                'B1',
                'DESCRIPCION'
            );

            $second->setCellValue(
                'C1',
                'ACTIVO'
            );

            $second->setCellValue(
                'A2',
                'A'
            );

            $second->setCellValue(
                'B2',
                'Mayorista'
            );

            $second->setCellValue(
                'C2',
                'S'
            );

            $writer =
                new Xlsx(
                    $spreadsheet
                );

            $writer->save(
                $path
            );

            $result =
                dtbiSourceReader()->inspect(
                    $path,
                    'clientes-original.xlsx'
                );

            expect($result['format'])
                ->toBe('xlsx');

            expect(
                $result['reader_configuration']['sheet_count']
            )->toBe(2);

            expect(
                $result['reader_configuration']['selected_sheet']
            )->toBeNull();

            expect($result['sheets'])
                ->toHaveCount(2);

            expect(
                $result['sheets'][0]['name']
            )->toBe('Maestro Clientes');

            expect(
                $result['sheets'][0]['headers']
            )->toBe([
                'COD_CTE',
                'RAZON',
            ]);

            expect(
                $result['sheets'][0]['row_count']
            )->toBe(2);

            expect(
                $result['sheets'][1]['name']
            )->toBe('Catálogos');

            expect(
                $result['sheets'][1]['headers']
            )->toBe([
                'TIPO',
                'DESCRIPCION',
                'ACTIVO',
            ]);

            expect(
                $result['sheets'][1]['row_count']
            )->toBe(1);

            foreach ($result['sheets'] as $sheet) {
                expect(
                    array_key_exists(
                        'rows',
                        $sheet
                    )
                )->toBeFalse();
            }
        } finally {
            $spreadsheet
                ->disconnectWorksheets();

            @unlink($path);
        }
    }
);

it(
    'rejects unsupported source extensions',
    function (): void {
        $path =
            dtbiTempSourceFile(
                '.txt'
            );

        try {
            file_put_contents(
                $path,
                "CODIGO,NOMBRE\n1,Cliente\n"
            );

            expect(
                fn () =>
                    dtbiSourceReader()
                        ->inspect(
                            $path,
                            'clientes.txt'
                        )
            )->toThrow(
                RuntimeException::class,
                'Formato fuente no soportado'
            );
        } finally {
            @unlink($path);
        }
    }
);
