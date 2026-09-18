<?php

namespace App\Services\Diagnosis;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

final class DataTransformationBiDomainIntakeFileReader
{
    public const MAX_ROWS_PER_DOMAIN = 50000;

    /**
     * @return array{
     *     domain:string,
     *     format:string,
     *     headers:array<int,string>,
     *     rows:array<int,array<string,mixed>>
     * }
     */
    public function read(
        string $domain,
        string $path,
        string $originalName
    ): array {
        $this->assertDomain(
            $domain
        );

        if (! is_file($path)) {
            throw new RuntimeException(
                'El archivo recibido no está disponible.'
            );
        }

        $extension =
            strtolower(
                pathinfo(
                    $originalName,
                    PATHINFO_EXTENSION
                )
            );

        return match ($extension) {
            'xlsx' =>
                $this->readXlsx(
                    $domain,
                    $path
                ),

            'csv' =>
                $this->readCsv(
                    $domain,
                    $path
                ),

            default =>
                throw new RuntimeException(
                    'Formato de dominio no soportado. '
                    .'Use XLSX o CSV.'
                ),
        };
    }

    private function readXlsx(
        string $domain,
        string $path
    ): array {
        try {
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
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'No se pudo abrir el archivo XLSX del dominio.',
                0,
                $exception
            );
        }

