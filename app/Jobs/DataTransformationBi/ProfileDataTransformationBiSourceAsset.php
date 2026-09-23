<?php

namespace App\Jobs\DataTransformationBi;

use App\Models\DataTransformationBiIntakeSession;
use App\Models\DataTransformationBiSourceAsset;
use App\Models\DataTransformationBiSourceAssetFile;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use App\Services\Diagnosis\DataTransformationBiSourceAssetProfilingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class ProfileDataTransformationBiSourceAsset implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    /*
     * Dedicated Data BI worker timeout is 900 seconds.
     * Keep the job limit below the worker and retry_after=900 boundary.
     */
    public int $timeout = 840;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $implementationRequestId,
        public readonly int $companyId,
        public readonly int $sessionId,
        public readonly int $sourceAssetId,
        public readonly int $sourceFileId,
        public readonly string $sourceSha256,
        public readonly int $actorUserId,
        public readonly string $runUuid
    ) {
    }

    public function handle(
        DataTransformationBiSourceAssetProfilingService $service
    ): void {
        if (! $this->markProcessingIfCurrent()) {
            Log::info(
                'DATA_BI_SOURCE_PROFILING_STALE_JOB_SKIPPED',
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
             * Match the production web memory boundary while keeping the
             * profiler itself width/chunk bounded.
             */
            ini_set(
                'memory_limit',
                '128M'
            );

            $implementationRequest =
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

            $asset =
                DataTransformationBiSourceAsset::query()
                    ->whereKey(
                        $this->sourceAssetId
                    )
                    ->where(
                        'company_id',
                        $this->companyId
                    )
                    ->where(
                        'data_transformation_bi_intake_session_id',
                        $this->sessionId
                    )
                    ->firstOrFail();

            $actor =
                User::query()
                    ->findOrFail(
                        $this->actorUserId
                    );

            $this->assertPinnedArtifact();

            $service->profile(
                $implementationRequest,
                $session,
                $asset,
                $actor
            );

            $this->markCompletedIfCurrent();

            Log::info(
                'DATA_BI_SOURCE_PROFILING_COMPLETED',
                array_merge(
                    $this->logContext(),
                    [
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
                'source_profiling_failed'
            );

            Log::error(
                'DATA_BI_SOURCE_PROFILING_FAILED',
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
                ? 'source_profiling_timeout'
                : 'source_profiling_failed';

        $this->markFailedIfCurrent(
            $code
        );

        Log::error(
            'DATA_BI_SOURCE_PROFILING_JOB_FAILED',
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
                $asset =
                    DataTransformationBiSourceAsset::query()
                        ->whereKey(
                            $this->sourceAssetId
                        )
                        ->where(
                            'company_id',
                            $this->companyId
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    $asset === null
                    || (string) $asset->profiling_job_uuid
                        !== $this->runUuid
                    || (string) $asset->profiling_status
                        !== DataTransformationBiSourceAsset
                            ::PROFILING_QUEUED
                ) {
                    return false;
                }

                $artifact =
                    DataTransformationBiSourceAssetFile::query()
                        ->whereKey(
                            $this->sourceFileId
                        )
                        ->where(
                            'company_id',
                            $this->companyId
                        )
                        ->where(
                            'data_transformation_bi_source_asset_id',
                            $this->sourceAssetId
                        )
                        ->where(
                            'status',
                            DataTransformationBiSourceAssetFile
                                ::STATUS_UPLOADED
                        )
                        ->first();

                if (
                    $artifact === null
                    || ! hash_equals(
                        strtolower(
                            $this->sourceSha256
                        ),
                        strtolower(
                            (string) $artifact->source_sha256
                        )
                    )
                ) {
                    $asset->forceFill([
                        'profiling_status' =>
                            DataTransformationBiSourceAsset
                                ::PROFILING_FAILED,

                        'profiling_finished_at' =>
                            now(),

                        'failure_code' =>
                            'source_profiling_artifact_changed',

                        'failure_message' =>
                            'El archivo fuente cambió antes de iniciar el profiling técnico.',
                    ]);

                    $asset->save();

                    return false;
                }

                $asset->forceFill([
                    'profiling_status' =>
                        DataTransformationBiSourceAsset
                            ::PROFILING_PROCESSING,

                    'profiling_started_at' =>
                        now(),

                    'profiling_finished_at' =>
                        null,

                    'failure_code' =>
                        null,

                    'failure_message' =>
                        null,
                ]);

                $asset->save();

                return true;
            }
        );
    }

    private function assertPinnedArtifact(): void
    {
        $artifact =
            DataTransformationBiSourceAssetFile::query()
                ->whereKey(
                    $this->sourceFileId
                )
                ->where(
                    'company_id',
                    $this->companyId
                )
                ->where(
                    'data_transformation_bi_source_asset_id',
                    $this->sourceAssetId
                )
                ->where(
                    'status',
                    DataTransformationBiSourceAssetFile
                        ::STATUS_UPLOADED
                )
                ->first();

        if (
            $artifact === null
            || ! hash_equals(
                strtolower(
                    $this->sourceSha256
                ),
                strtolower(
                    (string) $artifact->source_sha256
                )
            )
        ) {
            throw new RuntimeException(
                'El archivo fuente cambió antes o durante el profiling técnico.'
            );
        }
    }

    private function markCompletedIfCurrent(): void
    {
        DataTransformationBiSourceAsset::query()
            ->whereKey(
                $this->sourceAssetId
            )
            ->where(
                'company_id',
                $this->companyId
            )
            ->where(
                'profiling_job_uuid',
                $this->runUuid
            )
            ->where(
                'data_status',
                DataTransformationBiSourceAsset::DATA_ANALYZED
            )
            ->update([
                'profiling_status' =>
                    DataTransformationBiSourceAsset
                        ::PROFILING_COMPLETED,

                'profiling_finished_at' =>
                    now(),

                'failure_code' =>
                    null,

                'failure_message' =>
                    null,

                'updated_at' =>
                    now(),
            ]);
    }

    private function markFailedIfCurrent(
        string $failureCode
    ): void {
        DataTransformationBiSourceAsset::query()
            ->whereKey(
                $this->sourceAssetId
            )
            ->where(
                'company_id',
                $this->companyId
            )
            ->where(
                'profiling_job_uuid',
                $this->runUuid
            )
            ->whereIn(
                'profiling_status',
                [
                    DataTransformationBiSourceAsset
                        ::PROFILING_QUEUED,

                    DataTransformationBiSourceAsset
                        ::PROFILING_PROCESSING,
                ]
            )
            ->update([
                'profiling_status' =>
                    DataTransformationBiSourceAsset
                        ::PROFILING_FAILED,

                'profiling_finished_at' =>
                    now(),

                'failure_code' =>
                    $failureCode,

                /*
                 * sourceAssetPayload is visible to Admin LAUDA. Keep the
                 * persisted message generic; detailed exceptions stay in log.
                 */
                'failure_message' =>
                    $failureCode === 'source_profiling_timeout'
                        ? 'El profiling técnico excedió el tiempo disponible del worker.'
                        : 'No se pudo completar el profiling técnico de la fuente.',

                'updated_at' =>
                    now(),
            ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function logContext(): array
    {
        return [
            'implementation_request_id' =>
                $this->implementationRequestId,

            'company_id' =>
                $this->companyId,

            'session_id' =>
                $this->sessionId,

            'source_asset_id' =>
                $this->sourceAssetId,

            'source_file_id' =>
                $this->sourceFileId,

            'run_uuid' =>
                $this->runUuid,
        ];
    }
}
