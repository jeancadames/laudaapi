<?php

namespace App\Services\Diagnosis;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use RuntimeException;
use Throwable;

final class DataTransformationBiSourceValueProfiler
{
    public const PROFILE_VERSION = 1;

    /*
     * Profiling is intentionally bounded.
     *
     * Small and medium files are scanned completely. Larger sources are
     * profiled deterministically across the source using a row stride.
     */
    public const MAX_PROFILE_ROWS_PER_SHEET = 50000;

    /*
     * XLSX is read in bounded row windows instead of loading an entire
     * potentially large worksheet into memory.
     *
     * The row window alone is not sufficient because worksheet width can
     * vary dramatically. A 10,000-row chunk with 148 columns can create
     * well over one million PhpSpreadsheet cell objects.
     *
     * Keep both:
     * - an upper bound on physical rows scanned per load;
     * - an upper bound on sampled cells materialized per load.
     */
    private const MAX_XLSX_CHUNK_PHYSICAL_ROWS = 10000;

    private const MAX_XLSX_CELLS_PER_CHUNK = 32768;

    public function __construct(
        private readonly DataTransformationBiStandardIntakeResourceGuard
            $resourceGuard
    ) {
    }

    /**
     * Profile client-native business values without requiring:
     * - canonical headers;
     * - a canonical domain;
     * - a fixed number of sources;
     * - persisted raw-row copies.
     *
     * Raw values and samples are deliberately absent from the returned
     * snapshot. Only aggregate technical statistics are persisted later.
     *
     * @param array<string,mixed> $readerConfiguration
     * @param array<string,mixed> $structureSnapshot
     *
     * @return array<string,mixed>
     */
    public function profile(
        string $path,
        string $originalName,
        array $readerConfiguration,
        array $structureSnapshot
    ): array {
        if (! is_file($path)) {
            throw new RuntimeException(
                'El archivo fuente no está disponible para profiling.'
            );
        }

        $extension =
            strtolower(
                pathinfo(
                    $originalName,
                    PATHINFO_EXTENSION
                )
            );

        if (
            ! in_array(
                $extension,
                [
                    'csv',
                    'xlsx',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'El profiling solo admite archivos CSV o XLSX.'
            );
        }

        $sheets =
            $structureSnapshot['sheets']
            ?? [];

        if (! is_array($sheets) || $sheets === []) {
            throw new RuntimeException(
                'La fuente no contiene metadata estructural suficiente para profiling.'
            );
        }

        if ($extension === 'xlsx') {
            $guard =
                $this->resourceGuard
                    ->validate(
                        $path,
                        $originalName
                    );

            if (($guard['valid'] ?? false) !== true) {
                $errors =
                    array_values(
                        array_filter(
                            array_map(
                                static fn (mixed $error): string =>
                                    trim(
                                        (string) $error
                                    ),
                                $guard['errors']
                                ?? []
                            )
                        )
                    );

                throw new RuntimeException(
                    $errors !== []
                        ? implode(' ', $errors)
                        : 'El XLSX no superó el resource guard.'
                );
            }
        }

        $sheetProfiles =
            $extension === 'csv'
                ? [
                    $this->profileCsv(
                        $path,
                        $readerConfiguration,
                        $sheets
                    ),
                ]
                : $this->profileXlsx(
                    $path,
                    $readerConfiguration,
                    $sheets
                );

        $sourceRowCount = 0;
        $profiledRowCount = 0;
        $fullScan = true;

        foreach ($sheetProfiles as $sheetProfile) {
            $sourceRowCount +=
                (int) (
                    $sheetProfile['source_row_count']
                    ?? 0
                );

            $profiledRowCount +=
                (int) (
                    $sheetProfile['profiled_row_count']
                    ?? 0
                );

            if (
                ($sheetProfile['truncated'] ?? false)
                === true
            ) {
                $fullScan = false;
            }
        }

        return [
            'version' =>
                self::PROFILE_VERSION,

            'kind' =>
                'source_asset_value_profile',

            'format' =>
                $extension,

            /*
             * Explicit privacy/data-minimization contract.
             */
            'contains_raw_values' =>
                false,

            'contains_sample_values' =>
                false,

            'max_profile_rows_per_sheet' =>
                self::MAX_PROFILE_ROWS_PER_SHEET,

            'sheet_count' =>
                count(
                    $sheetProfiles
                ),

            'source_row_count' =>
                $sourceRowCount,

            'profiled_row_count' =>
                $profiledRowCount,

            'full_scan' =>
                $fullScan,

            'sheets' =>
                $sheetProfiles,
        ];
    }

    /**
     * @param array<string,mixed> $readerConfiguration
     * @param array<int,mixed> $sheets
     *
     * @return array<string,mixed>
     */
    private function profileCsv(
        string $path,
        array $readerConfiguration,
        array $sheets
    ): array {
        $sheetMetadata =
            $this->normalizeSheetMetadata(
                $sheets[0]
                ?? [],
                0
            );

        $sourceRowCount =
            (int) (
                $sheetMetadata['row_count']
                ?? 0
            );

        $stride =
            $this->profileStride(
                $sourceRowCount
            );

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

        $profile =
            $this->newSheetProfile(
                $sheetMetadata,
                $sourceRowCount,
                $stride
            );

        $stream =
            fopen(
                $path,
                'rb'
            );

        if ($stream === false) {
            throw new RuntimeException(
                'No se pudo abrir el CSV para profiling.'
            );
        }

        $physicalRow = 0;
        $logicalRow = 0;

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

                if (
                    $this->isEmptyCsvRow(
                        $row
                    )
                ) {
                    continue;
                }

                $logicalRow++;

                if (
                    (
                        ($logicalRow - 1)
                        % $stride
                    ) !== 0
                ) {
                    continue;
                }

                $normalizedRow = [];

                foreach ($row as $value) {
                    $normalizedRow[] =
                        $this->normalizeCsvValue(
                            $value,
                            $encoding
                        );
                }

                $this->profileRow(
                    $profile,
                    $normalizedRow
                );
            }
        } finally {
            fclose(
                $stream
            );
        }

