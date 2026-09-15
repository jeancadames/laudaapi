<?php

namespace App\Services\Diagnosis;

use RuntimeException;

final class DataTransformationBiCanonicalDatasetContext
{
    /**
     * Canonical descriptor fields shared by the current usable-dataset
     * resolver and explicit historical-dataset resolver.
     */
    private const DESCRIPTOR_KEYS = [
        'processing_run_id',
        'intake_batch_id',
        'definition_version',
        'schema_version',
        'profiling_version',
        'normalization_version',
        'normalized_row_count',
        'has_rows',
        'completed_at',
    ];

    /**
     * Build one immutable technical context around an already-resolved
     * canonical prepared dataset.
     *
     * No database resolution occurs here. The caller remains responsible
     * for resolving the current or historical dataset and proving ownership.
     *
     * @param array<string,mixed> $dataset
     *
     * @return array{
     *     company_id:int,
     *     implementation_request_id:int,
     *     dataset:array{
     *         processing_run_id:int,
     *         intake_batch_id:int,
     *         definition_version:int|null,
     *         schema_version:mixed,
     *         profiling_version:mixed,
     *         normalization_version:mixed,
     *         normalized_row_count:int,
     *         has_rows:bool,
     *         completed_at:string|null
     *     },
     *     domains:list<string>
     * }
     */
    public static function fromResolved(
        int $companyId,
        int $implementationRequestId,
        array $dataset
    ): array {
        if ($companyId <= 0) {
            throw new RuntimeException(
                'El contexto canónico requiere una empresa válida.'
            );
        }

        if ($implementationRequestId <= 0) {
            throw new RuntimeException(
                'El contexto canónico requiere una solicitud válida.'
            );
        }

        return [
            'company_id' =>
                $companyId,

            'implementation_request_id' =>
                $implementationRequestId,

            'dataset' =>
                self::descriptor(
                    $dataset
                ),

            'domains' =>
                self::domains(),
        ];
    }

    /**
     * Normalize one resolved dataset into the single canonical descriptor
     * contract consumed by downstream foundational services.
     *
     * Extra input metadata is deliberately discarded.
     *
     * @param array<string,mixed> $dataset
     *
     * @return array{
     *     processing_run_id:int,
     *     intake_batch_id:int,
     *     definition_version:int|null,
     *     schema_version:mixed,
     *     profiling_version:mixed,
     *     normalization_version:mixed,
     *     normalized_row_count:int,
     *     has_rows:bool,
     *     completed_at:string|null
     * }
     */
    public static function descriptor(
        array $dataset
    ): array {
        foreach (
            self::DESCRIPTOR_KEYS
            as $key
        ) {
            if (! array_key_exists(
                $key,
                $dataset
            )) {
                throw new RuntimeException(
                    "El descriptor canónico no contiene {$key}."
                );
            }
        }

        $processingRunId =
            self::positiveInt(
                $dataset[
                    'processing_run_id'
                ],
                'processing_run_id'
            );

        $intakeBatchId =
            self::positiveInt(
                $dataset[
                    'intake_batch_id'
                ],
                'intake_batch_id'
            );

        $definitionVersion =
            self::nullablePositiveInt(
                $dataset[
                    'definition_version'
                ],
                'definition_version'
            );

        $schemaVersion =
            self::versionValue(
                $dataset[
                    'schema_version'
                ],
                'schema_version'
            );

        $profilingVersion =
            self::versionValue(
                $dataset[
                    'profiling_version'
                ],
                'profiling_version'
            );

        $normalizationVersion =
            self::versionValue(
                $dataset[
                    'normalization_version'
                ],
                'normalization_version'
            );

        $normalizedRowCount =
            self::nonNegativeInt(
                $dataset[
                    'normalized_row_count'
                ],
                'normalized_row_count'
            );

        $hasRows =
            $dataset[
                'has_rows'
            ];

        if (! is_bool($hasRows)) {
            throw new RuntimeException(
                'El descriptor canónico requiere has_rows booleano.'
            );
        }

        if (
            $hasRows
            !== (
                $normalizedRowCount > 0
            )
        ) {
            throw new RuntimeException(
                'has_rows no coincide con normalized_row_count.'
            );
        }

        $completedAt =
            $dataset[
                'completed_at'
            ];

        if (
            $completedAt !== null
            && (
                ! is_string(
                    $completedAt
                )
                || trim(
                    $completedAt
                ) === ''
            )
        ) {
            throw new RuntimeException(
                'completed_at debe ser texto no vacío o null.'
            );
        }

        return [
            'processing_run_id' =>
                $processingRunId,

            'intake_batch_id' =>
                $intakeBatchId,

            'definition_version' =>
                $definitionVersion,

            'schema_version' =>
                $schemaVersion,

            'profiling_version' =>
                $profilingVersion,

            'normalization_version' =>
                $normalizationVersion,

            'normalized_row_count' =>
                $normalizedRowCount,

            'has_rows' =>
                $hasRows,

            'completed_at' =>
                $completedAt,
        ];
    }

