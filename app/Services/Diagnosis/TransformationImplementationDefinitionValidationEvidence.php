<?php

namespace App\Services\Diagnosis;

use Illuminate\Validation\ValidationException;

final class TransformationImplementationDefinitionValidationEvidence
{
    public const CAPABILITY_KEY =
        'data_transformation_bi';

    private const STATUSES = [
        'pending',
        'validated',
        'blocked',
        'not_applicable',
    ];

    private const LEGACY_SOURCE_TYPES = [
        'sql_server',
        'mysql',
        'postgresql',
        'dbf',
        'quickbooks',
        'excel',
        'csv',
        'api',
        'other',
    ];

    private const SOURCE_ROLES = [
        'primary',
        'historical',
        'complementary',
        'derived',
    ];

    private const DELIVERY_FORMATS = [
        'csv',
        'xlsx',
    ];

    private const INPUT_KEYS = [
        'source_name',
        'source_type',
        'source_role',
        'delivery_format',
        'extraction_assistance_required',
        'data_domains',
        'owner',
        'historical_coverage',
        'granularity',
        'status',
        'notes',
    ];

    private const ACCESS_KEYS = [
        'source_name',
        'access_method',
        'authorized',
        'verified',
        'status',
        'notes',
    ];

    public static function empty(): array
    {
        return [
            'inputs' => [],
            'accesses' => [],
        ];
    }

    public static function normalize(
        mixed $value
    ): array {
        if ($value === null) {
            return self::empty();
        }

        if (! is_array($value)) {
            throw ValidationException::withMessages([
                'readiness.validation_evidence' => [
                    'La evidencia de validación debe tener una estructura válida.',
                ],
            ]);
        }

        self::assertAllowedKeys(
            $value,
            [
                'inputs',
                'accesses',
            ],
            'readiness.validation_evidence'
        );

        return [
            'inputs' =>
                self::normalizeInputs(
                    $value['inputs'] ?? []
                ),

            'accesses' =>
                self::normalizeAccesses(
                    $value['accesses'] ?? []
                ),
        ];
    }

    public static function assertSupportsConfirmations(
        array $humanValidation,
        array $evidence
    ): void {
        if (
            ($humanValidation['inputs_validated'] ?? false)
            === true
        ) {
            self::assertCompleteCollection(
                $evidence['inputs'] ?? [],
                'readiness.inputs_validated',
                'los insumos'
            );
        }

        if (
            ($humanValidation['accesses_validated'] ?? false)
            === true
        ) {
            self::assertCompleteCollection(
                $evidence['accesses'] ?? [],
                'readiness.accesses_validated',
                'los accesos'
            );
        }
    }

