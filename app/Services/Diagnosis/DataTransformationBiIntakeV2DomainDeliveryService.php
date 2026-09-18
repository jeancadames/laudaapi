<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiIntakeDomainDelivery;
use App\Models\DataTransformationBiIntakeSession;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class DataTransformationBiIntakeV2DomainDeliveryService
{
    public const SOURCE_DISK =
        'private';

    private DataTransformationBiDomainIntakeValidationService
        $validationService;

    public function __construct(
        ?DataTransformationBiDomainIntakeValidationService $validationService = null
    ) {
        $this->validationService =
            $validationService
            ?? new DataTransformationBiDomainIntakeValidationService();
    }

    /**
     * Persist one independently validated domain source.
     *
     * This is still pre-staging. It never creates:
     * - DataTransformationBiIntakeBatch;
     * - DataTransformationBiIntakeRow;
     * - processing runs;
     * - normalized rows.
     */
    public function persistUploadedDomain(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session,
        string $domain,
        UploadedFile $file,
        User $actor
    ): array {
        $this->assertAdmin(
            $actor
        );

        $this->assertRequestAndSession(
            $implementationRequest,
            $session
        );

        $this->assertDomain(
            $domain
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
            ! is_string($temporaryPath)
            || $temporaryPath === ''
            || ! is_file($temporaryPath)
        ) {
            throw ValidationException::withMessages([
                'file' => [
                    'No se pudo acceder al archivo temporal cargado.',
                ],
            ]);
        }

        $originalName =
            (string) $file->getClientOriginalName();

        $validation =
            $this->validationService
                ->validate(
                    $domain,
                    $temporaryPath,
                    $originalName
                );

        if (
            ($validation['valid'] ?? false)
            !== true
        ) {
            $errors =
                $validation['errors']
                ?? [
                    'El archivo no pasó la validación del dominio.',
                ];

            throw ValidationException::withMessages([
                'file' =>
                    array_values(
                        $errors
                    ),
            ]);
        }

        $format =
            (string) (
                $validation['format']
                ?? ''
            );

        if (
            ! in_array(
                $format,
                [
                    DataTransformationBiIntakeDomainDelivery
                        ::FORMAT_XLSX,

                    DataTransformationBiIntakeDomainDelivery
                        ::FORMAT_CSV,
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'file' => [
                    'El formato validado no puede persistirse '
                    .'como entrega de dominio.',
                ],
            ]);
        }

        $sourceSha256 =
            hash_file(
                'sha256',
                $temporaryPath
            );

        if (
            ! is_string($sourceSha256)
            || strlen($sourceSha256) !== 64
        ) {
            throw new RuntimeException(
                'No se pudo calcular SHA-256 del archivo.'
            );
        }

        $sourceSize =
            filesize(
                $temporaryPath
            );

        if ($sourceSize === false) {
            throw new RuntimeException(
                'No se pudo determinar el tamaño del archivo.'
            );
        }

        $mimeType =
            $file->getMimeType();

        $sourcePath =
            $this->sourcePath(
                (int) $implementationRequest->company_id,
                (int) $implementationRequest->getKey(),
                (int) $session->getKey(),
                $domain,
                $sourceSha256,
                $format
            );

        $disk =
            Storage::disk(
                self::SOURCE_DISK
            );

        $createdArtifact =
            false;

        $oldArtifact = null;

        try {
            $result =
                DB::transaction(
                    function () use (
                        $implementationRequest,
                        $session,
                        $domain,
                        $actor,
                        $temporaryPath,
                        $originalName,
                        $format,
                        $mimeType,
                        $sourceSize,
                        $sourceSha256,
                        $sourcePath,
                        $validation,
                        $disk,
                        &$createdArtifact,
                        &$oldArtifact
                    ): array {
                        /*
                         * Same lock order as the rest of the Data BI
                         * processing pipeline:
                         * request -> session -> domain slot.
                         */
                        $lockedRequest =
                            TransformationImplementationRequest::query()
                                ->whereKey(
                                    $implementationRequest->getKey()
                                )
                                ->lockForUpdate()
                                ->first();

                        if ($lockedRequest === null) {
                            throw ValidationException::withMessages([
                                'implementation_request' => [
                                    'La solicitud ya no existe.',
                                ],
                            ]);
                        }

                        $lockedSession =
                            DataTransformationBiIntakeSession::query()
                                ->whereKey(
                                    $session->getKey()
                                )
                                ->lockForUpdate()
                                ->first();

                        if ($lockedSession === null) {
                            throw ValidationException::withMessages([
                                'intake_session' => [
                                    'La sesión de intake ya no existe.',
                                ],
                            ]);
                        }

                        $this->assertLockedContract(
                            $lockedRequest,
                            $lockedSession
                        );

                        $delivery =
                            DataTransformationBiIntakeDomainDelivery::query()
                                ->where(
                                    'data_transformation_bi_intake_session_id',
                                    (int) $lockedSession->getKey()
                                )
                                ->where(
                                    'domain_key',
                                    $domain
                                )
                                ->lockForUpdate()
                                ->first();

                        if ($delivery === null) {
                            throw new RuntimeException(
                                "La sesión no contiene el slot {$domain}."
                            );
                        }

                        if (
                            $delivery->delivery_mode
                                === DataTransformationBiIntakeDomainDelivery
                                    ::MODE_UPLOADED
                            && $delivery->status
                                === DataTransformationBiIntakeDomainDelivery
                                    ::STATUS_VALID
                            && hash_equals(
                                (string) $delivery->source_sha256,
                                $sourceSha256
                            )
                            && (string) $delivery->source_disk
                                === self::SOURCE_DISK
                            && is_string(
                                $delivery->source_path
                            )
                            && $delivery->source_path !== ''
                            && $disk->exists(
                                (string) $delivery->source_path
                            )
                        ) {
                            return [
                                'delivery' =>
                                    $delivery,

                                'session' =>
                                    $lockedSession,

                                'reused' =>
                                    true,
                            ];
                        }

                        if (
                            is_string(
                                $delivery->source_path
                            )
                            && $delivery->source_path !== ''
                            && is_string(
                                $delivery->source_disk
                            )
                            && $delivery->source_disk !== ''
                        ) {
                            $oldArtifact = [
                                'disk' =>
                                    (string) $delivery->source_disk,

                                'path' =>
                                    (string) $delivery->source_path,
                            ];
                        }

                        if (
                            ! $disk->exists(
                                $sourcePath
                            )
                        ) {
                            $stream =
                                fopen(
                                    $temporaryPath,
                                    'rb'
                                );

                            if ($stream === false) {
                                throw new RuntimeException(
                                    'No se pudo abrir el archivo '
                                    .'para persistencia privada.'
                                );
                            }

                            try {
                                $written =
                                    $disk->put(
                                        $sourcePath,
                                        $stream
                                    );
                            } finally {
                                fclose(
                                    $stream
                                );
                            }

                            if ($written !== true) {
                                throw new RuntimeException(
                                    'No se pudo guardar el archivo '
                                    .'en almacenamiento privado.'
                                );
                            }

                            $createdArtifact =
                                true;
                        }

                        $this->assertArtifactIntegrity(
                            $disk,
                            $sourcePath,
                            $sourceSha256
                        );

                        $rowCount =
                            (int) (
                                $validation[
                                    'content'
                                ][
                                    'domain_report'
                                ][
                                    'row_count'
                                ]
                                ?? 0
                            );

                        $delivery
                            ->forceFill([
                                'company_id' =>
                                    (int) $lockedSession->company_id,

                                'delivery_mode' =>
                                    DataTransformationBiIntakeDomainDelivery
                                        ::MODE_UPLOADED,

                                'status' =>
                                    DataTransformationBiIntakeDomainDelivery
                                        ::STATUS_VALID,

                                'source_disk' =>
                                    self::SOURCE_DISK,

                                'source_path' =>
                                    $sourcePath,

                                'original_filename' =>
                                    $originalName,

                                'source_format' =>
                                    $format,

                                'source_mime_type' =>
                                    is_string($mimeType)
                                        && $mimeType !== ''
                                            ? $mimeType
                                            : null,

                                'source_size_bytes' =>
                                    (int) $sourceSize,

                                'source_sha256' =>
                                    $sourceSha256,

                                'validation_snapshot' =>
                                    $validation,

                                'source_row_count' =>
                                    $rowCount,

                                'accepted_row_count' =>
                                    $rowCount,

                                'carry_forward_processing_run_id' =>
                                    null,

                                'carry_forward_intake_batch_id' =>
                                    null,

                                'created_by_user_id' =>
                                    (int) $actor->getKey(),

                                'validated_at' =>
                                    now(),
                            ])
                            ->save();

                        /*
                         * Any domain replacement invalidates a previously
                         * calculated ready/relational state. The session
                         * returns to draft until the seven-domain logical
                         * cut is resolved again.
                         */
                        $lockedSession
                            ->forceFill([
                                'status' =>
                                    DataTransformationBiIntakeSession
                                        ::STATUS_DRAFT,

                                'resolved_manifest_sha256' =>
                                    null,

                                'relational_validation_snapshot' =>
                                    null,

                                'ready_at' =>
                                    null,

                                'failure_code' =>
                                    null,

                                'failure_message' =>
                                    null,
                            ])
                            ->save();

                        return [
                            'delivery' =>
                                $delivery->fresh(),

                            'session' =>
                                $lockedSession->fresh(),

                            'reused' =>
                                false,
                        ];
                    }
                );
        } catch (Throwable $exception) {
            if (
                $createdArtifact
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

        /*
         * Delete the superseded source only AFTER the new DB state commits.
         * Never remove an artifact still referenced by the active slot.
         */
        if (
            ($result['reused'] ?? false)
            !== true
            && is_array(
                $oldArtifact
            )
            && (
                (string) (
                    $oldArtifact['disk']
                    ?? ''
                )
                !== self::SOURCE_DISK
                || (string) (
                    $oldArtifact['path']
                    ?? ''
                )
                !== $sourcePath
            )
        ) {
            $oldDiskName =
                (string) (
                    $oldArtifact['disk']
                    ?? ''
                );

            $oldPath =
                (string) (
                    $oldArtifact['path']
                    ?? ''
                );

            if (
                $oldDiskName !== ''
                && $oldPath !== ''
            ) {
                try {
                    $oldDisk =
                        Storage::disk(
                            $oldDiskName
                        );

                    if (
                        $oldDisk->exists(
                            $oldPath
                        )
                    ) {
                        $oldDisk->delete(
                            $oldPath
                        );
                    }
                } catch (Throwable) {
                    /*
                     * A cleanup failure must not roll back the already
                     * committed valid delivery. Retention cleanup can be
                     * retried independently later.
                     */
                }
            }
        }

        /** @var DataTransformationBiIntakeDomainDelivery $delivery */
        $delivery =
            $result['delivery'];

        /** @var DataTransformationBiIntakeSession $resolvedSession */
        $resolvedSession =
            $result['session'];

        return [
            'reused' =>
                (bool) (
                    $result['reused']
                    ?? false
                ),

            'session_id' =>
                (int) $resolvedSession->getKey(),

            'session_status' =>
                (string) $resolvedSession->status,

            'delivery_id' =>
                (int) $delivery->getKey(),

            'domain' =>
                (string) $delivery->domain_key,

            'delivery_mode' =>
                (string) $delivery->delivery_mode,

            'status' =>
                (string) $delivery->status,

            'format' =>
                (string) $delivery->source_format,

            'original_filename' =>
                (string) $delivery->original_filename,

            'source_sha256' =>
                (string) $delivery->source_sha256,

            'source_size_bytes' =>
                (int) $delivery->source_size_bytes,

            'source_row_count' =>
                (int) $delivery->source_row_count,

            'accepted_row_count' =>
                (int) $delivery->accepted_row_count,

            'validated_at' =>
                $delivery->validated_at
                    ? $delivery->validated_at->toISOString()
                    : null,
        ];
    }


    /**
     * Resolve one domain explicitly as having no source rows.
     *
     * no_data is a first-class logical decision. It does not create
     * staging and it deliberately owns no private source artifact.
     */
    public function persistNoDataDomain(
        \App\Models\TransformationImplementationRequest $request,
        \App\Models\DataTransformationBiIntakeSession $session,
        string $domain,
        \App\Models\User $actor
    ): array {
        $this->assertDecisionInput(
            $request,
            $session,
            $domain,
            $actor
        );

        $result =
            \Illuminate\Support\Facades\DB::transaction(
                function () use (
                    $request,
                    $session,
                    $domain,
                    $actor
                ): array {
                    $lockedRequest =
                        \App\Models\TransformationImplementationRequest
                            ::query()
                            ->whereKey(
                                $request->getKey()
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $lockedSession =
                        \App\Models\DataTransformationBiIntakeSession
                            ::query()
                            ->whereKey(
                                $session->getKey()
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $this->assertLockedDecisionContext(
                        $lockedRequest,
                        $lockedSession
                    );

                    $delivery =
                        \App\Models\DataTransformationBiIntakeDomainDelivery
                            ::query()
                            ->where(
                                'data_transformation_bi_intake_session_id',
                                $lockedSession->getKey()
                            )
                            ->where(
                                'domain_key',
                                $domain
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    if (
                        $delivery->delivery_mode
                        === \App\Models\DataTransformationBiIntakeDomainDelivery
                            ::MODE_NO_DATA
                        && $delivery->status
                        === \App\Models\DataTransformationBiIntakeDomainDelivery
                            ::STATUS_VALID
                    ) {
                        if (
                            $delivery->source_disk !== null
                            || $delivery->source_path !== null
                            || $delivery->source_sha256 !== null
                            || $delivery->carry_forward_processing_run_id
                                !== null
                            || $delivery->carry_forward_intake_batch_id
                                !== null
                            || (int) $delivery->source_row_count !== 0
                            || (int) $delivery->accepted_row_count !== 0
                        ) {
                            throw new \RuntimeException(
                                'La decisión no_data persistida '
                                .'es internamente inconsistente.'
                            );
                        }

                        return [
                            'delivery' =>
                                $delivery,

                            'reused' =>
                                true,

                            'old_artifact' =>
                                null,
                        ];
                    }

                    $oldArtifact =
                        $this->existingSourceArtifact(
                            $delivery
                        );

                    $delivery->fill([
                        'delivery_mode' =>
                            \App\Models\DataTransformationBiIntakeDomainDelivery
                                ::MODE_NO_DATA,

                        'status' =>
                            \App\Models\DataTransformationBiIntakeDomainDelivery
                                ::STATUS_VALID,

                        'source_disk' =>
                            null,

                        'source_path' =>
                            null,

                        'original_filename' =>
                            null,

                        'source_format' =>
                            null,

                        'source_mime_type' =>
                            null,

                        'source_size_bytes' =>
                            null,

                        'source_sha256' =>
                            null,

                        'validation_snapshot' => [
                            'schema_version' =>
                                (int) $lockedSession->schema_version,

                            'domain' =>
                                $domain,

                            'valid' =>
                                true,

                            'mode' =>
                                \App\Models\DataTransformationBiIntakeDomainDelivery
                                    ::MODE_NO_DATA,

                            'reason' =>
                                'declared_no_data',

                            'source_row_count' =>
                                0,

                            'accepted_row_count' =>
                                0,
                        ],

                        'source_row_count' =>
                            0,

                        'accepted_row_count' =>
                            0,

                        'carry_forward_processing_run_id' =>
                            null,

                        'carry_forward_intake_batch_id' =>
                            null,

                        'created_by_user_id' =>
                            $actor->getKey(),

                        'validated_at' =>
                            now(),
                    ]);

                    $delivery->save();

                    $this->resetSessionAfterDecisionChange(
                        $lockedSession
                    );

                    return [
                        'delivery' =>
                            $delivery,

                        'reused' =>
                            false,

                        'old_artifact' =>
                            $oldArtifact,
                    ];
                }
            );

        if (
            ! ($result['reused'] ?? false)
            && is_array(
                $result['old_artifact'] ?? null
            )
        ) {
            $this->deleteArtifactIfUnreferenced(
                $result['old_artifact']
            );
        }

        return $this->decisionResult(
            $result['delivery'],
            (bool) $result['reused']
        );
    }

    /**
     * Resolve one domain using the exact current canonical usable dataset.
     *
     * The first selection resolves P13 exactly once and persists the
     * processing-run + intake-batch identity. Repeated calls reuse that
     * already-pinned identity instead of silently moving to a newer dataset.
     */
    public function persistCarryForwardDomain(
        \App\Models\TransformationImplementationRequest $request,
        \App\Models\DataTransformationBiIntakeSession $session,
        string $domain,
        \App\Models\User $actor
    ): array {
        $this->assertDecisionInput(
            $request,
            $session,
            $domain,
            $actor
        );

        $result =
            \Illuminate\Support\Facades\DB::transaction(
                function () use (
                    $request,
                    $session,
                    $domain,
                    $actor
                ): array {
                    $lockedRequest =
                        \App\Models\TransformationImplementationRequest
                            ::query()
                            ->whereKey(
                                $request->getKey()
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $lockedSession =
                        \App\Models\DataTransformationBiIntakeSession
                            ::query()
                            ->whereKey(
                                $session->getKey()
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $this->assertLockedDecisionContext(
                        $lockedRequest,
                        $lockedSession
                    );

                    $delivery =
                        \App\Models\DataTransformationBiIntakeDomainDelivery
                            ::query()
                            ->where(
                                'data_transformation_bi_intake_session_id',
                                $lockedSession->getKey()
                            )
                            ->where(
                                'domain_key',
                                $domain
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    /*
                     * Once pinned, repeated invocation is idempotent.
                     * Do not re-resolve P13 and silently advance the cut.
                     */
                    if (
                        $delivery->delivery_mode
                        === \App\Models\DataTransformationBiIntakeDomainDelivery
                            ::MODE_CARRY_FORWARD
                        && $delivery->status
                        === \App\Models\DataTransformationBiIntakeDomainDelivery
                            ::STATUS_VALID
                    ) {
                        $runId =
                            (int) (
                                $delivery
                                    ->carry_forward_processing_run_id
                                ?? 0
                            );

                        $batchId =
                            (int) (
                                $delivery
                                    ->carry_forward_intake_batch_id
                                ?? 0
                            );

                        if (
                            $runId <= 0
                            || $batchId <= 0
                            || $delivery->source_disk !== null
                            || $delivery->source_path !== null
                            || $delivery->source_sha256 !== null
                        ) {
                            throw new \RuntimeException(
                                'La decisión carry_forward persistida '
                                .'es internamente inconsistente.'
                            );
                        }

                        $pinned =
                            $this->validatePinnedCarryForward(
                                (int) $lockedRequest->company_id,
                                (int) $lockedRequest->getKey(),
                                (int) $lockedSession->schema_version,
                                $runId,
                                $batchId,
                                $domain
                            );

                        if (
                            (int) $delivery->accepted_row_count
                            !== (int) $pinned['domain_row_count']
                        ) {
                            throw new \RuntimeException(
                                'El conteo persistido del carry_forward '
                                .'no coincide con el dataset fijado.'
                            );
                        }

                        return [
                            'delivery' =>
                                $delivery,

                            'reused' =>
                                true,

                            'old_artifact' =>
                                null,
                        ];
                    }

                    $resolved =
                        app(
                            \App\Services\Diagnosis\DataTransformationBiUsableDatasetResolver
                                ::class
                        )->forRequest(
                            (int) $lockedRequest->company_id,
                            (int) $lockedRequest->getKey()
                        );

                    if (
                        ! ($resolved['available'] ?? false)
                        || ! is_array(
                            $resolved['dataset'] ?? null
                        )
                    ) {
                        throw \Illuminate\Validation\ValidationException
                            ::withMessages([
                                'domain' => [
                                    'No existe un dataset normalizado '
                                    .'usable para aplicar carry-forward.',
                                ],
                            ]);
                    }

                    $dataset =
                        $resolved['dataset'];

                    if (
                        (int) (
                            $dataset['schema_version']
                            ?? 0
                        )
                        !== (int) $lockedSession->schema_version
                    ) {
                        throw \Illuminate\Validation\ValidationException
                            ::withMessages([
                                'domain' => [
                                    'El dataset usable no pertenece '
                                    .'a la versión de esquema de la sesión.',
                                ],
                            ]);
                    }

                    $runId =
                        (int) (
                            $dataset['processing_run_id']
                            ?? 0
                        );

                    $batchId =
                        (int) (
                            $dataset['intake_batch_id']
                            ?? 0
                        );

                    $pinned =
                        $this->validatePinnedCarryForward(
                            (int) $lockedRequest->company_id,
                            (int) $lockedRequest->getKey(),
                            (int) $lockedSession->schema_version,
                            $runId,
                            $batchId,
                            $domain
                        );

                    $oldArtifact =
                        $this->existingSourceArtifact(
                            $delivery
                        );

                    $delivery->fill([
                        'delivery_mode' =>
                            \App\Models\DataTransformationBiIntakeDomainDelivery
                                ::MODE_CARRY_FORWARD,

                        'status' =>
                            \App\Models\DataTransformationBiIntakeDomainDelivery
                                ::STATUS_VALID,

                        'source_disk' =>
                            null,

                        'source_path' =>
                            null,

                        'original_filename' =>
                            null,

                        'source_format' =>
                            null,

                        'source_mime_type' =>
                            null,

                        'source_size_bytes' =>
                            null,

                        'source_sha256' =>
                            null,

                        'validation_snapshot' => [
                            'schema_version' =>
                                (int) $lockedSession->schema_version,

                            'domain' =>
                                $domain,

                            'valid' =>
                                true,

                            'mode' =>
                                \App\Models\DataTransformationBiIntakeDomainDelivery
                                    ::MODE_CARRY_FORWARD,

                            'reason' =>
                                (string) (
                                    $resolved['reason']
                                    ?? 'latest_successful_normalized_run'
                                ),

                            'processing_run_id' =>
                                $runId,

                            'intake_batch_id' =>
                                $batchId,

                            'source_row_count' =>
                                (int) $pinned['domain_row_count'],

                            'accepted_row_count' =>
                                (int) $pinned['domain_row_count'],
                        ],

                        'source_row_count' =>
                            (int) $pinned['domain_row_count'],

                        'accepted_row_count' =>
                            (int) $pinned['domain_row_count'],

                        'carry_forward_processing_run_id' =>
                            $runId,

                        'carry_forward_intake_batch_id' =>
                            $batchId,

                        'created_by_user_id' =>
                            $actor->getKey(),

                        'validated_at' =>
                            now(),
                    ]);

                    $delivery->save();

                    $this->resetSessionAfterDecisionChange(
                        $lockedSession
                    );

                    return [
                        'delivery' =>
                            $delivery,

                        'reused' =>
                            false,

                        'old_artifact' =>
                            $oldArtifact,
                    ];
                }
            );

        if (
            ! ($result['reused'] ?? false)
            && is_array(
                $result['old_artifact'] ?? null
            )
        ) {
            $this->deleteArtifactIfUnreferenced(
                $result['old_artifact']
            );
        }

        return $this->decisionResult(
            $result['delivery'],
            (bool) $result['reused']
        );
    }

    private function assertDecisionInput(
        \App\Models\TransformationImplementationRequest $request,
        \App\Models\DataTransformationBiIntakeSession $session,
        string $domain,
        \App\Models\User $actor
    ): void {
        if ((string) $actor->role !== 'admin') {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Solo un administrador puede modificar el intake.'
            );
        }

        if (
            ! $request->exists
            || (int) $request->getKey() <= 0
            || (int) $request->company_id <= 0
        ) {
            throw new \InvalidArgumentException(
                'La solicitud de implementación no está persistida.'
            );
        }

        if (
            ! $session->exists
            || (int) $session->getKey() <= 0
        ) {
            throw new \InvalidArgumentException(
                'La sesión de intake no está persistida.'
            );
        }

        if (
            ! in_array(
                $domain,
                \App\Services\Diagnosis\DataTransformationBiStandardIntakeSchema
                    ::domainKeys(),
                true
            )
        ) {
            throw \Illuminate\Validation\ValidationException
                ::withMessages([
                    'domain' => [
                        'Dominio de intake no soportado.',
                    ],
                ]);
        }
    }

    private function assertLockedDecisionContext(
        \App\Models\TransformationImplementationRequest $request,
        \App\Models\DataTransformationBiIntakeSession $session
    ): void {
        if (
            (string) $request->capability_key
            !== 'data_transformation_bi'
        ) {
            throw \Illuminate\Validation\ValidationException
                ::withMessages([
                    'request' => [
                        'La solicitud no corresponde a '
                        .'Transformación de Datos para BI.',
                    ],
                ]);
        }

        if (
            (int) $session->company_id
            !== (int) $request->company_id
            || (int) $session
                ->transformation_implementation_request_id
                !== (int) $request->getKey()
        ) {
            throw new \RuntimeException(
                'La sesión no pertenece a la solicitud indicada.'
            );
        }

        if (
            (int) $session->schema_version
            !== \App\Services\Diagnosis\DataTransformationBiStandardIntakeSchema
                ::VERSION
        ) {
            throw \Illuminate\Validation\ValidationException
                ::withMessages([
                    'session' => [
                        'La sesión pertenece a otra versión '
                        .'del esquema estándar.',
                    ],
                ]);
        }

        if (
            ! in_array(
                (string) $session->status,
                [
                    \App\Models\DataTransformationBiIntakeSession
                        ::STATUS_DRAFT,

                    \App\Models\DataTransformationBiIntakeSession
                        ::STATUS_READY,
                ],
                true
            )
        ) {
            throw \Illuminate\Validation\ValidationException
                ::withMessages([
                    'session' => [
                        'La sesión ya no admite cambios '
                        .'en sus dominios.',
                    ],
                ]);
        }
    }

    /**
     * Validate an already-pinned canonical usable dataset without resolving
     * P13 again. This preserves the exact run/batch selected by the user.
     *
     * @return array{
     *     dataset:array<string,mixed>,
     *     domain_row_count:int
     * }
     */
    private function validatePinnedCarryForward(
        int $companyId,
        int $requestId,
        int $schemaVersion,
        int $runId,
        int $batchId,
        string $domain
    ): array {
        if (
            $runId <= 0
            || $batchId <= 0
        ) {
            throw new \RuntimeException(
                'La identidad carry-forward no es válida.'
            );
        }

        $run =
            \App\Models\DataTransformationBiProcessingRun
                ::query()
                ->whereKey(
                    $runId
                )
                ->where(
                    'data_transformation_bi_intake_batch_id',
                    $batchId
                )
                ->where(
                    'company_id',
                    $companyId
                )
                ->where(
                    'transformation_implementation_request_id',
                    $requestId
                )
                ->where(
                    'status',
                    \App\Models\DataTransformationBiProcessingRun
                        ::STATUS_COMPLETED
                )
                ->where(
                    'blocking_issue_count',
                    0
                )
                ->first();

        if ($run === null) {
            throw new \RuntimeException(
                'El processing run fijado dejó de ser usable.'
            );
        }

        $batch =
            \App\Models\DataTransformationBiIntakeBatch
                ::query()
                ->whereKey(
                    $batchId
                )
                ->where(
                    'company_id',
                    $companyId
                )
                ->where(
                    'transformation_implementation_request_id',
                    $requestId
                )
                ->where(
                    'status',
                    \App\Models\DataTransformationBiIntakeBatch
                        ::STATUS_COMPLETED
                )
                ->first();

        if ($batch === null) {
            throw new \RuntimeException(
                'El intake batch fijado dejó de ser usable.'
            );
        }

        if (
            (int) $run->schema_version
            !== $schemaVersion
        ) {
            throw new \RuntimeException(
                'El dataset carry-forward cambió de versión '
                .'de esquema.'
            );
        }

        $persistedCount =
            \App\Models\DataTransformationBiNormalizedRow
                ::query()
                ->where(
                    'company_id',
                    $companyId
                )
                ->where(
                    'data_transformation_bi_processing_run_id',
                    $runId
                )
                ->where(
                    'data_transformation_bi_intake_batch_id',
                    $batchId
                )
                ->count();

        if (
            $persistedCount
            !== max(
                0,
                (int) $run->normalized_row_count
            )
        ) {
            throw new \RuntimeException(
                'El dataset carry-forward ya no es '
                .'internamente consistente.'
            );
        }

        $dataset = [
            'processing_run_id' =>
                $runId,

            'intake_batch_id' =>
                $batchId,

            'definition_version' =>
                $run->definition_version !== null
                    ? (int) $run->definition_version
                    : null,

            'schema_version' =>
                (int) $run->schema_version,

            'profiling_version' =>
                (int) $run->profiling_version,

            'normalization_version' =>
                (int) $run->normalization_version,

            'normalized_row_count' =>
                max(
                    0,
                    (int) $run->normalized_row_count
                ),

            'has_rows' =>
                (int) $run->normalized_row_count > 0,

            'completed_at' =>
                $run->completed_at?->toISOString(),
        ];

        $counts =
            app(
                \App\Services\Diagnosis\DataTransformationBiPreparedDatasetReader
                    ::class
            )->domainCountsInDataset(
                $companyId,
                $dataset
            );

        return [
            'dataset' =>
                $dataset,

            'domain_row_count' =>
                (int) (
                    $counts['domains'][$domain]
                    ?? 0
                ),
        ];
    }

    private function existingSourceArtifact(
        \App\Models\DataTransformationBiIntakeDomainDelivery $delivery
    ): ?array {
        if (
            ! is_string($delivery->source_disk)
            || trim($delivery->source_disk) === ''
            || ! is_string($delivery->source_path)
            || trim($delivery->source_path) === ''
        ) {
            return null;
        }

        return [
            'disk' =>
                (string) $delivery->source_disk,

            'path' =>
                (string) $delivery->source_path,
        ];
    }

    private function resetSessionAfterDecisionChange(
        \App\Models\DataTransformationBiIntakeSession $session
    ): void {
        $session->fill([
            'status' =>
                \App\Models\DataTransformationBiIntakeSession
                    ::STATUS_DRAFT,

            'resolved_manifest_sha256' =>
                null,

            'relational_validation_snapshot' =>
                null,

            'ready_at' =>
                null,

            'failure_code' =>
                null,

            'failure_message' =>
                null,
        ]);

        $session->save();
    }

    /**
     * Delete a superseded private source only if no current delivery points
     * at it. The reference check prevents a post-commit cleanup race from
     * deleting an artifact that has since become current again.
     *
     * @param array{disk:string,path:string} $artifact
     */
    private function deleteArtifactIfUnreferenced(
        array $artifact
    ): void {
        $diskName =
            trim(
                (string) (
                    $artifact['disk']
                    ?? ''
                )
            );

        $path =
            trim(
                (string) (
                    $artifact['path']
                    ?? ''
                )
            );

        if (
            $diskName === ''
            || $path === ''
        ) {
            return;
        }

        $referenced =
            \App\Models\DataTransformationBiIntakeDomainDelivery
                ::query()
                ->where(
                    'source_disk',
                    $diskName
                )
                ->where(
                    'source_path',
                    $path
                )
                ->exists();

        if ($referenced) {
            return;
        }

        try {
            $disk =
                \Illuminate\Support\Facades\Storage::disk(
                    $diskName
                );

            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        } catch (\Throwable) {
            /*
             * The database decision is already committed.
             * Artifact cleanup is best-effort and must not invalidate it.
             */
        }
    }

    private function decisionResult(
        \App\Models\DataTransformationBiIntakeDomainDelivery $delivery,
        bool $reused
    ): array {
        return [
            'reused' =>
                $reused,

            'delivery_id' =>
                (int) $delivery->getKey(),

            'session_id' =>
                (int) $delivery
                    ->data_transformation_bi_intake_session_id,

            'company_id' =>
                (int) $delivery->company_id,

            'domain' =>
                (string) $delivery->domain_key,

            'mode' =>
                $delivery->delivery_mode,

            'status' =>
                (string) $delivery->status,

            'source_format' =>
                $delivery->source_format,

            'original_filename' =>
                $delivery->original_filename,

            'source_sha256' =>
                $delivery->source_sha256,

            'source_row_count' =>
                (int) $delivery->source_row_count,

            'accepted_row_count' =>
                (int) $delivery->accepted_row_count,

            'carry_forward_processing_run_id' =>
                $delivery->carry_forward_processing_run_id,

            'carry_forward_intake_batch_id' =>
                $delivery->carry_forward_intake_batch_id,

            'validated_at' =>
                $delivery->validated_at
                    ?->toISOString(),
        ];
    }

    private function sourcePath(
        int $companyId,
        int $requestId,
        int $sessionId,
        string $domain,
        string $sha256,
        string $format
    ): string {
        $extension =
            $format
                === DataTransformationBiIntakeDomainDelivery
                    ::FORMAT_XLSX
                    ? 'xlsx'
                    : 'csv';

        return sprintf(
            'data-transformation-bi/intake-v2/'
            .'company_%d/request_%d/session_%d/'
            .'%s/%s.%s',
            $companyId,
            $requestId,
            $sessionId,
            $domain,
            $sha256,
            $extension
        );
    }

    private function assertArtifactIntegrity(
        mixed $disk,
        string $sourcePath,
        string $expectedSha256
    ): void {
        $stream =
            $disk->readStream(
                $sourcePath
            );

        if ($stream === false) {
            throw new RuntimeException(
                'No se pudo releer el archivo privado.'
            );
        }

        try {
            $context =
                hash_init(
                    'sha256'
                );

            hash_update_stream(
                $context,
                $stream
            );

            $actualSha256 =
                hash_final(
                    $context
                );
        } finally {
            fclose(
                $stream
            );
        }

        if (
            ! hash_equals(
                $expectedSha256,
                $actualSha256
            )
        ) {
            throw new RuntimeException(
                'El SHA-256 del archivo privado no coincide '
                .'con el archivo validado.'
            );
        }
    }

    private function assertRequestAndSession(
        TransformationImplementationRequest $request,
        DataTransformationBiIntakeSession $session
    ): void {
        if (
            ! $request->exists
            || ! $session->exists
        ) {
            throw ValidationException::withMessages([
                'intake_session' => [
                    'La solicitud y la sesión deben existir.',
                ],
            ]);
        }

        if (
            (string) $request->capability_key
            !== 'data_transformation_bi'
        ) {
            throw ValidationException::withMessages([
                'implementation_request' => [
                    'La solicitud no corresponde a Transformación '
                    .'de Datos para BI.',
                ],
            ]);
        }

        if (
            (int) $session->company_id
                !== (int) $request->company_id
            || (int) $session
                ->transformation_implementation_request_id
                !== (int) $request->getKey()
        ) {
            throw ValidationException::withMessages([
                'intake_session' => [
                    'La sesión no pertenece a la solicitud indicada.',
                ],
            ]);
        }

        if (
            (int) $session->schema_version
            !== DataTransformationBiStandardIntakeSchema
                ::VERSION
        ) {
            throw ValidationException::withMessages([
                'intake_session' => [
                    'La sesión usa una versión de esquema distinta '
                    .'a la versión activa.',
                ],
            ]);
        }
    }

    private function assertLockedContract(
        TransformationImplementationRequest $request,
        DataTransformationBiIntakeSession $session
    ): void {
        $this->assertRequestAndSession(
            $request,
            $session
        );

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
                    'La sesión ya no admite cambios de dominio.',
                ],
            ]);
        }
    }

    private function assertDomain(
        string $domain
    ): void {
        if (
            ! in_array(
                $domain,
                DataTransformationBiStandardIntakeSchema
                    ::domainKeys(),
                true
            )
        ) {
            throw ValidationException::withMessages([
                'domain' => [
                    "Dominio canónico no soportado: {$domain}.",
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
                'La gestión del intake requiere privilegios administrativos.'
            );
        }
    }
}
