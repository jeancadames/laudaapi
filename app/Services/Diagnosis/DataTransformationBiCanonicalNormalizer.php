<?php

namespace App\Services\Diagnosis;

use DateTimeImmutable;
use RuntimeException;

final class DataTransformationBiCanonicalNormalizer
{
    /**
     * Normalize one staged canonical payload according to schema v1.
     *
     * No persistence and no enrichment occur here.
     *
     * @return array{
     *     payload:array<string,mixed>,
     *     change_count:int,
     *     changed_fields:array<int,string>
     * }
     */
    public function normalize(
        string $domain,
        array $payload
    ): array {
        $schema =
            DataTransformationBiStandardIntakeSchema
                ::domains();

        if (
            ! array_key_exists(
                $domain,
                $schema
            )
        ) {
            throw new RuntimeException(
                "Dominio canónico no soportado: {$domain}."
            );
        }

        $normalized = [];
        $changedFields = [];

        foreach (
            $schema[$domain]['fields']
            as $field
        ) {
            $fieldKey =
                (string) $field['name'];

            $type =
                (string) $field['type'];

            $original =
                array_key_exists(
                    $fieldKey,
                    $payload
                )
                    ? $payload[$fieldKey]
                    : null;

            $value =
                $this->normalizeValue(
                    $type,
                    $original
                );

            $normalized[$fieldKey] =
                $value;

            if (
                ! $this->equivalent(
                    $original,
                    $value
                )
            ) {
                $changedFields[] =
                    $fieldKey;
            }
        }

        return [
            'payload' =>
                $normalized,

            'change_count' =>
                count(
                    $changedFields
                ),

            /*
             * Field names only.
             * Raw before/after values are deliberately not retained.
             */
            'changed_fields' =>
                $changedFields,
        ];
    }

    public function canonicalJson(
        array $payload
    ): string {
        /*
         * JSON object property order is not part of the business value.
         *
         * Hashes must therefore remain stable even when persistence,
         * transport or decoding returns associative keys in a different
         * physical order.
         */
        $canonical =
            $this->canonicalizeForHash(
                $payload
            );

        return json_encode(
            $canonical,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_PRESERVE_ZERO_FRACTION
            | JSON_THROW_ON_ERROR
        );
    }

    private function canonicalizeForHash(
        mixed $value
    ): mixed {
        if (! is_array($value)) {
            return $value;
        }

        /*
         * Lists are order-sensitive.
         * Associative objects are key-order-insensitive.
         */
        if (array_is_list($value)) {
            return array_map(
                fn (mixed $item): mixed =>
                    $this->canonicalizeForHash(
                        $item
                    ),
                $value
            );
        }

        ksort(
            $value,
            SORT_STRING
        );

        foreach (
            $value
            as $key => $item
        ) {
            $value[$key] =
                $this->canonicalizeForHash(
                    $item
                );
        }

        return $value;
    }

