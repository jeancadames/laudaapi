<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiIntakeBatch;
use App\Models\DataTransformationBiIntakeBatchDomain;
use App\Models\DataTransformationBiIntakeRow;
use App\Models\DataTransformationBiIntakeSession;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;
use Throwable;

final class DataTransformationBiIntakeV2StagingMaterializationService
{
    public const SOURCE_DISK =
        'private';

    public const INSERT_CHUNK_SIZE =
        500;

    public function __construct(
        private readonly DataTransformationBiIntakeV2SessionResolutionService
            $resolutionService
    ) {
    }

    public function materialize(
        TransformationImplementationRequest $request,
        DataTransformationBiIntakeSession $session,
        User $actor
    ): array {
        $this->assertAdmin(
            $actor
        );

        $freshSession =
            DataTransformationBiIntakeSession::query()
                ->whereKey(
                    $session->getKey()
                )
                ->firstOrFail();

        /*
         * A finalized session is immutable. Repeated materialization must
         * return the exact same canonical batch.
         */
        if (
            $freshSession->status
            === DataTransformationBiIntakeSession::STATUS_FINALIZED
        ) {
            return $this->reuseFinalized(
                $request,
                $freshSession
            );
        }

        if (
            $freshSession->status
            !== DataTransformationBiIntakeSession::STATUS_READY
        ) {
            throw ValidationException::withMessages([
                'session' => [
                    'La sesión debe encontrarse READY '
                    .'antes de crear staging.',
                ],
            ]);
        }

        $prepared =
            $this->resolutionService
                ->prepareReadyPayload(
                    $request,
                    $freshSession,
                    $actor
                );

        $manifest =
            $prepared['manifest'];

        $manifestBytes =
            $this->json(
                $manifest
            );

        $manifestSha256 =
            hash(
                'sha256',
                $manifestBytes
            );

        if (
            ! hash_equals(
                (string) $prepared['manifest_sha256'],
                $manifestSha256
            )
            || ! hash_equals(
                (string) $freshSession
                    ->resolved_manifest_sha256,
                $manifestSha256
            )
        ) {
            throw new RuntimeException(
                'El archivo manifest no coincide con '
                .'la identidad READY de la sesión.'
            );
        }

        $sourcePath =
            $this->manifestPath(
                $freshSession,
                $manifestSha256
            );

        $artifactCreatedNow =
            $this->ensureManifestArtifact(
                $sourcePath,
                $manifestBytes,
                $manifestSha256
            );

        try {
            return DB::transaction(
                function () use (
                    $request,
                    $freshSession,
                    $actor,
                    $prepared,
                    $manifestSha256,
                    $manifestBytes,
                    $sourcePath
                ): array {
                    /*
                     * Keep the established lock ordering:
                     * request first, then session, then batch.
                     */
                    $lockedRequest =
                        TransformationImplementationRequest::query()
                            ->whereKey(
                                $request->getKey()
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $this->assertRequest(
                        $lockedRequest
                    );

                    $lockedSession =
                        DataTransformationBiIntakeSession::query()
                            ->whereKey(
                                $freshSession->getKey()
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $this->assertReadySession(
                        $lockedRequest,
                        $lockedSession,
                        $manifestSha256
                    );

                    $rowsByDomain =
                        $prepared['rows_by_domain'];

                    $this->assertPreparedRowsMatchManifest(
                        $prepared['manifest'],
                        $rowsByDomain
                    );

                    $batch =
                        DataTransformationBiIntakeBatch::query()
                            ->where(
                                'transformation_implementation_request_id',
                                $lockedRequest->getKey()
                            )
                            ->where(
                                'schema_version',
                                $lockedSession->schema_version
                            )
                            ->where(
                                'source_sha256',
                                $manifestSha256
                            )
                            ->lockForUpdate()
                            ->first();

                    if ($batch !== null) {
                        if (
                            $batch->status
                            !== DataTransformationBiIntakeBatch
                                ::STATUS_COMPLETED
                        ) {
                            throw new RuntimeException(
                                'Ya existe un batch para este manifest '
                                .'pero no está completado.'
                            );
                        }

                        $linkedSession =
                            DataTransformationBiIntakeSession::query()
                                ->where(
                                    'resulting_intake_batch_id',
                                    $batch->getKey()
                                )
                                ->lockForUpdate()
                                ->first();

                        if (
                            $linkedSession !== null
                            && (int) $linkedSession->getKey()
                                !== (int) $lockedSession->getKey()
                        ) {
                            throw new RuntimeException(
                                'El batch lógico idéntico ya pertenece '
                                .'a otra sesión de intake.'
                            );
                        }

                        $this->assertCompletedBatchContract(
                            $lockedRequest,
                            $lockedSession,
                            $batch,
                            $sourcePath,
                            $manifestSha256
                        );

                        $this->finalizeSession(
                            $lockedSession,
                            $batch
                        );

                        return $this->summary(
                            $batch,
                            true
                        );
                    }

                    $batch =
                        DataTransformationBiIntakeBatch::query()
                            ->create([
                                'company_id' =>
                                    (int) $lockedRequest->company_id,

                                'transformation_implementation_request_id' =>
                                    (int) $lockedRequest->getKey(),

                                /*
                                 * V2 copies the definition pinned when the
                                 * session was created. It does not resolve a
                                 * newer definition at finalization time.
                                 */
                                'transformation_implementation_definition_id' =>
                                    $lockedSession
                                        ->transformation_implementation_definition_id,

                                'definition_version' =>
                                    $lockedSession->definition_version,

                                'schema_version' =>
                                    (int) $lockedSession->schema_version,

                                'source_disk' =>
                                    self::SOURCE_DISK,

                                'source_path' =>
                                    $sourcePath,

                                'original_filename' =>
                                    'intake-session-'
                                    .$lockedSession->getKey()
                                    .'-manifest.json',

                                'source_format' =>
                                    DataTransformationBiIntakeBatch
                                        ::FORMAT_DOMAIN_SESSION_MANIFEST,

                                'source_mime_type' =>
                                    'application/json',

                                'source_size_bytes' =>
                                    strlen(
                                        $manifestBytes
                                    ),

                                'source_sha256' =>
                                    $manifestSha256,

                                'validation_snapshot' => [
                                    'intake_version' =>
                                        2,

                                    'session_id' =>
                                        (int) $lockedSession->getKey(),

                                    'manifest_sha256' =>
                                        $manifestSha256,

                                    'relational_validation' =>
                                        $lockedSession
                                            ->relational_validation_snapshot,
                                ],

                                'status' =>
                                    DataTransformationBiIntakeBatch
                                        ::STATUS_PROCESSING,

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

                                'source_retention_until' =>
                                    null,

                                'source_deleted_at' =>
                                    null,

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
                            ]);

                    $stage =
                        $this->stageRows(
                            $batch,
                            $rowsByDomain
                        );

                    $batch->forceFill([
                        'status' =>
                            DataTransformationBiIntakeBatch
                                ::STATUS_COMPLETED,

                        'domain_count' =>
                            $stage['domain_count'],

                        'source_row_count' =>
                            $stage['source_row_count'],

                        'staged_row_count' =>
                            $stage['staged_row_count'],

                        'rejected_row_count' =>
                            0,

                        'completed_at' =>
                            now(),

                        'failed_at' =>
                            null,

                        'failure_code' =>
                            null,

                        'failure_message' =>
                            null,
                    ]);

                    $batch->save();

                    $this->finalizeSession(
                        $lockedSession,
                        $batch
                    );

                    return $this->summary(
                        $batch,
                        false
                    );
                }
            );
        } catch (Throwable $exception) {
            if ($artifactCreatedNow) {
                $this->deleteManifestIfUnreferenced(
                    $sourcePath
                );
            }

            throw $exception;
        }
    }

    private function reuseFinalized(
        TransformationImplementationRequest $request,
        DataTransformationBiIntakeSession $session
    ): array {
        $this->assertRequest(
            $request
        );

        if (
            (int) $session->company_id
                !== (int) $request->company_id
            || (int) $session
                ->transformation_implementation_request_id
                !== (int) $request->getKey()
        ) {
            throw new RuntimeException(
                'La sesión finalizada no pertenece '
                .'a la solicitud indicada.'
            );
        }

        $batchId =
            (int) (
                $session->resulting_intake_batch_id
                ?? 0
            );

        if ($batchId <= 0) {
            throw new RuntimeException(
                'La sesión finalizada no conserva '
                .'su batch resultante.'
            );
        }

        $batch =
            DataTransformationBiIntakeBatch::query()
                ->findOrFail(
                    $batchId
                );

        $manifestSha256 =
            (string) (
                $session->resolved_manifest_sha256
                ?? ''
            );

        $sourcePath =
            $this->manifestPath(
                $session,
                $manifestSha256
            );

        $this->assertCompletedBatchContract(
            $request,
            $session,
            $batch,
            $sourcePath,
            $manifestSha256
        );

        return $this->summary(
            $batch,
            true
        );
    }

    /**
     * @param array<string,array<int,array<string,mixed>>> $rowsByDomain
     * @return array{
     *     domain_count:int,
     *     source_row_count:int,
     *     staged_row_count:int
     * }
     */
    private function stageRows(
        DataTransformationBiIntakeBatch $batch,
        array $rowsByDomain
    ): array {
        $schema =
            DataTransformationBiStandardIntakeSchema::domains();

        $totalSourceRows = 0;
        $totalStagedRows = 0;
        $domainCount = 0;

        foreach (
            DataTransformationBiStandardIntakeSchema::domainKeys()
            as $domain
        ) {
            $rows =
                $rowsByDomain[$domain]
                ?? [];

            if (! is_array($rows)) {
                throw new RuntimeException(
                    "{$domain}: las filas preparadas "
                    .'no son una lista.'
                );
            }

            $sourceCount =
                count(
                    $rows
                );

            $stagedCount =
                $this->insertDomainRows(
                    $batch,
                    $domain,
                    $rows,
                    $schema[$domain]
                );

            if ($sourceCount !== $stagedCount) {
                throw new RuntimeException(
                    "{$domain}: las filas staged no coinciden "
                    .'con las filas resueltas.'
                );
            }

            DataTransformationBiIntakeBatchDomain::query()
                ->create([
                    'data_transformation_bi_intake_batch_id' =>
                        (int) $batch->getKey(),

                    'company_id' =>
                        (int) $batch->company_id,

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

            $domainCount++;
            $totalSourceRows +=
                $sourceCount;

            $totalStagedRows +=
                $stagedCount;
        }

        if ($domainCount !== 7) {
            throw new RuntimeException(
                'El batch v2 debe materializar '
                .'exactamente siete dominios.'
            );
        }

        if ($totalSourceRows !== $totalStagedRows) {
            throw new RuntimeException(
                'La cantidad total staged no coincide '
                .'con la cantidad resuelta.'
            );
        }

        return [
            'domain_count' =>
                $domainCount,

            'source_row_count' =>
                $totalSourceRows,

            'staged_row_count' =>
                $totalStagedRows,
        ];
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
                    "{$domain}: fila inválida para staging."
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

                /*
                 * Preserve the exact v1 staging hash semantic:
                 * SHA256({domain,row}).
                 */
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
                )->insert(
                    $buffer
                );

                $buffer = [];
            }
        }

        if ($buffer !== []) {
            DB::table(
                'data_transformation_bi_intake_rows'
            )->insert(
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
        $identityFields =
            DataTransformationBiStandardIntakeSchema
                ::identityKeys()[$domain]
            ?? [];

        if (
            ! is_array($identityFields)
            || $identityFields === []
        ) {
            return null;
        }

        $identity = [];

        foreach ($identityFields as $field) {
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

    /**
     * @param array<string,mixed> $manifest
     * @param array<string,array<int,array<string,mixed>>> $rowsByDomain
     */
    private function assertPreparedRowsMatchManifest(
        array $manifest,
        array $rowsByDomain
    ): void {
        $manifestDomains =
            $manifest['domains']
            ?? null;

        if (! is_array($manifestDomains)) {
            throw new RuntimeException(
                'El manifest no contiene dominios.'
            );
        }

        $counts = [];

        foreach ($manifestDomains as $entry) {
            if (! is_array($entry)) {
                throw new RuntimeException(
                    'Entrada de dominio inválida en manifest.'
                );
            }

            $domain =
                (string) (
                    $entry['domain']
                    ?? ''
                );

            $counts[$domain] =
                (int) (
                    $entry['row_count']
                    ?? -1
                );
        }

        foreach (
            DataTransformationBiStandardIntakeSchema::domainKeys()
            as $domain
        ) {
            if (
                ! array_key_exists(
                    $domain,
                    $counts
                )
            ) {
                throw new RuntimeException(
                    "{$domain}: no está presente en el manifest."
                );
            }

            $rows =
                $rowsByDomain[$domain]
                ?? null;

            if (
                ! is_array($rows)
                || count($rows)
                    !== $counts[$domain]
            ) {
                throw new RuntimeException(
                    "{$domain}: el conteo preparado no coincide "
                    .'con el manifest.'
                );
            }
        }
    }

    private function finalizeSession(
        DataTransformationBiIntakeSession $session,
        DataTransformationBiIntakeBatch $batch
    ): void {
        $session->forceFill([
            'status' =>
                DataTransformationBiIntakeSession
                    ::STATUS_FINALIZED,

            'resulting_intake_batch_id' =>
                (int) $batch->getKey(),

            'finalized_at' =>
                now(),

            'failure_code' =>
                null,

            'failure_message' =>
                null,
        ]);

        $session->save();
    }

    private function assertRequest(
        TransformationImplementationRequest $request
    ): void {
        if (
            ! $request->exists
            || (int) $request->getKey() <= 0
            || (int) $request->company_id <= 0
        ) {
            throw new RuntimeException(
                'La solicitud no está persistida.'
            );
        }

        if (
            (string) $request->capability_key
            !== 'data_transformation_bi'
        ) {
            throw ValidationException::withMessages([
                'request' => [
                    'La solicitud no corresponde a '
                    .'Transformación de Datos para BI.',
                ],
            ]);
        }
    }

    private function assertReadySession(
        TransformationImplementationRequest $request,
        DataTransformationBiIntakeSession $session,
        string $manifestSha256
    ): void {
        if (
            (int) $session->company_id
                !== (int) $request->company_id
            || (int) $session
                ->transformation_implementation_request_id
                !== (int) $request->getKey()
        ) {
            throw new RuntimeException(
                'La sesión no pertenece a la solicitud.'
            );
        }

        if (
            $session->status
            !== DataTransformationBiIntakeSession::STATUS_READY
        ) {
            throw ValidationException::withMessages([
                'session' => [
                    'La sesión dejó de estar READY '
                    .'antes de crear staging.',
                ],
            ]);
        }

        if (
            ! is_string(
                $session->resolved_manifest_sha256
            )
            || ! hash_equals(
                $manifestSha256,
                $session->resolved_manifest_sha256
            )
        ) {
            throw ValidationException::withMessages([
                'session' => [
                    'El manifest READY cambió '
                    .'antes de crear staging.',
                ],
            ]);
        }

        if (
            (
                $session
                    ->relational_validation_snapshot
                    ['valid']
                ?? false
            ) !== true
        ) {
            throw RuntimeException(
                'La sesión ya no conserva '
                .'validación relacional válida.'
            );
        }

        if (
            $session->resulting_intake_batch_id
            !== null
        ) {
            throw new RuntimeException(
                'Una sesión READY no debe tener '
                .'batch resultante todavía.'
            );
        }
    }

    private function assertCompletedBatchContract(
        TransformationImplementationRequest $request,
        DataTransformationBiIntakeSession $session,
        DataTransformationBiIntakeBatch $batch,
        string $sourcePath,
        string $manifestSha256
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
                !== (int) $session->schema_version
            || (string) $batch->source_disk
                !== self::SOURCE_DISK
            || (string) $batch->source_path
                !== $sourcePath
            || (string) $batch->source_format
                !== DataTransformationBiIntakeBatch
                    ::FORMAT_DOMAIN_SESSION_MANIFEST
            || ! hash_equals(
                $manifestSha256,
                (string) $batch->source_sha256
            )
            || (int) $batch->domain_count !== 7
            || (int) $batch->rejected_row_count !== 0
            || (int) $batch->source_row_count
                !== (int) $batch->staged_row_count
        ) {
            throw new RuntimeException(
                'El batch completado no coincide '
                .'con el contrato Intake v2.'
            );
        }

        $domainCount =
            DataTransformationBiIntakeBatchDomain::query()
                ->where(
                    'data_transformation_bi_intake_batch_id',
                    $batch->getKey()
                )
                ->count();

        $rowCount =
            DataTransformationBiIntakeRow::query()
                ->where(
                    'data_transformation_bi_intake_batch_id',
                    $batch->getKey()
                )
                ->count();

        if (
            $domainCount !== 7
            || $rowCount
                !== (int) $batch->staged_row_count
        ) {
            throw new RuntimeException(
                'El contenido persisted del batch '
                .'no coincide con sus contadores.'
            );
        }

        $this->assertManifestIntegrity(
            $sourcePath,
            $manifestSha256
        );
    }

    private function manifestPath(
        DataTransformationBiIntakeSession $session,
        string $sha256
    ): string {
        return sprintf(
            'data-transformation-bi/intake-v2/'
            .'company_%d/request_%d/session_%d/'
            .'manifest/%s.json',
            (int) $session->company_id,
            (int) $session
                ->transformation_implementation_request_id,
            (int) $session->getKey(),
            $sha256
        );
    }

    private function ensureManifestArtifact(
        string $sourcePath,
        string $manifestBytes,
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
            $this->assertManifestIntegrity(
                $sourcePath,
                $expectedSha256
            );

            return false;
        }

        $stream =
            fopen(
                'php://temp',
                'w+b'
            );

        if ($stream === false) {
            throw new RuntimeException(
                'No se pudo crear stream '
                .'para el manifest.'
            );
        }

        try {
            $written =
                fwrite(
                    $stream,
                    $manifestBytes
                );

            if (
                $written === false
                || $written !== strlen(
                    $manifestBytes
                )
            ) {
                throw new RuntimeException(
                    'No se pudo escribir el manifest.'
                );
            }

            rewind(
                $stream
            );

            if (
                $disk->put(
                    $sourcePath,
                    $stream
                ) !== true
            ) {
                throw new RuntimeException(
                    'No se pudo almacenar '
                    .'el manifest privado.'
                );
            }
        } finally {
            fclose(
                $stream
            );
        }

        try {
            $this->assertManifestIntegrity(
                $sourcePath,
                $expectedSha256
            );
        } catch (Throwable $exception) {
            try {
                $disk->delete(
                    $sourcePath
                );
            } catch (Throwable) {
            }

            throw $exception;
        }

        return true;
    }

    private function assertManifestIntegrity(
        string $sourcePath,
        string $expectedSha256
    ): void {
        $disk =
            Storage::disk(
                self::SOURCE_DISK
            );

        if (
            ! $disk->exists(
                $sourcePath
            )
        ) {
            throw new RuntimeException(
                'El manifest privado no existe.'
            );
        }

        $stream =
            $disk->readStream(
                $sourcePath
            );

        if (! is_resource($stream)) {
            throw new RuntimeException(
                'No se pudo leer el manifest privado.'
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
                'El manifest privado no coincide '
                .'con su SHA-256.'
            );
        }
    }

    private function deleteManifestIfUnreferenced(
        string $sourcePath
    ): void {
        $referenced =
            DataTransformationBiIntakeBatch::query()
                ->where(
                    'source_disk',
                    self::SOURCE_DISK
                )
                ->where(
                    'source_path',
                    $sourcePath
                )
                ->exists();

        if ($referenced) {
            return;
        }

        try {
            $disk =
                Storage::disk(
                    self::SOURCE_DISK
                );

            if (
                $disk->exists(
                    $sourcePath
                )
            ) {
                $disk->delete(
                    $sourcePath
                );
            }
        } catch (Throwable) {
        }
    }

    /**
     * Same JSON flags used by v1 staging and by D9 manifest hashing.
     *
     * @throws JsonException
     */
    private function json(
        mixed $value
    ): string {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION
        );
    }

    private function assertAdmin(
        User $actor
    ): void {
        if ((string) $actor->role !== 'admin') {
            throw new AuthorizationException(
                'Solo un administrador puede '
                .'materializar staging.'
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
                    'domain_key'
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

            'source_sha256' =>
                (string) $batch->source_sha256,

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
}
