<?php

namespace App\Services\Diagnosis;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use RuntimeException;
use Throwable;

final class DataTransformationBiSourceAssetRowReader
{
    /*
     * Follow the bounded XLSX reading principle already used by profiling.
     * This is intentionally independent from Standard Intake domains.
     */
    private const MAX_XLSX_CHUNK_PHYSICAL_ROWS = 5000;

    private const MAX_XLSX_CELLS_PER_CHUNK = 32768;

    /**
     * Iterate one exact physical sheet from one already-inspected
     * client-native source artifact.
     *
     * Values are exposed by stable positional keys:
     *
     * column_1, column_2, ...
     *
     * Headers are display metadata only and therefore cannot make duplicate
     * or blank client headers collide.
     *
     * @param array<string,mixed> $readerConfiguration
     * @param array<string,mixed> $structureSnapshot
     *
     * @return \Generator<int,array{
     *     source_row_number:int,
     *     values:array<string,mixed>
     * }>
     */
    public function iterate(
        string $path,
        string $originalName,
        array $readerConfiguration,
        array $structureSnapshot,
        int $sourceSheetIndex
    ): \Generator {
        if (! is_file($path)) {
            throw new RuntimeException(
                'El archivo fuente no está disponible para lectura.'
            );
        }

        if ($sourceSheetIndex < 0) {
            throw new RuntimeException(
                'El índice de hoja fuente no puede ser negativo.'
            );
        }

        $extension =
            strtolower(
                pathinfo(
                    $originalName,
                    PATHINFO_EXTENSION
                )
            );

        yield from match ($extension) {
            'csv' =>
                $this->iterateCsv(
                    $path,
                    $readerConfiguration,
                    $sourceSheetIndex
                ),

            'xlsx' =>
                $this->iterateXlsx(
                    $path,
                    $readerConfiguration,
                    $structureSnapshot,
                    $sourceSheetIndex
                ),

            default =>
                throw new RuntimeException(
                    'Formato fuente no soportado para lectura de filas.'
                ),
        };
    }

