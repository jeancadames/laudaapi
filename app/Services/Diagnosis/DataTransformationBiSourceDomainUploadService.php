<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiIntakeDomainDelivery;
use App\Models\DataTransformationBiIntakeSession;
use App\Models\DataTransformationBiSourceDomainFile;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class DataTransformationBiSourceDomainUploadService
{
    public const SOURCE_DISK = 'private';

    public const MAX_UPLOAD_KILOBYTES = 32768;

    private DataTransformationBiSourceFileReader $reader;

    public function __construct(
        ?DataTransformationBiSourceFileReader $reader = null
    ) {
        $this->reader =
            $reader
            ?? app(DataTransformationBiSourceFileReader::class);
    }

    /**
     * Receive one client-native CSV/XLSX for a known domain.
     *
     * This operation deliberately does NOT:
     * - validate canonical headers;
     * - mark the canonical delivery as uploaded/valid;
     * - create staging rows;
     * - create raw-row database copies;
     * - profile business values;
     * - create field mappings.
     *
     * @return array<string,mixed>
     */
    public function persistSourceDomain(
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

        $this->assertEditableSession(
            $session
        );

        DataTransformationBiSourceDomainRegistry
            ::assertSupported(
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
            || trim($temporaryPath) === ''
            || ! is_file($temporaryPath)
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
            (string) (
                $inspection['format']
                ?? ''
            );

        if (
            ! in_array(
                $format,
                [
                    DataTransformationBiSourceDomainFile
                        ::FORMAT_CSV,

                    DataTransformationBiSourceDomainFile
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

        $sourceSha256 =
            (string) (
                $inspection['source_sha256']
                ?? ''
            );

        if (
            strlen($sourceSha256) !== 64
            || preg_match(
                '/^[a-f0-9]{64}$/i',
                $sourceSha256
            ) !== 1
        ) {
            throw new RuntimeException(
                'El reader no devolvió un SHA-256 válido.'
            );
        }

        $sourceSize =
            $inspection['source_size_bytes']
            ?? null;

        if (
            ! is_int($sourceSize)
            || $sourceSize < 0
        ) {
            throw new RuntimeException(
                'El reader no devolvió un tamaño de archivo válido.'
            );
        }

        $delivery =
            DataTransformationBiIntakeDomainDelivery::query()
                ->where(
                    'data_transformation_bi_intake_session_id',
                    (int) $session->getKey()
                )
                ->where(
                    'company_id',
                    (int) $implementationRequest->company_id
                )
                ->where(
                    'domain_key',
                    $domain
                )
                ->first();

        if ($delivery === null) {
            throw ValidationException::withMessages([
                'domain' => [
                    "No existe una entrega preparada para el dominio {$domain}.",
                ],
            ]);
        }

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

        $oldArtifact = null;

        try {
            /** @var DataTransformationBiSourceDomainFile $sourceFile */
            $sourceFile =
                DB::transaction(
                    function () use (
                        $implementationRequest,
                        $delivery,
                        $domain,
                        $actor,
                        $file,
                        $inspection,
                        $format,
                        $sourceSize,
                        $sourceSha256,
                        $sourcePath,
                        $disk,
                        &$oldArtifact
                    ): DataTransformationBiSourceDomainFile {
                        /** @var DataTransformationBiSourceDomainFile|null $existing */
                        $existing =
                            DataTransformationBiSourceDomainFile::query()
                                ->where(
                                    'data_transformation_bi_intake_domain_delivery_id',
                                    (int) $delivery->getKey()
                                )
                                ->lockForUpdate()
                                ->first();

                        if (
                            $existing !== null
                            && (string) $existing->source_sha256
                                === $sourceSha256
                            && (string) $existing->source_path
                                === $sourcePath
                            && $disk->exists(
                                $sourcePath
                            )
                        ) {
                            $existing->forceFill([
                                'original_filename' =>
                                    (string) (
                                        $inspection['original_filename']
                                        ?? $file->getClientOriginalName()
                                    ),

                                'source_mime_type' =>
                                    $file->getMimeType(),

                                'uploaded_by_user_id' =>
                                    (int) $actor->getKey(),

                                'uploaded_at' =>
                                    now(),

                                'failure_code' =>
                                    null,

                                'failure_message' =>
                                    null,
                            ])->save();

                            return $existing->fresh();
                        }

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
                            $oldArtifact =
                                (string) $existing->source_path;
                        }

                        $sourceFile =
                            $existing
                            ?? new DataTransformationBiSourceDomainFile();

                        if (
                            $existing !== null
                            && (string) $existing->source_sha256
                                !== $sourceSha256
                        ) {
                            $existing
                                ->fieldMappings()
                                ->delete();
                        }

                        $sourceFile->forceFill([
                            'data_transformation_bi_intake_domain_delivery_id' =>
                                (int) $delivery->getKey(),

                            'company_id' =>
                                (int) $implementationRequest->company_id,

                            'domain_key' =>
                                $domain,

                            'status' =>
                                DataTransformationBiSourceDomainFile
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

                            'profiling_snapshot' =>
                                null,

                            'source_row_count' =>
                                $this->initialSourceRowCount(
                                    $inspection
                                ),

                            'uploaded_by_user_id' =>
                                (int) $actor->getKey(),

                            'uploaded_at' =>
                                now(),

                            'profiled_at' =>
                                null,

                            'mapping_ready_at' =>
                                null,

                            'transformed_at' =>
                                null,

                            'failure_code' =>
                                null,

                            'failure_message' =>
                                null,
                        ]);

                        $sourceFile->save();

                        return $sourceFile->fresh();
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
            is_string($oldArtifact)
            && $oldArtifact !== ''
            && $oldArtifact !== $sourcePath
            && $disk->exists(
                $oldArtifact
            )
        ) {
            $disk->delete(
                $oldArtifact
            );
        }

        return $this->payload(
            $sourceFile
        );
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
            ! is_array($sheets)
            || count($sheets) !== 1
        ) {
            /*
             * Para XLSX con varias hojas todavía no sabemos cuál será
             * seleccionada para mapping/profiling.
             */
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

        return is_int($rowCount)
            && $rowCount >= 0
                ? $rowCount
                : 0;
    }

    private function sourcePath(
        int $companyId,
        int $implementationRequestId,
        int $sessionId,
        string $domain,
        string $sha256,
        string $format
    ): string {
        return implode(
            '/',
            [
                'data-transformation-bi',
                'source-native',
                'companies',
                (string) $companyId,
                'implementation-requests',
                (string) $implementationRequestId,
                'sessions',
                (string) $sessionId,
                'domains',
                $domain,
                "{$sha256}.{$format}",
            ]
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function payload(
        DataTransformationBiSourceDomainFile $sourceFile
    ): array {
        return [
            'id' =>
                (int) $sourceFile->getKey(),

            'domain_key' =>
                (string) $sourceFile->domain_key,

            'status' =>
                (string) $sourceFile->status,

            'original_filename' =>
                (string) $sourceFile->original_filename,

            'source_format' =>
                (string) $sourceFile->source_format,

            'source_size_bytes' =>
                (int) $sourceFile->source_size_bytes,

            'source_sha256' =>
                (string) $sourceFile->source_sha256,

            'reader_configuration' =>
                $sourceFile->reader_configuration,

            'source_structure_snapshot' =>
                $sourceFile->source_structure_snapshot,

            'source_row_count' =>
                (int) $sourceFile->source_row_count,

            'uploaded_at' =>
                $sourceFile->uploaded_at?->toISOString(),
        ];
    }

    private function assertRequestAndSession(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session
    ): void {
        if (
            (int) $session->company_id
                !== (int) $implementationRequest->company_id
            || (int) $session
                ->transformation_implementation_request_id
                !== (int) $implementationRequest->getKey()
        ) {
            throw ValidationException::withMessages([
                'intake_session' => [
                    'La sesión no pertenece a esta solicitud de implementación.',
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
                    'La sesión ya no admite cambios de dominio.',
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
