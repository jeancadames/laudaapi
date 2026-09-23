<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataTransformationBiSourceFileReader;
use App\Services\Diagnosis\DataTransformationBiSourceValueProfiler;
use App\Services\Diagnosis\DataTransformationBiStandardIntakeResourceGuard;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

final class DataTransformationBiSourceValueProfilerTest
    extends TestCase
{
    public function test_csv_profiles_native_values_without_persisting_raw_samples(): void
    {
        $path =
            tempnam(
                sys_get_temp_dir(),
                'lauda-source-profile-csv-'
            );

        self::assertNotFalse(
            $path
        );

        file_put_contents(
            $path,
            implode(
                "\n",
                [
                    'Id,Nombre,Email',
                    '1,Ana,ana@example.com',
                    '2,Beto,',
                    '3,Carla,carla@example.com',
                ]
            )
        );

        try {
            $guard =
                new DataTransformationBiStandardIntakeResourceGuard();

            $reader =
                new DataTransformationBiSourceFileReader(
                    $guard
                );

            $inspection =
                $reader->inspect(
                    $path,
                    'clientes.csv'
                );

            $profiler =
                new DataTransformationBiSourceValueProfiler(
                    $guard
                );

            $profile =
                $profiler->profile(
                    $path,
                    'clientes.csv',
                    $inspection['reader_configuration'],
                    [
                        'sheets' =>
                            $inspection['sheets'],
                    ]
                );

            self::assertSame(
                1,
                $profile['version']
            );

            self::assertSame(
                'source_asset_value_profile',
                $profile['kind']
            );

            self::assertSame(
                'csv',
                $profile['format']
            );

            self::assertFalse(
                $profile['contains_raw_values']
            );

            self::assertFalse(
                $profile['contains_sample_values']
            );

            self::assertTrue(
                $profile['full_scan']
            );

            self::assertSame(
                3,
                $profile['source_row_count']
            );

            self::assertSame(
                3,
                $profile['profiled_row_count']
            );

            $columns =
                $profile['sheets'][0]['columns'];

            self::assertSame(
                'Id',
                $columns[0]['header']
            );

            self::assertSame(
                3,
                $columns[0]['primitive_types']['integer']
            );

            self::assertSame(
                1,
                $columns[2]['empty_count']
            );

            self::assertSame(
                2,
                $columns[2]['non_empty_count']
            );

            self::assertArrayNotHasKey(
                'samples',
                $columns[2]
            );

            self::assertArrayNotHasKey(
                'values',
                $columns[2]
            );
        } finally {
            @unlink(
                $path
            );
        }
    }

    public function test_xlsx_profiles_native_sheet_values_without_canonical_domain(): void
    {
        $path =
            tempnam(
                sys_get_temp_dir(),
                'lauda-source-profile-xlsx-'
            );

        self::assertNotFalse(
            $path
        );

        $xlsxPath =
            $path.'.xlsx';

        @unlink(
            $path
        );

        $spreadsheet =
            new Spreadsheet();

        $sheet =
            $spreadsheet->getActiveSheet();

        $sheet->setTitle(
            'Clientes'
        );

        $sheet->fromArray(
            [
                [
                    'Id',
                    'Nombre',
                    'Balance',
                ],
                [
                    1,
                    'Ana',
                    125.50,
                ],
                [
                    2,
                    'Beto',
                    0,
                ],
                [
                    3,
                    '',
                    89.25,
                ],
            ],
            null,
            'A1'
        );

        /*
         * Keep numeric zero explicit in the XLSX fixture.
         *
         * The profiling contract requires 0 to be treated as a real,
         * non-empty business value. Writing the cell explicitly keeps
         * this test independent from helper-array serialization details.
         */
        $sheet->setCellValue(
            'C3',
            0
        );

        $writer =
            new Xlsx(
                $spreadsheet
            );

        $writer->save(
            $xlsxPath
        );

        $spreadsheet
            ->disconnectWorksheets();

        try {
            $guard =
                new DataTransformationBiStandardIntakeResourceGuard();

            $reader =
                new DataTransformationBiSourceFileReader(
                    $guard
                );

            $inspection =
                $reader->inspect(
                    $xlsxPath,
                    'clientes.xlsx'
                );

            $profiler =
                new DataTransformationBiSourceValueProfiler(
                    $guard
                );

            $profile =
                $profiler->profile(
                    $xlsxPath,
                    'clientes.xlsx',
                    $inspection['reader_configuration'],
                    [
                        'sheets' =>
                            $inspection['sheets'],
                    ]
                );

            self::assertSame(
                'xlsx',
                $profile['format']
            );

            self::assertSame(
                1,
                $profile['sheet_count']
            );

            self::assertSame(
                3,
                $profile['source_row_count']
            );

            self::assertSame(
                3,
                $profile['profiled_row_count']
            );

            self::assertTrue(
                $profile['full_scan']
            );

            $columns =
                $profile['sheets'][0]['columns'];

            self::assertSame(
                'Nombre',
                $columns[1]['header']
            );

            self::assertSame(
                1,
                $columns[1]['empty_count']
            );

            self::assertSame(
                2,
                $columns[1]['primitive_types']['text']
            );

            self::assertSame(
                3,
                $columns[2]['non_empty_count']
            );

            self::assertFalse(
                $profile['contains_raw_values']
            );
        } finally {
            @unlink(
                $xlsxPath
            );
        }
    }

    public function test_xlsx_profiles_multiple_dynamic_sheets(): void
    {
        $base =
            tempnam(
                sys_get_temp_dir(),
                'lauda-source-profile-multi-'
            );

        self::assertNotFalse(
            $base
        );

        $path =
            $base.'.xlsx';

        @unlink(
            $base
        );

        $spreadsheet =
            new Spreadsheet();

        $clientes =
            $spreadsheet->getActiveSheet();

        $clientes->setTitle(
            'Clientes'
        );

        $clientes->fromArray(
            [
                ['Id', 'Nombre'],
                [1, 'Ana'],
                [2, 'Beto'],
            ],
            null,
            'A1'
        );

        $ventas =
            $spreadsheet->createSheet();

        $ventas->setTitle(
            'Ventas 2026'
        );

        $ventas->fromArray(
            [
                ['Factura', 'Monto'],
                ['F001', 100.50],
                ['F002', 0],
                ['F003', 250],
            ],
            null,
            'A1'
        );

        /*
         * Keep zero explicit in the generated XLSX fixture.
         * Zero is a valid non-empty business value.
         */
        $ventas->setCellValue(
            'B3',
            0
        );

        $writer =
            new Xlsx(
                $spreadsheet
            );

        $writer->save(
            $path
        );

        $spreadsheet
            ->disconnectWorksheets();

        try {
            $guard =
                new DataTransformationBiStandardIntakeResourceGuard();

            $reader =
                new DataTransformationBiSourceFileReader(
                    $guard
                );

            $inspection =
                $reader->inspect(
                    $path,
                    'empresa.xlsx'
                );

            $profiler =
                new DataTransformationBiSourceValueProfiler(
                    $guard
                );

            $profile =
                $profiler->profile(
                    $path,
                    'empresa.xlsx',
                    $inspection['reader_configuration'],
                    [
                        'sheets' =>
                            $inspection['sheets'],
                    ]
                );

            self::assertSame(
                2,
                $profile['sheet_count']
            );

            self::assertSame(
                5,
                $profile['source_row_count']
            );

            self::assertSame(
                5,
                $profile['profiled_row_count']
            );

            self::assertSame(
                'Clientes',
                $profile['sheets'][0]['name']
            );

            self::assertSame(
                'Ventas 2026',
                $profile['sheets'][1]['name']
            );

            self::assertSame(
                3,
                $profile['sheets'][1]
                    ['columns'][1]
                    ['non_empty_count']
            );
        } finally {
            @unlink(
                $path
            );
        }
    }

    public function test_csv_preserves_duplicate_and_blank_headers_during_profiling(): void
    {
        $path =
            tempnam(
                sys_get_temp_dir(),
                'lauda-source-profile-headers-'
            );

        self::assertNotFalse(
            $path
        );

        file_put_contents(
            $path,
            implode(
                "\n",
                [
                    'Codigo,Codigo,,Nombre',
                    '1,A,,Ana',
                    '2,B,X,Beto',
                ]
            )
        );

        try {
            $guard =
                new DataTransformationBiStandardIntakeResourceGuard();

            $reader =
                new DataTransformationBiSourceFileReader(
                    $guard
                );

            $inspection =
                $reader->inspect(
                    $path,
                    'legacy.csv'
                );

            $profiler =
                new DataTransformationBiSourceValueProfiler(
                    $guard
                );

            $profile =
                $profiler->profile(
                    $path,
                    'legacy.csv',
                    $inspection['reader_configuration'],
                    [
                        'sheets' =>
                            $inspection['sheets'],
                    ]
                );

            $columns =
                $profile['sheets'][0]['columns'];

            self::assertSame(
                4,
                $profile['sheets'][0]['column_count']
            );

            self::assertSame(
                'Codigo',
                $columns[0]['header']
            );

            self::assertSame(
                'Codigo',
                $columns[1]['header']
            );

            self::assertSame(
                '',
                $columns[2]['header']
            );

            self::assertSame(
                1,
                $columns[2]['empty_count']
            );

            self::assertSame(
                1,
                $columns[2]['non_empty_count']
            );
        } finally {
            @unlink(
                $path
            );
        }
    }

    public function test_large_csv_uses_bounded_deterministic_profiling(): void
    {
        $path =
            tempnam(
                sys_get_temp_dir(),
                'lauda-source-profile-large-'
            );

        self::assertNotFalse(
            $path
        );

        $stream =
            fopen(
                $path,
                'wb'
            );

        self::assertNotFalse(
            $stream
        );

        try {
            fputcsv(
                $stream,
                [
                    'Id',
                    'Nombre',
                ],
                ',',
                '"',
                ''
            );

            for (
                $i = 1;
                $i <= 50001;
                $i++
            ) {
                fputcsv(
                    $stream,
                    [
                        $i,
                        'Cliente '.$i,
                    ],
                    ',',
                    '"',
                    ''
                );
            }
        } finally {
            fclose(
                $stream
            );
        }

        try {
            $guard =
                new DataTransformationBiStandardIntakeResourceGuard();

            $reader =
                new DataTransformationBiSourceFileReader(
                    $guard
                );

            $inspection =
                $reader->inspect(
                    $path,
                    'clientes-grande.csv'
                );

            $profiler =
                new DataTransformationBiSourceValueProfiler(
                    $guard
                );

            $profile =
                $profiler->profile(
                    $path,
                    'clientes-grande.csv',
                    $inspection['reader_configuration'],
                    [
                        'sheets' =>
                            $inspection['sheets'],
                    ]
                );

            self::assertSame(
                50001,
                $profile['source_row_count']
            );

            self::assertFalse(
                $profile['full_scan']
            );

            self::assertSame(
                2,
                $profile['sheets'][0]['sampling_stride']
            );

            self::assertLessThanOrEqual(
                DataTransformationBiSourceValueProfiler
                    ::MAX_PROFILE_ROWS_PER_SHEET,
                $profile['profiled_row_count']
            );

            self::assertGreaterThan(
                0,
                $profile['profiled_row_count']
            );

            self::assertTrue(
                $profile['sheets'][0]['truncated']
            );

            self::assertFalse(
                $profile['contains_raw_values']
            );

            self::assertFalse(
                $profile['contains_sample_values']
            );
        } finally {
            @unlink(
                $path
            );
        }
    }


    public function test_xlsx_chunk_size_is_bounded_by_source_width(): void
    {
        $guard =
            new DataTransformationBiStandardIntakeResourceGuard();

        $profiler =
            new DataTransformationBiSourceValueProfiler(
                $guard
            );

        $method =
            new \ReflectionMethod(
                DataTransformationBiSourceValueProfiler::class,
                'xlsxChunkPhysicalRows'
            );

        $wideChunk =
            $method->invoke(
                $profiler,
                148,
                1
            );

        self::assertIsInt(
            $wideChunk
        );

        self::assertGreaterThan(
            0,
            $wideChunk
        );

        self::assertLessThan(
            10000,
            $wideChunk
        );

        self::assertLessThanOrEqual(
            32768,
            $wideChunk * 148
        );

        /*
         * For the real CTES width, one profiling load must now stay around
         * tens of thousands of cells rather than loading all 5,309 x 148
         * source positions at once.
         */
        self::assertLessThan(
            5309,
            $wideChunk
        );

        $sampledChunk =
            $method->invoke(
                $profiler,
                148,
                5
            );

        self::assertSame(
            0,
            $sampledChunk % 5
        );

        self::assertLessThanOrEqual(
            32768,
            intdiv(
                $sampledChunk,
                5
            ) * 148
        );

        $narrowChunk =
            $method->invoke(
                $profiler,
                3,
                1
            );

        self::assertSame(
            10000,
            $narrowChunk
        );
    }

}