    /**
     * @return \Generator<int,array{
     *     source_row_number:int,
     *     values:array<string,mixed>
     * }>
     */
    private function iterateCsv(
        string $path,
        array $readerConfiguration,
        int $sourceSheetIndex
    ): \Generator {
        if ($sourceSheetIndex !== 0) {
            throw new RuntimeException(
                'CSV solamente admite source_sheet_index=0.'
            );
        }

        $headerRow =
            max(
                1,
                (int) (
                    $readerConfiguration['header_row']
                    ?? 1
                )
            );

        $delimiter =
            (string) (
                $readerConfiguration[
                    'delimiter_character'
                ]
                ?? ','
            );

        if ($delimiter === '') {
            $delimiter = ',';
        }

        $encoding =
            strtoupper(
                trim(
                    (string) (
                        $readerConfiguration['encoding']
                        ?? 'UTF-8'
                    )
                )
            );

        $stream =
            fopen(
                $path,
                'rb'
            );

        if ($stream === false) {
            throw new RuntimeException(
                'No se pudo abrir el archivo CSV fuente.'
            );
        }

        $physicalRow = 0;

        try {
            while (
                (
                    $row =
                        fgetcsv(
                            $stream,
                            null,
                            $delimiter,
                            '"',
                            ''
                        )
                ) !== false
            ) {
                $physicalRow++;

                if ($physicalRow <= $headerRow) {
                    continue;
                }

                $values = [];

                foreach ($row as $offset => $value) {
                    $values[
                        'column_'.($offset + 1)
                    ] =
                        $this->normalizeCsvValue(
                            $value,
                            $encoding
                        );
                }

                if ($this->valuesAreEmpty($values)) {
                    continue;
                }

                yield [
                    'source_row_number' =>
                        $physicalRow,

                    'values' =>
                        $values,
                ];
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * @return \Generator<int,array{
     *     source_row_number:int,
     *     values:array<string,mixed>
     * }>
     */
    private function iterateXlsx(
        string $path,
        array $readerConfiguration,
        array $structureSnapshot,
        int $sourceSheetIndex
    ): \Generator {
        $sheet =
            $this->sheetMetadata(
                $structureSnapshot,
                $sourceSheetIndex
            );

        $sheetName =
            trim(
                (string) (
                    $sheet['name']
                    ?? ''
                )
            );

        if ($sheetName === '') {
            throw new RuntimeException(
                'La hoja XLSX fijada no contiene un nombre válido.'
            );
        }

        $headerRow =
            max(
                1,
                (int) (
                    $readerConfiguration['header_row']
                    ?? 1
                )
            );

        $totalRows =
            max(
                $headerRow,
                (int) (
                    $sheet['total_row_count']
                    ?? 0
                )
            );

        $columnCount =
            max(
                1,
                (int) (
                    $sheet['column_count']
                    ?? count(
                        is_array(
                            $sheet['columns']
                            ?? null
                        )
                            ? $sheet['columns']
                            : []
                    )
                )
            );

        $dataStartRow =
            $headerRow + 1;

        if ($dataStartRow > $totalRows) {
            return;
        }

        $chunkRows =
            $this->xlsxChunkRows(
                $columnCount
            );

        for (
            $chunkStart = $dataStartRow;
            $chunkStart <= $totalRows;
            $chunkStart += $chunkRows
        ) {
            $chunkEnd =
                min(
                    $totalRows,
                    $chunkStart
                        + $chunkRows
                        - 1
                );

            try {
                $reader =
                    IOFactory::createReaderForFile(
                        $path
                    );

                $reader->setReadDataOnly(
                    true
                );

                $reader->setLoadSheetsOnly([
                    $sheetName,
                ]);

                $reader->setReadFilter(
                    new class(
                        $sheetName,
                        $chunkStart,
                        $chunkEnd,
                        $columnCount
                    ) implements IReadFilter {
                        public function __construct(
                            private readonly string $sheetName,
                            private readonly int $startRow,
                            private readonly int $endRow,
                            private readonly int $maxColumnIndex
                        ) {
                        }

                        public function readCell(
                            string $columnAddress,
                            int $row,
                            string $worksheetName = ''
                        ): bool {
                            if (
                                $worksheetName
                                !== $this->sheetName
                            ) {
                                return false;
                            }

                            if (
                                $row < $this->startRow
                                || $row > $this->endRow
                            ) {
                                return false;
                            }

                            return Coordinate
                                ::columnIndexFromString(
                                    $columnAddress
                                )
                                <= $this->maxColumnIndex;
                        }
                    }
                );

                $spreadsheet =
                    $reader->load(
                        $path
                    );
            } catch (Throwable $exception) {
                throw new RuntimeException(
                    'No se pudo leer la hoja XLSX fuente.',
                    0,
                    $exception
                );
            }

            try {
                $worksheet =
                    $spreadsheet
                        ->getSheetByName(
                            $sheetName
                        );

                if ($worksheet === null) {
                    throw new RuntimeException(
                        'La hoja XLSX fijada ya no está disponible.'
                    );
                }

                for (
                    $rowNumber = $chunkStart;
                    $rowNumber <= $chunkEnd;
                    $rowNumber++
                ) {
                    $values = [];

                    for (
                        $column = 1;
                        $column <= $columnCount;
                        $column++
                    ) {
                        $value =
                            $worksheet
                                ->getCell([
                                    $column,
                                    $rowNumber,
                                ])
                                ->getCalculatedValue();

                        $values[
                            "column_{$column}"
                        ] =
                            $value;
                    }

                    if ($this->valuesAreEmpty($values)) {
                        continue;
                    }

                    yield [
                        'source_row_number' =>
                            $rowNumber,

                        'values' =>
                            $values,
                    ];
                }
            } finally {
                $spreadsheet
                    ->disconnectWorksheets();
            }
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function sheetMetadata(
        array $structureSnapshot,
        int $sourceSheetIndex
    ): array {
        $sheets =
            is_array(
                $structureSnapshot['sheets']
                ?? null
            )
                ? $structureSnapshot['sheets']
                : [];

        foreach ($sheets as $sheet) {
            if (
                is_array($sheet)
                && isset($sheet['index'])
                && (int) $sheet['index']
                    === $sourceSheetIndex
            ) {
                return $sheet;
            }
        }

        throw new RuntimeException(
            'El índice de hoja fijado no existe en la estructura fuente.'
        );
    }

    private function normalizeCsvValue(
        mixed $value,
        string $encoding
    ): mixed {
        if ($value === null) {
            return null;
        }

        $text =
            (string) $value;

        if (
            $encoding !== ''
            && ! in_array(
                $encoding,
                [
                    'UTF-8',
                    'UTF-8-BOM',
                ],
                true
            )
            && function_exists(
                'mb_convert_encoding'
            )
        ) {
            $text =
                mb_convert_encoding(
                    $text,
                    'UTF-8',
                    $encoding
                );
        }

        return $text;
    }

    /**
     * @param array<string,mixed> $values
     */
    private function valuesAreEmpty(
        array $values
    ): bool {
        foreach ($values as $value) {
            if (
                trim(
                    (string) (
                        $value
                        ?? ''
                    )
                ) !== ''
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Bound each XLSX load by both physical rows and approximate cells.
     *
     * XLSX permits wide sheets. A fixed row window alone can therefore
     * make one load disproportionately large. The row count is reduced
     * as width grows while always allowing at least one physical row.
     */
    private function xlsxChunkRows(
        int $columnCount
    ): int {
        $safeColumnCount =
            max(
                1,
                $columnCount
            );

        return min(
            self::MAX_XLSX_CHUNK_PHYSICAL_ROWS,
            max(
                1,
                intdiv(
                    self::MAX_XLSX_CELLS_PER_CHUNK,
                    $safeColumnCount
                )
            )
        );
    }

}
