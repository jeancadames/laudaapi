<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiIntakeBatch;
use App\Models\DataTransformationBiIntakeRow;
use App\Models\DataTransformationBiNormalizedRow;
use App\Models\DataTransformationBiProcessingRun;
use App\Models\DataTransformationBiQualityIssue;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class DataTransformationBiStagingNormalizationService
{
    private DataTransformationBiCanonicalNormalizer
        $normalizer;

    public function __construct(
        ?DataTransformationBiCanonicalNormalizer $normalizer = null
    ) {
        $this->normalizer =
            $normalizer
            ?? new DataTransformationBiCanonicalNormalizer();
    }

    public function normalize(
        DataTransformationBiProcessingRun $run,
        User $actor
    ): array {
        $this->assertAdmin(
            $actor
        );

        $run->refresh();

        if (
            $run->status
            === DataTransformationBiProcessingRun::STATUS_COMPLETED
        ) {
            $this->assertCompletedRun(
                $run
            );

            return $this->summary(
                $run,
                true
            );
        }

        $batch =
            DataTransformationBiIntakeBatch::query()
                ->findOrFail(
                    $run->data_transformation_bi_intake_batch_id
                );

        $this->assertReadyForNormalization(
            $run,
            $batch
        );

        try {
            $this->execute(
                $run,
                $batch
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
                'Solo un administrador de LAUDA puede ejecutar normalización.'
            );
        }
    }

    private function assertReadyForNormalization(
        DataTransformationBiProcessingRun $run,
        DataTransformationBiIntakeBatch $batch
    ): void {
        if (
            ! $run->exists
            || $run->getKey() === null
        ) {
            throw new RuntimeException(
                'El processing run debe existir antes de normalizar.'
            );
        }

        if (
            ! in_array(
                $run->status,
                [
                    DataTransformationBiProcessingRun::STATUS_PROCESSING,
                    DataTransformationBiProcessingRun::STATUS_FAILED,
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'El processing run no se encuentra en un estado normalizable.'
            );
        }

        if (
            $batch->status
            !== DataTransformationBiIntakeBatch::STATUS_COMPLETED
        ) {
            throw new RuntimeException(
                'El intake batch debe permanecer completado.'
            );
        }

        if (
            (int) $run->company_id
                !== (int) $batch->company_id
            || (int) $run->data_transformation_bi_intake_batch_id
                !== (int) $batch->getKey()
            || (int) $run->transformation_implementation_request_id
                !== (int) $batch
                    ->transformation_implementation_request_id
            || (int) $run->schema_version
                !== (int) $batch->schema_version
        ) {
            throw new RuntimeException(
                'El processing run perdió trazabilidad con el intake batch.'
            );
        }

        if (
            (int) $run->profiling_version
            !== DataTransformationBiStagingProfilingService
                ::PROFILING_VERSION
            || (int) $run->normalization_version
            !== DataTransformationBiStagingProfilingService
                ::NORMALIZATION_VERSION
        ) {
            throw new RuntimeException(
                'La versión del processing run no es compatible.'
            );
        }

        $expectedDomainProfiles =
            count(
                DataTransformationBiStandardIntakeSchema
                    ::domains()
            );

        $expectedFieldProfiles =
            array_sum(
                array_map(
                    static fn (
                        array $domain
                    ): int =>
                        count(
                            $domain['fields']
                            ?? []
                        ),
                    DataTransformationBiStandardIntakeSchema
                        ::domains()
                )
            );

        if (
            $run->domainProfiles()
                ->count()
            !== $expectedDomainProfiles
        ) {
            throw new RuntimeException(
                'El run no contiene todos los perfiles de dominio requeridos.'
            );
        }

        if (
            $run->fieldProfiles()
                ->count()
            !== $expectedFieldProfiles
        ) {
            throw new RuntimeException(
                'El run no contiene todos los perfiles de campo requeridos.'
            );
        }

        if (
            (int) $run->profiled_row_count
            !== (int) $batch->staged_row_count
        ) {
            throw new RuntimeException(
                'El perfilado no cubre todas las filas staged.'
            );
        }

        $blocking =
            DataTransformationBiQualityIssue::query()
                ->where(
                    'data_transformation_bi_processing_run_id',
                    $run->getKey()
                )
                ->where(
                    'severity',
                    DataTransformationBiQualityIssue::SEVERITY_BLOCKING
                )
                ->count();

        if ($blocking > 0) {
            throw new RuntimeException(
                'El run contiene issues bloqueantes y no puede normalizarse.'
            );
        }
    }

    private function execute(
        DataTransformationBiProcessingRun $run,
        DataTransformationBiIntakeBatch $batch
    ): void {
        DB::transaction(
            function () use (
                $run,
                $batch
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
                    $this->assertCompletedRun(
                        $lockedRun
                    );

                    return;
                }

                /*
                 * Deterministic retry:
                 * clear only rows derived by this processing run.
                 * Intake staging remains immutable.
                 */
                DataTransformationBiNormalizedRow::query()
                    ->where(
                        'data_transformation_bi_processing_run_id',
                        $lockedRun->getKey()
                    )
                    ->delete();

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

                if (
                    $rows->count()
                    !== (int) $batch->staged_row_count
                ) {
                    throw new RuntimeException(
                        'El conteo físico de staging cambió antes '
                        .'de la normalización.'
                    );
                }

                $canonicalIdentity =
                    app(
                        DataTransformationBiCanonicalIdentity::class
                    );

                foreach ($rows as $row) {
                    if (
                        (int) $row->company_id
                        !== (int) $batch->company_id
                    ) {
                        throw new RuntimeException(
                            'Una fila staged pertenece a otra empresa.'
                        );
                    }

                    if (! is_array($row->row_payload)) {
                        throw new RuntimeException(
                            'Una fila staged no contiene payload canónico.'
                        );
                    }

                    $result =
                        $this->normalizer
                            ->normalize(
                                (string) $row->domain_key,
                                $row->row_payload
                            );

                    $normalizedPayload =
                        $result['payload'];

                    $canonicalIdentityHash =
                        $canonicalIdentity
                            ->hashForNormalizedPayload(
                                (string) $row->domain_key,
                                $normalizedPayload
                            );

                    $canonicalJson =
                        $this->normalizer
                            ->canonicalJson(
                                $normalizedPayload
                            );

                    $normalizedSha256 =
                        hash(
                            'sha256',
                            $canonicalJson
                        );

                    DataTransformationBiNormalizedRow::query()
                        ->create([
                            'data_transformation_bi_processing_run_id' =>
                                (int) $lockedRun->getKey(),

                            'data_transformation_bi_intake_batch_id' =>
                                (int) $batch->getKey(),

                            'data_transformation_bi_intake_row_id' =>
                                (int) $row->getKey(),

                            'company_id' =>
                                (int) $batch->company_id,

                            'domain_key' =>
                                (string) $row->domain_key,

                            'source_row_number' =>
                                (int) $row->source_row_number,

                            /*
                             * Existing staging identity is reused exactly.
                             */
                            'identity_hash' =>
                                (string) $row->identity_hash,

                            'canonical_identity_hash' =>
                                $canonicalIdentityHash,

                            'source_row_sha256' =>
                                (string) $row->row_sha256,

                            'normalized_sha256' =>
                                $normalizedSha256,

                            'change_count' =>
                                (int) $result['change_count'],

                            'normalized_payload' =>
                                $normalizedPayload,

                            /*
                             * Metadata contains field names and versions only;
                             * raw before/after values are not duplicated here.
                             */
                            'normalization_meta' => [
                                'schema_version' =>
                                    (int) $lockedRun->schema_version,

                                'normalization_version' =>
                                    (int) $lockedRun
                                        ->normalization_version,

                                'changed_fields' =>
                                    $result['changed_fields'],
                            ],
                        ]);
                }

                $normalizedCount =
                    DataTransformationBiNormalizedRow::query()
                        ->where(
                            'data_transformation_bi_processing_run_id',
                            $lockedRun->getKey()
                        )
                        ->count();

                if (
                    $normalizedCount
                    !== $rows->count()
                ) {
                    throw new RuntimeException(
                        'La normalización no produjo una fila derivada '
                        .'por cada fila staged.'
                    );
                }

                /*
                 * Update domain summaries with normalized row counts.
                 */
                foreach (
                    DataTransformationBiStandardIntakeSchema
                        ::domainKeys()
                    as $domain
                ) {
                    $domainCount =
                        DataTransformationBiNormalizedRow::query()
                            ->where(
                                'data_transformation_bi_processing_run_id',
                                $lockedRun->getKey()
                            )
                            ->where(
                                'domain_key',
                                $domain
                            )
                            ->count();

                    $lockedRun
                        ->domainProfiles()
                        ->where(
                            'domain_key',
                            $domain
                        )
                        ->update([
                            'normalized_row_count' =>
                                $domainCount,
                        ]);
                }

                $lockedRun
                    ->forceFill([
                        'status' =>
                            DataTransformationBiProcessingRun
                                ::STATUS_COMPLETED,

                        'normalized_row_count' =>
                            $normalizedCount,

                        'completed_at' =>
                            now(),

                        'failed_at' =>
                            null,

                        'failure_code' =>
                            null,

                        'failure_message' =>
                            null,
                    ])
                    ->save();

                $this->assertCompletedRun(
                    $lockedRun
                );
            }
        );
    }

    private function assertCompletedRun(
        DataTransformationBiProcessingRun $run
    ): void {
        if (
            $run->status
            !== DataTransformationBiProcessingRun::STATUS_COMPLETED
        ) {
            throw new RuntimeException(
                'El processing run no está completado.'
            );
        }

        if (
            (int) $run->source_row_count
            !== (int) $run->profiled_row_count
            || (int) $run->profiled_row_count
            !== (int) $run->normalized_row_count
        ) {
            throw new RuntimeException(
                'El processing run completado presenta conteos inconsistentes.'
            );
        }

        $physicalRows =
            DataTransformationBiNormalizedRow::query()
                ->where(
                    'data_transformation_bi_processing_run_id',
                    $run->getKey()
                )
                ->count();

        if (
            $physicalRows
            !== (int) $run->normalized_row_count
        ) {
            throw new RuntimeException(
                'El processing run completado no coincide '
                .'con las filas normalizadas persistidas.'
            );
        }

        $duplicates =
            DataTransformationBiNormalizedRow::query()
                ->where(
                    'data_transformation_bi_processing_run_id',
                    $run->getKey()
                )
                ->select(
                    'data_transformation_bi_intake_row_id'
                )
                ->groupBy(
                    'data_transformation_bi_intake_row_id'
                )
                ->havingRaw(
                    'COUNT(*) > 1'
                )
                ->exists();

        if ($duplicates) {
            throw new RuntimeException(
                'El processing run contiene normalizaciones duplicadas.'
            );
        }
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

                if (
                    $locked === null
                    || $locked->status
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

                        'failure_message' =>
                            mb_substr(
                                $exception->getMessage(),
                                0,
                                2000
                            ),

                        'completed_at' =>
                            null,
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

        $changes =
            DataTransformationBiNormalizedRow::query()
                ->where(
                    'data_transformation_bi_processing_run_id',
                    $run->getKey()
                )
                ->sum(
                    'change_count'
                );

        return [
            'run_id' =>
                (int) $run->getKey(),

            'batch_id' =>
                (int) $run
                    ->data_transformation_bi_intake_batch_id,

            'company_id' =>
                (int) $run->company_id,

            'status' =>
                (string) $run->status,

            'profiling_version' =>
                (int) $run->profiling_version,

            'normalization_version' =>
                (int) $run->normalization_version,

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

            'normalization_change_count' =>
                (int) $changes,

            'reused' =>
                $reused,
        ];
    }
}