        /*
         * Prefer the observed logical count when available so a stale
         * structural count cannot silently become authoritative.
         */
        if ($logicalRow > 0) {
            $profile['source_row_count'] =
                $logicalRow;

            $profile['truncated'] =
                $logicalRow
                    > (int) $profile[
                        'profiled_row_count'
                    ];
        }

        return $this->finalizeSheetProfile(
            $profile
        );
    }

    /**
     * @param array<string,mixed> $readerConfiguration
     * @param array<int,mixed> $sheets
     *
     * @return array<int,array<string,mixed>>
     */
    private function profileXlsx(
        string $path,
        array $readerConfiguration,
        array $sheets
    ): array {
        $headerRow =
            max(
                1,
                (int) (
                    $readerConfiguration['header_row']
                    ?? 1
                )
            );

        $profiles = [];

        foreach (
            array_values(
                $sheets
            )
            as $sheetOffset => $sheet
        ) {
            $sheetMetadata =
                $this->normalizeSheetMetadata(
                    $sheet,
                    $sheetOffset
                );

            $sheetName =
                $sheetMetadata['name']
                ?? null;

            if (
                ! is_string(
                    $sheetName
                )
                || trim(
                    $sheetName
                ) === ''
            ) {
                throw new RuntimeException(
                    'El XLSX contiene una hoja sin nombre válido.'
                );
            }

            $sourceRowCount =
                max(
                    0,
                    (int) (
                        $sheetMetadata['row_count']
                        ?? 0
                    )
                );

            $stride =
                $this->profileStride(
                    $sourceRowCount
                );

            $profile =
                $this->newSheetProfile(
                    $sheetMetadata,
                    $sourceRowCount,
                    $stride
                );

            $dataStartRow =
                $headerRow + 1;

            $lastPhysicalRow =
                max(
                    $headerRow,
                    (int) (
                        $sheetMetadata[
                            'total_row_count'
                        ]
                        ?? (
                            $sourceRowCount
                            + $headerRow
                        )
                    )
                );

            if ($lastPhysicalRow < $dataStartRow) {
                $profiles[] =
                    $this->finalizeSheetProfile(
                        $profile
                    );

                continue;
            }

            $columnCount =
                max(
                    1,
                    (int) (
                        $profile['column_count']
                        ?? 0
                    )
                );

            $chunkPhysicalRows =
                $this->xlsxChunkPhysicalRows(
                    $columnCount,
                    $stride
                );

            for (
                $chunkStart = $dataStartRow;
                $chunkStart <= $lastPhysicalRow;
                $chunkStart +=
                    $chunkPhysicalRows
            ) {
                $chunkEnd =
                    min(
                        $lastPhysicalRow,
                        $chunkStart
                            + $chunkPhysicalRows
                            - 1
                    );

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
                        $dataStartRow,
                        $stride,
                        $columnCount
                    ) implements IReadFilter {
                        public function __construct(
                            private readonly string $sheetName,
                            private readonly int $startRow,
                            private readonly int $endRow,
                            private readonly int $dataStartRow,
                            private readonly int $stride,
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

                            if (
                                Coordinate::columnIndexFromString(
                                    $columnAddress
                                )
                                > $this->maxColumnIndex
                            ) {
                                return false;
                            }

                            return (
                                (
                                    $row
                                    - $this->dataStartRow
                                )
                                % $this->stride
                            ) === 0;
                        }
                    }
                );

                $spreadsheet = null;
                $worksheet = null;

                try {
                    $spreadsheet =
                        $reader->load(
                            $path
                        );

                    $worksheet =
                        $spreadsheet
                            ->getSheetByName(
                                $sheetName
                            );

                    if ($worksheet === null) {
                        throw new RuntimeException(
                            "No se pudo cargar la hoja {$sheetName}."
                        );
                    }

                    for (
                        $rowNumber = $chunkStart;
                        $rowNumber <= $chunkEnd;
                        $rowNumber++
                    ) {
                        if (
                            (
                                (
                                    $rowNumber
                                    - $dataStartRow
                                )
                                % $stride
                            ) !== 0
                        ) {
                            continue;
                        }

                        $row = [];

                        $columnCount =
                            (int) (
                                $profile['column_count']
                                ?? 0
                            );

                        for (
                            $columnIndex = 1;
                            $columnIndex <= $columnCount;
                            $columnIndex++
                        ) {
                            $coordinate =
                                Coordinate::stringFromColumnIndex(
                                    $columnIndex
                                )
                                .$rowNumber;

                            $row[] =
                                $worksheet
                                    ->cellExists(
                                        $coordinate
                                    )
                                    ? $worksheet
                                        ->getCell(
                                            $coordinate
                                        )
                                        ->getValue()
                                    : null;
                        }

                        $this->profileRow(
                            $profile,
                            $row
                        );
                    }
                } catch (Throwable $exception) {
                    throw new RuntimeException(
                        "No se pudo perfilar la hoja {$sheetName}: "
                        .$exception->getMessage(),
                        0,
                        $exception
                    );
                } finally {
                    if ($spreadsheet !== null) {
                        $spreadsheet
                            ->disconnectWorksheets();
                    }

                    unset(
                        $worksheet,
                        $spreadsheet,
                        $reader
                    );

                    gc_collect_cycles();
                }
            }

            $profiles[] =
                $this->finalizeSheetProfile(
                    $profile
                );
        }

        return $profiles;
    }

    private function xlsxChunkPhysicalRows(
        int $columnCount,
        int $stride
    ): int {
        $safeColumnCount =
            max(
                1,
                $columnCount
            );

        $safeStride =
            max(
                1,
                $stride
            );

        $sampledRowsPerChunk =
            max(
                1,
                intdiv(
                    self::MAX_XLSX_CELLS_PER_CHUNK,
                    $safeColumnCount
                )
            );

        /*
         * A sampled row represents one physical row every $stride rows.
         * Keeping the chunk length aligned to stride preserves deterministic
         * sampling across chunk boundaries.
         */
        $physicalRows =
            $sampledRowsPerChunk
            * $safeStride;

        return max(
            $safeStride,
            min(
                self::MAX_XLSX_CHUNK_PHYSICAL_ROWS,
                $physicalRows
            )
        );
    }

    private function profileStride(
        int $sourceRowCount
    ): int {
        if (
            $sourceRowCount
            <= self::MAX_PROFILE_ROWS_PER_SHEET
        ) {
            return 1;
        }

        return max(
            1,
            (int) ceil(
                $sourceRowCount
                / self::MAX_PROFILE_ROWS_PER_SHEET
            )
        );
    }

    /**
     * @param mixed $sheet
     *
     * @return array<string,mixed>
     */
    private function normalizeSheetMetadata(
        mixed $sheet,
        int $fallbackIndex
    ): array {
        if (! is_array($sheet)) {
            throw new RuntimeException(
                'La metadata de hoja no es válida.'
            );
        }

        $columns =
            $sheet['columns']
            ?? [];

        if (! is_array($columns)) {
            $columns = [];
        }

        $columnCount =
            max(
                (int) (
                    $sheet['column_count']
                    ?? 0
                ),
                count(
                    $columns
                )
            );

        return [
            'index' =>
                (int) (
                    $sheet['index']
                    ?? $fallbackIndex
                ),

            'name' =>
                $sheet['name']
                ?? null,

            'row_count' =>
                max(
                    0,
                    (int) (
                        $sheet['row_count']
                        ?? 0
                    )
                ),

            'total_row_count' =>
                max(
                    0,
                    (int) (
                        $sheet['total_row_count']
                        ?? 0
                    )
                ),

            'column_count' =>
                $columnCount,

            'columns' =>
                $columns,
        ];
    }

    /**
     * @param array<string,mixed> $sheetMetadata
     *
     * @return array<string,mixed>
     */
    private function newSheetProfile(
        array $sheetMetadata,
        int $sourceRowCount,
        int $stride
    ): array {
        $columns = [];

        $metadataColumns =
            $sheetMetadata['columns']
            ?? [];

        $columnCount =
            (int) (
                $sheetMetadata['column_count']
                ?? 0
            );

        for (
            $index = 1;
            $index <= $columnCount;
            $index++
        ) {
            $metadata =
                is_array(
                    $metadataColumns[
                        $index - 1
                    ]
                    ?? null
                )
                    ? $metadataColumns[
                        $index - 1
                    ]
                    : [];

            $columns[] = [
                'index' =>
                    $index,

                'key' =>
                    (string) (
                        $metadata['key']
                        ?? "column_{$index}"
                    ),

                'header' =>
                    (string) (
                        $metadata['header']
                        ?? ''
                    ),

                'profiled_value_count' =>
                    0,

                'non_empty_count' =>
                    0,

                'empty_count' =>
                    0,

                'min_length' =>
                    null,

                'max_length' =>
                    0,

                'primitive_types' => [
                    'boolean' => 0,
                    'integer' => 0,
                    'decimal' => 0,
                    'text' => 0,
                    'other' => 0,
                ],
            ];
        }

        return [
            'index' =>
                (int) (
                    $sheetMetadata['index']
                    ?? 0
                ),

            'name' =>
                $sheetMetadata['name']
                ?? null,

            'source_row_count' =>
                $sourceRowCount,

            'profiled_row_count' =>
                0,

            'column_count' =>
                $columnCount,

            'sampling_stride' =>
                $stride,

            'truncated' =>
                $sourceRowCount
                    > self::MAX_PROFILE_ROWS_PER_SHEET,

            'columns' =>
                $columns,
        ];
    }

    /**
     * @param array<string,mixed> $profile
     * @param array<int,mixed> $row
     */
    private function profileRow(
        array &$profile,
        array $row
    ): void {
        $profile['profiled_row_count'] =
            (int) (
                $profile['profiled_row_count']
                ?? 0
            ) + 1;

        $columnCount =
            (int) (
                $profile['column_count']
                ?? 0
            );

        for (
            $offset = 0;
            $offset < $columnCount;
            $offset++
        ) {
            $value =
                $row[$offset]
                ?? null;

            $column =
                $profile['columns'][$offset];

            $column['profiled_value_count'] =
                (int) $column[
                    'profiled_value_count'
                ] + 1;

            if (
                $value === null
                || (
                    is_string(
                        $value
                    )
                    && trim(
                        $value
                    ) === ''
                )
            ) {
                $column['empty_count'] =
                    (int) $column[
                        'empty_count'
                    ] + 1;

                $profile['columns'][$offset] =
                    $column;

                continue;
            }

            $column['non_empty_count'] =
                (int) $column[
                    'non_empty_count'
                ] + 1;

            $type =
                $this->primitiveType(
                    $value
                );

            $column[
                'primitive_types'
            ][$type] =
                (int) (
                    $column[
                        'primitive_types'
                    ][$type]
                    ?? 0
                ) + 1;

            $text =
                $this->valueAsText(
                    $value
                );

            $length =
                function_exists(
                    'mb_strlen'
                )
                    ? mb_strlen(
                        $text,
                        'UTF-8'
                    )
                    : strlen(
                        $text
                    );

            $column['max_length'] =
                max(
                    (int) $column[
                        'max_length'
                    ],
                    $length
                );

            if (
                $column['min_length']
                === null
                || $length
                    < (int) $column[
                        'min_length'
                    ]
            ) {
                $column['min_length'] =
                    $length;
            }

            $profile['columns'][$offset] =
                $column;
        }
    }

    /**
     * @param array<string,mixed> $profile
     *
     * @return array<string,mixed>
     */
    private function finalizeSheetProfile(
        array $profile
    ): array {
        $profiledRows =
            (int) (
                $profile['profiled_row_count']
                ?? 0
            );

        foreach (
            $profile['columns']
            as $offset => $column
        ) {
            $nonEmpty =
                (int) (
                    $column['non_empty_count']
                    ?? 0
                );

            $empty =
                (int) (
                    $column['empty_count']
                    ?? 0
                );

            $column['non_empty_ratio'] =
                $profiledRows > 0
                    ? round(
                        $nonEmpty
                        / $profiledRows,
                        6
                    )
                    : 0.0;

            $column['empty_ratio'] =
                $profiledRows > 0
                    ? round(
                        $empty
                        / $profiledRows,
                        6
                    )
                    : 0.0;

            $profile['columns'][$offset] =
                $column;
        }

        return $profile;
    }

    private function primitiveType(
        mixed $value
    ): string {
        if (is_bool($value)) {
            return 'boolean';
        }

        if (is_int($value)) {
            return 'integer';
        }

        if (is_float($value)) {
            return floor($value) === $value
                ? 'integer'
                : 'decimal';
        }

        if (is_string($value)) {
            $trimmed =
                trim(
                    $value
                );

            if (
                preg_match(
                    '/^[+-]?\d+$/',
                    $trimmed
                ) === 1
            ) {
                return 'integer';
            }

            if (
                is_numeric(
                    $trimmed
                )
            ) {
                return 'decimal';
            }

            return 'text';
        }

        return 'other';
    }

    private function valueAsText(
        mixed $value
    ): string {
        if (is_bool($value)) {
            return $value
                ? '1'
                : '0';
        }

        if (
            is_scalar(
                $value
            )
        ) {
            return (string) $value;
        }

        return '';
    }

    /**
     * @param array<int,mixed> $row
     */
    private function isEmptyCsvRow(
        array $row
    ): bool {
        foreach ($row as $value) {
            if (
                $value !== null
                && trim(
                    (string) $value
                ) !== ''
            ) {
                return false;
            }
        }

        return true;
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
            && $encoding !== 'UTF-8'
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
}