    private static function normalizeInputs(
        mixed $items
    ): array {
        if (! is_array($items)) {
            throw ValidationException::withMessages([
                'readiness.validation_evidence.inputs' => [
                    'La evidencia de insumos debe enviarse como una lista.',
                ],
            ]);
        }

        $normalized = [];

        foreach ($items as $index => $item) {
            $field =
                "readiness.validation_evidence.inputs.{$index}";

            if (! is_array($item)) {
                throw ValidationException::withMessages([
                    $field => [
                        'La evidencia del insumo no es válida.',
                    ],
                ]);
            }

            self::assertAllowedKeys(
                $item,
                self::INPUT_KEYS,
                $field
            );

            $hasLegacySourceType =
                array_key_exists(
                    'source_type',
                    $item
                );

            /*
             * El intake estándar comienza en la entrega de
             * archivos CSV/XLSX. El tipo técnico del sistema
             * de origen se conserva solo por compatibilidad
             * con evidencia histórica.
             */
            $usesStandardIntakeContract =
                array_key_exists('source_role', $item)
                || array_key_exists('delivery_format', $item)
                || array_key_exists(
                    'extraction_assistance_required',
                    $item
                );

            $sourceName =
                self::requiredString(
                    $item['source_name'] ?? null,
                    "{$field}.source_name",
                    'La fuente de datos es obligatoria.'
                );

            $sourceType =
                self::nullableEnum(
                    $item['source_type'] ?? null,
                    "{$field}.source_type",
                    self::LEGACY_SOURCE_TYPES,
                    'El tipo de fuente no es válido.'
                );

            $sourceRole =
                self::nullableEnum(
                    $item['source_role'] ?? null,
                    "{$field}.source_role",
                    self::SOURCE_ROLES,
                    'El rol de la fuente no es válido.'
                );

            $deliveryFormat =
                self::nullableEnum(
                    $item['delivery_format'] ?? null,
                    "{$field}.delivery_format",
                    self::DELIVERY_FORMATS,
                    'El formato de entrega debe ser CSV o XLSX.'
                );

            $extractionAssistanceRequired =
                self::nullableBoolean(
                    $item[
                        'extraction_assistance_required'
                    ] ?? null,
                    "{$field}.extraction_assistance_required"
                );

            $dataDomains =
                self::stringList(
                    $item['data_domains'] ?? null,
                    "{$field}.data_domains",
                    'Debe indicarse al menos un dominio de datos.'
                );

            $owner =
                self::nullableString(
                    $item['owner'] ?? null,
                    "{$field}.owner"
                );

            $historicalCoverage =
                self::nullableString(
                    $item['historical_coverage'] ?? null,
                    "{$field}.historical_coverage"
                );

            $granularity =
                self::nullableString(
                    $item['granularity'] ?? null,
                    "{$field}.granularity"
                );

            $status =
                self::status(
                    $item['status'] ?? null,
                    "{$field}.status"
                );

            $notes =
                self::nullableString(
                    $item['notes'] ?? null,
                    "{$field}.notes"
                );

            if ($status === 'validated') {
                foreach ([
                    'owner' =>
                        $owner,

                    'historical_coverage' =>
                        $historicalCoverage,

                    'granularity' =>
                        $granularity,
                ] as $key => $value) {
                    if ($value === null) {
                        throw ValidationException::withMessages([
                            "{$field}.{$key}" => [
                                'La evidencia validada debe completar este campo.',
                            ],
                        ]);
                    }
                }
            }

            if (
                $usesStandardIntakeContract
                && $status === 'validated'
            ) {
                if ($sourceRole === null) {
                    throw ValidationException::withMessages([
                        "{$field}.source_role" => [
                            'La evidencia validada debe indicar el rol de la fuente.',
                        ],
                    ]);
                }

                if ($extractionAssistanceRequired === null) {
                    throw ValidationException::withMessages([
                        "{$field}.extraction_assistance_required" => [
                            'La evidencia validada debe indicar si requiere asistencia de extracción.',
                        ],
                    ]);
                }

                if ($extractionAssistanceRequired === true) {
                    throw ValidationException::withMessages([
                        "{$field}.extraction_assistance_required" => [
                            'La fuente no puede marcarse como validada mientras requiera asistencia de extracción.',
                        ],
                    ]);
                }

                if ($deliveryFormat === null) {
                    throw ValidationException::withMessages([
                        "{$field}.delivery_format" => [
                            'La evidencia validada debe indicar un formato estándar de entrega: CSV o XLSX.',
                        ],
                    ]);
                }
            }

            if (
                $status === 'not_applicable'
                && $notes === null
            ) {
                throw ValidationException::withMessages([
                    "{$field}.notes" => [
                        'Debe explicarse por qué esta fuente no aplica.',
                    ],
                ]);
            }

            $normalized[] = [
                'source_name' =>
                    $sourceName,

                ...(
                    $hasLegacySourceType
                        ? [
                            'source_type' =>
                                $sourceType,
                        ]
                        : []
                ),

                ...(
                    $usesStandardIntakeContract
                        ? [
                            'source_role' =>
                                $sourceRole,

                            'delivery_format' =>
                                $deliveryFormat,

                            'extraction_assistance_required' =>
                                $extractionAssistanceRequired,
                        ]
                        : []
                ),

                'data_domains' =>
                    $dataDomains,

                'owner' =>
                    $owner,

                'historical_coverage' =>
                    $historicalCoverage,

                'granularity' =>
                    $granularity,

                'status' =>
                    $status,

                'notes' =>
                    $notes,
            ];
        }

        return $normalized;
    }

    private static function normalizeAccesses(
        mixed $items
    ): array {
        if (! is_array($items)) {
            throw ValidationException::withMessages([
                'readiness.validation_evidence.accesses' => [
                    'La evidencia de accesos debe enviarse como una lista.',
                ],
            ]);
        }

        $normalized = [];

        foreach ($items as $index => $item) {
            $field =
                "readiness.validation_evidence.accesses.{$index}";

            if (! is_array($item)) {
                throw ValidationException::withMessages([
                    $field => [
                        'La evidencia del acceso no es válida.',
                    ],
                ]);
            }

            self::assertAllowedKeys(
                $item,
                self::ACCESS_KEYS,
                $field
            );

            $sourceName =
                self::requiredString(
                    $item['source_name'] ?? null,
                    "{$field}.source_name",
                    'La fuente asociada al acceso es obligatoria.'
                );

            $accessMethod =
                self::nullableString(
                    $item['access_method'] ?? null,
                    "{$field}.access_method"
                );

            $authorized =
                self::boolean(
                    $item['authorized'] ?? false,
                    "{$field}.authorized"
                );

            $verified =
                self::boolean(
                    $item['verified'] ?? false,
                    "{$field}.verified"
                );

            $status =
                self::status(
                    $item['status'] ?? null,
                    "{$field}.status"
                );

            $notes =
                self::nullableString(
                    $item['notes'] ?? null,
                    "{$field}.notes"
                );

            if ($status === 'validated') {
                if ($accessMethod === null) {
                    throw ValidationException::withMessages([
                        "{$field}.access_method" => [
                            'El acceso validado debe indicar el mecanismo de acceso.',
                        ],
                    ]);
                }

                if (! $authorized) {
                    throw ValidationException::withMessages([
                        "{$field}.authorized" => [
                            'El acceso validado debe estar autorizado.',
                        ],
                    ]);
                }

                if (! $verified) {
                    throw ValidationException::withMessages([
                        "{$field}.verified" => [
                            'El acceso validado debe haber sido verificado.',
                        ],
                    ]);
                }
            }

            if (
                $status === 'not_applicable'
                && $notes === null
            ) {
                throw ValidationException::withMessages([
                    "{$field}.notes" => [
                        'Debe explicarse por qué este acceso no aplica.',
                    ],
                ]);
            }

            $normalized[] = [
                'source_name' =>
                    $sourceName,

                'access_method' =>
                    $accessMethod,

                'authorized' =>
                    $authorized,

                'verified' =>
                    $verified,

                'status' =>
                    $status,

                'notes' =>
                    $notes,
            ];
        }

        return $normalized;
    }