    private function normalizeValue(
        string $type,
        mixed $value
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
            'text',
            'string',
            'identifier' =>
                $this->normalizeText(
                    $value
                ),

            'decimal',
            'number',
            'numeric',
            'money' =>
                $this->normalizeDecimal(
                    $value
                ),

            'integer',
            'int' =>
                $this->normalizeInteger(
                    $value
                ),

            'date' =>
                $this->normalizeDate(
                    $value
                ),

            'datetime' =>
                $this->normalizeDateTime(
                    $value
                ),

            'boolean',
            'bool' =>
                $this->normalizeBoolean(
                    $value
                ),

            default =>
                throw new RuntimeException(
                    "Tipo canónico no soportado: {$type}."
                ),
        };
    }

    private function normalizeText(
        mixed $value
    ): string {
        if (
            is_array($value)
            || is_object($value)
        ) {
            throw new RuntimeException(
                'Un campo de texto recibió una estructura no escalar.'
            );
        }

        return trim(
            (string) $value
        );
    }

    /**
     * Canonical decimals remain strings so normalization never introduces
     * binary floating-point precision changes.
     */
    private function normalizeDecimal(
        mixed $value
    ): string {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new RuntimeException(
                    'Un decimal no puede ser infinito o NaN.'
                );
            }

            $value =
                sprintf(
                    '%.14F',
                    $value
                );
        }

        if (! is_string($value)) {
            throw new RuntimeException(
                'Un decimal recibió un valor no escalar compatible.'
            );
        }

        $value =
            trim(
                $value
            );

        if (
            preg_match(
                '/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/',
                $value
            )
            !== 1
        ) {
            throw new RuntimeException(
                'El valor decimal no cumple el formato canónico.'
            );
        }

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

        [
            $integer,
            $fraction,
        ] =
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

        $canonical =
            $fraction === ''
                ? $integer
                : $integer.'.'.$fraction;

        if (
            $negative
            && $canonical !== '0'
        ) {
            $canonical =
                '-'.$canonical;
        }

        return $canonical;
    }

    private function normalizeInteger(
        mixed $value
    ): int {
        if (is_int($value)) {
            return $value;
        }

        if (
            is_float($value)
            && is_finite($value)
            && floor($value) === $value
        ) {
            return (int) $value;
        }

        if (
            is_string($value)
            && preg_match(
                '/^[+-]?\d+$/',
                trim($value)
            )
            === 1
        ) {
            return (int) trim(
                $value
            );
        }

        throw new RuntimeException(
            'El valor entero no cumple el formato canónico.'
        );
    }

    private function normalizeDate(
        mixed $value
    ): string {
        if (! is_string($value)) {
            throw new RuntimeException(
                'La fecha debe recibirse como texto canónico.'
            );
        }

        $value =
            trim(
                $value
            );

        $date =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $value
            );

        $errors =
            DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || (
                is_array($errors)
                && (
                    $errors['warning_count'] > 0
                    || $errors['error_count'] > 0
                )
            )
            || $date->format('Y-m-d') !== $value
        ) {
            throw new RuntimeException(
                'La fecha no cumple YYYY-MM-DD.'
            );
        }

        return $date->format(
            'Y-m-d'
        );
    }

    private function normalizeDateTime(
        mixed $value
    ): string {
        if (! is_string($value)) {
            throw new RuntimeException(
                'La fecha/hora debe recibirse como texto canónico.'
            );
        }

        $value =
            trim(
                $value
            );

        $date =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i:s',
                $value
            );

        $errors =
            DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || (
                is_array($errors)
                && (
                    $errors['warning_count'] > 0
                    || $errors['error_count'] > 0
                )
            )
            || $date->format(
                'Y-m-d H:i:s'
            )
                !== $value
        ) {
            throw new RuntimeException(
                'La fecha/hora no cumple YYYY-MM-DD HH:MM:SS.'
            );
        }

        return $date->format(
            'Y-m-d H:i:s'
        );
    }

    private function normalizeBoolean(
        mixed $value
    ): bool {
        if (is_bool($value)) {
            return $value;
        }

        if (
            $value === 1
            || $value === 0
        ) {
            return (bool) $value;
        }

        if (is_string($value)) {
            return match (
                strtolower(
                    trim(
                        $value
                    )
                )
            ) {
                'true',
                '1' =>
                    true,

                'false',
                '0' =>
                    false,

                default =>
                    throw new RuntimeException(
                        'El booleano no cumple true|false.'
                    ),
            };
        }

        throw new RuntimeException(
            'El booleano no cumple el formato canónico.'
        );
    }

    private function equivalent(
        mixed $original,
        mixed $normalized
    ): bool {
        if ($original === $normalized) {
            return true;
        }

        /*
         * Detect true normalization changes rather than PHP loose equality.
         */
        return serialize(
            $original
        )
            === serialize(
                $normalized
            );
    }
}
