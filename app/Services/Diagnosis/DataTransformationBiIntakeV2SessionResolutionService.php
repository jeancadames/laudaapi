<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiIntakeBatch;
use App\Models\DataTransformationBiIntakeDomainDelivery;
use App\Models\DataTransformationBiIntakeSession;
use App\Models\DataTransformationBiNormalizedRow;
use App\Models\DataTransformationBiProcessingRun;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

final class DataTransformationBiIntakeV2SessionResolutionService
{
    private DataTransformationBiDomainIntakeFileReader
        $domainFileReader;

    private DataTransformationBiDomainIntakeValidationService
        $domainValidationService;

    private DataTransformationBiStandardIntakeRowValidator
        $rowValidator;

    private DataTransformationBiPreparedDatasetReader
        $preparedDatasetReader;

    public function __construct(
        ?DataTransformationBiDomainIntakeFileReader $domainFileReader = null,
        ?DataTransformationBiDomainIntakeValidationService $domainValidationService = null,
        ?DataTransformationBiStandardIntakeRowValidator $rowValidator = null,
        ?DataTransformationBiPreparedDatasetReader $preparedDatasetReader = null
    ) {
        $this->domainFileReader =
            $domainFileReader
            ?? new DataTransformationBiDomainIntakeFileReader();

        $this->domainValidationService =
            $domainValidationService
            ?? new DataTransformationBiDomainIntakeValidationService();

        $this->rowValidator =
            $rowValidator
            ?? new DataTransformationBiStandardIntakeRowValidator();

        $this->preparedDatasetReader =
            $preparedDatasetReader
            ?? new DataTransformationBiPreparedDatasetReader(
                new DataTransformationBiUsableDatasetResolver()
            );
    }


    /**
     * Rebuild the exact READY logical cut for staging without mutating it.
     *
     * D10 uses this instead of duplicating D9 artifact, carry-forward and
     * relational-validation logic.
     *
     * @return array{
     *     manifest:array<string,mixed>,
     *     manifest_sha256:string,
     *     rows_by_domain:array<string,array<int,array<string,mixed>>>,
     *     validation:array<string,mixed>
     * }
     */
    public function prepareReadyPayload(
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

        $this->assertLockedContext(
            $request,
            $freshSession
        );

        if (
            $freshSession->status
            !== DataTransformationBiIntakeSession::STATUS_READY
        ) {
            throw ValidationException::withMessages([
                'session' => [
                    'La sesión debe encontrarse READY '
                    .'antes de materializar staging.',
                ],
            ]);
        }

        $expectedManifestSha =
            (string) (
                $freshSession->resolved_manifest_sha256
                ?? ''
            );

        if (
            preg_match(
                '/^[a-f0-9]{64}$/',
                $expectedManifestSha
            ) !== 1
            || (
                $freshSession
                    ->relational_validation_snapshot
                    ['valid']
                ?? false
            ) !== true
        ) {
            throw new RuntimeException(
                'La sesión READY no conserva un corte '
                .'relacional válido.'
            );
        }

        $captured =
            $this->captureResolvedDecisionSet(
                $request,
                $freshSession
            );

        if (
            ! hash_equals(
                $expectedManifestSha,
                $captured['manifest_sha256']
            )
        ) {
            throw ValidationException::withMessages([
                'session' => [
                    'Las decisiones ya no coinciden '
                    .'con el manifest READY.',
                ],
            ]);
        }

        $rowsByDomain =
            $this->materializeRows(
                $captured['decisions']
            );

        $validation =
            $this->rowValidator
                ->validate(
                    $rowsByDomain
                );

        if (
            ($validation['valid'] ?? false)
            !== true
        ) {
            throw ValidationException::withMessages([
                'session' =>
                    array_values(
                        $validation['errors']
                        ?? [
                            'El dataset dejó de superar '
                            .'la validación relacional.',
                        ]
                    ),
            ]);
        }

        return [
            'manifest' =>
                $captured['manifest'],

            'manifest_sha256' =>
                $captured['manifest_sha256'],

            'rows_by_domain' =>
                $rowsByDomain,

            'validation' =>
                $validation,
        ];
    }

