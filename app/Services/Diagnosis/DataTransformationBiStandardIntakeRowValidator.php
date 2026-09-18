<?php

namespace App\Services\Diagnosis;

use DateTimeImmutable;

class DataTransformationBiStandardIntakeRowValidator
{
    public static function supportedTypes(): array
    {
        return [
            'text',
            'string',
            'identifier',
            'decimal',
            'number',
            'numeric',
            'money',
            'integer',
            'int',
            'date',
            'datetime',
            'boolean',
            'bool',
        ];
    }

    public function validate(
        array $rowsByDomain
    ): array {
        $errors = [];
        $warnings = [];
        $domains = [];
        $acceptedRows = [];

        $schemaDomains =
            DataTransformationBiStandardIntakeSchema
                ::domains();

        foreach (
            array_keys($rowsByDomain)
            as $domain
        ) {
            if (
                ! array_key_exists(
                    $domain,
                    $schemaDomains
                )
            ) {
                $warnings[] =
                    "Dominio adicional ignorado: {$domain}.";
            }
        }

        foreach (
            DataTransformationBiStandardIntakeSchema
                ::domainKeys()
            as $domain
        ) {
            $rows =
                $rowsByDomain[$domain]
                ?? [];

            if (! is_array($rows)) {
                $message =
                    "{$domain}: las filas deben recibirse como una lista.";

                $errors[] =
                    $message;

                $domains[$domain] = [
                    'valid' => false,
                    'row_count' => 0,
                    'errors' => [
                        $message,
                    ],
                    'duplicate_keys' => [],
                    'relation_errors' => [],
                ];

                $acceptedRows[$domain] = [];

                continue;
            }

            [
                'summary' => $summary,
                'rows' => $domainRows,
            ] =
                $this->validateDomainRows(
                    $domain,
                    $rows,
                    $schemaDomains[$domain]
                );

            $domains[$domain] =
                $summary;

            $acceptedRows[$domain] =
                $domainRows;

            foreach (
                $summary['errors']
                as $message
            ) {
                $errors[] =
                    $message;
            }
        }

        $this->validateRelationships(
            $acceptedRows,
            $domains,
            $errors
        );

        foreach (
            $domains
            as &$summary
        ) {
            $summary['valid'] =
                $summary['errors'] === []
                && $summary['relation_errors'] === [];
        }

        unset($summary);

        return [
            'valid' => $errors === [],
            'schema_version' =>
                DataTransformationBiStandardIntakeSchema
                    ::VERSION,
            'errors' => $errors,
            'warnings' => $warnings,
            'domains' => $domains,
        ];
    }

    /**
     * Validate one canonical domain without executing cross-domain
     * relationships.
     *
     * Intake v2 uses this method while domains arrive independently.
     * The existing validate() method remains the authoritative
     * full-dataset gate and still executes relational validation.
     */
    public function validateDomain(
        string $domain,
        array $rows
    ): array {
        $schemaDomains =
            DataTransformationBiStandardIntakeSchema
                ::domains();

        if (
            ! array_key_exists(
                $domain,
                $schemaDomains
            )
        ) {
            $message =
                "Dominio canónico no soportado: {$domain}.";

            return [
                'valid' =>
                    false,

                'schema_version' =>
                    DataTransformationBiStandardIntakeSchema
                        ::VERSION,

                'domain' =>
                    $domain,

                'errors' => [
                    $message,
                ],

                'warnings' =>
                    [],

                'domain_report' => [
                    'valid' =>
                        false,

                    'row_count' =>
                        0,

                    'errors' => [
                        $message,
                    ],

                    'duplicate_keys' =>
                        [],

                    'relation_errors' =>
                        [],
                ],
            ];
        }

        [
            'summary' => $summary,
        ] =
            $this->validateDomainRows(
                $domain,
                $rows,
                $schemaDomains[$domain]
            );

        return [
            'valid' =>
                $summary['errors'] === [],

            'schema_version' =>
                DataTransformationBiStandardIntakeSchema
                    ::VERSION,

            'domain' =>
                $domain,

            'errors' =>
                $summary['errors'],

            'warnings' =>
                [],

            'domain_report' =>
                $summary,
        ];
    }

