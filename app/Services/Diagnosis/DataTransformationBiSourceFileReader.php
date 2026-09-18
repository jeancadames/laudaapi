<?php

namespace App\Services\Diagnosis;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use RuntimeException;
use Throwable;

final class DataTransformationBiSourceFileReader
{
    /**
     * Bytes used only for CSV dialect/encoding detection.
     * The complete CSV is subsequently streamed; it is not loaded
     * into memory or persisted as raw rows.
     */
    private const CSV_SAMPLE_BYTES = 65536;

    /**
     * Number of non-empty logical sample lines considered when
     * choosing the CSV delimiter.
     */
    private const CSV_SAMPLE_LINES = 10;

    /**
     * Initial source-reader contract.
     *
     * The source layer deliberately accepts client-native headers.
     * Canonical validation happens only after mapping/transformation.
     */
    private const SUPPORTED_EXTENSIONS = [
        'csv',
        'xlsx',
    ];

    public function __construct(
        private readonly DataTransformationBiStandardIntakeResourceGuard
            $resourceGuard
    ) {
    }

    /**
     * Inspect the structure of a client-native CSV/XLSX.
     *
     * This method:
     * - does not require canonical headers;
     * - does not know or validate the canonical domain;
     * - does not persist rows;
     * - does not transform values;
     * - does not choose source-to-canonical mappings.
     *
     * @return array{
     *     format:string,
     *     original_filename:string,
     *     source_size_bytes:int,
     *     source_sha256:string,
     *     reader_configuration:array<string,mixed>,
     *     sheets:array<int,array{
     *         index:int,
     *         name:string|null,
     *         total_row_count:int,
     *         row_count:int,
     *         column_count:int,
     *         headers:array<int,string>,
     *         columns:array<int,array{
     *             index:int,
     *             key:string,
     *             header:string
     *         }>
     *     }>
     * }
     */
    public function inspect(
        string $path,
        string $originalName
    ): array {
        if (! is_file($path)) {
            throw new RuntimeException(
                'El archivo fuente recibido no está disponible.'
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
                self::SUPPORTED_EXTENSIONS,
                true
            )
        ) {
            throw new RuntimeException(
                'Formato fuente no soportado. Use CSV o XLSX.'
            );
        }

        $size =
            filesize($path);

        if ($size === false) {
            throw new RuntimeException(
                'No se pudo determinar el tamaño del archivo fuente.'
            );
        }

        $sha256 =
            hash_file(
                'sha256',
                $path
            );

        if ($sha256 === false) {
            throw new RuntimeException(
                'No se pudo calcular la huella del archivo fuente.'
            );
        }

        $inspection =
            match ($extension) {
                'csv' =>
                    $this->inspectCsv(
                        $path
                    ),

                'xlsx' =>
                    $this->inspectXlsx(
                        $path,
                        $originalName
                    ),
            };

        return [
            'format' =>
                $extension,

            'original_filename' =>
                $originalName,

            'source_size_bytes' =>
                (int) $size,

            'source_sha256' =>
                $sha256,

            'reader_configuration' =>
                $inspection['reader_configuration'],

            'sheets' =>
                $inspection['sheets'],
        ];
    }

