<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiCanonicalField;
use App\Models\DataTransformationBiSourceAssetFieldMapping;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class DataTransformationBiMappingProjector
{
    /**
     * Project one physical source row into one canonical entity payload.
     *
     * Foundation scope:
     * - direct
     * - default
     * - unmapped
     *
     * transform remains deliberately blocked until a controlled,
     * versioned transformation catalog exists.
     *
     * @param array<string,mixed> $sourceValues
     * @param array<int,array<string,mixed>> $canonicalFields
     * @param array<int,array<string,mixed>> $fieldMappings
     *
     * @return array{
     *     payload:array<string,mixed>,
     *     projection_meta:array{
     *         mapped_fields:array<int,string>,
     *         defaulted_fields:array<int,string>,
     *         unmapped_fields:array<int,string>,
     *         null_fields:array<int,string>
     *     }
     * }
     */
    public function project(
        array $sourceValues,
        array $canonicalFields,
        array $fieldMappings
    ): array {
        $canonicalByKey =
            $this->canonicalFieldsByKey(
                $canonicalFields
            );

        $mappingsByField =
            $this->mappingsByCanonicalField(
                $fieldMappings
            );

        $payload = [];

        $meta = [
            'mapped_fields' => [],
            'defaulted_fields' => [],
            'unmapped_fields' => [],
            'null_fields' => [],
        ];

        foreach (
            $canonicalByKey
            as $fieldKey => $canonicalField
        ) {
            if (
                ! array_key_exists(
                    $fieldKey,
                    $mappingsByField
                )
            ) {
                throw ValidationException::withMessages([
                    'mapping' => [
                        "Falta una decisión de mapeo para {$fieldKey}.",
                    ],
                ]);
            }

            $mapping =
                $mappingsByField[$fieldKey];

            $mappingType =
                (string) (
                    $mapping['mapping_type']
                    ?? ''
                );

            $rawValue =
                match ($mappingType) {
                    DataTransformationBiSourceAssetFieldMapping
                        ::TYPE_DIRECT =>
                            $this->directValue(
                                $sourceValues,
                                $mapping,
                                $fieldKey
                            ),

                    DataTransformationBiSourceAssetFieldMapping
                        ::TYPE_DEFAULT =>
                            $mapping['default_value']
                            ?? null,

                    DataTransformationBiSourceAssetFieldMapping
                        ::TYPE_UNMAPPED =>
                            null,

                    DataTransformationBiSourceAssetFieldMapping
                        ::TYPE_TRANSFORM =>
                            throw ValidationException
                                ::withMessages([
                                    'mapping' => [
                                        "El campo {$fieldKey} usa una transformación todavía no ejecutable.",
                                    ],
                                ]),

                    default =>
                        throw ValidationException
                            ::withMessages([
                                'mapping' => [
                                    "Tipo de mapeo no soportado para {$fieldKey}.",
                                ],
                            ]),
                };

            $value =
                $this->coerce(
                    $rawValue,
                    (string) (
                        $canonicalField['data_type']
                        ?? ''
                    ),
                    $fieldKey
                );

            $payload[$fieldKey] =
                $value;

            match ($mappingType) {
                DataTransformationBiSourceAssetFieldMapping
                    ::TYPE_DIRECT =>
                        $meta['mapped_fields'][] =
                            $fieldKey,

                DataTransformationBiSourceAssetFieldMapping
                    ::TYPE_DEFAULT =>
                        $meta['defaulted_fields'][] =
                            $fieldKey,

                DataTransformationBiSourceAssetFieldMapping
                    ::TYPE_UNMAPPED =>
                        $meta['unmapped_fields'][] =
                            $fieldKey,

                default =>
                    null,
            };

            if ($value === null) {
                $meta['null_fields'][] =
                    $fieldKey;
            }
        }

        return [
            'payload' =>
                $payload,

            'projection_meta' =>
                $meta,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $fields
     *
     * @return array<string,array<string,mixed>>
     */
    private function canonicalFieldsByKey(
        array $fields
    ): array {
        $result = [];

        foreach ($fields as $field) {
            $key =
                trim(
                    (string) (
                        $field['field_key']
                        ?? ''
                    )
                );

            if ($key === '') {
                throw ValidationException::withMessages([
                    'canonical_fields' => [
                        'Existe un campo canónico sin field_key.',
                    ],
                ]);
            }

            if (isset($result[$key])) {
                throw ValidationException::withMessages([
                    'canonical_fields' => [
                        "El campo canónico {$key} está duplicado.",
                    ],
                ]);
            }

            $result[$key] =
                $field;
        }

        return $result;
    }

    /**
     * @param array<int,array<string,mixed>> $mappings
     *
     * @return array<string,array<string,mixed>>
     */
    private function mappingsByCanonicalField(
        array $mappings
    ): array {
        $result = [];

        foreach ($mappings as $mapping) {
            $fieldKey =
                trim(
                    (string) (
                        $mapping['canonical_field_key']
                        ?? ''
                    )
                );

            if ($fieldKey === '') {
                throw ValidationException::withMessages([
                    'mapping' => [
                        'Existe una decisión sin canonical_field_key.',
                    ],
                ]);
            }

            if (isset($result[$fieldKey])) {
                throw ValidationException::withMessages([
                    'mapping' => [
                        "Existe más de una decisión para {$fieldKey}.",
                    ],
                ]);
            }

            $result[$fieldKey] =
                $mapping;
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $sourceValues
     * @param array<string,mixed> $mapping
     */
    private function directValue(
        array $sourceValues,
        array $mapping,
        string $fieldKey
    ): mixed {
        $sourceColumn =
            trim(
                (string) (
                    $mapping['source_column_key']
                    ?? ''
                )
            );

        if ($sourceColumn === '') {
            throw ValidationException::withMessages([
                'mapping' => [
                    "El mapeo directo de {$fieldKey} no define columna fuente.",
                ],
            ]);
        }

        if (
            ! array_key_exists(
                $sourceColumn,
                $sourceValues
            )
        ) {
            throw ValidationException::withMessages([
                'source_row' => [
                    "La columna fuente {$sourceColumn} no existe en la fila.",
                ],
            ]);
        }

        return $sourceValues[
            $sourceColumn
        ];
    }

    private function coerce(
        mixed $value,
        string $type,
        string $fieldKey
    ): mixed {
        if (
            $value === null
            || (
                is_string($value)
                && trim($value) === ''
            )
        ) {
            return null;
        }

        return match ($type) {
            DataTransformationBiCanonicalField::TYPE_TEXT =>
                $this->coerceText(
                    $value,
                    $fieldKey
                ),

            DataTransformationBiCanonicalField::TYPE_INTEGER =>
                $this->coerceInteger(
                    $value,
                    $fieldKey
                ),

            DataTransformationBiCanonicalField::TYPE_DECIMAL =>
                $this->coerceDecimal(
                    $value,
                    $fieldKey
                ),

            DataTransformationBiCanonicalField::TYPE_BOOLEAN =>
                $this->coerceBoolean(
                    $value,
                    $fieldKey
                ),

            DataTransformationBiCanonicalField::TYPE_DATE =>
                $this->coerceDate(
                    $value,
                    $fieldKey
                ),

            DataTransformationBiCanonicalField::TYPE_DATETIME =>
                $this->coerceDateTime(
                    $value,
                    $fieldKey
                ),

            default =>
                throw ValidationException::withMessages([
                    'canonical_fields' => [
                        "Tipo canónico no soportado para {$fieldKey}.",
                    ],
                ]),
        };
    }

    private function coerceText(
        mixed $value,
        string $fieldKey
    ): string {
        if (! is_scalar($value)) {
            throw $this->coercionException(
                $fieldKey,
                'text'
            );
        }

        return trim(
            (string) $value
        );
    }

    private function coerceInteger(
        mixed $value,
        string $fieldKey
    ): int {
        if (is_int($value)) {
            return $value;
        }

        if (
            is_float($value)
            && floor($value) === $value
        ) {
            return (int) $value;
        }

        if (
            is_string($value)
            && preg_match(
                '/^[+-]?\d+$/',
                trim($value)
            ) === 1
        ) {
            return (int) trim($value);
        }

        throw $this->coercionException(
            $fieldKey,
            'integer'
        );
    }

    private function coerceDecimal(
        mixed $value,
        string $fieldKey
    ): string {
        if (
            is_int($value)
            || is_float($value)
        ) {
            $value =
                (string) $value;
        }

        if (! is_string($value)) {
            throw $this->coercionException(
                $fieldKey,
                'decimal'
            );
        }

        $normalized =
            trim($value);

        if (
            preg_match(
                '/^[+-]?(?:\d+\.?\d*|\.\d+)$/',
                $normalized
            ) !== 1
        ) {
            throw $this->coercionException(
                $fieldKey,
                'decimal'
            );
        }

        return $this->normalizeDecimalString(
            $normalized
        );
    }

    private function coerceBoolean(
        mixed $value,
        string $fieldKey
    ): bool {
        if (is_bool($value)) {
            return $value;
        }

        if (
            is_int($value)
            && in_array(
                $value,
                [0, 1],
                true
            )
        ) {
            return $value === 1;
        }

        if (is_string($value)) {
            $normalized =
                strtolower(
                    trim($value)
                );

            return match ($normalized) {
                '1',
                'true',
                'yes',
                'y',
                'si',
                'sí' =>
                    true,

                '0',
                'false',
                'no',
                'n' =>
                    false,

                default =>
                    throw $this->coercionException(
                        $fieldKey,
                        'boolean'
                    ),
            };
        }

        throw $this->coercionException(
            $fieldKey,
            'boolean'
        );
    }

    private function coerceDate(
        mixed $value,
        string $fieldKey
    ): string {
        try {
            if (
                $value instanceof \DateTimeInterface
            ) {
                return CarbonImmutable
                    ::instance($value)
                    ->format('Y-m-d');
            }

            if (! is_scalar($value)) {
                throw new \RuntimeException();
            }

            $text =
                trim(
                    (string) $value
                );

            $date =
                CarbonImmutable::createFromFormat(
                    '!Y-m-d',
                    $text
                );

            if (
                $date === false
                || $date->format('Y-m-d')
                    !== $text
            ) {
                throw new \RuntimeException();
            }

            return $text;
        } catch (\Throwable) {
            throw $this->coercionException(
                $fieldKey,
                'date'
            );
        }
    }

    private function coerceDateTime(
        mixed $value,
        string $fieldKey
    ): string {
        if (
            $value instanceof \DateTimeInterface
        ) {
            return CarbonImmutable
                ::instance($value)
                ->format('Y-m-d H:i:s');
        }

        if (! is_scalar($value)) {
            throw $this->coercionException(
                $fieldKey,
                'datetime'
            );
        }

        $text =
            trim(
                (string) $value
            );

        foreach (
            [
                'Y-m-d H:i:s',
                'Y-m-d\TH:i:s',
            ]
            as $format
        ) {
            try {
                $date =
                    CarbonImmutable::createFromFormat(
                        '!'.$format,
                        $text
                    );
            } catch (\Throwable) {
                /*
                 * A mismatch with one accepted representation must not
                 * prevent trying the next supported datetime format.
                 */
                continue;
            }

            if (
                $date !== false
                && $date->format($format)
                    === $text
            ) {
                return $date->format(
                    'Y-m-d H:i:s'
                );
            }
        }

        throw $this->coercionException(
            $fieldKey,
            'datetime'
        );
    }

    private function normalizeDecimalString(
        string $value
    ): string {
        $negative =
            str_starts_with(
                $value,
                '-'
            );

        $unsigned =
            ltrim(
                $value,
                '+-'
            );

        [$integer, $fraction] =
            array_pad(
                explode(
                    '.',
                    $unsigned,
                    2
                ),
                2,
                ''
            );

        $integer =
            ltrim(
                $integer,
                '0'
            );

        if ($integer === '') {
            $integer = '0';
        }

        $fraction =
            rtrim(
                $fraction,
                '0'
            );

        $normalized =
            $fraction === ''
                ? $integer
                : $integer.'.'.$fraction;

        if (
            $negative
            && $normalized !== '0'
        ) {
            $normalized =
                '-'.$normalized;
        }

        return $normalized;
    }

    private function coercionException(
        string $fieldKey,
        string $type
    ): ValidationException {
        return ValidationException::withMessages([
            'canonical_payload' => [
                "El valor de {$fieldKey} no puede convertirse a {$type}.",
            ],
        ]);
    }
}
