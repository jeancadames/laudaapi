<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiIntakeBatch;
use App\Models\DataTransformationBiIntakeBatchDomain;
use App\Models\TransformationImplementationDefinition;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;
use Throwable;

final class DataTransformationBiStandardIntakeIngestionService
{
    public const SOURCE_DISK =
        'private';

    public const MAX_SOURCE_BYTES =
        2097152;

    public const INSERT_CHUNK_SIZE =
        500;

    private DataTransformationBiStandardIntakeValidationService
        $validationService;

    private DataTransformationBiStandardIntakeFileReader
        $fileReader;

    public function __construct(
        ?DataTransformationBiStandardIntakeValidationService $validationService = null,
        ?DataTransformationBiStandardIntakeFileReader $fileReader = null
    ) {
        $this->validationService =
            $validationService
            ?? new DataTransformationBiStandardIntakeValidationService();

        $this->fileReader =
            $fileReader
            ?? new DataTransformationBiStandardIntakeFileReader();
    }

    /**
     * Persist a standard intake only after the complete I15 validation
     * pipeline has passed.
     *
     * This method creates staging data only. It does not mutate:
     * - Implementation Request lifecycle;
     * - Definition lifecycle;
     * - capability activation;
     * - commercial state;
     * - final BI models.
     */
    public function ingest(
        TransformationImplementationRequest $implementationRequest,
        UploadedFile $file,
        User $actor
    ): array {
        $this->assertAdmin(
            $actor
        );

        $this->assertPersistedRequest(
            $implementationRequest
        );

        $upload =
            $this->inspectUpload(
                $file
            );

        $validation =
            $this->validationService
                ->validate(
                    $upload['path'],
                    $upload['original_name']
                );

        if (
            ($validation['valid'] ?? false)
            !== true
        ) {
            $messages =
                array_values(
                    array_filter(
                        array_map(
                            static fn (mixed $message): string =>
                                trim(
                                    (string) $message
                                ),
                            $validation['errors']
                            ?? []
                        ),
                        static fn (string $message): bool =>
                            $message !== ''
                    )
                );

            if ($messages === []) {
                $messages = [
                    'El archivo no superó la validación estándar de intake.',
                ];
            }

            throw ValidationException::withMessages([
                'file' =>
                    $messages,
            ]);
        }

        $schemaVersion =
            (int) (
                $validation['schema_version']
                ?? DataTransformationBiStandardIntakeSchema::VERSION
            );

        if (
            $schemaVersion
            !== DataTransformationBiStandardIntakeSchema::VERSION
        ) {
            throw ValidationException::withMessages([
                'file' => [
                    'La versión del esquema validado no coincide con '
                    .'la versión soportada por staging.',
                ],
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
                    DataTransformationBiIntakeBatch::FORMAT_XLSX,
                    DataTransformationBiIntakeBatch::FORMAT_CSV_ZIP,
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'file' => [
                    'El formato validado no puede pasar a staging.',
                ],
            ]);
        }

        /*
         * I15 intentionally does not expose client rows in its HTTP report.
         * Once PASS is known, the same hardened reader is used again here
         * to obtain the canonical source rows for controlled staging.
         */
        $read =
            $this->fileReader
                ->read(
                    $upload['path'],
                    $format
                );

        $rowsByDomain =
            $read['rows']
            ?? null;

        if (! is_array($rowsByDomain)) {
            throw new RuntimeException(
                'El lector de intake no devolvió filas canónicas.'
            );
        }

        $sourceSha256 =
            hash_file(
                'sha256',
                $upload['path']
            );

        if (
            ! is_string($sourceSha256)
            || strlen($sourceSha256) !== 64
        ) {
            throw new RuntimeException(
                'No se pudo calcular SHA-256 del archivo de intake.'
            );
        }

        $sourcePath =
            $this->sourcePath(
                (int) $implementationRequest->company_id,
                (int) $implementationRequest->getKey(),
                $schemaVersion,
                $sourceSha256,
                $format
            );

        $reservation =
            $this->reserveBatch(
                $implementationRequest,
                $actor,
                $upload,
                $validation,
                $schemaVersion,
                $format,
                $sourceSha256,
                $sourcePath
            );

        /** @var DataTransformationBiIntakeBatch $batch */
        $batch =
            $reservation['batch'];

        if (
            ($reservation['reused'] ?? false)
            === true
        ) {
            /*
             * A completed batch is reusable only when its immutable
             * metadata still matches the content-addressed artifact
             * contract.
             */
            $this->assertCompletedBatchContract(
                $implementationRequest,
                $batch,
                $schemaVersion,
                $format,
                $sourceSha256,
                $sourcePath
            );

            /*
             * Missing artifact:
             * restore it from the newly validated upload.
             *
             * Existing but corrupted artifact:
             * never overwrite silently; integrity verification throws.
             */
            $this->ensureSourceArtifact(
                $upload['path'],
                $sourcePath,
                $sourceSha256
            );

            return $this->summary(
                $batch,
                true
            );
        }

        $artifactCreatedNow =
            false;

        try {
            /*
             * Existing artifacts are accepted only after SHA-256
             * verification. New artifacts are verified immediately
             * after writing.
             */
            $artifactCreatedNow =
                $this->ensureSourceArtifact(
                    $upload['path'],
                    $sourcePath,
                    $sourceSha256
                );

            return $this->stageValidatedRows(
                $implementationRequest,
                $batch,
                $rowsByDomain
            );
        } catch (Throwable $exception) {
            /*
             * Remove only an artifact created by THIS attempt.
             *
             * Never delete a pre-existing verified artifact merely
             * because a later database staging operation failed.
             */
            if ($artifactCreatedNow) {
                try {
                    Storage::disk(
                        self::SOURCE_DISK
                    )
                        ->delete(
                            $sourcePath
                        );
                } catch (Throwable) {
                    /*
                     * Preserve the original ingestion exception.
                     */
                }
            }

            $this->markFailed(
                $batch,
                $exception
            );

            throw $exception;
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
                'Solo un administrador de LAUDA puede ingerir este intake.'
            );
        }