    /**
     * CSV is inspected as a single logical source sheet.
     *
     * Raw rows are streamed only to count rows/columns.
     * They are never accumulated in the returned payload.
     *
     * @return array{
     *     reader_configuration:array<string,mixed>,
     *     sheets:array<int,array<string,mixed>>
     * }
     */
    private function inspectCsv(
        string $path
    ): array {
        [
            'encoding' => $encoding,
            'delimiter' => $delimiter,
            'has_bom' => $hasBom,
        ] =
            $this->detectCsvDialect(
                $path
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

        try {
            $rawHeaders =
                fgetcsv(
                    $stream,
                    null,
                    $delimiter,
                    '"',
                    ''
                );

            if (
                $rawHeaders === false
                || $rawHeaders === [null]
            ) {
                throw new RuntimeException(
                    'El CSV fuente no contiene una fila de encabezados.'
                );
            }

            $headers =
                array_map(
                    fn (mixed $value): string =>
                        $this->normalizeCsvText(
                            $value,
                            $encoding
                        ),
                    $rawHeaders
                );

            if (
                isset($headers[0])
                && str_starts_with(
                    $headers[0],
                    "\xEF\xBB\xBF"
                )
            ) {
                $headers[0] =
                    substr(
                        $headers[0],
                        3
                    );
            }

            $columnCount =
                count($headers);

            $dataRowCount = 0;
            $maxColumnCount =
                $columnCount;

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
                if (
                    $this->isEmptyCsvRow(
                        $row
                    )
                ) {
                    continue;
                }

                $dataRowCount++;

                $maxColumnCount =
                    max(
                        $maxColumnCount,
                        count($row)
                    );
            }

            /*
             * If data contains more columns than the header,
             * preserve those positions as blank-header columns.
             * This is source structure discovery, not canonical validation.
             */
            while (
                count($headers)
                < $maxColumnCount
            ) {
                $headers[] = '';
            }

            return [
                'reader_configuration' => [
                    'encoding' =>
                        $encoding,

                    'delimiter' =>
                        $this->delimiterName(
                            $delimiter
                        ),

                    'delimiter_character' =>
                        $delimiter,

                    'header_row' =>
                        1,

                    'has_bom' =>
                        $hasBom,
                ],

                'sheets' => [
                    [
                        'index' =>
                            0,

                        'name' =>
                            null,

                        'total_row_count' =>
                            $dataRowCount + 1,

                        'row_count' =>
                            $dataRowCount,

                        'column_count' =>
                            count($headers),

                        'headers' =>
                            $headers,

                        'columns' =>
                            $this->columnsFromHeaders(
                                $headers
                            ),
                    ],
                ],
            ];
        } finally {
            fclose($stream);
        }
    }

    /**
     * XLSX structural inspection deliberately does not require:
     * - canonical sheet names;
     * - one sheet only;
     * - canonical headers.
     *
     * Every worksheet is discovered.
     *
     * @return array{
     *     reader_configuration:array<string,mixed>,
     *     sheets:array<int,array<string,mixed>>
     * }
     */
    private function inspectXlsx(
        string $path,
        string $originalName
    ): array {
        $guard =
            $this->resourceGuard->validate(
                $path,
                $originalName
            );

        if (
            ($guard['valid'] ?? false)
            !== true
        ) {
            $errors =
                array_values(
                    array_filter(
                        $guard['errors']
                        ?? [],
                        static fn (mixed $error): bool =>
                            is_string($error)
                            && trim($error) !== ''
                    )
                );

            throw new RuntimeException(
                $errors !== []
                    ? implode(
                        ' ',
                        $errors
                    )
                    : 'El XLSX fuente no superó la inspección de seguridad.'
            );
        }

        try {
            $reader =
                IOFactory::createReader(
                    'Xlsx'
                );

            $reader->setReadDataOnly(
                true
            );

            /*
             * listWorksheetInfo gives us sheet/row/column counts
             * without materializing the workbook data.
             */
            $worksheetInfo =
                $reader->listWorksheetInfo(
                    $path
                );

            /*
             * A second lightweight read loads only row 1 from every
             * worksheet so original candidate headers can be preserved.
             */
            $reader->setReadFilter(
                new class implements IReadFilter {
                    public function readCell(
                        string $columnAddress,
                        int $row,
                        string $worksheetName = ''
                    ): bool {
                        return $row === 1;
                    }
                }
            );

            $spreadsheet =
                $reader->load(
                    $path
                );
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'No se pudo inspeccionar el archivo XLSX fuente.',
                0,
                $exception
            );
        }

