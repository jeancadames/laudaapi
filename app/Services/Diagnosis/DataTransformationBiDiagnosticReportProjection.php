<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiEvaluation;
use App\Models\DataTransformationBiEvaluationFinding;

final class DataTransformationBiDiagnosticReportProjection
{
    /**
     * Presentation-only composition of the current professional
     * evaluation and its diagnostic-analysis authority.
     *
     * This class does not persist a report, recompute frozen analysis,
     * create findings, or create recommendations.
     *
     * The caller must provide findings.sources already loaded so this
     * projection remains free from hidden database reads.
     *
     * @return array<string,mixed>|null
     */
    public static function fromEvaluation(
        ?DataTransformationBiEvaluation $evaluation
    ): ?array {
        if ($evaluation === null) {
            return null;
        }

        self::assertRequiredRelationsLoaded(
            $evaluation
        );

        $diagnosticAnalysis =
            DataTransformationBiDiagnosticAnalysisWorkspaceProjection
                ::fromEvaluation(
                    $evaluation
                );

        $findings =
            $evaluation
                ->getRelation('findings')
                ->map(
                    static function (
                        DataTransformationBiEvaluationFinding $finding
                    ) use (
                        $evaluation
                    ): array {
                        $sources =
                            $finding
                                ->getRelation('sources');

                        return [
                            'id' =>
                                (int) $finding->getKey(),

                            'finding_type' =>
                                (string) $finding->finding_type,

                            'title' =>
                                (string) $finding->title,

                            'details' =>
                                (string) $finding->details,

                            'recommendation' =>
                                $finding->recommendation !== null
                                    ? (string) $finding->recommendation
                                    : null,

                            'priority' =>
                                $finding->priority !== null
                                    ? (string) $finding->priority
                                    : null,

                            'evidence_version' =>
                                (int) $finding->evidence_version,

                            'evidence_current' =>
                                (int) $finding->evidence_version
                                === (int) $evaluation->evidence_version,

                            'sort_order' =>
                                (int) $finding->sort_order,

                            'source_ids' =>
                                $sources
                                    ->pluck('id')
                                    ->map(
                                        static fn ($id): int =>
                                            (int) $id
                                    )
                                    ->values()
                                    ->all(),
                        ];
                    }
                )
                ->values()
                ->all();

        return [
            'report_context' => [
                'evaluation_status' =>
                    (string) $evaluation->status,

                'evidence_version' =>
                    (int) $evaluation->evidence_version,

                'evidence_captured_at' =>
                    $evaluation
                        ->evidence_captured_at
                        ?->toISOString(),

                'analysis_schema_version' =>
                    is_array($diagnosticAnalysis)
                        ? (
                            $diagnosticAnalysis[
                                'analysis_schema_version'
                            ]
                            ?? null
                        )
                        : null,

                'analysis_generated_at' =>
                    is_array($diagnosticAnalysis)
                        ? (
                            $diagnosticAnalysis[
                                'generated_at'
                            ]
                            ?? null
                        )
                        : null,

                'published_at' =>
                    $evaluation
                        ->published_at
                        ?->toISOString(),

                'evaluated_source_count' =>
                    self::evaluatedSourceCount(
                        $diagnosticAnalysis
                    ),
            ],

            'scope_statement' => [
                'Este informe describe únicamente la entrega evaluada.',

                'La ausencia de evidencia en esta entrega no demuestra '
                .'que la empresa carezca de esa información.',

                'Este informe no calcula un porcentaje global '
                .'de preparación para BI.',
            ],

            /*
             * Exact diagnostic-analysis lifecycle authority:
             *
             * draft:
             * preview from pinned evidence_snapshot.
             *
             * ready/published:
             * exact frozen diagnostic_analysis_snapshot.
             *
             * historical:
             * unavailable remains unavailable.
             */
            'structural_analysis' =>
                $diagnosticAnalysis,

            /*
             * Human-authored professional layer.
             *
             * This projection only presents existing findings.
             */
            'professional_findings' => [
                'count' =>
                    count(
                        $findings
                    ),

                'weakness_count' =>
                    self::findingCountByType(
                        $findings,
                        DataTransformationBiEvaluationFinding
                            ::TYPE_WEAKNESS
                    ),

                'opportunity_count' =>
                    self::findingCountByType(
                        $findings,
                        DataTransformationBiEvaluationFinding
                            ::TYPE_OPPORTUNITY
                    ),

                'observation_count' =>
                    self::findingCountByType(
                        $findings,
                        DataTransformationBiEvaluationFinding
                            ::TYPE_OBSERVATION
                    ),

                'stale_count' =>
                    count(
                        array_filter(
                            $findings,
                            static fn (array $finding): bool =>
                                ($finding['evidence_current'] ?? false)
                                !== true
                        )
                    ),

                'items' =>
                    $findings,
            ],

            'interpretation_boundaries' => [
                'structural_analysis_authority' =>
                    'diagnostic_analysis_projection',

                'professional_findings_authority' =>
                    'human_evaluation_findings',

                'presentation_only' =>
                    true,

                'automatic_findings' =>
                    false,

                'automatic_recommendations' =>
                    false,
            ],
        ];
    }

    /**
     * @param array<string,mixed>|null $diagnosticAnalysis
     */
    private static function evaluatedSourceCount(
        ?array $diagnosticAnalysis
    ): ?int {
        if (
            ! is_array(
                $diagnosticAnalysis[
                    'snapshot'
                ]
                ?? null
            )
        ) {
            return null;
        }

        $sourceCount =
            $diagnosticAnalysis[
                'snapshot'
            ][
                'source_count'
            ]
            ?? null;

        if (! is_numeric($sourceCount)) {
            return null;
        }

        return max(
            0,
            (int) $sourceCount
        );
    }

    /**
     * @param list<array<string,mixed>> $findings
     */
    private static function findingCountByType(
        array $findings,
        string $findingType
    ): int {
        return count(
            array_filter(
                $findings,
                static fn (array $finding): bool =>
                    ($finding['finding_type'] ?? null)
                    === $findingType
            )
        );
    }

    private static function assertRequiredRelationsLoaded(
        DataTransformationBiEvaluation $evaluation
    ): void {
        if (! $evaluation->relationLoaded('findings')) {
            throw new \LogicException(
                'Diagnostic report projection requires '
                .'the findings relation to be preloaded.'
            );
        }

        foreach (
            $evaluation->getRelation('findings')
            as $finding
        ) {
            if (
                ! $finding instanceof
                    DataTransformationBiEvaluationFinding
            ) {
                throw new \LogicException(
                    'Diagnostic report projection received '
                    .'an invalid finding relation item.'
                );
            }

            if (! $finding->relationLoaded('sources')) {
                throw new \LogicException(
                    'Diagnostic report projection requires '
                    .'finding sources to be preloaded.'
                );
            }
        }
    }
}