    /**
     * Return the canonical descriptor from an existing context while
     * revalidating its contract.
     *
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public static function dataset(
        array $context
    ): array {
        $dataset =
            $context[
                'dataset'
            ]
            ?? null;

        if (! is_array($dataset)) {
            throw new RuntimeException(
                'El contexto canónico no contiene un dataset válido.'
            );
        }

        return self::descriptor(
            $dataset
        );
    }

    /**
     * Technical identity sufficient to pin every downstream read to the
     * same company, request, processing run and intake batch.
     *
     * @param array<string,mixed> $context
     *
     * @return array{
     *     company_id:int,
     *     implementation_request_id:int,
     *     processing_run_id:int,
     *     intake_batch_id:int
     * }
     */
    public static function identity(
        array $context
    ): array {
        $companyId =
            self::positiveInt(
                $context[
                    'company_id'
                ]
                ?? null,
                'company_id'
            );

        $implementationRequestId =
            self::positiveInt(
                $context[
                    'implementation_request_id'
                ]
                ?? null,
                'implementation_request_id'
            );

        $dataset =
            self::dataset(
                $context
            );

        return [
            'company_id' =>
                $companyId,

            'implementation_request_id' =>
                $implementationRequestId,

            'processing_run_id' =>
                (int) $dataset[
                    'processing_run_id'
                ],

            'intake_batch_id' =>
                (int) $dataset[
                    'intake_batch_id'
                ],
        ];
    }

    /**
     * Stable canonical domain order owned by Standard Intake Schema.
     *
     * @return list<string>
     */
    public static function domains(): array
    {
        $domains =
            array_values(
                DataTransformationBiStandardIntakeSchema
                    ::domainKeys()
            );

        if ($domains === []) {
            throw new RuntimeException(
                'El catálogo canónico de dominios está vacío.'
            );
        }

        $seen = [];

        foreach ($domains as $domain) {
            if (
                ! is_string($domain)
                || trim($domain) === ''
            ) {
                throw new RuntimeException(
                    'El catálogo canónico contiene un dominio inválido.'
                );
            }

            if (
                array_key_exists(
                    $domain,
                    $seen
                )
            ) {
                throw new RuntimeException(
                    'El catálogo canónico contiene dominios duplicados.'
                );
            }

            $seen[$domain] =
                true;
        }

        return $domains;
    }

    private static function positiveInt(
        mixed $value,
        string $field
    ): int {
        if (
            is_int($value)
            && $value > 0
        ) {
            return $value;
        }

        if (
            is_string($value)
            && ctype_digit($value)
            && (int) $value > 0
        ) {
            return (int) $value;
        }

        throw new RuntimeException(
            "El descriptor canónico requiere {$field} positivo."
        );
    }

    private static function nullablePositiveInt(
        mixed $value,
        string $field
    ): ?int {
        if ($value === null) {
            return null;
        }

        return self::positiveInt(
            $value,
            $field
        );
    }

    private static function nonNegativeInt(
        mixed $value,
        string $field
    ): int {
        if (
            is_int($value)
            && $value >= 0
        ) {
            return $value;
        }

        if (
            is_string($value)
            && ctype_digit($value)
        ) {
            return (int) $value;
        }

        throw new RuntimeException(
            "El descriptor canónico requiere {$field} no negativo."
        );
    }

    private static function versionValue(
        mixed $value,
        string $field
    ): mixed {
        if (
            is_int($value)
            && $value >= 0
        ) {
            return $value;
        }

        if (
            is_string($value)
            && trim($value) !== ''
        ) {
            return $value;
        }

        throw new RuntimeException(
            "El descriptor canónico requiere {$field} válido."
        );
    }
}
