<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiEvaluation;
use App\Models\DataTransformationBiEvaluationFinding;
use App\Models\DataTransformationBiEvaluationImplementationChallenge;
use App\Models\DataTransformationBiIntakeSession;
use App\Models\DataTransformationBiSourceAsset;
use App\Models\User;
use App\Services\AuditService;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DataTransformationBiEvaluationService
{
    private const LEGACY_EVIDENCE_SCHEMA_VERSION = 1;

    private const EVIDENCE_SCHEMA_VERSION = 2;

    /**
     * Create the session-level evaluation draft from the current
     * aggregate profiling evidence.
     *
     * Calling this method again while the evaluation is still a
     * draft refreshes the pinned evidence only when it changed.
     */
    public function createOrRefreshDraft(
        DataTransformationBiIntakeSession $session,
        User $actor
    ): DataTransformationBiEvaluation {
        return DB::transaction(
            function () use (
                $session,
                $actor
            ): DataTransformationBiEvaluation {
                $lockedSession =
                    DataTransformationBiIntakeSession::query()
                        ->whereKey(
                            $session->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertSubmitted(
                    $lockedSession
                );

                $current =
                    DataTransformationBiEvaluation::query()
                        ->where(
                            'data_transformation_bi_intake_session_id',
                            $lockedSession->getKey()
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    $current
                    && ! $current->isDraft()
                ) {
                    throw ValidationException::withMessages([
                        'evaluation' => [
                            'La evaluación ya salió del estado borrador y su evidencia no puede reemplazarse.',
                        ],
                    ]);
                }

                $evidence =
                    $this->captureEvidence(
                        $lockedSession
                    );

                if (
                    $current
                    && hash_equals(
                        (string) $current->evidence_sha256,
                        $evidence['sha256']
                    )
                ) {
                    return $current;
                }

                if (! $current) {
                    $evaluation =
                        DataTransformationBiEvaluation::create([
                            'data_transformation_bi_intake_session_id' =>
                                $lockedSession->getKey(),

                            'company_id' =>
                                (int) $lockedSession->company_id,

                            'transformation_implementation_request_id' =>
                                (int) $lockedSession
                                    ->transformation_implementation_request_id,

                            'status' =>
                                DataTransformationBiEvaluation
                                    ::STATUS_DRAFT,

                            'submission_manifest_sha256' =>
                                (string) $lockedSession
                                    ->submitted_manifest_sha256,

                            'evidence_version' =>
                                1,

                            'evidence_sha256' =>
                                $evidence['sha256'],

                            'evidence_snapshot' =>
                                $evidence['snapshot'],

                            'evidence_captured_at' =>
                                now(),

                            'created_by_user_id' =>
                                $actor->getKey(),
                        ]);

                    $this->audit(
                        'data_transformation_bi_evaluation_draft_created',
                        $evaluation,
                        $lockedSession,
                        $actor,
                        [
                            'evidence_version' =>
                                1,
                        ]
                    );

                    return $evaluation;
                }

                $nextEvidenceVersion =
                    ((int) $current->evidence_version)
                    + 1;

                $current->forceFill([
                    'submission_manifest_sha256' =>
                        (string) $lockedSession
                            ->submitted_manifest_sha256,

                    'evidence_version' =>
                        $nextEvidenceVersion,

                    'evidence_sha256' =>
                        $evidence['sha256'],

                    'evidence_snapshot' =>
                        $evidence['snapshot'],

                    'evidence_captured_at' =>
                        now(),

                    /*
                     * A refreshed evidence revision invalidates any
                     * diagnostic-analysis result that could have been
                     * associated with the prior evidence revision.
                     */
                    'diagnostic_analysis_schema_version' =>
                        null,

                    'diagnostic_analysis_evidence_version' =>
                        null,

                    'diagnostic_analysis_sha256' =>
                        null,

                    'diagnostic_analysis_snapshot' =>
                        null,

                    'diagnostic_analysis_generated_at' =>
                        null,
                ])->save();

                $this->audit(
                    'data_transformation_bi_evaluation_evidence_refreshed',
                    $current,
                    $lockedSession,
                    $actor,
                    [
                        'evidence_version' =>
                            $nextEvidenceVersion,
                    ]
                );

                return $current->fresh();
            },
            3
        );
    }

    /**
     * Freeze the current draft evidence for professional review.
     *
     * If profiling changed after the draft evidence was captured,
     * the draft must be explicitly refreshed first.
     */
    public function markReadyForReview(
        DataTransformationBiIntakeSession $session,
        User $actor
    ): DataTransformationBiEvaluation {
        return DB::transaction(
            function () use (
                $session,
                $actor
            ): DataTransformationBiEvaluation {
                $lockedSession =
                    DataTransformationBiIntakeSession::query()
                        ->whereKey(
                            $session->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertSubmitted(
                    $lockedSession
                );

                $evaluation =
                    $this->lockedEvaluation(
                        $lockedSession
                    );

                if (
                    $evaluation->isReadyForReview()
                ) {
                    $this->assertDiagnosticAnalysisFrozen(
                        $evaluation
                    );

                    return $evaluation;
                }

                if (! $evaluation->isDraft()) {
                    throw ValidationException::withMessages([
                        'evaluation' => [
                            'Solo una evaluación en borrador puede enviarse a revisión.',
                        ],
                    ]);
                }

                $this->assertEvidenceStillCurrent(
                    $evaluation,
                    $lockedSession
                );

                $this->assertReviewableFindings(
                    $evaluation
                );

                $this->assertReviewableImplementationChallenges(
                    $evaluation
                );

                $diagnosticAnalysis =
                    $this->captureDiagnosticAnalysis(
                        $evaluation
                    );

                $evaluation->forceFill([
                    'diagnostic_analysis_schema_version' =>
                        $diagnosticAnalysis[
                            'schema_version'
                        ],

                    'diagnostic_analysis_evidence_version' =>
                        (int) $evaluation
                            ->evidence_version,

                    'diagnostic_analysis_sha256' =>
                        $diagnosticAnalysis[
                            'sha256'
                        ],

                    'diagnostic_analysis_snapshot' =>
                        $diagnosticAnalysis[
                            'snapshot'
                        ],

                    'diagnostic_analysis_generated_at' =>
                        now(),

                    'status' =>
                        DataTransformationBiEvaluation
                            ::STATUS_READY_FOR_REVIEW,

                    'ready_for_review_by_user_id' =>
                        $actor->getKey(),

                    'ready_for_review_at' =>
                        now(),
                ])->save();

                $this->audit(
                    'data_transformation_bi_evaluation_ready_for_review',
                    $evaluation,
                    $lockedSession,
                    $actor
                );

                return $evaluation->fresh();
            },
            3
        );
    }

    /**
     * Publish the reviewed diagnostic evaluation.
     *
     * Publication never changes the submitted intake session,
     * never starts ETL and never materializes normalized data.
     */
    public function publish(
        DataTransformationBiIntakeSession $session,
        User $actor
    ): DataTransformationBiEvaluation {
        return DB::transaction(
            function () use (
                $session,
                $actor
            ): DataTransformationBiEvaluation {
                $lockedSession =
                    DataTransformationBiIntakeSession::query()
                        ->whereKey(
                            $session->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertSubmitted(
                    $lockedSession
                );

                $evaluation =
                    $this->lockedEvaluation(
                        $lockedSession
                    );

                if ($evaluation->isPublished()) {
                    return $evaluation;
                }

                if (
                    ! $evaluation->isReadyForReview()
                ) {
                    throw ValidationException::withMessages([
                        'evaluation' => [
                            'La evaluación debe estar lista para revisión antes de publicarse.',
                        ],
                    ]);
                }

                $this->assertEvidenceStillCurrent(
                    $evaluation,
                    $lockedSession
                );

                $this->assertReviewableFindings(
                    $evaluation
                );

                $this->assertReviewableImplementationChallenges(
                    $evaluation
                );

                /*
                 * Publication consumes the analysis frozen when the
                 * evaluation entered review. It must never recompute
                 * analytical conclusions at publication time.
                 */
                $this->assertDiagnosticAnalysisFrozen(
                    $evaluation
                );

                $evaluation->forceFill([
                    'status' =>
                        DataTransformationBiEvaluation
                            ::STATUS_PUBLISHED,

                    'published_by_user_id' =>
                        $actor->getKey(),

                    'published_at' =>
                        now(),
                ])->save();

                $this->audit(
                    'data_transformation_bi_evaluation_published',
                    $evaluation,
                    $lockedSession,
                    $actor
                );

                return $evaluation->fresh();
            },
            3
        );
    }

    private function assertSubmitted(
        DataTransformationBiIntakeSession $session
    ): void {
        if (
            $session->status
            !== DataTransformationBiIntakeSession
                ::STATUS_SUBMITTED_FOR_EVALUATION
            || empty(
                $session->submitted_manifest_sha256
            )
        ) {
            throw ValidationException::withMessages([
                'evaluation' => [
                    'La entrega debe estar enviada y congelada antes de iniciar su evaluación diagnóstica.',
                ],
            ]);
        }
    }

    private function lockedEvaluation(
        DataTransformationBiIntakeSession $session
    ): DataTransformationBiEvaluation {
        $evaluation =
            DataTransformationBiEvaluation::query()
                ->where(
                    'data_transformation_bi_intake_session_id',
                    $session->getKey()
                )
                ->lockForUpdate()
                ->first();

        if (! $evaluation) {
            throw ValidationException::withMessages([
                'evaluation' => [
                    'Todavía no existe un borrador de evaluación para esta entrega.',
                ],
            ]);
        }

        return $evaluation;
    }

    private function assertReviewableFindings(
        DataTransformationBiEvaluation $evaluation
    ): void {
        $findings =
            DataTransformationBiEvaluationFinding::query()
                ->where(
                    'data_transformation_bi_evaluation_id',
                    $evaluation->getKey()
                )
                ->lockForUpdate()
                ->get([
                    'id',
                    'evidence_version',
                ]);

        if ($findings->isEmpty()) {
            throw ValidationException::withMessages([
                'evaluation' => [
                    'Registra al menos un hallazgo profesional antes de enviar la evaluación a revisión.',
                ],
            ]);
        }

        $currentEvidenceVersion =
            (int) $evaluation
                ->evidence_version;

        $hasStaleFindings =
            $findings->contains(
                fn (
                    DataTransformationBiEvaluationFinding $finding
                ): bool =>
                    (int) $finding->evidence_version
                    !== $currentEvidenceVersion
            );

        if ($hasStaleFindings) {
            throw ValidationException::withMessages([
                'evaluation' => [
                    'Hay hallazgos que no han sido confirmados contra la versión actual de la evidencia diagnóstica.',
                ],
            ]);
        }
    }

    /**
     * Implementation challenges are optional.
     *
     * If professional implementation implications were authored,
     * every challenge must already be confirmed against the exact
     * evidence version entering review.
     *
     * This gate is validation-only:
     * it never creates, updates or reconfirms challenges.
     */
    private function assertReviewableImplementationChallenges(
        DataTransformationBiEvaluation $evaluation
    ): void {
        $challenges =
            DataTransformationBiEvaluationImplementationChallenge
                ::query()
                ->where(
                    'data_transformation_bi_evaluation_id',
                    $evaluation->getKey()
                )
                ->lockForUpdate()
                ->get([
                    'id',
                    'evidence_version',
                ]);

        /*
         * Zero challenges is explicitly valid.
         *
         * Challenges are a professional implementation layer,
         * not a mandatory fourth finding type.
         */
        if ($challenges->isEmpty()) {
            return;
        }

        $currentEvidenceVersion =
            (int) $evaluation
                ->evidence_version;

        $hasStaleChallenges =
            $challenges->contains(
                fn (
                    DataTransformationBiEvaluationImplementationChallenge $challenge
                ): bool =>
                    (int) $challenge->evidence_version
                    !== $currentEvidenceVersion
            );

        if ($hasStaleChallenges) {
            throw ValidationException::withMessages([
                'evaluation' => [
                    'Hay retos de implementación que no han sido confirmados contra la versión actual de la evidencia diagnóstica.',
                ],
            ]);
        }
    }


    private function assertEvidenceStillCurrent(
        DataTransformationBiEvaluation $evaluation,
        DataTransformationBiIntakeSession $session
    ): void {
        $current =
            $this->captureEvidence(
                $session,
                $this->evidenceSchemaVersion(
                    $evaluation
                )
            );

        if (
            ! hash_equals(
                (string) $evaluation->evidence_sha256,
                $current['sha256']
            )
        ) {
            throw ValidationException::withMessages([
                'evaluation' => [
                    'El profiling cambió después de capturar la evidencia de esta evaluación. Actualiza primero el borrador diagnóstico.',
                ],
            ]);
        }

        if (
            ! hash_equals(
                (string) $evaluation
                    ->submission_manifest_sha256,
                (string) $session
                    ->submitted_manifest_sha256
            )
        ) {
            throw ValidationException::withMessages([
                'evaluation' => [
                    'El manifest de la entrega no coincide con la evidencia fijada para esta evaluación.',
                ],
            ]);
        }
    }

    /**
     * Build the deterministic diagnostic-analysis result exclusively
     * from the evaluation's pinned evidence snapshot.
     *
     * No live source query is allowed here.
     *
     * @return array{
     *     schema_version:int,
     *     snapshot:array<string,mixed>,
     *     sha256:string
     * }
     */
    private function captureDiagnosticAnalysis(
        DataTransformationBiEvaluation $evaluation
    ): array {
        $evidenceSnapshot =
            is_array(
                $evaluation->evidence_snapshot
            )
                ? $evaluation->evidence_snapshot
                : [];

        $snapshot =
            DataTransformationBiDiagnosticAnalysisReadModel
                ::fromEvidence(
                    $evidenceSnapshot,
                    (int) $evaluation
                        ->evidence_version,
                    (string) $evaluation
                        ->evidence_sha256
                );

        if (
            ($snapshot['available'] ?? false)
            !== true
        ) {
            throw ValidationException::withMessages([
                'evaluation' => [
                    'La evidencia fijada para esta evaluación no soporta todavía el contrato de análisis diagnóstico vigente. Actualiza primero el borrador diagnóstico.',
                ],
            ]);
        }

        $schemaVersion =
            (int) (
                $snapshot['schema_version']
                ?? 0
            );

        if ($schemaVersion <= 0) {
            throw ValidationException::withMessages([
                'evaluation' => [
                    'El análisis diagnóstico generado no contiene una versión de contrato válida.',
                ],
            ]);
        }

        return [
            'schema_version' =>
                $schemaVersion,

            'snapshot' =>
                $snapshot,

            /*
             * Analysis hashes use canonical semantic JSON:
             * associative-object keys are ordered recursively while
             * list order remains meaningful and preserved.
             */
            'sha256' =>
                $this->diagnosticAnalysisSha256(
                    $snapshot
                ),
        ];
    }

    private function assertDiagnosticAnalysisFrozen(
        DataTransformationBiEvaluation $evaluation
    ): void {
        $snapshot =
            is_array(
                $evaluation
                    ->diagnostic_analysis_snapshot
            )
                ? $evaluation
                    ->diagnostic_analysis_snapshot
                : null;

        $schemaVersion =
            (int) (
                $evaluation
                    ->diagnostic_analysis_schema_version
                ?? 0
            );

        $analysisEvidenceVersion =
            (int) (
                $evaluation
                    ->diagnostic_analysis_evidence_version
                ?? 0
            );

        $sha256 =
            strtolower(
                trim(
                    (string) (
                        $evaluation
                            ->diagnostic_analysis_sha256
                        ?? ''
                    )
                )
            );

        if (
            ! is_array($snapshot)
            || $schemaVersion <= 0
            || $analysisEvidenceVersion <= 0
            || preg_match(
                '/^[a-f0-9]{64}$/',
                $sha256
            ) !== 1
            || $evaluation
                ->diagnostic_analysis_generated_at
                === null
        ) {
            throw ValidationException::withMessages([
                'evaluation' => [
                    'La evaluación no tiene un análisis diagnóstico congelado y verificable para publicación.',
                ],
            ]);
        }

        if (
            $analysisEvidenceVersion
            !== (int) $evaluation
                ->evidence_version
        ) {
            throw ValidationException::withMessages([
                'evaluation' => [
                    'El análisis diagnóstico no corresponde a la versión actual de la evidencia.',
                ],
            ]);
        }

        if (
            (int) (
                $snapshot['schema_version']
                ?? 0
            ) !== $schemaVersion
        ) {
            throw ValidationException::withMessages([
                'evaluation' => [
                    'La versión del snapshot de análisis diagnóstico no coincide con su contrato persistido.',
                ],
            ]);
        }

        $snapshotEvidence =
            is_array(
                $snapshot['evidence']
                ?? null
            )
                ? $snapshot['evidence']
                : [];

        if (
            (int) (
                $snapshotEvidence[
                    'evidence_version'
                ]
                ?? 0
            ) !== (int) $evaluation
                ->evidence_version
            || ! hash_equals(
                strtolower(
                    trim(
                        (string) $evaluation
                            ->evidence_sha256
                    )
                ),
                strtolower(
                    trim(
                        (string) (
                            $snapshotEvidence[
                                'evidence_sha256'
                            ]
                            ?? ''
                        )
                    )
                )
            )
        ) {
            throw ValidationException::withMessages([
                'evaluation' => [
                    'La trazabilidad del análisis diagnóstico no coincide con la evidencia fijada.',
                ],
            ]);
        }

        $calculatedSha256 =
            $this->diagnosticAnalysisSha256(
                $snapshot
            );

        if (
            ! hash_equals(
                $sha256,
                $calculatedSha256
            )
        ) {
            throw ValidationException::withMessages([
                'evaluation' => [
                    'El snapshot de análisis diagnóstico no supera la validación de integridad.',
                ],
            ]);
        }
    }

    /**
     * @param array<string,mixed> $snapshot
     */
    private function diagnosticAnalysisSha256(
        array $snapshot
    ): string {
        $canonical =
            $this->canonicalizeDiagnosticAnalysisValue(
                $snapshot
            );

        $encoded =
            json_encode(
                $canonical,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION
                | JSON_THROW_ON_ERROR
            );

        return hash(
            'sha256',
            $encoded
        );
    }

    private function canonicalizeDiagnosticAnalysisValue(
        mixed $value
    ): mixed {
        /*
         * JSON numbers do not carry an integer-vs-float semantic type.
         *
         * MySQL JSON normalizes an integral floating-point value such as
         * 100.0 to the JSON number 100. If the hash preserved PHP's
         * float/int distinction, the exact same semantic snapshot could
         * fail integrity verification after a database round-trip.
         *
         * Normalize only finite, mathematically integral floats that fit
         * in the native integer range. Non-integral values keep their
         * floating-point representation.
         */
        if (
            is_float($value)
            && is_finite($value)
            && $value >= PHP_INT_MIN
            && $value <= PHP_INT_MAX
            && floor($value) === $value
        ) {
            return (int) $value;
        }

        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(
                fn (mixed $item): mixed =>
                    $this
                        ->canonicalizeDiagnosticAnalysisValue(
                            $item
                        ),
                $value
            );
        }

        ksort(
            $value,
            SORT_STRING
        );

        foreach (
            $value
            as $key => $item
        ) {
            $value[$key] =
                $this
                    ->canonicalizeDiagnosticAnalysisValue(
                        $item
                    );
        }

        return $value;
    }

    /**
     * Build a deterministic aggregate-only evidence snapshot.
     *
     * No raw client values, samples, canonical payloads,
     * staging rows or normalized rows are included.
     *
     * @return array{
     *     snapshot: array<string,mixed>,
     *     sha256: string
     * }
     */
    private function captureEvidence(
        DataTransformationBiIntakeSession $session,
        ?int $schemaVersion = null
    ): array {
        $schemaVersion ??=
            self::EVIDENCE_SCHEMA_VERSION;

        if (
            ! in_array(
                $schemaVersion,
                [
                    self::LEGACY_EVIDENCE_SCHEMA_VERSION,
                    self::EVIDENCE_SCHEMA_VERSION,
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'evaluation' => [
                    'La versión de evidencia diagnóstica no es compatible con esta versión de LAUDA.',
                ],
            ]);
        }

        /** @var Collection<int,DataTransformationBiSourceAsset> $sources */
        $sources =
            DataTransformationBiSourceAsset::query()
                ->where(
                    'data_transformation_bi_intake_session_id',
                    $session->getKey()
                )
                ->where(
                    'company_id',
                    $session->company_id
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

        if ($sources->isEmpty()) {
            throw ValidationException::withMessages([
                'evaluation' => [
                    'La entrega no contiene fuentes activas para evaluar.',
                ],
            ]);
        }

        $sourceEvidence =
            $sources
                ->map(
                    function (
                        DataTransformationBiSourceAsset $source
                    ) use (
                        $schemaVersion
                    ): array {
                        if (
                            (string) $source->data_status
                                !== 'analyzed'
                            || (string) $source->profiling_status
                                !== 'completed'
                            || ! is_array(
                                $source->profiling_snapshot
                            )
                        ) {
                            throw ValidationException::withMessages([
                                'evaluation' => [
                                    'Todas las fuentes activas deben completar el profiling antes de capturar la evidencia diagnóstica.',
                                ],
                            ]);
                        }

                        $summary =
                            DataTransformationBiSourceDiagnosticReadModel
                                ::fromSnapshot(
                                    $source->profiling_snapshot
                                );

                        if (
                            ($summary['available'] ?? false)
                            !== true
                        ) {
                            throw ValidationException::withMessages([
                                'evaluation' => [
                                    'Una fuente activa no dispone de evidencia diagnóstica utilizable.',
                                ],
                            ]);
                        }

                        $payload = [
                            'source_asset_id' =>
                                (int) $source->getKey(),

                            'display_name' =>
                                (string) $source->display_name,

                            'source_object_name' =>
                                (string) $source
                                    ->source_object_name,

                            'origin_system' =>
                                $source->origin_system !== null
                                    ? (string) $source
                                        ->origin_system
                                    : null,

                            'delivery_format' =>
                                $source->delivery_format !== null
                                    ? (string) $source
                                        ->delivery_format
                                    : null,

                            'profiling_job_uuid' =>
                                $source->profiling_job_uuid !== null
                                    ? (string) $source
                                        ->profiling_job_uuid
                                    : null,

                            'profiled_at' =>
                                $this->timestamp(
                                    $source->profiled_at
                                ),

                            'diagnostic_summary' =>
                                $summary,
                        ];

                        if (
                            $schemaVersion
                            >= self::EVIDENCE_SCHEMA_VERSION
                        ) {
                            $payload['business_domains'] =
                                $this->businessDomains(
                                    $source
                                );

                            $payload[
                                'structural_semantic_signals'
                            ] =
                                DataTransformationBiStructuralSemanticSignalsReadModel
                                    ::fromSnapshot(
                                        $source
                                            ->profiling_snapshot
                                    );
                        }

                        return $payload;
                    }
                )
                ->values()
                ->all();

        $snapshot = [
            'kind' =>
                'data_bi_diagnostic_evaluation_evidence',

            'schema_version' =>
                $schemaVersion,

            'session_id' =>
                (int) $session->getKey(),

            'company_id' =>
                (int) $session->company_id,

            'transformation_implementation_request_id' =>
                (int) $session
                    ->transformation_implementation_request_id,

            'submission_manifest_sha256' =>
                (string) $session
                    ->submitted_manifest_sha256,

            'source_count' =>
                count(
                    $sourceEvidence
                ),

            'sources' =>
                $sourceEvidence,
        ];

        if (
            $schemaVersion
            >= self::EVIDENCE_SCHEMA_VERSION
        ) {
            $snapshot['semantic_diagnostic'] =
                DataTransformationBiSemanticDiagnosticReadModel
                    ::fromSources(
                        $sourceEvidence
                    );
        }

        $encoded =
            json_encode(
                $snapshot,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION
                | JSON_THROW_ON_ERROR
            );

        return [
            'snapshot' =>
                $snapshot,

            'sha256' =>
                hash(
                    'sha256',
                    $encoded
                ),
        ];
    }

    private function evidenceSchemaVersion(
        DataTransformationBiEvaluation $evaluation
    ): int {
        $snapshot =
            is_array(
                $evaluation->evidence_snapshot
            )
                ? $evaluation->evidence_snapshot
                : [];

        $version =
            isset(
                $snapshot['schema_version']
            )
                ? (int) $snapshot[
                    'schema_version'
                ]
                : self::LEGACY_EVIDENCE_SCHEMA_VERSION;

        if (
            ! in_array(
                $version,
                [
                    self::LEGACY_EVIDENCE_SCHEMA_VERSION,
                    self::EVIDENCE_SCHEMA_VERSION,
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'evaluation' => [
                    'La versión de evidencia diagnóstica fijada para esta evaluación no es compatible.',
                ],
            ]);
        }

        return $version;
    }

    /**
     * Preserve tenant declaration order while normalizing the
     * associative key order used inside the evidence hash.
     *
     * @return list<array{
     *     domain:string,
     *     group:string
     * }>
     */
    private function businessDomains(
        DataTransformationBiSourceAsset $source
    ): array {
        $items =
            is_array(
                $source->business_domains
            )
                ? array_values(
                    $source->business_domains
                )
                : [];

        $result = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $domain =
                trim(
                    (string) (
                        $item['domain']
                        ?? ''
                    )
                );

            $group =
                strtolower(
                    trim(
                        (string) (
                            $item['group']
                            ?? ''
                        )
                    )
                );

            if (
                $domain === ''
                || ! in_array(
                    $group,
                    DataTransformationBiSourceAsset
                        ::BUSINESS_GROUPS,
                    true
                )
            ) {
                continue;
            }

            $result[] = [
                'domain' =>
                    $domain,

                'group' =>
                    $group,
            ];
        }

        return $result;
    }

    private function timestamp(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (
            $value instanceof DateTimeInterface
        ) {
            return $value->format(
                DATE_ATOM
            );
        }

        return (string) $value;
    }

    /**
     * @param array<string,mixed> $context
     */
    private function audit(
        string $event,
        DataTransformationBiEvaluation $evaluation,
        DataTransformationBiIntakeSession $session,
        User $actor,
        array $context = []
    ): void {
        AuditService::log(
            $event,
            $evaluation,
            array_merge(
                [
                    'company_id' =>
                        (int) $session->company_id,

                    'intake_session_id' =>
                        (int) $session->getKey(),

                    'transformation_implementation_request_id' =>
                        (int) $session
                            ->transformation_implementation_request_id,

                    'actor_user_id' =>
                        (int) $actor->getKey(),

                    'evaluation_status' =>
                        (string) $evaluation->status,

                    'evidence_sha256' =>
                        (string) $evaluation
                            ->evidence_sha256,

                    'commercial_acceptance' =>
                        false,

                    'implementation_started' =>
                        false,
                ],
                $context
            )
        );
    }
}