    private function validateDomainRows(
        string $domain,
        array $rows,
        array $definition
    ): array {
        $fields =
            $definition['fields']
            ?? [];

        $fieldDefinitions = [];

        foreach ($fields as $field) {
            $name =
                (string) (
                    $field['name']
                    ?? ''
                );

            if ($name === '') {
                continue;
            }

            $fieldDefinitions[$name] =
                $field;
        }

        $identityFields =
            DataTransformationBiStandardIntakeSchema
                ::identityKeys()[$domain]
            ?? [];

        $errors = [];
        $duplicateKeys = [];
        $acceptedRows = [];
        $seenKeys = [];
        $rowCount = 0;

        foreach (
            array_values($rows)
            as $offset => $row
        ) {
            $rowNumber =
                $offset + 2;

            if (! is_array($row)) {
                $errors[] =
                    "{$domain} fila {$rowNumber}: "
                    ."la fila no tiene una estructura válida.";

                continue;
            }

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $rowCount++;

            $rowHasTypeError =
                false;

            foreach (
                $fieldDefinitions
                as $fieldName => $field
            ) {
                $required =
                    ($field['required'] ?? false)
                    === true;

                $type =
                    strtolower(
                        trim(
                            (string) (
                                $field['type']
                                ?? 'text'
                            )
                        )
                    );

                $value =
                    array_key_exists(
                        $fieldName,
                        $row
                    )
                        ? $row[$fieldName]
                        : null;

                if ($this->blank($value)) {
                    if ($required) {
                        $errors[] =
                            "{$domain} fila {$rowNumber}: "
                            ."{$fieldName} es obligatorio.";
                    }

                    continue;
                }

                $typeError =
                    $this->typeError(
                        $value,
                        $type
                    );

                if ($typeError !== null) {
                    $rowHasTypeError =
                        true;

                    $errors[] =
                        "{$domain} fila {$rowNumber}: "
                        ."{$fieldName} {$typeError}";
                }
            }

            $identity =
                $this->identityKey(
                    $row,
                    $identityFields
                );

            if (
                $identity !== null
            ) {
                if (
                    array_key_exists(
                        $identity,
                        $seenKeys
                    )
                ) {
                    $firstRow =
                        $seenKeys[$identity];

                    $message =
                        "{$domain} fila {$rowNumber}: "
                        ."clave duplicada respecto a la fila "
                        ."{$firstRow} "
                        ."("
                        .implode(
                            ', ',
                            $identityFields
                        )
                        .").";

                    $errors[] =
                        $message;

                    $duplicateKeys[] = [
                        'row' => $rowNumber,
                        'first_row' => $firstRow,
                        'fields' => $identityFields,
                    ];
                } else {
                    $seenKeys[$identity] =
                        $rowNumber;
                }
            }

            /*
             * La fila se conserva para validar relaciones incluso
             * cuando contiene otro error, de modo que el reporte sea
             * completo y no oculte dependencias rotas.
             */
            $acceptedRows[] = [
                'row_number' =>
                    $rowNumber,

                'values' =>
                    $row,

                'has_type_error' =>
                    $rowHasTypeError,
            ];
        }

        return [
            'summary' => [
                'valid' =>
                    $errors === [],

                'row_count' =>
                    $rowCount,

                'errors' =>
                    $errors,

                'duplicate_keys' =>
                    $duplicateKeys,

                'relation_errors' =>
                    [],
            ],

            'rows' =>
                $acceptedRows,
        ];
    }

    private function validateRelationships(
        array $rowsByDomain,
        array &$domains,
        array &$errors
    ): void {
        foreach (
            DataTransformationBiStandardIntakeSchema
                ::relationships()
            as $relationship
        ) {
            $fromDomain =
                (string) $relationship[
                    'from_domain'
                ];

            $fromField =
                (string) $relationship[
                    'from_field'
                ];

            $toDomain =
                (string) $relationship[
                    'to_domain'
                ];

            $toField =
                (string) $relationship[
                    'to_field'
                ];

            $targetValues = [];

            foreach (
                $rowsByDomain[$toDomain]
                ?? []
                as $targetRow
            ) {
                $value =
                    $targetRow['values'][$toField]
                    ?? null;

                if ($this->blank($value)) {
                    continue;
                }

                $targetValues[
                    $this->comparable(
                        $value
                    )
                ] = true;
            }

            foreach (
                $rowsByDomain[$fromDomain]
                ?? []
                as $sourceRow
            ) {
                $value =
                    $sourceRow['values'][$fromField]
                    ?? null;

                if ($this->blank($value)) {
                    continue;
                }

                if (
                    array_key_exists(
                        $this->comparable($value),
                        $targetValues
                    )
                ) {
                    continue;
                }

                $rowNumber =
                    $sourceRow['row_number'];

                $message =
                    "{$fromDomain} fila {$rowNumber}: "
                    ."{$fromField} referencia un valor inexistente "
                    ."en {$toDomain}.{$toField}.";

                $errors[] =
                    $message;

                $domains[$fromDomain]
                    ['relation_errors'][] =
                    $message;
            }
        }
    }

