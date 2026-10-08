<?php

namespace App\Jobs\DataTransformationBi;

use App\Models\DataTransformationBiImplementationMaterializationRun;
use App\Models\DataTransformationBiIntakeSession;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use App\Services\Diagnosis\DataTransformationBiImplementationDatasetOrchestrator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class MaterializeDataTransformationBiImplementationSession
    implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    /*
     * Dedicated Data BI worker timeout is 900 seconds.
     * Keep this below retry_after=900 just like source profiling.
     */
    public int $timeout = 840;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $runId,
        public readonly string $runUuid,
        public readonly int $implementationRequestId,
        public readonly int $companyId,
        public readonly int $sessionId,
        public readonly int $actorUserId
    ) {
    }

    public function handle(
        DataTransformationBiImplementationDatasetOrchestrator $orchestrator
    ): void {
        if (! $this->markProcessingIfCurrent()) {
            Log::info(
                'DATA_BI_IMPLEMENTATION_MATERIALIZATION_STALE_JOB_SKIPPED',
                $this->logContext()
            );

            return;
        }

        $previousMemoryLimit =
            ini_get(
                'memory_limit'
            );

        $startedAt =
            microtime(
                true
            );

        try {
            /*
             * Keep the same production memory boundary as the profiling job.
             *
             * XLSX reading is already bounded by physical rows + cells,
             * while canonical rows are persisted incrementally.
             */
            ini_set(
                'memory_limit',
                '128M'
            );

            $request =
                TransformationImplementationRequest::query()
                    ->whereKey(
                        $this->implementationRequestId
                    )
                    ->where(
                        'company_id',
                        $this->companyId
                    )
                    ->firstOrFail();

            $session =
                DataTransformationBiIntakeSession::query()
                    ->whereKey(
                        $this->sessionId
                    )
                    ->where(
                        'company_id',
                        $this->companyId
                    )
                    ->where(
                        'transformation_implementation_request_id',
                        $this->implementationRequestId
                    )
                    ->firstOrFail();

            $actor =
                User::query()
                    ->findOrFail(
                        $this->actorUserId
                    );

            if ((string) $actor->role !== 'admin') {
                throw new AuthorizationException(
                    'La materialización técnica corresponde a Admin LAUDA.'
                );
            }

            /*
             * Orchestrator revalidates the modern authorization boundary at
             * execution time. A revoked authorization therefore stops a job
             * even if the run was valid when originally queued.
             */
            $result =
                $orchestrator->materializeSession(
                    $request,
                    $session,
                    $actor
                );

            $this->markCompletedIfCurrent(
                $result
            );

            Log::info(
                'DATA_BI_IMPLEMENTATION_MATERIALIZATION_COMPLETED',
                array_merge(
                    $this->logContext(),
                    [
                        'selected_mapping_count' =>
                            (int) (
                                $result['selected_mapping_count']
                                ?? 0
                            ),

                        'materialized_dataset_count' =>
                            (int) (
                                $result['materialized_dataset_count']
                                ?? 0
                            ),

                        'reused_dataset_count' =>
                            (int) (
                                $result['reused_dataset_count']
                                ?? 0
                            ),

                        'elapsed_seconds' =>
                            round(
                                microtime(true)
                                - $startedAt,
                                3
                            ),

                        'memory_peak_mb' =>
                            round(
                                memory_get_peak_usage(true)
                                / 1024
                                / 1024,
                                2
                            ),
                    ]
                )
            );
        } catch (Throwable $exception) {
            $this->markFailedIfCurrent(
                'implementation_materialization_failed'
            );

            Log::error(
                'DATA_BI_IMPLEMENTATION_MATERIALIZATION_FAILED',
                array_merge(
                    $this->logContext(),
                    [
                        'exception' =>
                            $exception::class,

                        'message' =>
                            $exception->getMessage(),

                        'elapsed_seconds' =>
                            round(
                                microtime(true)
                                - $startedAt,
                                3
                            ),
                    ]
                )
            );

            throw $exception;
        } finally {
            if (
                is_string(
                    $previousMemoryLimit
                )
                && $previousMemoryLimit !== ''
            ) {
                ini_set(
                    'memory_limit',
                    $previousMemoryLimit
                );
            }
        }
    }

    public function failed(
        ?Throwable $exception
    ): void {
        $code =
            $exception !== null
            && str_contains(
                strtolower(
                    $exception::class
                ),
                'timeout'
            )
                ? 'implementation_materialization_timeout'
                : 'implementation_materialization_failed';

        $this->markFailedIfCurrent(
            $code
        );

        Log::error(
            'DATA_BI_IMPLEMENTATION_MATERIALIZATION_JOB_FAILED',
            array_merge(
                $this->logContext(),
                [
                    'failure_code' =>
                        $code,

                    'exception' =>
                        $exception !== null
                            ? $exception::class
                            : null,

                    'message' =>
                        $exception?->getMessage(),
                ]
            )
        );
    }

    private function markProcessingIfCurrent(): bool
    {
        return DB::transaction(
            function (): bool {
                $run =
                    DataTransformationBiImplementationMaterializationRun
                        ::query()
                        ->whereKey(
                            $this->runId
                        )
                        ->where(
                            'company_id',
                            $this->companyId
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    $run === null

                    || (string) $run->run_uuid
                        !== $this->runUuid

                    || (string) $run->status
                        !== DataTransformationBiImplementationMaterializationRun
                            ::STATUS_QUEUED

                    || (int) $run
                        ->transformation_implementation_request_id
                        !== $this->implementationRequestId

                    || (int) $run
                        ->data_transformation_bi_intake_session_id
                        !== $this->sessionId

                    || (int) $run
                        ->requested_by_user_id
                        !== $this->actorUserId
                ) {
                    return false;
                }

                $run->forceFill([
                    'status' =>
                        DataTransformationBiImplementationMaterializationRun
                            ::STATUS_PROCESSING,

                    'started_at' =>
                        now(),

                    'finished_at' =>
                        null,

                    'failure_code' =>
                        null,

                    'failure_message' =>
                        null,
                ]);

                $run->save();

                return true;
            }
        );
    }

    /**
     * @param array<string,mixed> $result
     */
    private function markCompletedIfCurrent(
        array $result
    ): void {
        DB::transaction(
            function () use (
                $result
            ): void {
                $run =
                    DataTransformationBiImplementationMaterializationRun
                        ::query()
                        ->whereKey(
                            $this->runId
                        )
                        ->where(
                            'company_id',
                            $this->companyId
                        )
                        ->where(
                            'run_uuid',
                            $this->runUuid
                        )
                        ->where(
                            'status',
                            DataTransformationBiImplementationMaterializationRun
                                ::STATUS_PROCESSING
                        )
                        ->lockForUpdate()
                        ->first();

                if ($run === null) {
                    return;
                }

                $run->forceFill([
                    'status' =>
                        DataTransformationBiImplementationMaterializationRun
                            ::STATUS_COMPLETED,

                    'selected_mapping_count' =>
                        (int) (
                            $result['selected_mapping_count']
                            ?? 0
                        ),

                    'materialized_dataset_count' =>
                        (int) (
                            $result['materialized_dataset_count']
                            ?? 0
                        ),

                    'reused_dataset_count' =>
                        (int) (
                            $result['reused_dataset_count']
                            ?? 0
                        ),

                    /*
                     * Orchestrator returns dataset identifiers, pins, hashes
                     * and counts only. Canonical row payloads remain solely in
                     * ImplementationRow.
                     */
                    'result_snapshot' =>
                        $result,

                    'failure_code' =>
                        null,

                    'failure_message' =>
                        null,

                    'finished_at' =>
                        now(),
                ]);

                $run->save();
            }
        );
    }

    private function markFailedIfCurrent(
        string $failureCode
    ): void {
        $run =
            DataTransformationBiImplementationMaterializationRun
                ::query()
                ->whereKey(
                    $this->runId
                )
                ->where(
                    'company_id',
                    $this->companyId
                )
                ->where(
                    'run_uuid',
                    $this->runUuid
                )
                ->whereIn(
                    'status',
                    [
                        DataTransformationBiImplementationMaterializationRun
                            ::STATUS_QUEUED,

                        DataTransformationBiImplementationMaterializationRun
                            ::STATUS_PROCESSING,
                    ]
                )
                ->first();

        if ($run === null) {
            return;
        }

        $run->forceFill([
            'status' =>
                DataTransformationBiImplementationMaterializationRun
                    ::STATUS_FAILED,

            'failure_code' =>
                $failureCode,

            'failure_message' =>
                $failureCode
                === 'implementation_materialization_timeout'
                    ? 'La materialización técnica excedió el tiempo disponible del worker.'
                    : 'No se pudo completar la materialización técnica de la sesión.',

            'finished_at' =>
                now(),
        ]);

        $run->save();
    }

    /**
     * @return array<string,mixed>
     */
    private function logContext(): array
    {
        return [
            'run_id' =>
                $this->runId,

            'run_uuid' =>
                $this->runUuid,

            'implementation_request_id' =>
                $this->implementationRequestId,

            'company_id' =>
                $this->companyId,

            'session_id' =>
                $this->sessionId,

            'actor_user_id' =>
                $this->actorUserId,
        ];
    }
}