        try {
            $sheets = [];

            foreach (
                $worksheetInfo
                as $index => $info
            ) {
                $sheetName =
                    (string) (
                        $info['worksheetName']
                        ?? ''
                    );

                $totalRows =
                    max(
                        0,
                        (int) (
                            $info['totalRows']
                            ?? 0
                        )
                    );

                $totalColumns =
                    max(
                        0,
                        (int) (
                            $info['totalColumns']
                            ?? 0
                        )
                    );

                $worksheet =
                    $spreadsheet
                        ->getSheetByName(
                            $sheetName
                        );

                $headers = [];

                if ($worksheet !== null) {
                    for (
                        $column = 1;
                        $column <= $totalColumns;
                        $column++
                    ) {
                        $value =
                            $worksheet
                                ->getCell([
                                    $column,
                                    1,
                                ])
                                ->getValue();

                        $headers[] =
                            $this->normalizeHeader(
                                $value
                            );
                    }
                }

                while (
                    count($headers)
                    < $totalColumns
                ) {
                    $headers[] = '';
                }

                $sheets[] = [
                    'index' =>
                        (int) $index,

                    'name' =>
                        $sheetName,

                    'total_row_count' =>
                        $totalRows,

                    'row_count' =>
                        max(
                            0,
                            $totalRows - 1
                        ),

                    'column_count' =>
                        $totalColumns,

                    'headers' =>
                        $headers,

                    'columns' =>
                        $this->columnsFromHeaders(
                            $headers
                        ),
                ];
            }

            return [
                'reader_configuration' => [
                    /*
                     * Row 1 is only the initial candidate.
                     * A later mapping/configuration step may choose
                     * another header row or worksheet.
                     */
                    'header_row' =>
                        1,

                    'selected_sheet' =>
                        null,

                    'sheet_count' =>
                        count($sheets),
                ],

                'sheets' =>
                    $sheets,
            ];
        } finally {
            $spreadsheet
                ->disconnectWorksheets();
        }
    }

    /**
     * Detect source CSV encoding and delimiter without requiring
     * canonical column names.
     *
     * Supported practical legacy encodings:
     * - UTF-8
     * - UTF-8 BOM
     * - Windows-1252 / ISO-8859-1 compatible byte streams
     *
     * @return array{
     *     encoding:string,
     *     delimiter:string,
     *     has_bom:bool
     * }
     */
    private function detectCsvDialect(
        string $path
    ): array {
        $stream =
            fopen(
                $path,
                'rb'
            );

        if ($stream === false) {
            throw new RuntimeException(
                'No se pudo abrir el CSV para detectar su estructura.'
            );
        }

        try {
            $sample =
                fread(
                    $stream,
                    self::CSV_SAMPLE_BYTES
                );
        } finally {
            fclose($stream);
        }

        if ($sample === false) {
            throw new RuntimeException(
                'No se pudo leer la muestra del CSV.'
            );
        }

        if ($sample === '') {
            throw new RuntimeException(
                'El archivo CSV está vacío.'
            );
        }

        $hasBom =
            str_starts_with(
                $sample,
                "\xEF\xBB\xBF"
            );

        if ($hasBom) {
            $encoding =
                'UTF-8-BOM';

            $utf8Sample =
                substr(
                    $sample,
                    3
                );
        } elseif (
            mb_check_encoding(
                $sample,
                'UTF-8'
            )
        ) {
            $encoding =
                'UTF-8';

            $utf8Sample =
                $sample;
        } else {
            /*
             * Windows-1252 is the practical legacy fallback for
             * Spanish-language exports. ISO-8859-1 data is compatible
             * for the common business characters we need to inspect.
             */
            $encoding =
                'Windows-1252';

            $utf8Sample =
                mb_convert_encoding(
                    $sample,
                    'UTF-8',
                    'Windows-1252'
                );
        }

        if (
            str_starts_with(
                $utf8Sample,
                "\xFF\xFE"
            )
            || str_starts_with(
                $utf8Sample,
                "\xFE\xFF"
            )
        ) {
            throw new RuntimeException(
                'CSV UTF-16 todavía no está soportado por el reader source.'
            );
        }

        $lines =
            preg_split(
                "/\r\n|\n|\r/",
                $utf8Sample
            );

        if (! is_array($lines)) {
            throw new RuntimeException(
                'No se pudo analizar la muestra del CSV.'
            );
        }

        $sampleLines = [];

        foreach ($lines as $line) {
            if (
                trim($line)
                === ''
            ) {
                continue;
            }

            $sampleLines[] =
                $line;

            if (
                count($sampleLines)
                >= self::CSV_SAMPLE_LINES
            ) {
                break;
            }
        }

        if ($sampleLines === []) {
            throw new RuntimeException(
                'El CSV no contiene filas utilizables.'
            );
        }

        $delimiter =
            $this->detectDelimiter(
                $sampleLines
            );

        return [
            'encoding' =>
                $encoding,

            'delimiter' =>
                $delimiter,

            'has_bom' =>
                $hasBom,
        ];
    }

    /**
     * Prefer a delimiter that produces a stable multi-column structure
     * across the sample. Single-column CSV remains valid and falls back
     * to comma.
     *
     * @param array<int,string> $lines
     */
    private function detectDelimiter(
        array $lines
    ): string {
        $candidates = [
            ',',
            ';',
            "\t",
            '|',
        ];

        $bestDelimiter = ',';
        $bestScore = -1;

        foreach ($candidates as $delimiter) {
            $counts = [];

            foreach ($lines as $line) {
                $fields =
                    str_getcsv(
                        $line,
                        $delimiter,
                        '"',
                        ''
                    );

                $counts[] =
                    count($fields);
            }

            $frequencies =
                array_count_values(
                    $counts
                );

            arsort(
                $frequencies
            );

            $modeCount =
                (int) array_key_first(
                    $frequencies
                );

            $consistency =
                (int) reset(
                    $frequencies
                );

            /*
             * Strong preference for:
             * 1. > 1 columns
             * 2. same column count across multiple sample rows
             * 3. larger plausible column count as a final tie-breaker
             */
            $score =
                ($modeCount > 1 ? 100000 : 0)
                + ($consistency * 1000)
                + $modeCount;

            if ($score > $bestScore) {
                $bestScore =
                    $score;

                $bestDelimiter =
                    $delimiter;
            }
        }

        return $bestDelimiter;
    }

    private function normalizeCsvText(
        mixed $value,
        string $encoding
    ): string {
        if (! is_scalar($value)) {
            return '';
        }

        $text =
            (string) $value;

        if ($encoding === 'Windows-1252') {
            $text =
                mb_convert_encoding(
                    $text,
                    'UTF-8',
                    'Windows-1252'
                );
        }

        return trim(
            $text
        );
    }

    private function normalizeHeader(
        mixed $value
    ): string {
        if (! is_scalar($value)) {
            return '';
        }

        $header =
            trim(
                (string) $value
            );

        if (
            str_starts_with(
                $header,
                "\xEF\xBB\xBF"
            )
        ) {
            $header =
                substr(
                    $header,
                    3
                );
        }

        return $header;
    }

    /**
     * Preserve duplicate and blank client headers.
     *
     * Mapping uses the stable positional key (column_N), not the display
     * header, because legacy files can contain repeated or blank names.
     *
     * @param array<int,string> $headers
     *
     * @return array<int,array{
     *     index:int,
     *     key:string,
     *     header:string
     * }>
     */
    private function columnsFromHeaders(
        array $headers
    ): array {
        $columns = [];

        foreach (
            $headers
            as $offset => $header
        ) {
            $index =
                $offset + 1;

            $columns[] = [
                'index' =>
                    $index,

                'key' =>
                    "column_{$index}",

                'header' =>
                    (string) $header,
            ];
        }

        return $columns;
    }

    /**
     * @param array<int,mixed> $row
     */
    private function isEmptyCsvRow(
        array $row
    ): bool {
        foreach ($row as $value) {
            if (
                trim(
                    (string) (
                        $value
                        ?? ''
                    )
                )
                !== ''
            ) {
                return false;
            }
        }

        return true;
    }

    private function delimiterName(
        string $delimiter
    ): string {
        return match ($delimiter) {
            ',' =>
                'comma',

            ';' =>
                'semicolon',

            "\t" =>
                'tab',

            '|' =>
                'pipe',

            default =>
                'unknown',
        };
    }
}