    private function typeError(
        mixed $value,
        string $type
    ): ?string {
        return match ($type) {
            'text',
            'string',
            'identifier' =>
                is_string($value)
                    ? null
                    : 'debe ser texto.',

            'decimal',
            'number',
            'numeric',
            'money' =>
                $this->validDecimal($value)
                    ? null
                    : 'debe ser un decimal con punto y sin separador de miles.',

            'integer',
            'int' =>
                $this->validInteger($value)
                    ? null
                    : 'debe ser un entero.',

            'date' =>
                $this->validDate($value)
                    ? null
                    : 'debe usar el formato YYYY-MM-DD.',

            'datetime' =>
                $this->validDateTime($value)
                    ? null
                    : 'debe usar el formato YYYY-MM-DD HH:MM:SS.',

            'boolean',
            'bool' =>
                $this->validBoolean($value)
                    ? null
                    : 'debe usar true o false.',

            default =>
                "usa un tipo de esquema no soportado ({$type}).",
        };
    }

    private function validDecimal(
        mixed $value
    ): bool {
        if (
            is_int($value)
            || is_float($value)
        ) {
            return is_finite(
                (float) $value
            );
        }

        if (! is_string($value)) {
            return false;
        }

        return preg_match(
            '/^-?\d+(?:\.\d+)?$/',
            trim($value)
        ) === 1;
    }

    private function validInteger(
        mixed $value
    ): bool {
        if (is_int($value)) {
            return true;
        }

        if (is_float($value)) {
            return is_finite($value)
                && floor($value) === $value;
        }

        if (! is_string($value)) {
            return false;
        }

        return preg_match(
            '/^-?\d+$/',
            trim($value)
        ) === 1;
    }

    private function validDate(
        mixed $value
    ): bool {
        if (! is_string($value)) {
            return false;
        }

        $value =
            trim($value);

        $date =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $value
            );

        if ($date === false) {
            return false;
        }

        $state =
            DateTimeImmutable::getLastErrors();

        return (
            $state === false
            || (
                $state['warning_count'] === 0
                && $state['error_count'] === 0
            )
        )
        && $date->format('Y-m-d')
            === $value;
    }

    private function validDateTime(
        mixed $value
    ): bool {
        if (! is_string($value)) {
            return false;
        }

        $value =
            trim($value);

        $date =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i:s',
                $value
            );

        if ($date === false) {
            return false;
        }

        $state =
            DateTimeImmutable::getLastErrors();

        return (
            $state === false
            || (
                $state['warning_count'] === 0
                && $state['error_count'] === 0
            )
        )
        && $date->format(
            'Y-m-d H:i:s'
        ) === $value;
    }

    private function validBoolean(
        mixed $value
    ): bool {
        if (is_bool($value)) {
            return true;
        }

        return is_string($value)
            && (
                trim($value) === 'true'
                || trim($value) === 'false'
            );
    }

    private function identityKey(
        array $row,
        array $fields
    ): ?string {
        if ($fields === []) {
            return null;
        }

        $values = [];

        foreach ($fields as $field) {
            $value =
                $row[$field]
                ?? null;

            /*
             * Campos opcionales dentro de una clave compuesta,
             * como branch/warehouse de inventory, pueden quedar
             * vacíos y siguen formando parte determinista de la clave.
             */
            if ($this->blank($value)) {
                $values[] = '';
                continue;
            }

            $values[] =
                $this->comparable(
                    $value
                );
        }

        /*
         * Si toda la identidad está vacía, los errores de required
         * son suficientes y evitamos reportes duplicados artificiales.
         */
        if (
            count(
                array_filter(
                    $values,
                    static fn (
                        string $value
                    ): bool =>
                        $value !== ''
                )
            ) === 0
        ) {
            return null;
        }

        return json_encode(
            $values,
            JSON_THROW_ON_ERROR
        );
    }

    private function comparable(
        mixed $value
    ): string {
        if (is_bool($value)) {
            return $value
                ? 'true'
                : 'false';
        }

        if (is_string($value)) {
            return trim($value);
        }

        if (
            is_int($value)
            || is_float($value)
        ) {
            return (string) $value;
        }

        return json_encode(
            $value,
            JSON_THROW_ON_ERROR
        );
    }

    private function rowIsEmpty(
        array $row
    ): bool {
        foreach ($row as $value) {
            if (! $this->blank($value)) {
                return false;
            }
        }

        return true;
    }

    private function blank(
        mixed $value
    ): bool {
        return $value === null
            || (
                is_string($value)
                && trim($value) === ''
            );
    }
}
