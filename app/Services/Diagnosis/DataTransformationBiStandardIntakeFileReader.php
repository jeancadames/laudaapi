<?php

namespace App\Services\Diagnosis;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;
use ZipArchive;

class DataTransformationBiStandardIntakeFileReader
{
    public const MAX_ROWS_PER_DOMAIN = 50000;

    public const MAX_TOTAL_ROWS = 150000;

    public function read(
        string $path,
        string $format
    ): array {
        if (! is_file($path)) {
            throw new RuntimeException(
                'El archivo de intake no está disponible.'
            );
        }

        return match ($format) {
            'xlsx' =>
                [
                    'format' => 'xlsx',
                    'rows' => $this->readXlsx($path),
                ],

            'csv_zip' =>
                [
                    'format' => 'csv_zip',
                    'rows' => $this->readCsvZip($path),
                ],

            default =>
                throw new RuntimeException(
                    "Formato de intake no soportado: {$format}."
                ),
        };
    }

    private function readXlsx(
        string $path
    ): array {
        $reader =
            IOFactory::createReader(
                'Xlsx'
            );

        $reader->setReadDataOnly(
            true
        );

        $spreadsheet =
            $reader->load(
                $path
            );

        try {
            $rowsByDomain = [];
            $totalRows = 0;

            foreach (
                DataTransformationBiStandardIntakeSchema
                    ::domainKeys()
                as $domain
            ) {
                $sheet =
                    $spreadsheet
                        ->getSheetByName(
                            $domain
                        );

                if ($sheet === null) {
                    throw new RuntimeException(
                        "Falta la hoja {$domain}."
                    );
                }

                $rowsByDomain[$domain] =
                    $this->readWorksheet(
                        $sheet,
                        $domain
                    );

                $totalRows +=
                    count(
                        $rowsByDomain[$domain]
                    );

                if (
                    $totalRows
                    > self::MAX_TOTAL_ROWS
                ) {
                    throw new \LengthException(
                        'El archivo supera el máximo total de '
                        .self::MAX_TOTAL_ROWS
                        .' filas permitido para validación.'
                    );
                }
            }

            return $rowsByDomain;
        } finally {
            $spreadsheet
                ->disconnectWorksheets();
        }
    }

    private function readWorksheet(
        Worksheet $sheet,
        string $domain
    ): array {
        $highestColumn =
            $sheet->getHighestColumn();

        $highestRow =
            $sheet->getHighestRow();

        if (
            $highestRow
            > self::MAX_ROWS_PER_DOMAIN + 1
        ) {
            throw new \LengthException(
                "{$domain}: supera el máximo de "
                .self::MAX_ROWS_PER_DOMAIN
                ." filas permitido para validación."
            );
        }

        $headerRow =
            $sheet->rangeToArray(
                "A1:{$highestColumn}1",
                null,
                true,
                false
            )[0] ?? [];

        $headers =
            $this->normalizeHeaders(
                $headerRow
            );

        $fieldTypes =
            $this->fieldTypes(
                $domain
            );

        $rows = [];

        for (
            $rowNumber = 2;
            $rowNumber <= $highestRow;
            $rowNumber++
        ) {
            $values = [];

            foreach (
                $headers
                as $columnOffset => $header
            ) {
                if ($header === '') {
                    $values[] = null;

                    continue;
                }

                $cell =
                    $sheet->getCell([
                        $columnOffset + 1,
                        $rowNumber,
                    ]);

                $values[] =
                    $this->normalizeXlsxCellValue(
                        $cell,
                        $fieldTypes[$header]
                            ?? 'text'
                    );
            }

            $rows[] =
                $this->associateRow(
                    $headers,
                    $values
                );
        }

        return $rows;
    }

    private function fieldTypes(
        string $domain
    ): array {
        $definition =
            DataTransformationBiStandardIntakeSchema
                ::domains()[$domain]
            ?? null;

        if (! is_array($definition)) {
            return [];
        }

        $types = [];

        foreach (
            $definition['fields']
            ?? []
            as $field
        ) {
            $name =
                trim(
                    (string) (
                        $field['name']
                        ?? ''
                    )
                );

            if ($name === '') {
                continue;
            }

            $types[$name] =
                strtolower(
                    trim(
                        (string) (
                            $field['type']
                            ?? 'text'
                        )
                    )
                );
        }

        return $types;
    }

