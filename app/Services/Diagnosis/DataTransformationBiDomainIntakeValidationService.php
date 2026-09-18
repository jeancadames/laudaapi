<?php

namespace App\Services\Diagnosis;

use RuntimeException;
use Throwable;

final class DataTransformationBiDomainIntakeValidationService
{
    public const MAX_SOURCE_BYTES =
        2097152;

    private DataTransformationBiDomainIntakeFileReader
        $fileReader;

    private DataTransformationBiStandardIntakeRowValidator
        $rowValidator;

    public function __construct(
        ?DataTransformationBiDomainIntakeFileReader $fileReader = null,
        ?DataTransformationBiStandardIntakeRowValidator $rowValidator = null
    ) {
        $this->fileReader =
            $fileReader
            ?? new DataTransformationBiDomainIntakeFileReader();

        $this->rowValidator =
            $rowValidator
            ?? new DataTransformationBiStandardIntakeRowValidator();
    }

    public function validate(
        string $domain,
        string $path,
        string $originalName
    ): array {
        if (
            ! in_array(
                $domain,
                DataTransformationBiStandardIntakeSchema
                    ::domainKeys(),
                true
            )
        ) {
            return $this->invalid(
                $domain,
                'unknown',
                [
                    "Dominio canónico no soportado: {$domain}.",
                ]
            );
        }

        if (! is_file($path)) {
            return $this->invalid(
                $domain,
                'unknown',
                [
                    'El archivo recibido no está disponible.',
                ]
            );
        }

        $size =
            filesize(
                $path
            );

        if ($size === false) {
            return $this->invalid(
                $domain,
                'unknown',
                [
                    'No se pudo determinar el tamaño del archivo.',
                ]
            );
        }

        if (
            $size
            > self::MAX_SOURCE_BYTES
        ) {
            return $this->invalid(
                $domain,
                'unknown',
                [
                    'El archivo supera el límite actual de 2 MB '
                    .'por dominio.',
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

        if (
            ! in_array(
                $extension,
                [
                    'xlsx',
                    'csv',
                ],
                true
            )
        ) {
            return $this->invalid(
                $domain,
                $extension !== ''
                    ? $extension
                    : 'unknown',
                [
                    'Formato no soportado. '
                    .'Cada dominio debe cargarse como XLSX o CSV.',
                ]
            );
        }

        /*
         * XLSX remains a ZIP-based container. Reuse the existing hardened
         * resource guard for archive limits. Raw CSV does not need the ZIP
         * guard and is bounded by MAX_SOURCE_BYTES above.
         */
        if ($extension === 'xlsx') {
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
                return $this->invalid(
                    $domain,
                    'xlsx',
                    $resource['errors']
                        ?? [
                            'El archivo XLSX no pasó el control de recursos.',
                        ],
                    $resource['warnings']
                        ?? []
                );
            }
        }

        try {
            $payload =
                $this->fileReader
                    ->read(
                        $domain,
                        $path,
                        $originalName
                    );
        } catch (Throwable $exception) {
            return $this->invalid(
                $domain,
                $extension,
                [
                    $exception instanceof RuntimeException
                        ? $exception->getMessage()
                        : 'No se pudo leer el archivo del dominio.',
                ]
            );
        }

        $structural =
            $this->validateHeaders(
                $domain,
                $payload['headers']
                    ?? []
            );

        if (
            ($structural['valid'] ?? false)
            !== true
        ) {
            return [
                'valid' =>
                    false,

                'schema_version' =>
                    DataTransformationBiStandardIntakeSchema
                        ::VERSION,

                'domain' =>
                    $domain,

                'format' =>
                    $extension,

                'source_size_bytes' =>
                    (int) $size,

                'errors' =>
                    $structural['errors'],

                'warnings' =>
                    $structural['warnings'],

                'structural' =>
                    $structural,

                'content' => [
                    'executed' =>
                        false,

                    'valid' =>
                        false,

                    'errors' =>
                        [],

                    'warnings' =>
                        [],

                    'domain_report' =>
                        null,
                ],
            ];
        }

        $rowWidthErrors =
            $this->rowWidthErrors(
                $domain,
                $payload['rows']
                    ?? []
            );

        if ($rowWidthErrors !== []) {
            $structural['valid'] =
                false;

            $structural['errors'] =
                array_values(
                    array_merge(
                        $structural['errors'],
                        $rowWidthErrors
                    )
                );

            return [
                'valid' =>
                    false,

                'schema_version' =>
                    DataTransformationBiStandardIntakeSchema
                        ::VERSION,

                'domain' =>
                    $domain,

                'format' =>
                    $extension,

                'source_size_bytes' =>
                    (int) $size,

                'errors' =>
                    $structural['errors'],

                'warnings' =>
                    $structural['warnings'],

                'structural' =>
                    $structural,

                'content' => [
                    'executed' =>
                        false,

                    'valid' =>
                        false,

                    'errors' =>
                        [],

                    'warnings' =>
                        [],

                    'domain_report' =>
                        null,
                ],
            ];
        }

        $content =
            $this->rowValidator
                ->validateDomain(
                    $domain,
                    $payload['rows']
                        ?? []
                );

        return [
            'valid' =>
                ($content['valid'] ?? false)
                === true,

            'schema_version' =>
                DataTransformationBiStandardIntakeSchema
                    ::VERSION,

            'domain' =>
                $domain,

            'format' =>
                $extension,

            'source_size_bytes' =>
                (int) $size,

            'errors' =>
                $content['errors']
                ?? [],

            'warnings' =>
                array_values(
                    array_merge(
                        $structural['warnings']
                            ?? [],
                        $content['warnings']
                            ?? []
                    )
                ),

            'structural' =>
                $structural,

            'content' => [
                'executed' =>
                    true,

                ...$content,
            ],
        ];
    }

    private function validateHeaders(
        string $domain,
        mixed $headers
    ): array {
        $expected =
            array_values(
                array_map(
                    static fn (array $field): string =>
                        (string) (
                            $field['name']
                            ?? ''
                        ),
                    DataTransformationBiStandardIntakeSchema
                        ::domains()[$domain]['fields']
                    ?? []
                )
            );

        if (! is_array($headers)) {
            return [
                'valid' =>
                    false,

                'expected_headers' =>
                    $expected,

                'actual_headers' =>
                    [],

                'errors' => [
                    "{$domain}: no se recibió una fila de encabezados válida.",
                ],

                'warnings' =>
                    [],
            ];
        }

        $actual =
            array_values(
                array_map(
                    static fn (mixed $header): string =>
                        trim(
                            (string) $header
                        ),
                    $headers
                )
            );

        $errors = [];

        if (
            in_array(
                '',
                $actual,
                true
            )
        ) {
            $errors[] =
                "{$domain}: existen encabezados vacíos.";
        }

        if (
            count($actual)
            !== count(
                array_unique(
                    $actual
                )
            )
        ) {
            $errors[] =
                "{$domain}: existen encabezados duplicados.";
        }

        $missing =
            array_values(
                array_diff(
                    $expected,
                    $actual
                )
            );

        if ($missing !== []) {
            $errors[] =
                "{$domain}: faltan columnas requeridas por el esquema: "
                .implode(
                    ', ',
                    $missing
                )
                .'.';
        }

        $extra =
            array_values(
                array_diff(
                    $actual,
                    $expected
                )
            );

        if ($extra !== []) {
            $errors[] =
                "{$domain}: contiene columnas no definidas por el esquema: "
                .implode(
                    ', ',
                    $extra
                )
                .'.';
        }

        if (
            $missing === []
            && $extra === []
            && $actual !== $expected
        ) {
            $errors[] =
                "{$domain}: las columnas deben respetar el orden "
                .'canónico del esquema.';
        }

        return [
            'valid' =>
                $errors === [],

            'expected_headers' =>
                $expected,

            'actual_headers' =>
                $actual,

            'errors' =>
                $errors,

            'warnings' =>
                [],
        ];
    }

    private function rowWidthErrors(
        string $domain,
        mixed $rows
    ): array {
        if (! is_array($rows)) {
            return [
                "{$domain}: las filas no tienen una estructura válida.",
            ];
        }

        $errors = [];

        foreach (
            array_values($rows)
            as $offset => $row
        ) {
            if (! is_array($row)) {
                continue;
            }

            foreach (
                array_keys($row)
                as $key
            ) {
                if (
                    str_starts_with(
                        (string) $key,
                        '__extra_column_'
                    )
                ) {
                    $errors[] =
                        "{$domain} fila "
                        .($offset + 2)
                        .': contiene más columnas que el encabezado.';

                    break;
                }
            }
        }

        return $errors;
    }

    private function invalid(
        string $domain,
        string $format,
        array $errors,
        array $warnings = []
    ): array {
        return [
            'valid' =>
                false,

            'schema_version' =>
                DataTransformationBiStandardIntakeSchema
                    ::VERSION,

            'domain' =>
                $domain,

            'format' =>
                $format,

            'source_size_bytes' =>
                null,

            'errors' =>
                array_values(
                    $errors
                ),

            'warnings' =>
                array_values(
                    $warnings
                ),

            'structural' => [
                'valid' =>
                    false,

                'expected_headers' =>
                    [],

                'actual_headers' =>
                    [],

                'errors' =>
                    array_values(
                        $errors
                    ),

                'warnings' =>
                    array_values(
                        $warnings
                    ),
            ],

            'content' => [
                'executed' =>
                    false,

                'valid' =>
                    false,

                'errors' =>
                    [],

                'warnings' =>
                    [],

                'domain_report' =>
                    null,
            ],
        ];
    }
}