        if (
            ! $actor->exists
            || $actor->getKey() === null
        ) {
            throw new AuthorizationException(
                'El administrador debe existir antes de iniciar la ingestión.'
            );
        }
    }

    private function assertPersistedRequest(
        TransformationImplementationRequest $request
    ): void {
        if (
            ! $request->exists
            || $request->getKey() === null
        ) {
            throw ValidationException::withMessages([
                'implementation_request' => [
                    'La solicitud de implementación debe existir antes '
                    .'de iniciar staging.',
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
                    .'e Inteligencia de Datos para BI.',
                ],
            ]);
        }

        if (
            (int) $request->company_id
            <= 0
        ) {
            throw ValidationException::withMessages([
                'implementation_request' => [
                    'La solicitud no tiene una empresa válida.',
                ],
            ]);
        }
    }

    private function inspectUpload(
        UploadedFile $file
    ): array {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'file' => [
                    'El archivo recibido no es válido.',
                ],
            ]);
        }

        $path =
            $file->getRealPath();

        if (
            ! is_string($path)
            || $path === ''
            || ! is_file($path)
        ) {
            throw ValidationException::withMessages([
                'file' => [
                    'No se pudo acceder al archivo temporal recibido.',
                ],
            ]);
        }

        $originalName =
            basename(
                str_replace(
                    '\\',
                    '/',
                    $file->getClientOriginalName()
                )
            );

        if (
            $originalName === ''
            || strlen($originalName) > 255
        ) {
            throw ValidationException::withMessages([
                'file' => [
                    'El nombre del archivo no es válido.',
                ],
            ]);
        }

        $extension =
            strtolower(
                pathinfo(
                    $originalName,
                    PATHINFO_EXTENSION
                )
            );

        if (
            ! in_array(
                $extension,
                [
                    'xlsx',
                    'zip',
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'file' => [
                    'Selecciona un archivo XLSX o el paquete ZIP '
                    .'de CSV de LAUDA.',
                ],
            ]);
        }

        $size =
            filesize(
                $path
            );

        if (
            ! is_int($size)
            || $size < 0
            || $size > self::MAX_SOURCE_BYTES
        ) {
            throw ValidationException::withMessages([
                'file' => [
                    'El archivo supera el máximo permitido de 2 MB.',
                ],
            ]);
        }

        $serverMime =
            (new \finfo(
                FILEINFO_MIME_TYPE
            ))
                ->file(
                    $path
                );

        if (
            ! is_string($serverMime)
            || ! in_array(
                strtolower(
                    $serverMime
                ),
                $this->allowedMimes(
                    $extension
                ),
                true
            )
        ) {
            throw ValidationException::withMessages([
                'file' => [
                    'El contenido del archivo no corresponde '
                    .'al formato seleccionado.',
                ],
            ]);
        }

        return [
            'path' =>
                $path,

            'original_name' =>
                $originalName,

            'extension' =>
                $extension,

            'mime_type' =>
                strtolower(
                    $serverMime
                ),

            'size_bytes' =>
                $size,
        ];
    }

    private function allowedMimes(
        string $extension
    ): array {
        return match ($extension) {
            'xlsx' => [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip',
                'application/x-zip',
                'application/x-zip-compressed',
                'application/octet-stream',
            ],

            'zip' => [
                'application/zip',
                'application/x-zip',
                'application/x-zip-compressed',
                'application/octet-stream',
            ],

            default => [],
        };
    }

    private function sourcePath(
        int $companyId,
        int $requestId,
        int $schemaVersion,
        string $sha256,
        string $format
    ): string {
        $extension =
            $format
                === DataTransformationBiIntakeBatch::FORMAT_XLSX
                ? 'xlsx'
                : 'zip';

        return sprintf(
            'data-transformation-bi/intake/company_%d/request_%d/schema_v%d/%s.%s',
            $companyId,
            $requestId,
            $schemaVersion,
            $sha256,
            $extension
        );
    }

    private function reserveBatch(
        TransformationImplementationRequest $request,
        User $actor,
        array $upload,
        array $validation,
        int $schemaVersion,
        string $format,
        string $sourceSha256,
        string $sourcePath
    ): array {
        return DB::transaction(
            function () use (
                $request,
                $actor,
                $upload,
                $validation,
                $schemaVersion,
                $format,
                $sourceSha256,
                $sourcePath
            ): array {
                $lockedRequest =
                    TransformationImplementationRequest::query()
                        ->whereKey(
                            $request->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertPersistedRequest(
                    $lockedRequest
                );

                if (
                    (int) $lockedRequest->company_id
                    !== (int) $request->company_id
                ) {
                    throw ValidationException::withMessages([
                        'implementation_request' => [
                            'La empresa de la solicitud cambió durante '
                            .'la preparación del intake.',
                        ],
                    ]);
                }

                $batch =
                    DataTransformationBiIntakeBatch::query()
                        ->where(
                            'transformation_implementation_request_id',
                            $lockedRequest->getKey()
                        )
                        ->where(
                            'schema_version',
                            $schemaVersion
                        )
                        ->where(
                            'source_sha256',
                            $sourceSha256
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    $batch !== null
                    && $batch->status
                        === DataTransformationBiIntakeBatch::STATUS_COMPLETED
                ) {
                    return [
                        'batch' =>
                            $batch,

                        'reused' =>
                            true,
                    ];
                }

                if (
                    $batch !== null
                    && $batch->status
                        === DataTransformationBiIntakeBatch::STATUS_PROCESSING
                ) {
                    throw ValidationException::withMessages([
                        'file' => [
                            'Este mismo archivo ya está siendo procesado.',
                        ],
                    ]);
                }

                $definition =
                    $this->latestDefinitionFor(
                        $lockedRequest
                    );

                if ($batch === null) {
                    $batch =
                        DataTransformationBiIntakeBatch::query()
                            ->create([
                                'company_id' =>
                                    (int) $lockedRequest->company_id,

                                'transformation_implementation_request_id' =>
                                    (int) $lockedRequest->getKey(),

                                'transformation_implementation_definition_id' =>
                                    $definition?->getKey(),

                                'definition_version' =>
                                    $definition?->version,

                                'schema_version' =>
                                    $schemaVersion,

                                'source_disk' =>
                                    self::SOURCE_DISK,

                                'source_path' =>
                                    $sourcePath,

                                'original_filename' =>
                                    $upload['original_name'],

                                'source_format' =>
                                    $format,

                                'source_mime_type' =>
                                    $upload['mime_type'],

                                'source_size_bytes' =>
                                    $upload['size_bytes'],

                                'source_sha256' =>
                                    $sourceSha256,

                                'validation_snapshot' =>
                                    $validation,

                                'status' =>
                                    DataTransformationBiIntakeBatch::STATUS_PROCESSING,

                                'domain_count' =>
                                    0,

                                'source_row_count' =>
                                    0,

                                'staged_row_count' =>
                                    0,

                                'rejected_row_count' =>
                                    0,

                                'created_by_user_id' =>
                                    (int) $actor->getKey(),

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
                            ]);
                } else {
                    /*
                     * Failed/pending/purged logical batches may be
                     * deliberately retried using the same idempotency key.
                     * Old staging is cleared before the retry starts.
                     */
                    DB::table(
                        'data_transformation_bi_intake_rows'
                    )
                        ->where(
                            'data_transformation_bi_intake_batch_id',
                            $batch->getKey()
                        )
                        ->delete();

                    DB::table(
                        'data_transformation_bi_intake_batch_domains'
                    )
                        ->where(
                            'data_transformation_bi_intake_batch_id',
                            $batch->getKey()
                        )
                        ->delete();

                    $batch
                        ->forceFill([
                            'company_id' =>
                                (int) $lockedRequest->company_id,

                            'transformation_implementation_definition_id' =>
                                $definition?->getKey(),

                            'definition_version' =>
                                $definition?->version,

                            'source_disk' =>
                                self::SOURCE_DISK,

                            'source_path' =>
                                $sourcePath,

                            'original_filename' =>
                                $upload['original_name'],

                            'source_format' =>
                                $format,

                            'source_mime_type' =>
                                $upload['mime_type'],

                            'source_size_bytes' =>
                                $upload['size_bytes'],

                            'validation_snapshot' =>
                                $validation,

                            'status' =>
                                DataTransformationBiIntakeBatch::STATUS_PROCESSING,

                            'domain_count' =>
                                0,

                            'source_row_count' =>
                                0,

                            'staged_row_count' =>
                                0,

                            'rejected_row_count' =>
                                0,

                            'created_by_user_id' =>
                                (int) $actor->getKey(),

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

                            'purged_at' =>
                                null,
                        ])
                        ->save();
                }

                return [
                    'batch' =>
                        $batch,

                    'reused' =>
                        false,
                ];
            }
        );
    }

    private function latestDefinitionFor(
        TransformationImplementationRequest $request
    ): ?TransformationImplementationDefinition {
        return TransformationImplementationDefinition::query()
            ->where(
                'transformation_implementation_request_id',
                $request->getKey()
            )
            ->where(
                'company_id',
                $request->company_id
            )
            ->where(
                'capability_key',
                'data_transformation_bi'
            )
            ->orderByDesc(
                'version'
            )
            ->orderByDesc(
                'id'
            )
            ->first();
    }

    /**
     * Ensure the content-addressed private source artifact exists
     * and matches the expected SHA-256.
     *
     * Returns true only when THIS call created the artifact.
     *
     * Existing corrupted content is never overwritten automatically.
     */
    private function ensureSourceArtifact(
        string $temporaryPath,
        string $sourcePath,
        string $expectedSha256
    ): bool {
        $disk =
            Storage::disk(
                self::SOURCE_DISK
            );

        if (
            $disk->exists(
                $sourcePath
            )
        ) {
            $this->assertSourceArtifactIntegrity(
                $disk,
                $sourcePath,
                $expectedSha256
            );

            return false;
        }

        $stream =
            fopen(
                $temporaryPath,
                'rb'
            );

        if ($stream === false) {
            throw new RuntimeException(
                'No se pudo abrir el archivo validado para almacenamiento.'
            );
        }

        try {
            $stored =
                $disk->put(
                    $sourcePath,
                    $stream
                );

            if ($stored !== true) {
                throw new RuntimeException(
                    'No se pudo guardar el archivo validado '
                    .'en almacenamiento privado.'
                );
            }
        } finally {
            fclose(
                $stream
            );
        }

        try {
            $this->assertSourceArtifactIntegrity(
                $disk,
                $sourcePath,
                $expectedSha256
            );
        } catch (Throwable $exception) {
            /*
             * A newly written artifact that fails its own checksum
             * must never remain available for later reuse.
             */
            try {
                $disk->delete(
                    $sourcePath
                );
            } catch (Throwable) {
                /*
                 * Preserve the integrity exception.
                 */
            }

            throw $exception;
        }

        return true;
    }

    private function assertCompletedBatchContract(
        TransformationImplementationRequest $request,
        DataTransformationBiIntakeBatch $batch,
        int $schemaVersion,
        string $format,
        string $sourceSha256,
        string $sourcePath
    ): void {
        if (
            $batch->status
                !== DataTransformationBiIntakeBatch::STATUS_COMPLETED
            || (int) $batch->company_id
                !== (int) $request->company_id
            || (int) $batch
                ->transformation_implementation_request_id
                !== (int) $request->getKey()
            || (int) $batch->schema_version
                !== $schemaVersion
            || (string) $batch->source_format
                !== $format
            || (string) $batch->source_sha256
                !== $sourceSha256
            || (string) $batch->source_disk
                !== self::SOURCE_DISK
            || (string) $batch->source_path
                !== $sourcePath
        ) {
            throw new RuntimeException(
                'El batch completado no coincide con el contrato '
                .'inmutable del archivo de intake.'
            );
        }
    }

    private function assertSourceArtifactIntegrity(
        FilesystemAdapter $disk,
        string $sourcePath,
        string $expectedSha256
    ): void {
        $actualSha256 =
            $this->sourceArtifactSha256(
                $disk,
                $sourcePath
            );

        if (
            ! hash_equals(
                $expectedSha256,
                $actualSha256
            )
        ) {
            throw new RuntimeException(
                'El archivo privado de intake no coincide '
                .'con su SHA-256 esperado.'
            );
        }
    }

    private function sourceArtifactSha256(
        FilesystemAdapter $disk,
        string $sourcePath
    ): string {
        $stream =
            $disk->readStream(
                $sourcePath
            );

        if (
            ! is_resource(
                $stream
            )
        ) {
            throw new RuntimeException(
                'No se pudo leer el archivo privado de intake '
                .'para verificar su integridad.'
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

            $sha256 =
                hash_final(
                    $context
                );
        } finally {
            fclose(
                $stream
            );
        }

        if (
            strlen(
                $sha256
            )
            !== 64
        ) {
            throw new RuntimeException(
                'No se pudo verificar SHA-256 del archivo privado.'
            );
        }

        return $sha256;
    }

    private function stageValidatedRows(
        TransformationImplementationRequest $request,
        DataTransformationBiIntakeBatch $batch,
        array $rowsByDomain
    ): array {
        return DB::transaction(
            function () use (
                $request,
                $batch,
                $rowsByDomain
            ): array {
                /*
                 * Keep lock ordering stable:
                 * request first, then batch.
                 */
                $lockedRequest =
                    TransformationImplementationRequest::query()
                        ->whereKey(
                            $request->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertPersistedRequest(
                    $lockedRequest
                );

                $lockedBatch =
                    DataTransformationBiIntakeBatch::query()
                        ->whereKey(
                            $batch->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    (int) $lockedBatch->company_id
                        !== (int) $lockedRequest->company_id
                    || (int) $lockedBatch
                        ->transformation_implementation_request_id
                        !== (int) $lockedRequest->getKey()
                ) {
                    throw new RuntimeException(
                        'El batch de intake no pertenece a la solicitud indicada.'
                    );
                }

                if (
                    $lockedBatch->status
                    !== DataTransformationBiIntakeBatch::STATUS_PROCESSING
                ) {
                    throw new RuntimeException(
                        'El batch no se encuentra en estado de procesamiento.'
                    );
                }

                DB::table(
                    'data_transformation_bi_intake_rows'
                )
                    ->where(
                        'data_transformation_bi_intake_batch_id',
                        $lockedBatch->getKey()
                    )
                    ->delete();

                DB::table(
                    'data_transformation_bi_intake_batch_domains'
                )
                    ->where(
                        'data_transformation_bi_intake_batch_id',
                        $lockedBatch->getKey()
                    )
                    ->delete();

                $schemaDomains =
                    DataTransformationBiStandardIntakeSchema
                        ::domains();

                $totalSourceRows = 0;
                $totalStagedRows = 0;
                $domainSummary = [];

                foreach (
                    DataTransformationBiStandardIntakeSchema
                        ::domainKeys()
                    as $domain
                ) {
                    $rows =
                        $rowsByDomain[$domain]
                        ?? [];

                    if (! is_array($rows)) {
                        throw new RuntimeException(
                            "El dominio {$domain} no contiene una lista de filas."
                        );
                    }

                    $sourceCount =
                        count(
                            $rows
                        );

                    $totalSourceRows +=
                        $sourceCount;

                    $stagedCount =
                        $this->insertDomainRows(
                            $lockedBatch,
                            $domain,
                            $rows,
                            $schemaDomains[$domain]
                        );

                    $totalStagedRows +=
                        $stagedCount;

                    DataTransformationBiIntakeBatchDomain::query()
                        ->create([
                            'data_transformation_bi_intake_batch_id' =>
                                (int) $lockedBatch->getKey(),

                            'company_id' =>
                                (int) $lockedBatch->company_id,

                            'domain_key' =>
                                $domain,

                            'status' =>
                                DataTransformationBiIntakeBatchDomain
                                    ::STATUS_COMPLETED,

                            'source_row_count' =>
                                $sourceCount,

                            'staged_row_count' =>
                                $stagedCount,

                            'rejected_row_count' =>
                                0,

                            'failure_message' =>
                                null,
                        ]);

                    $domainSummary[$domain] = [
                        'source_row_count' =>
                            $sourceCount,

                        'staged_row_count' =>
                            $stagedCount,

                        'rejected_row_count' =>
                            0,
                    ];
                }

                if (
                    $totalSourceRows
                    !== $totalStagedRows
                ) {
                    throw new RuntimeException(
                        'La cantidad de filas staged no coincide '
                        .'con las filas validadas.'
                    );
                }

                $lockedBatch
                    ->forceFill([
                        'status' =>
                            DataTransformationBiIntakeBatch::STATUS_COMPLETED,

                        'domain_count' =>
                            count(
                                $domainSummary
                            ),

                        'source_row_count' =>
                            $totalSourceRows,

                        'staged_row_count' =>
                            $totalStagedRows,

                        'rejected_row_count' =>
                            0,

                        'source_deleted_at' =>
                            null,

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

                return [
                    'reused' =>
                        false,

                    'batch_id' =>
                        (int) $lockedBatch->getKey(),

                    'status' =>
                        DataTransformationBiIntakeBatch::STATUS_COMPLETED,

                    'schema_version' =>
                        (int) $lockedBatch->schema_version,

                    'format' =>
                        (string) $lockedBatch->source_format,

                    'original_filename' =>
                        (string) $lockedBatch->original_filename,

                    'domain_count' =>
                        (int) $lockedBatch->domain_count,

                    'source_row_count' =>
                        (int) $lockedBatch->source_row_count,

                    'staged_row_count' =>
                        (int) $lockedBatch->staged_row_count,

                    'rejected_row_count' =>
                        (int) $lockedBatch->rejected_row_count,

                    'domains' =>
                        $domainSummary,
                ];
            }
        );
    }

    private function insertDomainRows(
        DataTransformationBiIntakeBatch $batch,
        string $domain,
        array $rows,
        array $domainDefinition
    ): int {
        $buffer = [];
        $inserted = 0;
        $sourceRowNumber = 2;
        $now = now();

        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new RuntimeException(
                    "{$domain}: se recibió una fila no válida para staging."
                );
            }

            $canonicalRow =
                $this->canonicalRow(
                    $row,
                    $domainDefinition
                );

            $rowJson =
                $this->json(
                    $canonicalRow
                );

            $identityHash =
                $this->identityHash(
                    $domain,
                    $canonicalRow
                );

            $buffer[] = [
                'data_transformation_bi_intake_batch_id' =>
                    (int) $batch->getKey(),

                'company_id' =>
                    (int) $batch->company_id,

                'domain_key' =>
                    $domain,

                'source_row_number' =>
                    $sourceRowNumber,

                'identity_hash' =>
                    $identityHash,

                'row_sha256' =>
                    hash(
                        'sha256',
                        $this->json([
                            'domain' =>
                                $domain,

                            'row' =>
                                $canonicalRow,
                        ])
                    ),

                'row_payload' =>
                    $rowJson,

                'created_at' =>
                    $now,

                'updated_at' =>
                    $now,
            ];

            $sourceRowNumber++;
            $inserted++;

            if (
                count($buffer)
                >= self::INSERT_CHUNK_SIZE
            ) {
                DB::table(
                    'data_transformation_bi_intake_rows'
                )
                    ->insert(
                        $buffer
                    );

                $buffer = [];
            }
        }

        if ($buffer !== []) {
            DB::table(
                'data_transformation_bi_intake_rows'
            )
                ->insert(
                    $buffer
                );
        }

        return $inserted;
    }

    private function canonicalRow(
        array $row,
        array $domainDefinition
    ): array {
        $canonical = [];

        foreach (
            $domainDefinition['fields']
            ?? []
            as $field
        ) {
            $name =
                (string) (
                    $field['name']
                    ?? ''
                );

            if ($name === '') {
                continue;
            }

            $canonical[$name] =
                array_key_exists(
                    $name,
                    $row
                )
                    ? $row[$name]
                    : null;
        }

        return $canonical;
    }

    private function identityHash(
        string $domain,
        array $row
    ): ?string {
        $fields =
            match ($domain) {
                'customers' => [
                    'customer_id',
                ],

                'products' => [
                    'product_id',
                ],

                'inventory' => [
                    'snapshot_date',
                    'product_id',
                    'branch',
                    'warehouse',
                ],

                'sales' => [
                    'document_id',
                    'line_number',
                ],

                'accounts_receivable' => [
                    'document_id',
                ],

                'suppliers' => [
                    'supplier_id',
                ],

                'accounts_payable' => [
                    'document_id',
                ],

                default => [],
            };

        if ($fields === []) {
            return null;
        }

        $identity = [];

        foreach ($fields as $field) {
            $value =
                $row[$field]
                ?? null;

            if (
                is_string($value)
                && trim($value) === ''
            ) {
                $value = null;
            }

            $identity[$field] =
                $value;
        }

        return hash(
            'sha256',
            $this->json([
                'domain' =>
                    $domain,

                'identity' =>
                    $identity,
            ])
        );
    }

    private function json(
        mixed $value
    ): string {
        try {
            return json_encode(
                $value,
                JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_PRESERVE_ZERO_FRACTION
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'No se pudo serializar una fila canónica para staging.',
                0,
                $exception
            );
        }
    }

    private function summary(
        DataTransformationBiIntakeBatch $batch,
        bool $reused
    ): array {
        $domains =
            DataTransformationBiIntakeBatchDomain::query()
                ->where(
                    'data_transformation_bi_intake_batch_id',
                    $batch->getKey()
                )
                ->orderBy(
                    'id'
                )
                ->get();

        $domainSummary = [];

        foreach ($domains as $domain) {
            $domainSummary[
                (string) $domain->domain_key
            ] = [
                'source_row_count' =>
                    (int) $domain->source_row_count,

                'staged_row_count' =>
                    (int) $domain->staged_row_count,

                'rejected_row_count' =>
                    (int) $domain->rejected_row_count,
            ];
        }

        return [
            'reused' =>
                $reused,

            'batch_id' =>
                (int) $batch->getKey(),

            'status' =>
                (string) $batch->status,

            'schema_version' =>
                (int) $batch->schema_version,

            'format' =>
                (string) $batch->source_format,

            'original_filename' =>
                (string) $batch->original_filename,

            'domain_count' =>
                (int) $batch->domain_count,

            'source_row_count' =>
                (int) $batch->source_row_count,

            'staged_row_count' =>
                (int) $batch->staged_row_count,

            'rejected_row_count' =>
                (int) $batch->rejected_row_count,

            'domains' =>
                $domainSummary,
        ];
    }

    private function markFailed(
        DataTransformationBiIntakeBatch $batch,
        Throwable $exception
    ): void {
        try {
            DB::transaction(
                function () use (
                    $batch,
                    $exception
                ): void {
                    $locked =
                        DataTransformationBiIntakeBatch::query()
                            ->whereKey(
                                $batch->getKey()
                            )
                            ->lockForUpdate()
                            ->first();

                    if (
                        $locked === null
                        || $locked->status
                            === DataTransformationBiIntakeBatch::STATUS_COMPLETED
                    ) {
                        return;
                    }

                    $locked
                        ->forceFill([
                            'status' =>
                                DataTransformationBiIntakeBatch::STATUS_FAILED,

                            'completed_at' =>
                                null,

                            'failed_at' =>
                                now(),

                            'failure_code' =>
                                $this->failureCode(
                                    $exception
                                ),

                            'failure_message' =>
                                mb_substr(
                                    trim(
                                        $exception->getMessage()
                                    ),
                                    0,
                                    4000
                                ),
                        ])
                        ->save();
                }
            );
        } catch (Throwable) {
            /*
             * Never replace the original ingestion exception with
             * secondary failure-reporting trouble.
             */
        }
    }

    private function failureCode(
        Throwable $exception
    ): string {
        $name =
            class_basename(
                $exception
            );

        $normalized =
            strtolower(
                preg_replace(
                    '/[^A-Za-z0-9]+/',
                    '_',
                    $name
                )
                ?? 'ingestion_error'
            );

        return substr(
            trim(
                $normalized,
                '_'
            ),
            0,
            100
        );
    }
}
