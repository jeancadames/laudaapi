<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataTransformationBiSourceAssetRowReader;
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

}