    private function normalizeXlsxCellValue(
        Cell $cell,
        string $type
    ): mixed {
        $value =
            $cell->getValue();

        if (
            $value === null
            || $value === ''
        ) {
            return $value;
        }

        if (
            ! in_array(
                $type,
                [
                    'date',
                    'datetime',
                ],
                true
            )
        ) {
            return $value;
        }

        if (
            ! is_int($value)
            && ! is_float($value)
        ) {
            return $value;
        }

        $serial =
            (float) $value;

        /*
         * Excel almacena fechas reales como números seriales.
         * Solo normalizamos campos cuyo tipo canónico es date/datetime.
         *
         * El límite superior corresponde aproximadamente al máximo
         * representable por el calendario de Excel (9999-12-31).
         */
        if (
            $serial < 1
            || $serial > 2958465
        ) {
            return $value;
        }

        try {
            $date =
                ExcelDate::excelToDateTimeObject(
                    $serial
                );

            return $type === 'date'
                ? $date->format(
                    'Y-m-d'
                )
                : $date->format(
                    'Y-m-d H:i:s'
                );
        } catch (Throwable) {
            /*
             * Dejamos el valor original para que el validador de
             * contenido produzca el error canónico correspondiente.
             */
            return $value;
        }
    }

    private function readCsvZip(
        string $path
    ): array {
        $zip =
            new ZipArchive();

        if (
            $zip->open($path)
            !== true
        ) {
            throw new RuntimeException(
                'No se pudo abrir el paquete ZIP.'
            );
        }

        try {
            $rowsByDomain = [];
            $totalRows = 0;

            foreach (
                DataTransformationBiStandardIntakeSchema
                    ::domainKeys()
                as $domain
            ) {
                $filename =
                    "{$domain}.csv";

                $stream =
                    $zip->getStream(
                        $filename
                    );

                if ($stream === false) {
                    throw new RuntimeException(
                        "No se pudo leer {$filename}."
                    );
                }

                try {
                    $header =
                        fgetcsv(
                            $stream,
                            0,
                            ',',
                            '"',
                            '\\'
                        );

                    if ($header === false) {
                        throw new RuntimeException(
                            "{$filename} no contiene cabecera."
                        );
                    }

                    if (
                        isset($header[0])
                        && is_string($header[0])
                        && str_starts_with(
                            $header[0],
                            "\xEF\xBB\xBF"
                        )
                    ) {
                        $header[0] =
                            substr(
                                $header[0],
                                3
                            );
                    }

                    $headers =
                        $this->normalizeHeaders(
                            $header
                        );

                    $rows = [];
                    $domainRows = 0;

                    while (
                        (
                            $values =
                                fgetcsv(
                                    $stream,
                                    0,
                                    ',',
                                    '"',
                                    '\\'
                                )
                        ) !== false
                    ) {
                        $domainRows++;
                        $totalRows++;

                        if (
                            $domainRows
                            > self::MAX_ROWS_PER_DOMAIN
                        ) {
                            throw new \LengthException(
                                "{$domain}: supera el máximo de "
                                .self::MAX_ROWS_PER_DOMAIN
                                ." filas permitido para validación."
                            );
                        }

                        if (
                            $totalRows
                            > self::MAX_TOTAL_ROWS
                        ) {
                            throw new \LengthException(
                                'El archivo supera el máximo total de '
                                .self::MAX_TOTAL_ROWS
                                .' filas permitido para validación.'
                            );
                        }

                        $rows[] =
                            $this->associateRow(
                                $headers,
                                $values
                            );
                    }

                    $rowsByDomain[$domain] =
                        $rows;
                } finally {
                    fclose(
                        $stream
                    );
                }
            }

            return $rowsByDomain;
        } finally {
            $zip->close();
        }
    }

    private function normalizeHeaders(
        array $headers
    ): array {
        return array_map(
            static fn (
                mixed $header
            ): string =>
                trim(
                    (string) $header
                ),
            $headers
        );
    }

    private function associateRow(
        array $headers,
        array $values
    ): array {
        $row = [];

        foreach (
            $headers
            as $index => $header
        ) {
            if ($header === '') {
                continue;
            }

            $row[$header] =
                $values[$index]
                ?? null;
        }

        return $row;
    }
}
