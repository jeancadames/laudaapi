<?php

namespace App\Services\Diagnosis;

use App\Jobs\DataTransformationBi\ProfileDataTransformationBiSourceAsset;
use App\Models\DataTransformationBiIntakeSession;
use App\Models\DataTransformationBiSourceAsset;
use App\Models\DataTransformationBiSourceAssetFile;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class DataTransformationBiSourceAssetProfilingDispatchService
{
    public function __construct(
        private readonly DataTransformationBiIntakeActorAuthorizationService
            $authorization
    ) {
    }

    public function dispatch(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session,
        DataTransformationBiSourceAsset $asset,
        User $actor
    ): DataTransformationBiSourceAsset {
        $this->authorization
            ->assertCanManage(
                $implementationRequest,
                $actor
            );

        $this->assertAdmin(
            $actor
        );

        $this->assertScope(
            $implementationRequest,
            $session,
            $asset
        );

        $queued =
            DB::transaction(
                function () use (
                    $implementationRequest,
                    $session,
                    $asset,
                    $actor
                ): array {
                    $lockedAsset =
                        DataTransformationBiSourceAsset::query()
                            ->whereKey(
                                (int) $asset->getKey()
                            )
                            ->where(
                                'company_id',
                                (int) $implementationRequest->company_id
                            )
                            ->where(
                                'data_transformation_bi_intake_session_id',
                                (int) $session->getKey()
                            )
                            ->lockForUpdate()
                            ->first();

                    if ($lockedAsset === null) {
                        throw new AuthorizationException(
                            'La fuente ya no pertenece a esta sesión.'
                        );
                    }

                    $this->assertEditable(
                        $session,
                        $lockedAsset
                    );

                    if (
                        in_array(
                            (string) $lockedAsset->profiling_status,
                            [
                                DataTransformationBiSourceAsset
                                    ::PROFILING_QUEUED,

                                DataTransformationBiSourceAsset
                                    ::PROFILING_PROCESSING,
                            ],
                            true
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'source_asset' => [
                                'La fuente ya tiene un profiling técnico en proceso.',
                            ],
                        ]);
                    }

                    $artifact =
                        DataTransformationBiSourceAssetFile::query()
                            ->where(
                                'data_transformation_bi_source_asset_id',
                                (int) $lockedAsset->getKey()
                            )
                            ->where(
                                'company_id',
                                (int) $implementationRequest->company_id
                            )
                            ->where(
                                'status',
                                DataTransformationBiSourceAssetFile
                                    ::STATUS_UPLOADED
                            )
                            ->lockForUpdate()
                            ->first();

                    if ($artifact === null) {
                        throw ValidationException::withMessages([
                            'source_asset' => [
                                'La fuente todavía no tiene un archivo CSV/XLSX vigente para analizar.',
                            ],
                        ]);
                    }

                    $sha256 =
                        strtolower(
                            trim(
                                (string) $artifact->source_sha256
                            )
                        );

                    if (strlen($sha256) !== 64) {
                        throw new RuntimeException(
                            'El archivo fuente no conserva una huella SHA-256 válida.'
                        );
                    }

                    $runUuid =
                        (string) Str::uuid();

                    $lockedAsset->forceFill([
                        'profiling_status' =>
                            DataTransformationBiSourceAsset
                                ::PROFILING_QUEUED,

                        'profiling_job_uuid' =>
                            $runUuid,

                        'profiling_queued_at' =>
                            now(),

                        'profiling_started_at' =>
                            null,

                        'profiling_finished_at' =>
                            null,

                        'failure_code' =>
                            null,

                        'failure_message' =>
                            null,

                        'updated_by_user_id' =>
                            (int) $actor->getKey(),
                    ]);

                    $lockedAsset->save();

                    return [
                        'asset' =>
                            $lockedAsset->fresh()
                            ?? $lockedAsset,

                        'source_file_id' =>
                            (int) $artifact->getKey(),

                        'source_sha256' =>
                            $sha256,

                        'run_uuid' =>
                            $runUuid,
                    ];
                }
            );

        try {
            ProfileDataTransformationBiSourceAsset::dispatch(
                (int) $implementationRequest->getKey(),
                (int) $implementationRequest->company_id,
                (int) $session->getKey(),
                (int) $asset->getKey(),
                (int) $queued['source_file_id'],
                (string) $queued['source_sha256'],
                (int) $actor->getKey(),
                (string) $queued['run_uuid']
            )
                ->onQueue(
                    'default'
                );
        } catch (Throwable $exception) {
            $this->markDispatchFailure(
                (int) $implementationRequest->company_id,
                (int) $asset->getKey(),
                (string) $queued['run_uuid']
            );

            throw new RuntimeException(
                'No se pudo encolar el profiling técnico de la fuente.',
                0,
                $exception
            );
        }

        return DataTransformationBiSourceAsset::query()
            ->findOrFail(
                (int) $asset->getKey()
            );
    }

    private function assertScope(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session,
        DataTransformationBiSourceAsset $asset
    ): void {
        if (
            ! $implementationRequest->exists
            || (int) $implementationRequest->getKey() <= 0
            || (string) $implementationRequest->capability_key
                !== 'data_transformation_bi'
            || (int) $session->company_id
                !== (int) $implementationRequest->company_id
            || (int) $session
                ->transformation_implementation_request_id
                !== (int) $implementationRequest->getKey()
            || ! $asset->exists
            || (int) $asset->company_id
                !== (int) $implementationRequest->company_id
            || (int) $asset
                ->data_transformation_bi_intake_session_id
                !== (int) $session->getKey()
        ) {
            throw new AuthorizationException(
                'La fuente no pertenece a esta solicitud y sesión.'
            );
        }
    }

    private function assertEditable(
        DataTransformationBiIntakeSession $session,
        DataTransformationBiSourceAsset $asset
    ): void {
        if (
            ! in_array(
                (string) $session->status,
                [
                    DataTransformationBiIntakeSession::STATUS_DRAFT,
                    DataTransformationBiIntakeSession::STATUS_READY,
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'intake_session' => [
                    'La sesión ya no admite profiling técnico de fuentes.',
                ],
            ]);
        }

        if (
            $asset->archived_at !== null
            || (string) $asset->status
                === DataTransformationBiSourceAsset::STATUS_ARCHIVED
        ) {
            throw ValidationException::withMessages([
                'source_asset' => [
                    'Una fuente archivada no puede pasar a profiling.',
                ],
            ]);
        }
    }

    private function assertAdmin(
        User $actor
    ): void {
        if ((string) $actor->role !== 'admin') {
            throw new AuthorizationException(
                'El profiling técnico de fuentes corresponde a Admin LAUDA.'
            );
        }
    }

    private function markDispatchFailure(
        int $companyId,
        int $sourceAssetId,
        string $runUuid
    ): void {
        DataTransformationBiSourceAsset::query()
            ->whereKey(
                $sourceAssetId
            )
            ->where(
                'company_id',
                $companyId
            )
            ->where(
                'profiling_job_uuid',
                $runUuid
            )
            ->update([
                'profiling_status' =>
                    DataTransformationBiSourceAsset
                        ::PROFILING_FAILED,

                'profiling_finished_at' =>
                    now(),

                'failure_code' =>
                    'source_profiling_dispatch_failed',

                'failure_message' =>
                    'No se pudo iniciar el profiling técnico de la fuente.',

                'updated_at' =>
                    now(),
            ]);
    }
}
