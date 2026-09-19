<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiIntakeSession;
use App\Models\DataTransformationBiSourceAsset;
use App\Models\DataTransformationBiSourceAssetFile;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class DataTransformationBiSourceAssetDataUploadService
{
    public const SOURCE_DISK =
        'private';

    public const MAX_UPLOAD_KILOBYTES =
        32768;

    private DataTransformationBiSourceFileReader $reader;

    public function __construct(
        ?DataTransformationBiSourceFileReader $reader = null
    ) {
        $this->reader =
            $reader
            ?? app(
                DataTransformationBiSourceFileReader::class
            );
    }

    /**
     * Receive the current client-native CSV/XLSX
     * for one dynamic SourceAsset.
     *
     * This operation deliberately does NOT:
     * - require a canonical domain;
     * - require canonical headers;
     * - persist raw client rows;
     * - create canonical staging rows;
     * - profile business values;
     * - connect to the client's database/server.
     *
     * @return array{
     *     source_asset:DataTransformationBiSourceAsset,
     *     data_file:array<string,mixed>
     * }
     */
    public function persist(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session,
        DataTransformationBiSourceAsset $asset,
        UploadedFile $file,
        User $actor
    ): array {
        $this->assertCanManage(
            $implementationRequest,
            $actor
        );

        $this->assertRequestAndSession(
            $implementationRequest,
            $session
        );

        $this->assertEditableSession(
            $session
        );

        $this->assertAssetBelongsToSession(
            $implementationRequest,
            $session,
            $asset
        );

        $this->assertNotArchived(
            $asset
        );

        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'file' => [
                    'El archivo recibido no es válido.',
                ],
            ]);
        }

        $temporaryPath =
            $file->getRealPath();

        if (
            ! is_string(
                $temporaryPath
            )
            || trim(
                $temporaryPath
            ) === ''
            || ! is_file(
                $temporaryPath
            )
        ) {
            throw ValidationException::withMessages([
                'file' => [
                    'No se pudo acceder al archivo temporal cargado.',
                ],
            ]);
        }

        $originalName =
            trim(
                (string) $file->getClientOriginalName()
            );

        if ($originalName === '') {
            throw ValidationException::withMessages([
                'file' => [
                    'El archivo debe conservar un nombre válido.',
                ],
            ]);
        }

        try {
            $inspection =
                $this->reader
                    ->inspect(
                        $temporaryPath,
                        $originalName
                    );
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'file' => [
                    $exception->getMessage(),
                ],
            ]);
        }

        $format =
            strtolower(
                (string) (
                    $inspection['format']
                    ?? ''
                )
            );

        if (
            ! in_array(
                $format,
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
                'file' => [
                    'Solo se admiten archivos CSV o XLSX.',
                ],
            ]);
        }

        $sourceSize =
            $inspection['source_size_bytes']
            ?? null;

        if (
            ! is_int(
                $sourceSize
            )
            || $sourceSize < 0
        ) {
            throw new RuntimeException(
                'El reader no devolvió un tamaño de archivo válido.'
            );
        }

        if (
            $sourceSize
            > self::MAX_UPLOAD_KILOBYTES * 1024
        ) {
            throw ValidationException::withMessages([
                'file' => [
                    'El archivo fuente excede el máximo permitido de 32 MB.',
                ],
            ]);
        }

        $sourceSha256 =
            strtolower(
                (string) (
                    $inspection['source_sha256']
                    ?? ''
                )
            );

        if (
            strlen(
                $sourceSha256
            ) !== 64
            || preg_match(
                '/^[a-f0-9]{64}$/',
                $sourceSha256
            ) !== 1
        ) {
            throw new RuntimeException(
                'El reader no devolvió un SHA-256 válido.'
            );
        }

        $preferredFormat =
            $asset->delivery_format !== null
                ? strtolower(
                    trim(
                        (string) $asset->delivery_format
                    )
                )
                : null;

        if (
            $preferredFormat !== null
            && $preferredFormat !== ''
            && $preferredFormat !== $format
        ) {
            throw ValidationException::withMessages([
                'file' => [
                    'El archivo no coincide con el formato de entrega seleccionado para esta fuente.',
                ],
            ]);
        }

        $sourcePath =
            $this->sourcePath(
                (int) $implementationRequest->company_id,
                (int) $implementationRequest->getKey(),
                (int) $session->getKey(),
                (int) $asset->getKey(),
                $sourceSha256,
                $format
            );

        $disk =
            Storage::disk(
                self::SOURCE_DISK
            );

        $pathExistedBefore =
            $disk->exists(
                $sourcePath
            );

        if (! $pathExistedBefore) {
            $stream =
                fopen(
                    $temporaryPath,
                    'rb'
                );

            if ($stream === false) {
                throw new RuntimeException(
                    'No se pudo abrir el archivo temporal para almacenamiento.'
                );
            }

            try {
                $stored =
                    $disk->put(
                        $sourcePath,
                        $stream
                    );
            } finally {
                fclose(
                    $stream
                );
            }

            if ($stored !== true) {
                throw new RuntimeException(
                    'No se pudo conservar el archivo fuente en almacenamiento privado.'
                );
            }
        }

        $oldArtifactPath =
            null;

        try {
            $result =
                DB::transaction(
                    function () use (
                        $implementationRequest,
                        $session,
                        $asset,
                        $file,
                        $actor,
                        $inspection,
                        $format,
                        $sourceSize,
                        $sourceSha256,
                        $sourcePath,
                        $disk,
                        &$oldArtifactPath
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

                        $this->assertNotArchived(
                            $lockedAsset
                        );

                        $existing =
                            DataTransformationBiSourceAssetFile::query()
                                ->where(
                                    'data_transformation_bi_source_asset_id',
                                    (int) $lockedAsset->getKey()
                                )
                                ->lockForUpdate()
                                ->first();

                        if (
                            $existing !== null
                            && is_string(
                                $existing->source_path
                            )
                            && trim(
                                $existing->source_path
                            ) !== ''
                            && (string) $existing->source_path
                                !== $sourcePath
                        ) {
                            $oldArtifactPath =
                                (string) $existing->source_path;
                        }

                        $artifact =
                            $existing
                            ?? new DataTransformationBiSourceAssetFile();

                        $artifact->forceFill([
                            'data_transformation_bi_source_asset_id' =>
                                (int) $lockedAsset->getKey(),

                            'company_id' =>
                                (int) $implementationRequest->company_id,

                            'status' =>
                                DataTransformationBiSourceAssetFile
                                    ::STATUS_UPLOADED,

                            'source_disk' =>
                                self::SOURCE_DISK,

                            'source_path' =>
                                $sourcePath,

                            'original_filename' =>
                                (string) (
                                    $inspection['original_filename']
                                    ?? $file->getClientOriginalName()
                                ),

                            'source_format' =>
                                $format,

                            'source_mime_type' =>
                                $file->getMimeType(),

                            'source_size_bytes' =>
                                $sourceSize,

                            'source_sha256' =>
                                $sourceSha256,

                            'reader_configuration' =>
                                $inspection['reader_configuration']
                                ?? null,

                            'source_structure_snapshot' => [
                                'sheets' =>
                                    $inspection['sheets']
                                    ?? [],
                            ],

                            'source_row_count' =>
                                $this->initialSourceRowCount(
                                    $inspection
                                ),

                            'uploaded_by_user_id' =>
                                (int) $actor->getKey(),

                            'uploaded_at' =>
                                now(),

                            'failure_code' =>
                                null,

                            'failure_message' =>
                                null,
                        ]);

                        $artifact->save();

                        $existingStructureSnapshot =
                            is_array(
                                $lockedAsset->structure_snapshot
                            )
                                ? $lockedAsset->structure_snapshot
                                : [];

                        $existingStructureSnapshot[
                            'observed_file'
                        ] = [
                            'format' =>
                                $format,

                            'reader_configuration' =>
                                $inspection['reader_configuration']
                                ?? null,

                            'sheets' =>
                                $inspection['sheets']
                                ?? [],

                            'source_row_count' =>
                                $this->initialSourceRowCount(
                                    $inspection
                                ),
                        ];

                        /*
                         * Uploading/replacing source data invalidates
                         * any future profile derived from the old file.
                         *
                         * The file reader has analyzed physical structure,
                         * but it has NOT profiled business values yet.
                         */
                        $lockedAsset->forceFill([
                            'delivery_format' =>
                                $format,

                            'status' =>
                                DataTransformationBiSourceAsset
                                    ::STATUS_ACTIVE,

                            'structure_status' =>
                                DataTransformationBiSourceAsset
                                    ::STRUCTURE_ANALYZED,

                            'data_status' =>
                                DataTransformationBiSourceAsset
                                    ::DATA_RECEIVED,

                            'structure_snapshot' =>
                                $existingStructureSnapshot,

                            'profiling_snapshot' =>
                                null,

                            'structure_analyzed_at' =>
                                now(),

                            'data_received_at' =>
                                now(),

                            'profiled_at' =>
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
                            'source_asset' =>
                                $lockedAsset->fresh()
                                ?? $lockedAsset,

                            'artifact' =>
                                $artifact->fresh()
                                ?? $artifact,
                        ];
                    }
                );
        } catch (Throwable $exception) {
            if (
                ! $pathExistedBefore
                && $disk->exists(
                    $sourcePath
                )
            ) {
                $disk->delete(
                    $sourcePath
                );
            }

            throw $exception;
        }

        if (
            is_string(
                $oldArtifactPath
            )
            && $oldArtifactPath !== ''
            && $oldArtifactPath !== $sourcePath
            && $disk->exists(
                $oldArtifactPath
            )
        ) {
            $disk->delete(
                $oldArtifactPath
            );
        }

        return [
            'source_asset' =>
                $result['source_asset'],

            'data_file' =>
                $this->payload(
                    $result['artifact']
                ),
        ];
    }

    /**
     * @param array<string,mixed> $inspection
     */
    private function initialSourceRowCount(
        array $inspection
    ): int {
        $sheets =
            $inspection['sheets']
            ?? [];

        if (
            ! is_array(
                $sheets
            )
            || count(
                $sheets
            ) !== 1
        ) {
            return 0;
        }

        $first =
            reset(
                $sheets
            );

        if (! is_array($first)) {
            return 0;
        }

        $rowCount =
            $first['row_count']
            ?? 0;

        return is_int(
            $rowCount
        )
            && $rowCount >= 0
                ? $rowCount
                : 0;
    }

    private function sourcePath(
        int $companyId,
        int $implementationRequestId,
        int $sessionId,
        int $sourceAssetId,
        string $sha256,
        string $format
    ): string {
        return implode(
            '/',
            [
                'data-transformation-bi',
                'source-assets',
                'companies',
                (string) $companyId,
                'implementation-requests',
                (string) $implementationRequestId,
                'sessions',
                (string) $sessionId,
                'assets',
                (string) $sourceAssetId,
                "{$sha256}.{$format}",
            ]
        );
    }

    /**
     * Safe external representation.
     *
     * Private disk/path are deliberately absent.
     *
     * @return array<string,mixed>
     */
    private function payload(
        DataTransformationBiSourceAssetFile $artifact
    ): array {
        return [
            'id' =>
                (int) $artifact->getKey(),

            'source_asset_id' =>
                (int) $artifact->data_transformation_bi_source_asset_id,

            'status' =>
                (string) $artifact->status,

            'original_filename' =>
                (string) $artifact->original_filename,

            'source_format' =>
                (string) $artifact->source_format,

            'source_mime_type' =>
                $artifact->source_mime_type !== null
                    ? (string) $artifact->source_mime_type
                    : null,

            'source_size_bytes' =>
                (int) $artifact->source_size_bytes,

            'source_sha256' =>
                (string) $artifact->source_sha256,

            'reader_configuration' =>
                $artifact->reader_configuration,

            'source_structure_snapshot' =>
                $artifact->source_structure_snapshot,

            'source_row_count' =>
                (int) $artifact->source_row_count,

            'uploaded_at' =>
                $artifact->uploaded_at
                    ?->toISOString(),
        ];
    }

    private function assertCanManage(
        TransformationImplementationRequest $implementationRequest,
        User $actor
    ): void {
        app(
            DataTransformationBiIntakeActorAuthorizationService::class
        )->assertCanManage(
            $implementationRequest,
            $actor
        );
    }

    private function assertRequestAndSession(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session
    ): void {
        if (
            (int) $session->company_id
            !== (int) $implementationRequest->company_id
            || (int) $session->transformation_implementation_request_id
                !== (int) $implementationRequest->getKey()
        ) {
            throw new AuthorizationException(
                'La sesión no pertenece a esta solicitud de implementación.'
            );
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
                'session' => [
                    'La sesión ya no permite modificar sus fuentes de datos.',
                ],
            ]);
        }
    }

    private function assertAssetBelongsToSession(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session,
        DataTransformationBiSourceAsset $asset
    ): void {
        if (
            (int) $asset->company_id
            !== (int) $implementationRequest->company_id
            || (int) $asset->data_transformation_bi_intake_session_id
                !== (int) $session->getKey()
        ) {
            throw new AuthorizationException(
                'La fuente no pertenece a esta sesión.'
            );
        }
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
                    'La fuente está archivada y no puede recibir archivos.',
                ],
            ]);
        }
    }
}
