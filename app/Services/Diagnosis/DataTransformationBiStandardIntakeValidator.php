<?php

namespace App\Services\Diagnosis;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;
use ZipArchive;

class DataTransformationBiStandardIntakeValidator
{
    public function validate(
        string $path,
        string $originalName
    ): array {
        $resource =
            (new DataTransformationBiStandardIntakeResourceGuard())
                ->validate(
                    $path,
                    $originalName
                );

        if (
            ($resource['valid'] ?? false)
            !== true
        ) {
            return [
                'valid' => false,
                'schema_version' =>
                    DataTransformationBiStandardIntakeSchema
                        ::VERSION,
                'format' =>
                    $resource['format']
                    ?? 'unknown',
                'errors' =>
                    $resource['errors']
                    ?? [],
                'warnings' =>
                    $resource['warnings']
                    ?? [],
                'domains' => [],
                'extra_entries' => [],
                'resource' => $resource,
            ];
        }


        if (! is_file($path)) {
            return $this->invalidResult(
                'unknown',
                [
                    'El archivo recibido no está disponible.',
                ]
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
                $this->validateXlsx($path),

            'zip' =>
                $this->validateCsvZip($path),

            default =>
                $this->invalidResult(
                    'unsupported',
                    [
                        'Formato no soportado. '
                        .'Usa la plantilla XLSX de LAUDA '
                        .'o el paquete ZIP de archivos CSV.',
                    ]
                ),
        };
    }

    private function validateXlsx(
        string $path
    ): array {
        try {
            $reader =
                IOFactory::createReader('Xlsx');

            $reader->setReadDataOnly(true);

            $spreadsheet =
                $reader->load($path);
        } catch (Throwable) {
            return $this->invalidResult(
                'xlsx',
                [
                    'No se pudo leer el archivo XLSX.',
                ]
            );
        }

        try {
            $errors = [];
            $warnings = [];
            $domains = [];

            $expectedDomains =
                DataTransformationBiStandardIntakeSchema
                    ::domainKeys();

            $sheetNames =
                $spreadsheet->getSheetNames();

            if (
                ! in_array(
                    'README',
                    $sheetNames,
                    true
                )
            ) {
                $errors[] =
                    'Falta la hoja README.';
            }

            foreach (
                $expectedDomains as $domain
            ) {
                if (
                    ! in_array(
                        $domain,
                        $sheetNames,
                        true
                    )
                ) {
                    $errors[] =
                        "Falta la hoja requerida {$domain}.";

                    $domains[$domain] = [
                        'present' => false,
                        'valid' => false,
                        'missing_required' =>
                            $this->requiredFields(
                                $domain
                            ),
                        'unknown_columns' => [],
                        'duplicate_columns' => [],
                    ];

                    continue;
                }

                $sheet =
                    $spreadsheet
                        ->getSheetByName(
                            $domain
                        );

                $highestColumn =
                    $sheet->getHighestColumn();

                $row =
                    $sheet->rangeToArray(
                        "A1:{$highestColumn}1",
                        null,
                        true,
                        false
                    )[0] ?? [];

                $inspection =
                    $this->inspectHeaders(
                        $domain,
                        $row
                    );

                $domains[$domain] =
                    $inspection;

                foreach (
                    $inspection['errors']
                    as $message
                ) {
                    $errors[] = $message;
                }

                foreach (
                    $inspection['warnings']
                    as $message
                ) {
                    $warnings[] = $message;
                }
            }

            $expectedSheets = [
                'README',
                ...$expectedDomains,
            ];

            $extraSheets =
                array_values(
                    array_diff(
                        $sheetNames,
                        $expectedSheets
                    )
                );

            if ($extraSheets !== []) {
                $warnings[] =
                    'Hojas adicionales no reconocidas: '
                    .implode(
                        ', ',
                        $extraSheets
                    )
                    .'.';
            }

            return [
                'valid' => $errors === [],
                'schema_version' =>
                    DataTransformationBiStandardIntakeSchema
                        ::VERSION,
                'format' => 'xlsx',
                'errors' => $errors,
                'warnings' => $warnings,
                'domains' => $domains,
                'extra_entries' => $extraSheets,
            ];
        } finally {
            $spreadsheet
                ->disconnectWorksheets();
        }
    }

    private function validateCsvZip(
        string $path
    ): array {
        $zip =
            new ZipArchive();

        $opened =
            $zip->open($path);

        if ($opened !== true) {
            return $this->invalidResult(
                'csv_zip',
                [
                    'No se pudo abrir el paquete ZIP.',
                ]
            );
        }

        try {
            $errors = [];
            $warnings = [];
            $domains = [];
            $entries = [];

            for (
                $index = 0;
                $index < $zip->numFiles;
                $index++
            ) {
                $name =
                    $zip->getNameIndex(
                        $index
                    );

                if (
                    ! is_string($name)
                    || $name === ''
                ) {
                    continue;
                }

                $entries[] = $name;

                if (
                    $this->unsafeZipPath(
                        $name
                    )
                ) {
                    $errors[] =
                        "Entrada ZIP insegura detectada: {$name}.";
                }
            }

            if (
                ! in_array(
                    'README.csv',
                    $entries,
                    true
                )
            ) {
                $errors[] =
                    'Falta README.csv.';
            }

            $expectedDomains =
                DataTransformationBiStandardIntakeSchema
                    ::domainKeys();

            foreach (
                $expectedDomains as $domain
            ) {
                $filename =
                    "{$domain}.csv";

                if (
                    ! in_array(
                        $filename,
                        $entries,
                        true
                    )
                ) {
                    $errors[] =
                        "Falta el archivo requerido {$filename}.";

                    $domains[$domain] = [
                        'present' => false,
                        'valid' => false,
                        'missing_required' =>
                            $this->requiredFields(
                                $domain
                            ),
                        'unknown_columns' => [],
                        'duplicate_columns' => [],
                    ];

                    continue;
                }

                $stream =
                    $zip->getStream(
                        $filename
                    );

                if ($stream === false) {
                    $errors[] =
                        "No se pudo leer {$filename}.";

                    $domains[$domain] = [
                        'present' => true,
                        'valid' => false,
                        'missing_required' =>
                            $this->requiredFields(
                                $domain
                            ),
                        'unknown_columns' => [],
                        'duplicate_columns' => [],
                    ];

                    continue;
                }

                try {
                    $headerLine =
                        fgets($stream);
                } finally {
                    fclose($stream);
                }

                if (
                    $headerLine === false
                ) {
                    $headerLine = '';
                }

                if (
                    str_starts_with(
                        $headerLine,
                        "\xEF\xBB\xBF"
                    )
                ) {
                    $headerLine =
                        substr(
                            $headerLine,
                            3
                        );
                }

                $headers =
                    str_getcsv(
                        rtrim(
                            $headerLine,
                            "\r\n"
                        ),
                        ',',
                        '"',
                        '\\'
                    );

                $inspection =
                    $this->inspectHeaders(
                        $domain,
                        $headers
                    );

                $domains[$domain] =
                    $inspection;

                foreach (
                    $inspection['errors']
                    as $message
                ) {
                    $errors[] = $message;
                }

                foreach (
                    $inspection['warnings']
                    as $message
                ) {
                    $warnings[] = $message;
                }
            }

            $expectedEntries = [
                'README.csv',
                ...array_map(
                    static fn (
                        string $domain
                    ): string =>
                        "{$domain}.csv",
                    $expectedDomains
                ),
            ];

            $extraEntries =
                array_values(
                    array_filter(
                        array_diff(
                            $entries,
                            $expectedEntries
                        ),
                        static fn (
                            string $entry
                        ): bool =>
                            ! str_ends_with(
                                $entry,
                                '/'
                            )
                    )
                );

            if ($extraEntries !== []) {
                $warnings[] =
                    'Archivos adicionales no reconocidos: '
                    .implode(
                        ', ',
                        $extraEntries
                    )
                    .'.';
            }

            return [
                'valid' => $errors === [],
                'schema_version' =>
                    DataTransformationBiStandardIntakeSchema
                        ::VERSION,
                'format' => 'csv_zip',
                'errors' => $errors,
                'warnings' => $warnings,
                'domains' => $domains,
                'extra_entries' => $extraEntries,
            ];
        } finally {
            $zip->close();
        }
    }

    private function inspectHeaders(
        string $domain,
        array $rawHeaders
    ): array {
        $headers =
            array_map(
                static fn (
                    mixed $header
                ): string =>
                    trim(
                        (string) $header
                    ),
                $rawHeaders
            );

        $headers =
            array_values(
                array_filter(
                    $headers,
                    static fn (
                        string $header
                    ): bool =>
                        $header !== ''
                )
            );

        $required =
            $this->requiredFields(
                $domain
            );

        $known =
            $this->knownFields(
                $domain
            );

        $missingRequired =
            array_values(
                array_diff(
                    $required,
                    $headers
                )
            );

        $unknownColumns =
            array_values(
                array_diff(
                    $headers,
                    $known
                )
            );

        $counts =
            array_count_values(
                $headers
            );

        $duplicateColumns =
            array_keys(
                array_filter(
                    $counts,
                    static fn (
                        int $count
                    ): bool =>
                        $count > 1
                )
            );

        $errors = [];
        $warnings = [];

        if (
            $missingRequired !== []
        ) {
            $errors[] =
                "{$domain}: faltan columnas requeridas: "
                .implode(
                    ', ',
                    $missingRequired
                )
                .'.';
        }

        if (
            $duplicateColumns !== []
        ) {
            $errors[] =
                "{$domain}: columnas duplicadas: "
                .implode(
                    ', ',
                    $duplicateColumns
                )
                .'.';
        }

        if (
            $unknownColumns !== []
        ) {
            $warnings[] =
                "{$domain}: columnas adicionales no reconocidas: "
                .implode(
                    ', ',
                    $unknownColumns
                )
                .'.';
        }

        return [
            'present' => true,
            'valid' =>
                $missingRequired === []
                && $duplicateColumns === [],
            'missing_required' =>
                $missingRequired,
            'unknown_columns' =>
                $unknownColumns,
            'duplicate_columns' =>
                $duplicateColumns,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    private function requiredFields(
        string $domain
    ): array {
        $definition =
            DataTransformationBiStandardIntakeSchema
                ::domains()[$domain]
            ?? null;

        if (! is_array($definition)) {
            return [];
        }

        $fields =
            $definition['fields']
            ?? [];

        return array_values(
            array_map(
                static fn (
                    array $field
                ): string =>
                    (string) $field['name'],
                array_filter(
                    $fields,
                    static fn (
                        array $field
                    ): bool =>
                        ($field['required'] ?? false)
                        === true
                )
            )
        );
    }

    private function knownFields(
        string $domain
    ): array {
        $definition =
            DataTransformationBiStandardIntakeSchema
                ::domains()[$domain]
            ?? null;

        if (! is_array($definition)) {
            return [];
        }

        return array_values(
            array_map(
                static fn (
                    array $field
                ): string =>
                    (string) $field['name'],
                $definition['fields']
                ?? []
            )
        );
    }

    private function unsafeZipPath(
        string $name
    ): bool {
        return
            str_contains(
                $name,
                '../'
            )
            || str_contains(
                $name,
                '..\\'
            )
            || str_starts_with(
                $name,
                '/'
            )
            || preg_match(
                '/^[A-Za-z]:[\\\\\/]/',
                $name
            ) === 1;
    }

    private function invalidResult(
        string $format,
        array $errors
    ): array {
        return [
            'valid' => false,
            'schema_version' =>
                DataTransformationBiStandardIntakeSchema
                    ::VERSION,
            'format' => $format,
            'errors' => $errors,
            'warnings' => [],
            'domains' => [],
            'extra_entries' => [],
        ];
    }
}