    public function resolve(
        TransformationImplementationRequest $request,
        DataTransformationBiIntakeSession $session,
        User $actor
    ): array {
        $this->assertAdmin(
            $actor
        );

        $captured =
            $this->captureResolvedDecisionSet(
                $request,
                $session
            );

        $rowsByDomain =
            $this->materializeRows(
                $captured['decisions']
            );

        /*
         * Authoritative full-dataset gate.
         *
         * Unlike validateDomain(), validate() executes all canonical
         * cross-domain relationships after intradomain validation.
         */
        $validation =
            $this->rowValidator
                ->validate(
                    $rowsByDomain
                );

        $snapshot = [
            'schema_version' =>
                DataTransformationBiStandardIntakeSchema::VERSION,

            'manifest_sha256' =>
                $captured['manifest_sha256'],

            'valid' =>
                ($validation['valid'] ?? false)
                === true,

            'errors' =>
                array_values(
                    $validation['errors']
                    ?? []
                ),

            'warnings' =>
                array_values(
                    $validation['warnings']
                    ?? []
                ),

            'domains' =>
                $validation['domains']
                ?? [],
        ];

        $persisted =
            DB::transaction(
                function () use (
                    $request,
                    $session,
                    $captured,
                    $snapshot
                ): array {
                    $lockedRequest =
                        TransformationImplementationRequest::query()
                            ->whereKey(
                                $request->getKey()
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $lockedSession =
                        DataTransformationBiIntakeSession::query()
                            ->whereKey(
                                $session->getKey()
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $this->assertLockedContext(
                        $lockedRequest,
                        $lockedSession
                    );

                    $deliveries =
                        DataTransformationBiIntakeDomainDelivery::query()
                            ->where(
                                'data_transformation_bi_intake_session_id',
                                $lockedSession->getKey()
                            )
                            ->lockForUpdate()
                            ->get();

                    $current =
                        $this->decisionSet(
                            $lockedSession,
                            $deliveries
                        );

                    if (
                        ! hash_equals(
                            $captured['manifest_sha256'],
                            $current['manifest_sha256']
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'session' => [
                                'Las decisiones del intake cambiaron '
                                .'durante la validación. '
                                .'Ejecute nuevamente la resolución.',
                            ],
                        ]);
                    }

                    $isValid =
                        ($snapshot['valid'] ?? false)
                        === true;

                    if ($isValid) {
                        $reused =
                            $lockedSession->status
                                === DataTransformationBiIntakeSession
                                    ::STATUS_READY
                            && is_string(
                                $lockedSession
                                    ->resolved_manifest_sha256
                            )
                            && hash_equals(
                                $current['manifest_sha256'],
                                (string) $lockedSession
                                    ->resolved_manifest_sha256
                            )
                            && (
                                $lockedSession
                                    ->relational_validation_snapshot
                                    ['valid']
                                ?? false
                            ) === true;

                        if (! $reused) {
                            $lockedSession->fill([
                                'status' =>
                                    DataTransformationBiIntakeSession
                                        ::STATUS_READY,

                                'resolved_manifest_sha256' =>
                                    $current['manifest_sha256'],

                                'relational_validation_snapshot' =>
                                    $snapshot,

                                'ready_at' =>
                                    now(),

                                'failure_code' =>
                                    null,

                                'failure_message' =>
                                    null,
                            ]);

                            $lockedSession->save();
                        }

                        return [
                            'session' =>
                                $lockedSession,

                            'manifest' =>
                                $current['manifest'],

                            'snapshot' =>
                                $reused
                                    ? $lockedSession
                                        ->relational_validation_snapshot
                                    : $snapshot,

                            'reused' =>
                                $reused,
                        ];
                    }

                    /*
                     * Relational/data-quality invalidity is correctable, not a
                     * terminal session failure. Preserve the exact validation
                     * snapshot and logical cut while leaving the session draft.
                     */
                    $lockedSession->fill([
                        'status' =>
                            DataTransformationBiIntakeSession
                                ::STATUS_DRAFT,

                        'resolved_manifest_sha256' =>
                            $current['manifest_sha256'],

                        'relational_validation_snapshot' =>
                            $snapshot,

                        'ready_at' =>
                            null,

                        'failure_code' =>
                            null,

                        'failure_message' =>
                            null,
                    ]);

                    $lockedSession->save();

                    return [
                        'session' =>
                            $lockedSession,

                        'manifest' =>
                            $current['manifest'],

                        'snapshot' =>
                            $snapshot,

                        'reused' =>
                            false,
                    ];
                }
            );

        return $this->result(
            $persisted['session'],
            $persisted['manifest'],
            $persisted['snapshot'],
            (bool) $persisted['reused']
        );
    }

    /**
     * Capture the exact seven-domain decision cut under lock.
     *
     * Heavy artifact reading and row validation happen AFTER this short
     * transaction. A second hash comparison protects the final commit.
     *
     * @return array{
     *     decisions:array<int,array<string,mixed>>,
     *     manifest:array<string,mixed>,
     *     manifest_sha256:string
     * }
     */
    private function captureResolvedDecisionSet(
        TransformationImplementationRequest $request,
        DataTransformationBiIntakeSession $session
    ): array {
        return DB::transaction(
            function () use (
                $request,
                $session
            ): array {
                $lockedRequest =
                    TransformationImplementationRequest::query()
                        ->whereKey(
                            $request->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $lockedSession =
                    DataTransformationBiIntakeSession::query()
                        ->whereKey(
                            $session->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertLockedContext(
                    $lockedRequest,
                    $lockedSession
                );

                $deliveries =
                    DataTransformationBiIntakeDomainDelivery::query()
                        ->where(
                            'data_transformation_bi_intake_session_id',
                            $lockedSession->getKey()
                        )
                        ->lockForUpdate()
                        ->get();

                return $this->decisionSet(
                    $lockedSession,
                    $deliveries
                );
            }
        );
    }

    /**
     * @return array{
     *     decisions:array<int,array<string,mixed>>,
     *     manifest:array<string,mixed>,
     *     manifest_sha256:string
     * }
     */
    private function decisionSet(
        DataTransformationBiIntakeSession $session,
        Collection $deliveries
    ): array {
        $expectedDomains =
            DataTransformationBiStandardIntakeSchema
                ::domainKeys();

        $byDomain =
            $deliveries
                ->keyBy(
                    'domain_key'
                );

        if (
            $deliveries->count()
            !== count($expectedDomains)
        ) {
            throw ValidationException::withMessages([
                'session' => [
                    'La sesión debe contener exactamente '
                    .'los siete dominios canónicos.',
                ],
            ]);
        }

        $actualDomains =
            $deliveries
                ->pluck(
                    'domain_key'
                )
                ->map(
                    static fn (mixed $domain): string =>
                        (string) $domain
                )
                ->sort()
                ->values()
                ->all();

        $sortedExpected =
            $expectedDomains;

        sort($sortedExpected);

        if ($actualDomains !== $sortedExpected) {
            throw ValidationException::withMessages([
                'session' => [
                    'Los dominios persistidos no coinciden '
                    .'con el esquema canónico.',
                ],
            ]);
        }

        $decisions = [];
        $manifestDomains = [];

        foreach ($expectedDomains as $domain) {
            $delivery =
                $byDomain->get(
                    $domain
                );

            if (
                ! $delivery
                    instanceof DataTransformationBiIntakeDomainDelivery
            ) {
                throw new RuntimeException(
                    "No existe el dominio {$domain}."
                );
            }

            if (
                $delivery->status
                !== DataTransformationBiIntakeDomainDelivery
                    ::STATUS_VALID
            ) {
                throw ValidationException::withMessages([
                    'domains' => [
                        "{$domain}: el dominio todavía no está validado.",
                    ],
                ]);
            }

            $mode =
                (string) (
                    $delivery->delivery_mode
                    ?? ''
                );

            $decision = [
                'delivery_id' =>
                    (int) $delivery->getKey(),

                'domain' =>
                    $domain,

                'mode' =>
                    $mode,

                'status' =>
                    (string) $delivery->status,

                'source_disk' =>
                    $delivery->source_disk,

                'source_path' =>
                    $delivery->source_path,

                'original_filename' =>
                    $delivery->original_filename,

                'source_format' =>
                    $delivery->source_format,

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
            ];

            $manifestDomain = [
                'domain' =>
                    $domain,

                'mode' =>
                    $mode,

                'row_count' =>
                    (int) $delivery->accepted_row_count,
            ];

            if (
                $mode
                === DataTransformationBiIntakeDomainDelivery
                    ::MODE_UPLOADED
            ) {
                $this->assertUploadedDecision(
                    $domain,
                    $delivery
                );

                $manifestDomain['source_format'] =
                    (string) $delivery->source_format;

                $manifestDomain['source_sha256'] =
                    (string) $delivery->source_sha256;
            } elseif (
                $mode
                === DataTransformationBiIntakeDomainDelivery
                    ::MODE_NO_DATA
            ) {
                $this->assertNoDataDecision(
                    $domain,
                    $delivery
                );
            } elseif (
                $mode
                === DataTransformationBiIntakeDomainDelivery
                    ::MODE_CARRY_FORWARD
            ) {
                $this->assertCarryForwardDecision(
                    $domain,
                    $delivery
                );

                $manifestDomain[
                    'processing_run_id'
                ] =
                    (int) $delivery
                        ->carry_forward_processing_run_id;

                $manifestDomain[
                    'intake_batch_id'
                ] =
                    (int) $delivery
                        ->carry_forward_intake_batch_id;
            } else {
                throw ValidationException::withMessages([
                    'domains' => [
                        "{$domain}: modo de entrega no resuelto.",
                    ],
                ]);
            }

            $decisions[] =
                $decision;

            $manifestDomains[] =
                $manifestDomain;
        }

        $manifest = [
            'schema_version' =>
                (int) $session->schema_version,

            'company_id' =>
                (int) $session->company_id,

            'transformation_implementation_request_id' =>
                (int) $session
                    ->transformation_implementation_request_id,

            'domains' =>
                $manifestDomains,
        ];

        return [
            'decisions' =>
                $decisions,

            'manifest' =>
                $manifest,

            'manifest_sha256' =>
                $this->hashManifest(
                    $manifest
                ),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $decisions
     * @return array<string,array<int,array<string,mixed>>>
     */
    private function materializeRows(
        array $decisions
    ): array {
        $rowsByDomain = [];

        foreach ($decisions as $decision) {
            $domain =
                (string) $decision['domain'];

            $mode =
                (string) $decision['mode'];

            $rowsByDomain[$domain] =
                match ($mode) {
                    DataTransformationBiIntakeDomainDelivery
                        ::MODE_UPLOADED =>
                        $this->uploadedRows(
                            $decision
                        ),

                    DataTransformationBiIntakeDomainDelivery
                        ::MODE_NO_DATA =>
                        [],

                    DataTransformationBiIntakeDomainDelivery
                        ::MODE_CARRY_FORWARD =>
                        $this->carryForwardRows(
                            $decision
                        ),

                    default =>
                        throw new RuntimeException(
                            "Modo no soportado: {$mode}."
                        ),
                };
        }

        return $rowsByDomain;
    }

    /**
     * @param array<string,mixed> $decision
     * @return array<int,array<string,mixed>>
     */
    private function uploadedRows(
        array $decision
    ): array {
        $domain =
            (string) $decision['domain'];

        $diskName =
            (string) $decision['source_disk'];

        $sourcePath =
            (string) $decision['source_path'];

        $originalFilename =
            (string) $decision['original_filename'];

        $expectedSha =
            (string) $decision['source_sha256'];

        $disk =
            Storage::disk(
                $diskName
            );

        if (! $disk->exists($sourcePath)) {
            throw new RuntimeException(
                "{$domain}: el artefacto privado no existe."
            );
        }

        $stream =
            $disk->readStream(
                $sourcePath
            );

        if (! is_resource($stream)) {
            throw new RuntimeException(
                "{$domain}: no se pudo abrir el artefacto privado."
            );
        }

        $temporaryPath =
            tempnam(
                sys_get_temp_dir(),
                'dtbi-v2-resolve-'
            );

        if (
            $temporaryPath === false
            || $temporaryPath === ''
        ) {
            fclose($stream);

            throw new RuntimeException(
                "{$domain}: no se pudo crear archivo temporal."
            );
        }

        $output =
            fopen(
                $temporaryPath,
                'wb'
            );

        if ($output === false) {
            fclose($stream);
            @unlink($temporaryPath);

            throw new RuntimeException(
                "{$domain}: no se pudo abrir archivo temporal."
            );
        }

        $hash =
            hash_init(
                'sha256'
            );

        try {
            while (! feof($stream)) {
                $chunk =
                    fread(
                        $stream,
                        1024 * 1024
                    );

                if ($chunk === false) {
                    throw new RuntimeException(
                        "{$domain}: error leyendo artefacto privado."
                    );
                }

                if ($chunk === '') {
                    continue;
                }

                hash_update(
                    $hash,
                    $chunk
                );

                $this->writeAll(
                    $output,
                    $chunk
                );
            }
        } finally {
            fclose($stream);
            fclose($output);
        }

        try {
            $actualSha =
                hash_final(
                    $hash
                );

            if (
                ! hash_equals(
                    $expectedSha,
                    $actualSha
                )
            ) {
                throw new RuntimeException(
                    "{$domain}: SHA-256 del artefacto "
                    .'privado no coincide.'
                );
            }

            /*
             * Defense in depth: rerun D5 structural + intradomain validation
             * against the exact bytes being resolved.
             */
            $validation =
                $this->domainValidationService
                    ->validate(
                        $domain,
                        $temporaryPath,
                        $originalFilename
                    );

            if (
                ($validation['valid'] ?? false)
                !== true
            ) {
                throw new RuntimeException(
                    "{$domain}: la fuente persistida "
                    .'ya no supera su validación individual.'
                );
            }

            $validatedCount =
                (int) (
                    $validation['content']
                        ['domain_report']
                        ['row_count']
                    ?? -1
                );

            if (
                $validatedCount
                !== (int) $decision['accepted_row_count']
            ) {
                throw new RuntimeException(
                    "{$domain}: el conteo validado no coincide "
                    .'con la decisión persistida.'
                );
            }

            $payload =
                $this->domainFileReader
                    ->read(
                        $domain,
                        $temporaryPath,
                        $originalFilename
                    );

            return array_values(
                $payload['rows']
                ?? []
            );
        } finally {
            @unlink(
                $temporaryPath
            );
        }
    }

    /**
     * @param array<string,mixed> $decision
     * @return array<int,array<string,mixed>>
     */
    private function carryForwardRows(
        array $decision
    ): array {
        $domain =
            (string) $decision['domain'];

        $runId =
            (int) (
                $decision[
                    'carry_forward_processing_run_id'
                ]
                ?? 0
            );

        $batchId =
            (int) (
                $decision[
                    'carry_forward_intake_batch_id'
                ]
                ?? 0
            );

        $dataset =
            $this->pinnedDataset(
                $runId,
                $batchId
            );

        $counts =
            $this->preparedDatasetReader
                ->domainCountsInDataset(
                    (int) $dataset['company_id'],
                    $dataset
                );

        $expectedDomainRows =
            (int) (
                $counts['domains'][$domain]
                ?? 0
            );

        if (
            $expectedDomainRows
            !== (int) $decision['accepted_row_count']
        ) {
            throw new RuntimeException(
                "{$domain}: el conteo del carry-forward "
                .'no coincide con la decisión persistida.'
            );
        }

        $rows = [];

        foreach (
            $this->preparedDatasetReader
                ->iterateDomainInDataset(
                    (int) $dataset['company_id'],
                    $dataset,
                    $domain
                )
            as $row
        ) {
            $payload =
                $row['payload']
                ?? null;

            if (! is_array($payload)) {
                throw new RuntimeException(
                    "{$domain}: fila normalizada inválida."
                );
            }

            $rows[] =
                $payload;
        }

        if (
            count($rows)
            !== $expectedDomainRows
        ) {
            throw new RuntimeException(
                "{$domain}: la iteración carry-forward "
                .'no coincide con el conteo fijado.'
            );
        }

        return $rows;
    }

    /**
     * Build an already-pinned descriptor without re-resolving P13.
     *
     * @return array<string,mixed>
     */
    private function pinnedDataset(
        int $runId,
        int $batchId
    ): array {
        $run =
            DataTransformationBiProcessingRun::query()
                ->whereKey(
                    $runId
                )
                ->where(
                    'data_transformation_bi_intake_batch_id',
                    $batchId
                )
                ->where(
                    'status',
                    DataTransformationBiProcessingRun
                        ::STATUS_COMPLETED
                )
                ->where(
                    'blocking_issue_count',
                    0
                )
                ->first();

        if ($run === null) {
            throw new RuntimeException(
                'El processing run carry-forward dejó de ser usable.'
            );
        }

        $batch =
            DataTransformationBiIntakeBatch::query()
                ->whereKey(
                    $batchId
                )
                ->where(
                    'company_id',
                    $run->company_id
                )
                ->where(
                    'transformation_implementation_request_id',
                    $run->transformation_implementation_request_id
                )
                ->where(
                    'status',
                    DataTransformationBiIntakeBatch
                        ::STATUS_COMPLETED
                )
                ->first();

        if ($batch === null) {
            throw new RuntimeException(
                'El batch carry-forward dejó de ser usable.'
            );
        }

        if (
            (int) $batch->schema_version
            !== (int) $run->schema_version
        ) {
            throw new RuntimeException(
                'El run y batch carry-forward '
                .'no comparten schema_version.'
            );
        }

        $persistedRows =
            DataTransformationBiNormalizedRow::query()
                ->where(
                    'company_id',
                    $run->company_id
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
            $persistedRows
            !== max(
                0,
                (int) $run->normalized_row_count
            )
        ) {
            throw new RuntimeException(
                'El dataset carry-forward dejó '
                .'de ser internamente consistente.'
            );
        }

        return [
            'company_id' =>
                (int) $run->company_id,

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
    }

    private function assertUploadedDecision(
        string $domain,
        DataTransformationBiIntakeDomainDelivery $delivery
    ): void {
        if (
            ! is_string($delivery->source_disk)
            || trim($delivery->source_disk) === ''
            || ! is_string($delivery->source_path)
            || trim($delivery->source_path) === ''
            || ! is_string($delivery->original_filename)
            || trim($delivery->original_filename) === ''
            || ! in_array(
                $delivery->source_format,
                [
                    DataTransformationBiIntakeDomainDelivery
                        ::FORMAT_XLSX,

                    DataTransformationBiIntakeDomainDelivery
                        ::FORMAT_CSV,
                ],
                true
            )
            || ! is_string($delivery->source_sha256)
            || preg_match(
                '/^[a-f0-9]{64}$/',
                $delivery->source_sha256
            ) !== 1
            || (int) $delivery->source_row_count
                !== (int) $delivery->accepted_row_count
        ) {
            throw new RuntimeException(
                "{$domain}: decisión uploaded inconsistente."
            );
        }
    }

    private function assertNoDataDecision(
        string $domain,
        DataTransformationBiIntakeDomainDelivery $delivery
    ): void {
        if (
            (int) $delivery->source_row_count !== 0
            || (int) $delivery->accepted_row_count !== 0
            || $delivery->source_disk !== null
            || $delivery->source_path !== null
            || $delivery->source_sha256 !== null
            || $delivery->carry_forward_processing_run_id !== null
            || $delivery->carry_forward_intake_batch_id !== null
        ) {
            throw new RuntimeException(
                "{$domain}: decisión no_data inconsistente."
            );
        }
    }

    private function assertCarryForwardDecision(
        string $domain,
        DataTransformationBiIntakeDomainDelivery $delivery
    ): void {
        if (
            (int) (
                $delivery->carry_forward_processing_run_id
                ?? 0
            ) <= 0
            || (int) (
                $delivery->carry_forward_intake_batch_id
                ?? 0
            ) <= 0
            || $delivery->source_disk !== null
            || $delivery->source_path !== null
            || $delivery->source_sha256 !== null
        ) {
            throw new RuntimeException(
                "{$domain}: decisión carry_forward inconsistente."
            );
        }
    }

    private function assertLockedContext(
        TransformationImplementationRequest $request,
        DataTransformationBiIntakeSession $session
    ): void {
        if (
            ! $request->exists
            || (int) $request->getKey() <= 0
            || (int) $request->company_id <= 0
        ) {
            throw new InvalidArgumentException(
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

        if (
            ! $session->exists
            || (int) $session->getKey() <= 0
        ) {
            throw new InvalidArgumentException(
                'La sesión no está persistida.'
            );
        }

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
            (int) $session->schema_version
            !== DataTransformationBiStandardIntakeSchema
                ::VERSION
        ) {
            throw ValidationException::withMessages([
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
                    'La sesión no admite resolución '
                    .'en su estado actual.',
                ],
            ]);
        }
    }

    private function assertAdmin(
        User $actor
    ): void {
        if ((string) $actor->role !== 'admin') {
            throw new AuthorizationException(
                'Solo un administrador puede resolver el intake.'
            );
        }
    }

    /**
     * Deterministic JSON because both top-level and domain field order are
     * explicitly constructed in canonical schema order.
     *
     * @throws JsonException
     */
    private function hashManifest(
        array $manifest
    ): string {
        return hash(
            'sha256',
            json_encode(
                $manifest,
                JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
            )
        );
    }

    private function writeAll(
        mixed $stream,
        string $contents
    ): void {
        $length =
            strlen(
                $contents
            );

        $offset =
            0;

        while ($offset < $length) {
            $written =
                fwrite(
                    $stream,
                    substr(
                        $contents,
                        $offset
                    )
                );

            if (
                $written === false
                || $written <= 0
            ) {
                throw new RuntimeException(
                    'No se pudo materializar '
                    .'el artefacto privado.'
                );
            }

            $offset +=
                $written;
        }
    }

    /**
     * @param array<string,mixed> $manifest
     * @param array<string,mixed> $snapshot
     */
    private function result(
        DataTransformationBiIntakeSession $session,
        array $manifest,
        array $snapshot,
        bool $reused
    ): array {
        return [
            'reused' =>
                $reused,

            'session_id' =>
                (int) $session->getKey(),

            'company_id' =>
                (int) $session->company_id,

            'request_id' =>
                (int) $session
                    ->transformation_implementation_request_id,

            'schema_version' =>
                (int) $session->schema_version,

            'status' =>
                (string) $session->status,

            'manifest_sha256' =>
                (string) $session
                    ->resolved_manifest_sha256,

            'valid' =>
                ($snapshot['valid'] ?? false)
                === true,

            'errors' =>
                $snapshot['errors']
                ?? [],

            'warnings' =>
                $snapshot['warnings']
                ?? [],

            'domains' =>
                $snapshot['domains']
                ?? [],

            'manifest' =>
                $manifest,

            'ready_at' =>
                $session->ready_at
                    ?->toISOString(),
        ];
    }
}
