<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiEvaluation;

/**
 * Admin-workspace projection for diagnostic analysis.
 *
 * Authority rules:
 *
 * - no evaluation:
 *   no diagnostic analysis exists;
 *
 * - draft:
 *   preview is deterministically derived ONLY from the evaluation's
 *   pinned evidence_snapshot;
 *
 * - ready_for_review / published:
 *   consume ONLY the already-frozen diagnostic_analysis_snapshot;
 *
 * - historical published evaluations that predate diagnostic-analysis
 *   snapshots remain explicitly unavailable and are never recomputed.
 *
 * This projection deliberately does NOT query source assets or other
 * mutable delivery state.
 */
final class DataTransformationBiDiagnosticAnalysisWorkspaceProjection
{
    public const MODE_PREVIEW =
        'preview';

    public const MODE_FROZEN =
        'frozen';

    public const MODE_HISTORICAL_UNAVAILABLE =
        'historical_unavailable';

    public const MODE_FROZEN_UNAVAILABLE =
        'frozen_unavailable';

    /**
     * @return array<string,mixed>|null
     */
    public static function fromEvaluation(
        ?DataTransformationBiEvaluation $evaluation
    ): ?array {
        if ($evaluation === null) {
            return null;
        }

        if ($evaluation->isDraft()) {
            return self::preview(
                $evaluation
            );
        }

        return self::frozen(
            $evaluation
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function preview(
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

        return [
            'mode' =>
                self::MODE_PREVIEW,

            'frozen' =>
                false,

            'available' =>
                ($snapshot['available'] ?? false)
                === true,

            'analysis_schema_version' =>
                self::positiveIntegerOrNull(
                    $snapshot[
                        'schema_version'
                    ]
                    ?? null
                ),

            'evidence_version' =>
                self::positiveIntegerOrNull(
                    $evaluation
                        ->evidence_version
                ),

            'generated_at' =>
                null,

            'unavailable_reason' =>
                isset(
                    $snapshot[
                        'unavailable_reason'
                    ]
                )
                    ? (string) $snapshot[
                        'unavailable_reason'
                    ]
                    : null,

            'snapshot' =>
                $snapshot,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function frozen(
        DataTransformationBiEvaluation $evaluation
    ): array {
        $snapshot =
            is_array(
                $evaluation
                    ->diagnostic_analysis_snapshot
            )
                ? $evaluation
                    ->diagnostic_analysis_snapshot
                : null;

        if (
            is_array($snapshot)
            && $snapshot !== []
        ) {
            return [
                'mode' =>
                    self::MODE_FROZEN,

                'frozen' =>
                    true,

                'available' =>
                    ($snapshot['available'] ?? false)
                    === true,

                /*
                 * Preserve the exact persisted contract version.
                 * Never substitute the current code version.
                 */
                'analysis_schema_version' =>
                    self::positiveIntegerOrNull(
                        $evaluation
                            ->diagnostic_analysis_schema_version
                    )
                    ?? self::positiveIntegerOrNull(
                        $snapshot[
                            'schema_version'
                        ]
                        ?? null
                    ),

                'evidence_version' =>
                    self::positiveIntegerOrNull(
                        $evaluation
                            ->diagnostic_analysis_evidence_version
                    ),

                'generated_at' =>
                    $evaluation
                        ->diagnostic_analysis_generated_at
                        ?->toISOString(),

                'unavailable_reason' =>
                    isset(
                        $snapshot[
                            'unavailable_reason'
                        ]
                    )
                        ? (string) $snapshot[
                            'unavailable_reason'
                        ]
                        : null,

                /*
                 * Exact frozen result.
                 *
                 * Do not merge with current source payloads and do
                 * not re-run the current diagnostic rules.
                 */
                'snapshot' =>
                    $snapshot,
            ];
        }

        return self::unavailableFrozen(
            $evaluation
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function unavailableFrozen(
        DataTransformationBiEvaluation $evaluation
    ): array {
        $evidenceSnapshot =
            is_array(
                $evaluation->evidence_snapshot
            )
                ? $evaluation->evidence_snapshot
                : [];

        $evidenceSchemaVersion =
            (int) (
                $evidenceSnapshot[
                    'schema_version'
                ]
                ?? 1
            );

        /*
         * Published evidence V1 predates semantic diagnostic-analysis
         * snapshots. Preserve history instead of manufacturing a new
         * conclusion with today's rules.
         */
        if (
            $evaluation->isPublished()
            && $evidenceSchemaVersion < 2
        ) {
            return [
                'mode' =>
                    self::MODE_HISTORICAL_UNAVAILABLE,

                'frozen' =>
                    false,

                'available' =>
                    false,

                'analysis_schema_version' =>
                    null,

                'evidence_version' =>
                    self::positiveIntegerOrNull(
                        $evaluation
                            ->evidence_version
                    ),

                'generated_at' =>
                    null,

                'unavailable_reason' =>
                    'diagnostic_analysis_not_available_for_historical_evaluation',

                'snapshot' =>
                    null,
            ];
        }

        /*
         * A modern non-draft evaluation is expected to have a frozen
         * snapshot. Missing persistence is exposed explicitly instead
         * of silently recomputing it.
         */
        return [
            'mode' =>
                self::MODE_FROZEN_UNAVAILABLE,

            'frozen' =>
                false,

            'available' =>
                false,

            'analysis_schema_version' =>
                null,

            'evidence_version' =>
                self::positiveIntegerOrNull(
                    $evaluation
                        ->evidence_version
                ),

            'generated_at' =>
                null,

            'unavailable_reason' =>
                'frozen_diagnostic_analysis_snapshot_not_available',

            'snapshot' =>
                null,
        ];
    }

    private static function positiveIntegerOrNull(
        mixed $value
    ): ?int {
        $value =
            (int) $value;

        return $value > 0
            ? $value
            : null;
    }
}
