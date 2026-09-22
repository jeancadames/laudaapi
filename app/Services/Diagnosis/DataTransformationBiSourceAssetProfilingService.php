<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiIntakeSession;
use App\Models\DataTransformationBiSourceAsset;
use App\Models\DataTransformationBiSourceAssetFile;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class DataTransformationBiSourceAssetProfilingService
{
    public function __construct(
        private readonly DataTransformationBiSourceValueProfiler
            $profiler,
        private readonly DataTransformationBiIntakeActorAuthorizationService
            $authorization
    ) {
    }

    /**
     * Technical profiling owned by LAUDA.
     *
     * SourceAsset remains completely independent from canonical domain
     * decisions. Profiling describes the client-native source as delivered;
     * semantic/canonical mapping happens later.
     *
     * @return array<string,mixed>
     */
    public function profile(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session,
        DataTransformationBiSourceAsset $asset,
        User $actor
    ): array {
        /*
         * Preserve the common lifecycle/tenant boundary first, then enforce
         * that this technical operation belongs only to Admin LAUDA.
         */
        $this->authorization
            ->assertCanManage(
                $implementationRequest,
                $actor
            );

        $this->assertAdmin(
            $actor
        );

        $this->assertRequestAndSession(
            $implementationRequest,
            $session
        );

        $this->assertEditableSession(
            $session
        );

        $this->assertAsset(
            $implementationRequest,
            $session,
            $asset
        );

        $artifact =
            DataTransformationBiSourceAssetFile::query()
                ->where(
                    'data_transformation_bi_source_asset_id',
                    (int) $asset->getKey()
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
                ->first();

        if ($artifact === null) {
            throw ValidationException::withMessages([
                'source_asset' => [
                    'La fuente todavía no tiene un archivo CSV/XLSX vigente para analizar.',
                ],
            ]);
        }

        $diskName =
            trim(
                (string) $artifact->source_disk
            );

        $sourcePath =
            trim(
                (string) $artifact->source_path
            );

        if (
            $diskName === ''
            || $sourcePath === ''
        ) {
            throw new RuntimeException(
                'El artefacto fuente no conserva una ubicación privada válida.'
            );
        }

        $disk =
            Storage::disk(
                $diskName
            );

        if (
            ! $disk->exists(
                $sourcePath
            )
        ) {
            throw ValidationException::withMessages([
                'source_asset' => [
                    'El archivo fuente vigente ya no está disponible en almacenamiento privado.',
                ],
            ]);
        }

        $localPath =
            $disk->path(
                $sourcePath
            );

        if (
            ! is_string(
                $localPath
            )
            || $localPath === ''
            || ! is_file(
                $localPath
            )
        ) {
            throw new RuntimeException(
                'No se pudo resolver la ruta privada del archivo fuente.'
            );
        }

        $expectedSha256 =
            strtolower(
                trim(
                    (string) $artifact->source_sha256
                )
            );

        $actualSha256 =
            hash_file(
                'sha256',
                $localPath
            );

        if (
            ! is_string(
                $actualSha256
            )
            || strlen(
                $actualSha256
            ) !== 64
            || ! hash_equals(
                $expectedSha256,
                strtolower(
                    $actualSha256
                )
            )
        ) {
            throw new RuntimeException(
                'La huella del archivo fuente no coincide con el artefacto registrado.'
            );
        }

        $snapshot =
            $this->profiler
                ->profile(
                    $localPath,
                    (string) $artifact->original_filename,
                    is_array(
                        $artifact->reader_configuration
                    )
                        ? $artifact->reader_configuration
                        : [],
                    is_array(
                        $artifact->source_structure_snapshot
                    )
                        ? $artifact->source_structure_snapshot
                        : []
                );

        /*
         * These identifiers provide traceability without exposing private
         * storage paths or raw client values.
         */
        $snapshot['source_asset_id'] =
            (int) $asset->getKey();

        $snapshot['source_file_id'] =
            (int) $artifact->getKey();

        $snapshot['source_sha256'] =
            $expectedSha256;

        $persisted =
            DB::transaction(
                function () use (
                    $implementationRequest,
                    $session,
                    $asset,
                    $artifact,
                    $actor,
                    $snapshot,
                    $expectedSha256,
                    $sourcePath
                ): DataTransformationBiSourceAsset {
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

                    $this->assertNotArchived(
                        $lockedAsset
                    );

                    $lockedArtifact =
                        DataTransformationBiSourceAssetFile::query()
                            ->whereKey(
                                (int) $artifact->getKey()
                            )
                            ->where(
                                'data_transformation_bi_source_asset_id',
                                (int) $lockedAsset->getKey()
                            )
                            ->where(
                                'company_id',
                                (int) $implementationRequest->company_id
                            )
                            ->lockForUpdate()
                            ->first();

                    if (
                        $lockedArtifact === null
                        || (string) $lockedArtifact->status
                            !== DataTransformationBiSourceAssetFile
                                ::STATUS_UPLOADED
                    ) {
                        throw ValidationException::withMessages([
                            'source_asset' => [
                                'El archivo fuente cambió durante el profiling. Ejecute nuevamente el análisis.',
                            ],
                        ]);
                    }

                    if (
                        ! hash_equals(
                            $expectedSha256,
                            strtolower(
                                (string) $lockedArtifact
                                    ->source_sha256
                            )
                        )
                        || (string) $lockedArtifact
                            ->source_path
                            !== $sourcePath
                    ) {
                        throw ValidationException::withMessages([
                            'source_asset' => [
                                'El archivo fuente fue reemplazado durante el profiling. Ejecute nuevamente el análisis.',
                            ],
                        ]);
                    }

                    $lockedAsset->forceFill([
                        'data_status' =>
                            DataTransformationBiSourceAsset
                                ::DATA_ANALYZED,

                        'profiling_snapshot' =>
                            $snapshot,

                        'profiled_at' =>
                            now(),

                        'failure_code' =>
                            null,

                        'failure_message' =>
                            null,

                        'updated_by_user_id' =>
                            (int) $actor->getKey(),
                    ]);

                    $lockedAsset->save();

                    return $lockedAsset->fresh()
                        ?? $lockedAsset;
                }
            );

        return [
            'source_asset_id' =>
                (int) $persisted->getKey(),

            'status' =>
                (string) $persisted->status,

            'data_status' =>
                (string) $persisted->data_status,

            'profiled_at' =>
                $persisted->profiled_at?->toISOString(),

            'profiling_snapshot' =>
                $persisted->profiling_snapshot,
        ];
    }

    private function assertRequestAndSession(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session
    ): void {
        if (
            ! $implementationRequest->exists
            || (int) $implementationRequest->getKey()
                <= 0
            || (string) $implementationRequest->capability_key
                !== 'data_transformation_bi'
            || (int) $session->company_id
                !== (int) $implementationRequest->company_id
            || (int) $session
                ->transformation_implementation_request_id
                !== (int) $implementationRequest->getKey()
        ) {
            throw ValidationException::withMessages([
                'intake_session' => [
                    'La sesión no pertenece a esta solicitud de Transformación de Datos para BI.',
                ],
            ]);
        }
    }

    private function assertEditableSession(
        DataTransformationBiIntakeSession $session
    ): void {
        if (
            ! in_array(
                (string) $session->status,
                [
                    DataTransformationBiIntakeSession
                        ::STATUS_DRAFT,
                    DataTransformationBiIntakeSession
                        ::STATUS_READY,
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
    }

    private function assertAsset(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session,
        DataTransformationBiSourceAsset $asset
    ): void {
        if (
            ! $asset->exists
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

        $this->assertNotArchived(
            $asset
        );
    }

    private function assertNotArchived(
        DataTransformationBiSourceAsset $asset
    ): void {
        if (
            $asset->archived_at !== null
            || (string) $asset->status
                === DataTransformationBiSourceAsset
                    ::STATUS_ARCHIVED
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
        if (
            (string) $actor->role
            !== 'admin'
        ) {
            throw new AuthorizationException(
                'El profiling técnico de fuentes corresponde a Admin LAUDA.'
            );
        }
    }
}
