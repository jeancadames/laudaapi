<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataTransformationBiSourceAssetRowReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DataTransformationBiSourceAssetRowReaderContractTest
    extends TestCase
{
    public function test_reader_streams_csv_with_stable_positional_keys(): void
    {
        $path =
            tempnam(
                sys_get_temp_dir(),
                'dtbi-reader-'
            );

        self::assertNotFalse(
            $path
        );

        file_put_contents(
            $path,
            "Código;Nombre;Nombre\n"
            ."C-1;Cliente Uno;Alias Uno\n"
            .";;\n"
            ."C-2;Cliente Dos;Alias Dos\n"
        );

        try {
            $reader =
                new DataTransformationBiSourceAssetRowReader();

            $rows =
                iterator_to_array(
                    $reader->iterate(
                        $path,
                        'clientes.csv',
                        [
                            'header_row' => 1,
                            'delimiter_character' => ';',
                            'encoding' => 'UTF-8',
                        ],
                        [
                            'sheets' => [
                                [
                                    'index' => 0,
                                    'name' => null,
                                    'total_row_count' => 4,
                                    'row_count' => 3,
                                    'column_count' => 3,
                                ],
                            ],
                        ],
                        0
                    ),
                    false
                );

            $this->assertCount(
                2,
                $rows
            );

            $this->assertSame(
                2,
                $rows[0]['source_row_number']
            );

            $this->assertSame(
                [
                    'column_1' => 'C-1',
                    'column_2' => 'Cliente Uno',
                    'column_3' => 'Alias Uno',
                ],
                $rows[0]['values']
            );

            $this->assertSame(
                4,
                $rows[1]['source_row_number']
            );
        } finally {
            @unlink(
                $path
            );
        }
    }

    public function test_csv_rejects_non_zero_sheet_index(): void
    {
        $path =
            tempnam(
                sys_get_temp_dir(),
                'dtbi-reader-'
            );

        self::assertNotFalse(
            $path
        );

        file_put_contents(
            $path,
            "A,B\n1,2\n"
        );

        try {
            $reader =
                new DataTransformationBiSourceAssetRowReader();

            $this->expectException(
                RuntimeException::class
            );

            iterator_to_array(
                $reader->iterate(
                    $path,
                    'test.csv',
                    [
                        'header_row' => 1,
                        'delimiter_character' => ',',
                        'encoding' => 'UTF-8',
                    ],
                    [
                        'sheets' => [
                            [
                                'index' => 0,
                                'name' => null,
                                'total_row_count' => 2,
                                'row_count' => 1,
                                'column_count' => 2,
                            ],
                        ],
                    ],
                    1
                )
            );
        } finally {
            @unlink(
                $path
            );
        }
    }

    public function test_xlsx_reader_bounds_chunks_by_rows_and_cells(): void
    {
        $reader =
            new DataTransformationBiSourceAssetRowReader();

        $method =
            new \ReflectionMethod(
                DataTransformationBiSourceAssetRowReader::class,
                'xlsxChunkRows'
            );

        $method->setAccessible(
            true
        );

        /*
         * Narrow sheets remain capped by the physical-row ceiling.
         */
        $this->assertSame(
            5000,
            $method->invoke(
                $reader,
                1
            )
        );

        $this->assertSame(
            5000,
            $method->invoke(
                $reader,
                6
            )
        );

        /*
         * At seven columns the cell budget becomes the limiting factor:
         *
         * floor(32768 / 7) = 4681.
         */
        $this->assertSame(
            4681,
            $method->invoke(
                $reader,
                7
            )
        );

        $this->assertSame(
            327,
            $method->invoke(
                $reader,
                100
            )
        );

        $this->assertSame(
            32,
            $method->invoke(
                $reader,
                1000
            )
        );

        /*
         * Maximum XLSX width is still safely bounded to a small window.
         */
        $this->assertSame(
            2,
            $method->invoke(
                $reader,
                16384
            )
        );

        /*
         * Defensive contract for malformed/zero metadata.
         */
        $this->assertSame(
            5000,
            $method->invoke(
                $reader,
                0
            )
        );
    }


    public function test_real_wide_xlsx_crosses_dynamic_chunk_boundary_without_row_loss_or_duplication(): void
    {
        /*
         * 100 columns force the production XLSX reader to use:
         *
         * floor(32768 / 100) = 327 physical data rows per chunk.
         *
         * With one header plus 330 data rows:
         *
         * - first data chunk: physical rows 2..328;
         * - second data chunk: physical rows 329..331.
         *
         * This fixture therefore crosses the exact cell-boundary logic
         * using a real XLSX file written by PhpSpreadsheet.
         */
        $columnCount =
            100;

        $dataRowCount =
            330;

        $placeholder =
            tempnam(
                sys_get_temp_dir(),
                'dtbi-wide-xlsx-'
            );

        self::assertNotFalse(
            $placeholder
        );

        $xlsxPath =
            $placeholder.'.xlsx';

        @unlink(
            $placeholder
        );

        $spreadsheet =
            new Spreadsheet();

        try {
            $sheet =
                $spreadsheet
                    ->getActiveSheet();

            $sheet->setTitle(
                'Clientes'
            );

            $headers = [];

            for (
                $column = 1;
                $column <= $columnCount;
                $column++
            ) {
                $headers[] =
                    sprintf(
                        'Columna %03d',
                        $column
                    );
            }

            $sheet->fromArray(
                $headers,
                null,
                'A1'
            );

            for (
                $dataRow = 1;
                $dataRow <= $dataRowCount;
                $dataRow++
            ) {
                $values = [];

                for (
                    $column = 1;
                    $column <= $columnCount;
                    $column++
                ) {
                    $values[] =
                        sprintf(
                            'R%03dC%03d',
                            $dataRow,
                            $column
                        );
                }

                $sheet->fromArray(
                    $values,
                    null,
                    'A'.($dataRow + 1)
                );
            }

            $writer =
                new Xlsx(
                    $spreadsheet
                );

            $writer->save(
                $xlsxPath
            );
        } finally {
            $spreadsheet
                ->disconnectWorksheets();

            unset(
                $spreadsheet
            );
        }

        try {
            $reader =
                new DataTransformationBiSourceAssetRowReader();

            $sourceRowNumbers = [];

            $boundaryValues = [];

            $yieldedCount =
                0;

            foreach (
                $reader->iterate(
                    $xlsxPath,
                    'wide-clientes.xlsx',
                    [
                        'header_row' => 1,
                    ],
                    [
                        'sheets' => [
                            [
                                'index' =>
                                    0,

                                'name' =>
                                    'Clientes',

                                'total_row_count' =>
                                    331,

                                'row_count' =>
                                    330,

                                'column_count' =>
                                    100,
                            ],
                        ],
                    ],
                    0
                )
                as $row
            ) {
                $yieldedCount++;

                $sourceRowNumber =
                    (int) $row[
                        'source_row_number'
                    ];

                $sourceRowNumbers[] =
                    $sourceRowNumber;

                if (
                    in_array(
                        $sourceRowNumber,
                        [
                            328,
                            329,
                            331,
                        ],
                        true
                    )
                ) {
                    $boundaryValues[
                        $sourceRowNumber
                    ] =
                        $row['values'];
                }
            }

            /*
             * Every physical data row must be emitted exactly once.
             */
            $this->assertSame(
                330,
                $yieldedCount
            );

            $this->assertSame(
                range(
                    2,
                    331
                ),
                $sourceRowNumbers
            );

            $this->assertSame(
                330,
                count(
                    array_unique(
                        $sourceRowNumbers
                    )
                )
            );

            /*
             * Physical row 328 is the last row of chunk 1.
             *
             * It represents data row 327 because physical row 1 is
             * the header.
             */
            $this->assertSame(
                'R327C001',
                $boundaryValues[328][
                    'column_1'
                ]
            );

            $this->assertSame(
                'R327C100',
                $boundaryValues[328][
                    'column_100'
                ]
            );

            /*
             * Physical row 329 is the FIRST row of chunk 2.
             *
             * If the chunk arithmetic skips or repeats the boundary,
             * these assertions fail.
             */
            $this->assertSame(
                'R328C001',
                $boundaryValues[329][
                    'column_1'
                ]
            );

            $this->assertSame(
                'R328C100',
                $boundaryValues[329][
                    'column_100'
                ]
            );

            /*
             * Final physical row must also survive the second chunk.
             */
            $this->assertSame(
                'R330C001',
                $boundaryValues[331][
                    'column_1'
                ]
            );

            $this->assertSame(
                'R330C100',
                $boundaryValues[331][
                    'column_100'
                ]
            );
        } finally {
            @unlink(
                $xlsxPath
            );
        }
    }

}