        try {
            /*
             * Preferred contract:
             * - sheet named exactly as the canonical domain.
             *
             * Practical compatibility:
             * - a domain-specific workbook with exactly one sheet is also
             *   accepted, regardless of that sheet's display name.
             */
            $worksheet =
                $spreadsheet->getSheetByName(
                    $domain
                );

            if (
                $worksheet === null
                && $spreadsheet->getSheetCount() === 1
            ) {
                $worksheet =
                    $spreadsheet->getSheet(
                        0
                    );
            }

            if (! $worksheet instanceof Worksheet) {
                throw new RuntimeException(
                    "El XLSX debe contener una hoja {$domain} "
                    .'o una única hoja de datos.'
                );
            }

            [
                'headers' => $headers,
                'rows' => $rows,
            ] =
                $this->readWorksheet(
                    $domain,
                    $worksheet
                );

            return [
                'domain' =>
                    $domain,

                'format' =>
                    'xlsx',

                'headers' =>
                    $headers,

                'rows' =>
                    $rows,
            ];
        } finally {
            $spreadsheet
                ->disconnectWorksheets();
        }
    }

    private function readWorksheet(
        string $domain,
        Worksheet $worksheet
    ): array {
        $highestRow =
            max(
                1,
                $worksheet->getHighestDataRow()
            );

        if (
            ($highestRow - 1)
            > self::MAX_ROWS_PER_DOMAIN
        ) {
            throw new RuntimeException(
                "{$domain}: supera el máximo de "
                .self::MAX_ROWS_PER_DOMAIN
                .' filas.'
            );
        }

        $highestColumn =
            $worksheet->getHighestDataColumn();

        $columnCount =
            max(
                1,
                Coordinate::columnIndexFromString(
                    $highestColumn
                )
            );

        $headers = [];

        for (
            $column = 1;
            $column <= $columnCount;
            $column++
        ) {
            $value =
                $worksheet
                    ->getCell([
                        $column,
                        1,
                    ])
                    ->getValue();

            $header =
                is_scalar($value)
                    ? trim((string) $value)
                    : '';

            if (
                $column === 1
                && str_starts_with(
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

            $headers[] =
                $header;
        }

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
            $row = [];

            foreach (
                $headers as $offset => $header
            ) {
                $column =
                    $offset + 1;

                $cell =
                    $worksheet
                        ->getCell([
                            $column,
                            $rowNumber,
                        ]);

                $value =
                    $cell->getCalculatedValue();

                $row[$header] =
                    $this->normalizeXlsxValue(
                        $value,
                        $fieldTypes[$header]
                            ?? null
                    );
            }

            $rows[] =
                $row;
        }

        return [
            'headers' =>
                $headers,

            'rows' =>
                $rows,
        ];
    }

    private function readCsv(
        string $domain,
        string $path
    ): array {
        $contents =
            file_get_contents(
                $path
            );

        if ($contents === false) {
            throw new RuntimeException(
                'No se pudo leer el archivo CSV.'
            );
        }

        if (
            ! mb_check_encoding(
                $contents,
                'UTF-8'
            )
        ) {
            throw new RuntimeException(
                'El CSV debe utilizar codificación UTF-8.'
            );
        }

        $stream =
            fopen(
                $path,
                'rb'
            );

        if ($stream === false) {
            throw new RuntimeException(
                'No se pudo abrir el archivo CSV.'
            );
        }

        try {
            $rawHeaders =
                fgetcsv(
                    $stream,
                    null,
                    ',',
                    '"',
                    ''
                );

            if (
                $rawHeaders === false
                || $rawHeaders === [null]
            ) {
                throw new RuntimeException(
                    'El CSV no contiene encabezados.'
                );
            }

            $headers =
                array_map(
                    static function (mixed $value): string {
                        return is_scalar($value)
                            ? trim((string) $value)
                            : '';
                    },
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

            $rows = [];

            while (
                (
                    $values =
                        fgetcsv(
                            $stream,
                            null,
                            ',',
                            '"',
                            ''
                        )
                )
                !== false
            ) {
                if (
                    count($rows)
                    >= self::MAX_ROWS_PER_DOMAIN
                ) {
                    throw new RuntimeException(
                        "{$domain}: supera el máximo de "
                        .self::MAX_ROWS_PER_DOMAIN
                        .' filas.'
                    );
                }

                /*
                 * Preserve width information for structural validation.
                 * Missing cells become null. Extra cells receive synthetic
                 * keys and therefore cannot silently disappear.
                 */
                $row = [];

                foreach (
                    $headers as $index => $header
                ) {
                    $row[$header] =
                        array_key_exists(
                            $index,
                            $values
                        )
                            ? $values[$index]
                            : null;
                }

                if (
                    count($values)
                    > count($headers)
                ) {
                    foreach (
                        array_slice(
                            $values,
                            count($headers)
                        )
                        as $extraIndex => $extraValue
                    ) {
                        $row[
                            '__extra_column_'
                            .($extraIndex + 1)
                        ] =
                            $extraValue;
                    }
                }

                $rows[] =
                    $row;
            }

            return [
                'domain' =>
                    $domain,

                'format' =>
                    'csv',

                'headers' =>
                    $headers,

                'rows' =>
                    $rows,
            ];
        } finally {
            fclose(
                $stream
            );
        }
    }

    private function normalizeXlsxValue(
        mixed $value,
        ?string $type
    ): mixed {
        if ($value === null) {
            return null;
        }

        $type =
            strtolower(
                trim(
                    (string) $type
                )
            );

        if (
            in_array(
                $type,
                [
                    'date',
                    'datetime',
                ],
                true
            )
            && (
                is_int($value)
                || is_float($value)
                || (
                    is_string($value)
                    && preg_match(
                        '/^\d+(?:\.\d+)?$/',
                        trim($value)
                    ) === 1
                )
            )
        ) {
            try {
                $date =
                    ExcelDate::excelToDateTimeObject(
                        (float) $value
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
                 * Keep the original value. The canonical row validator
                 * will return the appropriate type error.
                 */
            }
        }

        return $value;
    }

    /**
     * @return array<string,string>
     */
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

    private function assertDomain(
        string $domain
    ): void {
        if (
            ! in_array(
                $domain,
                DataTransformationBiStandardIntakeSchema
                    ::domainKeys(),
                true
            )
        ) {
            throw new RuntimeException(
                "Dominio canónico no soportado: {$domain}."
            );
        }
    }
}