    private static function assertCompleteCollection(
        array $items,
        string $field,
        string $label
    ): void {
        if ($items === []) {
            throw ValidationException::withMessages([
                $field => [
                    "No puede confirmarse {$label} sin evidencia registrada.",
                ],
            ]);
        }

        $validatedCount = 0;

        foreach ($items as $item) {
            $status =
                $item['status'] ?? null;

            if (
                in_array(
                    $status,
                    [
                        'pending',
                        'blocked',
                    ],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    $field => [
                        "No puede confirmarse {$label} mientras exista evidencia pendiente o bloqueada.",
                    ],
                ]);
            }

            if ($status === 'validated') {
                $validatedCount++;
            }
        }

        if ($validatedCount === 0) {
            throw ValidationException::withMessages([
                $field => [
                    "Debe existir al menos una evidencia validada para confirmar {$label}.",
                ],
            ]);
        }
    }

    private static function assertAllowedKeys(
        array $value,
        array $allowed,
        string $field
    ): void {
        $unknown =
            array_values(
                array_diff(
                    array_keys($value),
                    $allowed
                )
            );

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                $field => [
                    'La evidencia contiene campos no permitidos: '
                    .implode(', ', $unknown)
                    .'. No almacenes contraseñas, tokens, API keys ni credenciales.',
                ],
            ]);
        }
    }

    private static function nullableEnum(
        mixed $value,
        string $field,
        array $allowed,
        string $message
    ): ?string {
        $value =
            self::nullableString(
                $value,
                $field
            );

        if ($value === null) {
            return null;
        }

        if (! in_array($value, $allowed, true)) {
            throw ValidationException::withMessages([
                $field => [
                    $message,
                ],
            ]);
        }

        return $value;
    }

    private static function requiredString(
        mixed $value,
        string $field,
        string $message
    ): string {
        if (! is_string($value)) {
            throw ValidationException::withMessages([
                $field => [
                    $message,
                ],
            ]);
        }

        $value =
            trim($value);

        if ($value === '') {
            throw ValidationException::withMessages([
                $field => [
                    $message,
                ],
            ]);
        }

        return $value;
    }

    private static function nullableString(
        mixed $value,
        string $field
    ): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw ValidationException::withMessages([
                $field => [
                    'El valor debe ser texto.',
                ],
            ]);
        }

        $value =
            trim($value);

        return $value !== ''
            ? $value
            : null;
    }

    private static function stringList(
        mixed $value,
        string $field,
        string $message
    ): array {
        if (! is_array($value)) {
            throw ValidationException::withMessages([
                $field => [
                    $message,
                ],
            ]);
        }

        $items = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw ValidationException::withMessages([
                    $field => [
                        $message,
                    ],
                ]);
            }

            $item =
                trim($item);

            if ($item !== '') {
                $items[] =
                    $item;
            }
        }

        $items =
            array_values(
                array_unique($items)
            );

        if ($items === []) {
            throw ValidationException::withMessages([
                $field => [
                    $message,
                ],
            ]);
        }

        return $items;
    }

    private static function nullableBoolean(
        mixed $value,
        string $field
    ): ?bool {
        if ($value === null) {
            return null;
        }

        return self::boolean(
            $value,
            $field
        );
    }

    private static function boolean(
        mixed $value,
        string $field
    ): bool {
        if (! is_bool($value)) {
            throw ValidationException::withMessages([
                $field => [
                    'El valor debe ser verdadero o falso.',
                ],
            ]);
        }

        return $value;
    }

    private static function status(
        mixed $value,
        string $field
    ): string {
        if (! is_string($value)) {
            throw ValidationException::withMessages([
                $field => [
                    'El estado de evidencia no es válido.',
                ],
            ]);
        }

        $value =
            trim($value);

        if (
            ! in_array(
                $value,
                self::STATUSES,
                true
            )
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'El estado debe ser pending, validated, blocked o not_applicable.',
                ],
            ]);
        }

        return $value;
    }
}
