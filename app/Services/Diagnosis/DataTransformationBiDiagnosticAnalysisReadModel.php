<?php

namespace App\Services\Diagnosis;

/**
 * Deterministic diagnostic-analysis snapshot derived exclusively from
 * one pinned evaluation evidence snapshot.
 *
 * This is intentionally distinct from the professional capability
 * `data_transformation_bi`.
 *
 * At D1B this snapshot freezes the diagnostic context required by
 * later analysis rules:
 * - semantic inventory already pinned in evidence V2;
 * - domain coverage derived from pinned source evidence;
 * - conservative cross-source structural candidates.
 *
 * Actual BI diagnostic analyses are introduced in D2.
 *
 * This read model deliberately does NOT:
 * - query live sources;
 * - read raw client values or samples;
 * - generate findings or recommendations;
 * - calculate readiness, risk or opportunity scores;
 * - confirm joins or referential integrity;
 * - depend on canonical, staging, mapping or implementation models.
 */
final class DataTransformationBiDiagnosticAnalysisReadModel
{
    private const SCHEMA_VERSION = 1;

    private const SUPPORTED_EVIDENCE_SCHEMA_VERSION = 2;

    /**
     * @param array<string,mixed> $evidenceSnapshot
     *
     * @return array<string,mixed>
     */
    public static function fromEvidence(
        array $evidenceSnapshot,
        int $evidenceVersion,
        string $evidenceSha256
    ): array {
        if (
            ($evidenceSnapshot['kind'] ?? null)
                !== 'data_bi_diagnostic_evaluation_evidence'
            || (int) (
                $evidenceSnapshot['schema_version']
                ?? 0
            ) !== self::SUPPORTED_EVIDENCE_SCHEMA_VERSION
            || $evidenceVersion <= 0
            || preg_match(
                '/^[a-f0-9]{64}$/',
                strtolower(
                    trim(
                        $evidenceSha256
                    )
                )
            ) !== 1
        ) {
            return self::unavailable(
                'unsupported_evidence_contract',
                $evidenceVersion,
                $evidenceSha256,
                (int) (
                    $evidenceSnapshot[
                        'schema_version'
                    ]
                    ?? 0
                )
            );
        }

        $sources =
            is_array(
                $evidenceSnapshot['sources']
                ?? null
            )
                ? array_values(
                    $evidenceSnapshot['sources']
                )
                : [];

        if ($sources === []) {
            return self::unavailable(
                'no_pinned_sources',
                $evidenceVersion,
                $evidenceSha256,
                self::SUPPORTED_EVIDENCE_SCHEMA_VERSION
            );
        }

        $semanticDiagnostic =
            is_array(
                $evidenceSnapshot[
                    'semantic_diagnostic'
                ]
                ?? null
            )
                ? $evidenceSnapshot[
                    'semantic_diagnostic'
                ]
                : DataTransformationBiSemanticDiagnosticReadModel
                    ::fromSources(
                        $sources
                    );

        $domainCoverage =
            DataTransformationBiDomainCoverageReadModel
                ::fromSources(
                    $sources
                );

        $relationshipCandidates =
            DataTransformationBiCrossSourceRelationshipCandidatesReadModel
                ::fromSources(
                    $sources
                );

        return [
            'kind' =>
                'data_bi_diagnostic_analysis',

            'schema_version' =>
                self::SCHEMA_VERSION,

            /*
             * Everything in this snapshot refers only to the evaluated
             * delivery represented by the pinned evidence.
             */
            'scope' =>
                'evaluated_delivery',

            'available' =>
                true,

            'evidence' => [
                'schema_version' =>
                    self::SUPPORTED_EVIDENCE_SCHEMA_VERSION,

                'evidence_version' =>
                    $evidenceVersion,

                'evidence_sha256' =>
                    strtolower(
                        trim(
                            $evidenceSha256
                        )
                    ),
            ],

            'source_count' =>
                count(
                    $sources
                ),

            /*
             * Frozen diagnostic context.
             *
             * C1/C2 remain descriptive evidence interpretation only.
             */
            'semantic_diagnostic' =>
                $semanticDiagnostic,

            'domain_coverage' =>
                $domainCoverage,

            'cross_source_relationship_candidates' =>
                $relationshipCandidates,

            /*
             * D2 owns actual diagnostic BI analyses.
             *
             * Keeping the collection explicit now establishes the
             * versioned publication contract without inventing
             * unsupported analytical conclusions.
             */
            'analysis_count' =>
                0,

            'analyses' =>
                [],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function unavailable(
        string $reason,
        int $evidenceVersion,
        string $evidenceSha256,
        int $evidenceSchemaVersion
    ): array {
        return [
            'kind' =>
                'data_bi_diagnostic_analysis',

            'schema_version' =>
                self::SCHEMA_VERSION,

            'scope' =>
                'evaluated_delivery',

            'available' =>
                false,

            'unavailable_reason' =>
                $reason,

            'evidence' => [
                'schema_version' =>
                    $evidenceSchemaVersion,

                'evidence_version' =>
                    max(
                        0,
                        $evidenceVersion
                    ),

                'evidence_sha256' =>
                    strtolower(
                        trim(
                            $evidenceSha256
                        )
                    ),
            ],

            'source_count' =>
                0,

            'semantic_diagnostic' =>
                null,

            'domain_coverage' =>
                null,

            'cross_source_relationship_candidates' =>
                null,

            'analysis_count' =>
                0,

            'analyses' =>
                [],
        ];
    }
}
