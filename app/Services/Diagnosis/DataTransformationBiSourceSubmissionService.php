<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiIntakeSession;
use App\Models\DataTransformationBiSourceAsset;
use App\Models\DataTransformationBiSourceAssetFile;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;

final class DataTransformationBiSourceSubmissionService
{
    private const MANIFEST_VERSION = 2;

    public function __construct(
        private readonly DataTransformationBiIntakeActorAuthorizationService
            $authorization
    ) {
    }

    /**
     * Freeze the tenant-owned source delivery for LAUDA evaluation.
     *
     * @return array{
     *     reused:bool,
     *     session_id:int,
     *     status:string,
     *     submitted_at:string,
     *     submitted_manifest_sha256:string,
     *     source_count:int
     * }
     */
    public function submit(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session,
        User $actor
    ): array {
        $this->authorization
            ->assertCanManage(
                $implementationRequest,
                $actor
            );

        $this->assertScope(
            $implementationRequest,
            $session
        );

        return DB::transaction(
            function () use (
                $implementationRequest,
                $session,
                $actor
            ): array {
                $lockedSession =
                    DataTransformationBiIntakeSession::query()
                        ->whereKey(
                            (int) $session->getKey()
                        )
                        ->where(
                            'company_id',
                            (int) $implementationRequest->company_id
                        )
                        ->where(
                            'transformation_implementation_request_id',
                            (int) $implementationRequest->getKey()
                        )
                        ->lockForUpdate()
                        ->first();

                if ($lockedSession === null) {
                    throw new AuthorizationException(
                        'La sesión ya no pertenece a esta solicitud.'
                    );
                }

                $sources =
                    DataTransformationBiSourceAsset::query()
                        ->where(
                            'data_transformation_bi_intake_session_id',
                            (int) $lockedSession->getKey()
                        )
                        ->where(
                            'company_id',
                            (int) $implementationRequest->company_id
                        )
                        ->whereNull(
                            'archived_at'
                        )
                        ->orderBy(
                            'sort_order'
                        )
                        ->orderBy(
                            'id'
                        )
                        ->lockForUpdate()
                        ->get();

                if (
                    (string) $lockedSession->status
                    === DataTransformationBiIntakeSession
                        ::STATUS_SUBMITTED_FOR_EVALUATION
                ) {
                    return $this->submittedResult(
                        $lockedSession,
                        $sources->count(),
                        true
                    );
                }

                if (
                    (string) $lockedSession->status
                    !== DataTransformationBiIntakeSession::STATUS_DRAFT
                ) {
                    throw ValidationException::withMessages([
                        'intake_session' => [
                            'Solo una entrega en borrador puede enviarse a evaluación.',
                        ],
                    ]);
                }

                if ($sources->isEmpty()) {
                    throw ValidationException::withMessages([
                        'source_assets' => [
                            'Agrega al menos una fuente antes de enviar la entrega a evaluación.',
                        ],
                    ]);
                }

                $manifestSources = [];

                foreach ($sources as $source) {
                    if (
                        ! in_array(
                            (string) $source->status,
                            [
                                DataTransformationBiSourceAsset
                                    ::STATUS_ACTIVE,

                                DataTransformationBiSourceAsset
                                    ::STATUS_READY,
                            ],
                            true
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'source_assets' => [
                                'Todas las fuentes activas deben estar preparadas antes de enviar la entrega.',
                            ],
                        ]);
                    }

                    if (
                        ! in_array(
                            (string) $source->data_status,
                            [
                                DataTransformationBiSourceAsset
                                    ::DATA_RECEIVED,

                                DataTransformationBiSourceAsset
                                    ::DATA_ANALYZED,
                            ],
                            true
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'source_assets' => [
                                'Todas las fuentes deben tener un archivo vigente antes de enviar la entrega.',
                            ],
                        ]);
                    }

                    $file =
                        DataTransformationBiSourceAssetFile::query()
                            ->where(
                                'data_transformation_bi_source_asset_id',
                                (int) $source->getKey()
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
                            ->orderByDesc(
                                'id'
                            )
                            ->lockForUpdate()
                            ->first();

                    if ($file === null) {
                        throw ValidationException::withMessages([
                            'source_assets' => [
                                'Todas las fuentes deben tener un archivo CSV/XLSX vigente antes de enviar la entrega.',
                            ],
                        ]);
                    }

                    if (
                        ! in_array(
                            (string) $file->source_format,
                            [
                                DataTransformationBiSourceAssetFile
                                    ::FORMAT_CSV,

                                DataTransformationBiSourceAssetFile
                                    ::FORMAT_XLSX,
                            ],
                            true
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'source_assets' => [
                                'La entrega contiene un archivo con formato no permitido.',
                            ],
                        ]);
                    }

                    $sha256 =
                        strtolower(
                            trim(
                                (string) $file->source_sha256
                            )
                        );

                    if (
                        preg_match(
                            '/\A[a-f0-9]{64}\z/',
                            $sha256
                        )
                        !== 1
                    ) {
                        throw RuntimeException(
                            'El archivo vigente no conserva una identidad SHA-256 válida.'
                        );
                    }

                    $manifestSources[] = [
                        'source_asset_id' =>
                            (int) $source->getKey(),

                        'sort_order' =>
                            (int) $source->sort_order,

                        'display_name' =>
                            (string) $source->display_name,

                        'source_object_name' =>
                            (string) $source->source_object_name,

                        'description' =>
                            $source->description !== null
                                ? (string) $source->description
                                : null,

                        'origin_system' =>
                            $source->origin_system !== null
                                ? (string) $source->origin_system
                                : null,

                        'owner' =>
                            $source->owner !== null
                                ? (string) $source->owner
                                : null,

                        /*
                         * Tenant-declared diagnostic classification.
                         *
                         * Domains are dynamic. The group is limited to
                         * Operaciones, Gestión or Finanzas.
                         *
                         * Persisting this inside the manifest freezes the
                         * exact classification evaluated by LAUDA.
                         */
                        'business_domains' =>
                            is_array(
                                $source->business_domains
                            )
                                ? array_values(
                                    $source->business_domains
                                )
                                : [],

                        'delivery_format' =>
                            $source->delivery_format !== null
                                ? (string) $source->delivery_format
                                : null,

                        'structure_format' =>
                            $source->structure_format !== null
                                ? (string) $source->structure_format
                                : null,

                        'structure_text' =>
                            $source->structure_text !== null
                                ? (string) $source->structure_text
                                : null,

                        'file' => [
                            'original_filename' =>
                                (string) $file->original_filename,

                            'source_format' =>
                                (string) $file->source_format,

                            'source_size_bytes' =>
                                (int) $file->source_size_bytes,

                            'source_row_count' =>
                                (int) $file->source_row_count,

                            'source_sha256' =>
                                $sha256,
                        ],
                    ];
                }

                $manifest = [
                    'version' =>
                        self::MANIFEST_VERSION,

                    'company_id' =>
                        (int) $implementationRequest->company_id,

                    'implementation_request_id' =>
                        (int) $implementationRequest->getKey(),

                    'session_id' =>
                        (int) $lockedSession->getKey(),

                    'sources' =>
                        $manifestSources,
                ];

                try {
                    $encoded =
                        json_encode(
                            $manifest,
                            JSON_THROW_ON_ERROR
                            | JSON_UNESCAPED_SLASHES
                            | JSON_UNESCAPED_UNICODE
                            | JSON_PRESERVE_ZERO_FRACTION
                        );
                } catch (JsonException $exception) {
                    throw new RuntimeException(
                        'No fue posible construir el manifiesto de la entrega.',
                        0,
                        $exception
                    );
                }

                $lockedSession->forceFill([
                    'status' =>
                        DataTransformationBiIntakeSession
                            ::STATUS_SUBMITTED_FOR_EVALUATION,

                    'submitted_at' =>
                        now(),

                    'submitted_by_user_id' =>
                        (int) $actor->getKey(),

                    'submitted_manifest_sha256' =>
                        hash(
                            'sha256',
                            $encoded
                        ),
                ]);

                $lockedSession->save();

                return $this->submittedResult(
                    $lockedSession->fresh()
                        ?? $lockedSession,
                    count(
                        $manifestSources
                    ),
                    false
                );
            }
        );
    }

    private function assertScope(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session
    ): void {
        if (
            ! $implementationRequest->exists
            || ! $session->exists
            || (int) $session->company_id
                !== (int) $implementationRequest->company_id
            || (int) $session
                ->transformation_implementation_request_id
                !== (int) $implementationRequest->getKey()
        ) {
            throw new AuthorizationException(
                'La sesión no pertenece a esta solicitud de Transformación de Datos para BI.'
            );
        }
    }

    /**
     * @return array{
     *     reused:bool,
     *     session_id:int,
     *     status:string,
     *     submitted_at:string,
     *     submitted_manifest_sha256:string,
     *     source_count:int
     * }
     */
    private function submittedResult(
        DataTransformationBiIntakeSession $session,
        int $sourceCount,
        bool $reused
    ): array {
        $submittedAt =
            $session->submitted_at;

        $manifestSha256 =
            strtolower(
                trim(
                    (string) $session->submitted_manifest_sha256
                )
            );

        if (
            $submittedAt === null
            || preg_match(
                '/\A[a-f0-9]{64}\z/',
                $manifestSha256
            )
                !== 1
        ) {
            throw new RuntimeException(
                'La entrega enviada no conserva evidencia de submission válida.'
            );
        }

        return [
            'reused' =>
                $reused,

            'session_id' =>
                (int) $session->getKey(),

            'status' =>
                (string) $session->status,

            'submitted_at' =>
                $submittedAt->toISOString(),

            'submitted_manifest_sha256' =>
                $manifestSha256,

            'source_count' =>
                $sourceCount,
        ];
    }
}
