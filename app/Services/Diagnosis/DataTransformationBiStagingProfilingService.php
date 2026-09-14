<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiDomainProfile;
use App\Models\DataTransformationBiFieldProfile;
use App\Models\DataTransformationBiIntakeBatch;
use App\Models\DataTransformationBiIntakeRow;
use App\Models\DataTransformationBiProcessingRun;
use App\Models\DataTransformationBiQualityIssue;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class DataTransformationBiStagingProfilingService
{
    public const PROFILING_VERSION = 1;

    public const NORMALIZATION_VERSION = 1;

    private DataTransformationBiStandardIntakeRowValidator
        $rowValidator;

    public function __construct(
        ?DataTransformationBiStandardIntakeRowValidator $rowValidator = null
    ) {
        $this->rowValidator =
            $rowValidator
            ?? new DataTransformationBiStandardIntakeRowValidator();
    }

    public function profile(
        DataTransformationBiIntakeBatch $batch,
        User $actor
    ): array {
        $this->assertAdmin(
            $actor
        );

        $this->assertBatch(
            $batch
        );

        $run =
            $this->reserveRun(
                $batch,
                $actor
            );

        if (
            $run->status
            === DataTransformationBiProcessingRun::STATUS_COMPLETED
        ) {
            return $this->summary(
                $run,
                true
            );
        }

        try {
            $this->executeProfiling(
                $batch,
                $run
            );
        } catch (Throwable $exception) {
            $this->markFailed(
                $run,
                $exception
            );

            throw $exception;
        }

        $run->refresh();

        return $this->summary(
            $run,
            false
        );
    }

    private function assertAdmin(
        User $actor
    ): void {
        if (
            ! $actor->exists
            || $actor->getKey() === null
            || (string) $actor->role !== 'admin'
        ) {
            throw new AuthorizationException(
                'Solo un administrador de LAUDA puede ejecutar el perfilado.'
            );
        }
    }

    private function assertBatch(
        DataTransformationBiIntakeBatch $batch
    ): void {
        if (
            ! $batch->exists
            || $batch->getKey() === null
        ) {
            throw new RuntimeException(
                'El batch de intake debe existir antes del perfilado.'
            );
        }

        if (
            $batch->status
            !== DataTransformationBiIntakeBatch::STATUS_COMPLETED
        ) {
            throw new RuntimeException(
                'Solo un batch de intake completado puede perfilarse.'
            );
        }

        if (
            (int) $batch->schema_version
            !== DataTransformationBiStandardIntakeSchema::VERSION
        ) {
            throw new RuntimeException(
                'La versión del staging no coincide con el esquema soportado.'
            );
        }

        if (
            (int) $batch->company_id <= 0
            || (int) $batch->transformation_implementation_request_id <= 0
        ) {
            throw new RuntimeException(
                'El batch no conserva ownership suficiente para procesamiento.'
            );
        }
    }

    private function reserveRun(
        DataTransformationBiIntakeBatch $batch,
        User $actor
    ): DataTransformationBiProcessingRun {
        return DB::transaction(
            function () use (
                $batch,
                $actor
            ): DataTransformationBiProcessingRun {
                $lockedBatch =
                    DataTransformationBiIntakeBatch::query()
                        ->whereKey(
                            $batch->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertBatch(
                    $lockedBatch
                );

                $run =
                    DataTransformationBiProcessingRun::query()
                        ->where(
                            'data_transformation_bi_intake_batch_id',
                            $lockedBatch->getKey()
                        )
                        ->where(
                            'profiling_version',
                            self::PROFILING_VERSION
                        )
                        ->where(
                            'normalization_version',
                            self::NORMALIZATION_VERSION
                        )
                        ->lockForUpdate()
                        ->first();

                if ($run === null) {
                    return DataTransformationBiProcessingRun::query()
                        ->create([
                            'data_transformation_bi_intake_batch_id' =>
                                (int) $lockedBatch->getKey(),

                            'company_id' =>
                                (int) $lockedBatch->company_id,

                            'transformation_implementation_request_id' =>
                                (int) $lockedBatch
                                    ->transformation_implementation_request_id,

                            'transformation_implementation_definition_id' =>
                                $lockedBatch
                                    ->transformation_implementation_definition_id,

                            'definition_version' =>
                                $lockedBatch->definition_version,

                            'schema_version' =>
                                (int) $lockedBatch->schema_version,

                            'profiling_version' =>
                                self::PROFILING_VERSION,

                            'normalization_version' =>
                                self::NORMALIZATION_VERSION,

                            /*
                             * D2 completes profiling only.
                             * D3 normalization will complete the run.
                             */
                            'status' =>
                                DataTransformationBiProcessingRun
                                    ::STATUS_PROCESSING,

                            'source_row_count' =>
                                (int) $lockedBatch->staged_row_count,

                            'profiled_row_count' =>
                                0,

                            'normalized_row_count' =>
                                0,

                            'issue_count' =>
                                0,

                            'blocking_issue_count' =>
                                0,

                            'warning_issue_count' =>
                                0,

                            'created_by_user_id' =>
                                (int) $actor->getKey(),

                            'started_at' =>
                                now(),
                        ]);
                }

                $this->assertRunContract(
                    $lockedBatch,
                    $run
                );

                if (
                    $run->status
                    === DataTransformationBiProcessingRun::STATUS_COMPLETED
                ) {
                    return $run;
                }

                if (
                    $run->normalizedRows()
                        ->exists()
                ) {
                    throw new RuntimeException(
                        'No se puede recalcular profiling sobre un run '
                        .'con filas normalizadas parciales.'
                    );
                }

                $run
                    ->forceFill([
                        'status' =>
                            DataTransformationBiProcessingRun
                                ::STATUS_PROCESSING,

                        'source_row_count' =>
                            (int) $lockedBatch->staged_row_count,

                        'profiled_row_count' =>
                            0,

                        'normalized_row_count' =>
                            0,

                        'issue_count' =>
                            0,

                        'blocking_issue_count' =>
                            0,

                        'warning_issue_count' =>
                            0,

                        'started_at' =>
                            now(),

                        'completed_at' =>
                            null,

                        'failed_at' =>
                            null,

                        'failure_code' =>
                            null,

                        'failure_message' =>
                            null,
                    ])
                    ->save();

                return $run;
            }
        );
    }

    private function assertRunContract(
        DataTransformationBiIntakeBatch $batch,
        DataTransformationBiProcessingRun $run
    ): void {
        if (
            (int) $run->data_transformation_bi_intake_batch_id
                !== (int) $batch->getKey()
            || (int) $run->company_id
                !== (int) $batch->company_id
            || (int) $run->transformation_implementation_request_id
                !== (int) $batch
                    ->transformation_implementation_request_id
            || (int) $run->schema_version
                !== (int) $batch->schema_version
            || (int) $run->profiling_version
                !== self::PROFILING_VERSION
            || (int) $run->normalization_version
                !== self::NORMALIZATION_VERSION
        ) {
            throw new RuntimeException(
                'El processing run no coincide con el contrato inmutable '
                .'del intake batch.'
            );
        }
    }

    private function executeProfiling(
        DataTransformationBiIntakeBatch $batch,
        DataTransformationBiProcessingRun $run
    ): void {
        DB::transaction(
            function () use (
                $batch,
                $run
            ): void {
                $lockedRun =
                    DataTransformationBiProcessingRun::query()
                        ->whereKey(
                            $run->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $lockedRun->status
                    === DataTransformationBiProcessingRun::STATUS_COMPLETED
                ) {
                    return;
                }

                $rows =
                    DataTransformationBiIntakeRow::query()
                        ->where(
                            'data_transformation_bi_intake_batch_id',
                            $batch->getKey()
                        )
                        ->orderBy(
                            'domain_key'
                        )
                        ->orderBy(
                            'source_row_number'
                        )
                        ->orderBy(
                            'id'
                        )
                        ->get();

                $this->assertStagingIntegrity(
                    $batch,
                    $rows
                );

                /*
                 * Idempotent recalculation of D2-derived data.
                 * Source staging is never updated or deleted.
                 */
                DataTransformationBiQualityIssue::query()
                    ->where(
                        'data_transformation_bi_processing_run_id',
                        $lockedRun->getKey()
                    )
                    ->delete();

                DataTransformationBiFieldProfile::query()
                    ->where(
                        'data_transformation_bi_processing_run_id',
                        $lockedRun->getKey()
                    )
                    ->delete();

                DataTransformationBiDomainProfile::query()
                    ->where(
                        'data_transformation_bi_processing_run_id',
                        $lockedRun->getKey()
                    )
                    ->delete();

                $schema =
                    DataTransformationBiStandardIntakeSchema
                        ::domains();

                $rowsByDomain = [];

                foreach (
                    array_keys($schema)
                    as $domain
                ) {
                    $rowsByDomain[$domain] = [];
                }

                $unknownDomains = [];

                foreach ($rows as $row) {
                    $domain =
                        (string) $row->domain_key;

                    if (
                        ! array_key_exists(
                            $domain,
                            $schema
                        )
                    ) {
                        $unknownDomains[$domain] =
                            ($unknownDomains[$domain] ?? 0)
                            + 1;

                        continue;
                    }

                    if (! is_array($row->row_payload)) {
                        throw new RuntimeException(
                            'Una fila staged no contiene payload canónico válido.'
                        );
                    }

                    $rowsByDomain[$domain][] =
                        $row->row_payload;
                }

                /*
                 * Defense in depth:
                 * reuse the exact I15 row/relationship validator.
                 */
                $validation =
                    $this->rowValidator
                        ->validate(
                            $rowsByDomain
                        );

                foreach (
                    $unknownDomains
                    as $domain => $count
                ) {
                    $this->createIssue(
                        $lockedRun,
                        $batch,
                        $domain,
                        'unknown_staging_domain',
                        DataTransformationBiQualityIssue::SEVERITY_BLOCKING,
                        'Staging contiene filas de un dominio no reconocido '
                        .'por el esquema canónico.',
                        null,
                        null,
                        null,
                        [
                            'row_count' =>
                                $count,
                        ]
                    );
                }

                foreach (
                    $schema
                    as $domain => $definition
                ) {
                    $domainRows =
                        $rows->filter(
                            static fn (
                                DataTransformationBiIntakeRow $row
                            ): bool =>
                                (string) $row->domain_key
                                === $domain
                        )->values();

                    $this->profileDomain(
                        $lockedRun,
                        $batch,
                        $domain,
                        $definition,
                        $domainRows,
                        $validation['domains'][$domain]
                            ?? []
                    );
                }

                $issueCount =
                    DataTransformationBiQualityIssue::query()
                        ->where(
                            'data_transformation_bi_processing_run_id',
                            $lockedRun->getKey()
                        )
                        ->count();

                $blockingCount =
                    DataTransformationBiQualityIssue::query()
                        ->where(
                            'data_transformation_bi_processing_run_id',
                            $lockedRun->getKey()
                        )
                        ->where(
                            'severity',
                            DataTransformationBiQualityIssue
                                ::SEVERITY_BLOCKING
                        )
                        ->count();

                $warningCount =
                    DataTransformationBiQualityIssue::query()
                        ->where(
                            'data_transformation_bi_processing_run_id',
                            $lockedRun->getKey()
                        )
                        ->where(
                            'severity',
                            DataTransformationBiQualityIssue
                                ::SEVERITY_WARNING
                        )
                        ->count();

                $lockedRun
                    ->forceFill([
                        /*
                         * Remains processing until D3 normalization.
                         */
                        'status' =>
                            DataTransformationBiProcessingRun
                                ::STATUS_PROCESSING,

                        'source_row_count' =>
                            $rows->count(),

                        'profiled_row_count' =>
                            $rows->count(),

                        'normalized_row_count' =>
                            0,

                        'issue_count' =>
                            $issueCount,

                        'blocking_issue_count' =>
                            $blockingCount,

                        'warning_issue_count' =>
                            $warningCount,

                        'failed_at' =>
                            null,

                        'failure_code' =>
                            null,

                        'failure_message' =>
                            null,
                    ])
                    ->save();
            }
        );
    }

    private function assertStagingIntegrity(
        DataTransformationBiIntakeBatch $batch,
        Collection $rows
    ): void {
        if (
            $rows->count()
            !== (int) $batch->staged_row_count
        ) {
            throw new RuntimeException(
                'El conteo físico de staging no coincide con el batch.'
            );
        }

        foreach ($rows as $row) {
            if (
                (int) $row->company_id
                !== (int) $batch->company_id
            ) {
                throw new RuntimeException(
                    'Una fila staged no pertenece a la empresa del batch.'
                );
            }

            if (
                (int) $row->data_transformation_bi_intake_batch_id
                !== (int) $batch->getKey()
            ) {
                throw new RuntimeException(
                    'Una fila staged perdió la trazabilidad con su batch.'
                );
            }
        }
    }

    private function profileDomain(
        DataTransformationBiProcessingRun $run,
        DataTransformationBiIntakeBatch $batch,
        string $domain,
        array $definition,
        Collection $rows,
        array $validationSummary
    ): void {
        $rowCount =
            $rows->count();

        $identityHashes =
            $rows
                ->map(
                    static fn (
                        DataTransformationBiIntakeRow $row
                    ): string =>
                        trim(
                            (string) $row->identity_hash
                        )
                )
                ->filter(
                    static fn (
                        string $hash
                    ): bool =>
                        $hash !== ''
                );

        $identityCount =
            $identityHashes
                ->unique()
                ->count();

        $duplicateIdentityCount =
            $identityHashes
                ->countBy()
                ->reduce(
                    static fn (
                        int $carry,
                        int $count
                    ): int =>
                        $carry
                        + max(
                            0,
                            $count - 1
                        ),
                    0
                );

        $missingIdentityCount =
            $rowCount
            - $identityHashes->count();

        if ($missingIdentityCount > 0) {
            $this->createIssue(
                $run,
                $batch,
                $domain,
                'missing_identity_hash',
                DataTransformationBiQualityIssue::SEVERITY_BLOCKING,
                'Existen filas staged sin identidad canónica calculada.',
                null,
                null,
                null,
                [
                    'row_count' =>
                        $missingIdentityCount,
                ]
            );
        }

        if ($duplicateIdentityCount > 0) {
            $this->createIssue(
                $run,
                $batch,
                $domain,
                'duplicate_identity_in_staging',
                DataTransformationBiQualityIssue::SEVERITY_BLOCKING,
                'Staging contiene identidades canónicas duplicadas.',
                null,
                null,
                null,
                [
                    'duplicate_row_count' =>
                        $duplicateIdentityCount,
                ]
            );
        }

        foreach (
            $definition['fields'] ?? []
            as $field
        ) {
            $this->profileField(
                $run,
                $batch,
                $domain,
                $field,
                $rows
            );
        }

        $rowValidationErrors =
            is_array(
                $validationSummary['errors']
                ?? null
            )
                ? $validationSummary['errors']
                : [];

        if ($rowValidationErrors !== []) {
            $this->createIssue(
                $run,
                $batch,
                $domain,
                'staging_validation_contract_violation',
                DataTransformationBiQualityIssue::SEVERITY_BLOCKING,
                'Las filas staged ya no cumplen completamente '
                .'el contrato de validación canónica.',
                null,
                null,
                null,
                [
                    'error_count' =>
                        count(
                            $rowValidationErrors
                        ),
                ]
            );
        }

        $relationErrors =
            is_array(
                $validationSummary['relation_errors']
                ?? null
            )
                ? $validationSummary['relation_errors']
                : [];

        if ($relationErrors !== []) {
            $this->createIssue(
                $run,
                $batch,
                $domain,
                'staging_relation_contract_violation',
                DataTransformationBiQualityIssue::SEVERITY_BLOCKING,
                'Las relaciones canónicas del dominio presentan '
                .'inconsistencias en staging.',
                null,
                null,
                null,
                [
                    'error_count' =>
                        count(
                            $relationErrors
                        ),
                ]
            );
        }

        $issueQuery =
            DataTransformationBiQualityIssue::query()
                ->where(
                    'data_transformation_bi_processing_run_id',
                    $run->getKey()
                )
                ->where(
                    'domain_key',
                    $domain
                );

        DataTransformationBiDomainProfile::query()
            ->create([
                'data_transformation_bi_processing_run_id' =>
                    (int) $run->getKey(),

                'data_transformation_bi_intake_batch_id' =>
                    (int) $batch->getKey(),

                'company_id' =>
                    (int) $batch->company_id,

                'domain_key' =>
                    $domain,

                'status' =>
                    'completed',

                'row_count' =>
                    $rowCount,

                'field_count' =>
                    count(
                        $definition['fields']
                        ?? []
                    ),

                'identity_count' =>
                    $identityCount,

                'duplicate_identity_count' =>
                    $duplicateIdentityCount,

                'issue_count' =>
                    (clone $issueQuery)
                        ->count(),

                'blocking_issue_count' =>
                    (clone $issueQuery)
                        ->where(
                            'severity',
                            DataTransformationBiQualityIssue
                                ::SEVERITY_BLOCKING
                        )
                        ->count(),

                'warning_issue_count' =>
                    (clone $issueQuery)
                        ->where(
                            'severity',
                            DataTransformationBiQualityIssue
                                ::SEVERITY_WARNING
                        )
                        ->count(),

                /*
                 * D3 owns normalization.
                 */
                'normalized_row_count' =>
                    0,
            ]);
    }

    private function profileField(
        DataTransformationBiProcessingRun $run,
        DataTransformationBiIntakeBatch $batch,
        string $domain,
        array $field,
        Collection $rows
    ): void {
        $fieldKey =
            (string) (
                $field['name']
                ?? ''
            );

        if ($fieldKey === '') {
            throw new RuntimeException(
                'El esquema contiene un campo sin nombre.'
            );
        }

        $required =
            (bool) (
                $field['required']
                ?? false
            );

        $type =
            (string) (
                $field['type']
                ?? 'text'
            );

        $values =
            $rows->map(
                static function (
                    DataTransformationBiIntakeRow $row
                ) use (
                    $fieldKey
                ): mixed {
                    $payload =
                        $row->row_payload;

                    if (! is_array($payload)) {
                        return null;
                    }

                    return array_key_exists(
                        $fieldKey,
                        $payload
                    )
                        ? $payload[$fieldKey]
                        : null;
                }
            );

        $nullCount =
            $values
                ->filter(
                    static fn (
                        mixed $value
                    ): bool =>
                        $value === null
                )
                ->count();

        $blankCount =
            $values
                ->filter(
                    static fn (
                        mixed $value
                    ): bool =>
                        is_string($value)
                        && trim($value) === ''
                )
                ->count();

        $nonNullCount =
            $values->count()
            - $nullCount;

        $presentValues =
            $values
                ->filter(
                    static fn (
                        mixed $value
                    ): bool =>
                        ! self::blank(
                            $value
                        )
                );

        $distinctCount =
            $presentValues
                ->map(
                    static fn (
                        mixed $value
                    ): string =>
                        hash(
                            'sha256',
                            serialize(
                                $value
                            )
                        )
                )
                ->unique()
                ->count();

        $missingCount =
            $nullCount
            + $blankCount;

        $fieldIssueCount = 0;

        if ($missingCount > 0) {
            if ($required) {
                $this->createIssue(
                    $run,
                    $batch,
                    $domain,
                    'required_field_incomplete',
                    DataTransformationBiQualityIssue
                        ::SEVERITY_BLOCKING,
                    'El campo requerido presenta valores vacíos '
                    .'o nulos en staging.',
                    $fieldKey,
                    null,
                    null,
                    [
                        'missing_count' =>
                            $missingCount,

                        'row_count' =>
                            $values->count(),
                    ]
                );
            } else {
                /*
                 * Missing optional data is information,
                 * not an automatic business-quality failure.
                 */
                $this->createIssue(
                    $run,
                    $batch,
                    $domain,
                    'optional_field_incomplete',
                    DataTransformationBiQualityIssue
                        ::SEVERITY_INFO,
                    'El campo opcional presenta valores vacíos o nulos; '
                    .'se conserva como señal de completitud.',
                    $fieldKey,
                    null,
                    null,
                    [
                        'missing_count' =>
                            $missingCount,

                        'row_count' =>
                            $values->count(),
                    ]
                );
            }

            $fieldIssueCount++;
        }

        $rowCount =
            $values->count();

        $completeness =
            $rowCount > 0
                ? round(
                    (
                        ($rowCount - $missingCount)
                        / $rowCount
                    ) * 100,
                    2
                )
                : 100.0;

        DataTransformationBiFieldProfile::query()
            ->create([
                'data_transformation_bi_processing_run_id' =>
                    (int) $run->getKey(),

                'data_transformation_bi_intake_batch_id' =>
                    (int) $batch->getKey(),

                'company_id' =>
                    (int) $batch->company_id,

                'domain_key' =>
                    $domain,

                'field_key' =>
                    $fieldKey,

                'data_type' =>
                    $type,

                'required' =>
                    $required,

                'row_count' =>
                    $rowCount,

                'non_null_count' =>
                    $nonNullCount,

                'null_count' =>
                    $nullCount,

                'blank_count' =>
                    $blankCount,

                'distinct_count' =>
                    $distinctCount,

                /*
                 * Staging arrived through I15 validation.
                 * Any later contract violation is recorded separately
                 * by the reused canonical validator.
                 */
                'invalid_count' =>
                    0,

                'issue_count' =>
                    $fieldIssueCount,

                /*
                 * Aggregate-only metrics.
                 * No raw samples, minima, maxima or values are stored.
                 */
                'metrics' => [
                    'missing_count' =>
                        $missingCount,

                    'completeness_percent' =>
                        $completeness,
                ],
            ]);
    }

    private function createIssue(
        DataTransformationBiProcessingRun $run,
        DataTransformationBiIntakeBatch $batch,
        string $domain,
        string $code,
        string $severity,
        string $message,
        ?string $fieldKey,
        ?int $sourceRowNumber,
        ?string $identityHash,
        ?array $meta
    ): void {
        DataTransformationBiQualityIssue::query()
            ->create([
                'data_transformation_bi_processing_run_id' =>
                    (int) $run->getKey(),

                'data_transformation_bi_intake_batch_id' =>
                    (int) $batch->getKey(),

                'data_transformation_bi_intake_row_id' =>
                    null,

                'company_id' =>
                    (int) $batch->company_id,

                'domain_key' =>
                    $domain,

                'field_key' =>
                    $fieldKey,

                'source_row_number' =>
                    $sourceRowNumber,

                /*
                 * Hash only; never a raw business identifier.
                 */
                'identity_hash' =>
                    $identityHash,

                'issue_code' =>
                    $code,

                'severity' =>
                    $severity,

                'message' =>
                    $message,

                /*
                 * Counts and technical metadata only.
                 */
                'meta' =>
                    $meta,
            ]);
    }

    private function markFailed(
        DataTransformationBiProcessingRun $run,
        Throwable $exception
    ): void {
        DB::transaction(
            function () use (
                $run,
                $exception
            ): void {
                $locked =
                    DataTransformationBiProcessingRun::query()
                        ->whereKey(
                            $run->getKey()
                        )
                        ->lockForUpdate()
                        ->first();

                if ($locked === null) {
                    return;
                }

                if (
                    $locked->status
                    === DataTransformationBiProcessingRun::STATUS_COMPLETED
                ) {
                    return;
                }

                $locked
                    ->forceFill([
                        'status' =>
                            DataTransformationBiProcessingRun
                                ::STATUS_FAILED,

                        'failed_at' =>
                            now(),

                        'failure_code' =>
                            class_basename(
                                $exception
                            ),

                        /*
                         * Technical failure only.
                         * No source payload is appended.
                         */
                        'failure_message' =>
                            mb_substr(
                                $exception->getMessage(),
                                0,
                                2000
                            ),
                    ])
                    ->save();
            }
        );
    }

    private function summary(
        DataTransformationBiProcessingRun $run,
        bool $reused
    ): array {
        $run->refresh();

        return [
            'run_id' =>
                (int) $run->getKey(),

            'batch_id' =>
                (int) $run
                    ->data_transformation_bi_intake_batch_id,

            'company_id' =>
                (int) $run->company_id,

            'profiling_version' =>
                (int) $run->profiling_version,

            'normalization_version' =>
                (int) $run->normalization_version,

            'status' =>
                (string) $run->status,

            'source_row_count' =>
                (int) $run->source_row_count,

            'profiled_row_count' =>
                (int) $run->profiled_row_count,

            'normalized_row_count' =>
                (int) $run->normalized_row_count,

            'issue_count' =>
                (int) $run->issue_count,

            'blocking_issue_count' =>
                (int) $run->blocking_issue_count,

            'warning_issue_count' =>
                (int) $run->warning_issue_count,

            'domain_profile_count' =>
                $run
                    ->domainProfiles()
                    ->count(),

            'field_profile_count' =>
                $run
                    ->fieldProfiles()
                    ->count(),

            'normalization_pending' =>
                $run->status
                !== DataTransformationBiProcessingRun::STATUS_COMPLETED,

            'reused' =>
                $reused,
        ];
    }

    private static function blank(
        mixed $value
    ): bool {
        return $value === null
            || (
                is_string($value)
                && trim($value) === ''
            );
    }
}
