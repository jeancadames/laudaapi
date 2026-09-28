<?php

namespace App\Services\Diagnosis;

/**
 * Deterministic diagnostic analyses derived exclusively from one
 * pinned evaluation evidence snapshot.
 *
 * Important boundaries:
 *
 * - "supported", "partial" and
 *   "not_supported_by_current_evidence" describe ONLY the current
 *   evaluated delivery;
 * - absence of evidence never means that the company does not have
 *   that business information or analytical need;
 * - structural signals are source-level evidence and never establish
 *   field-to-domain mappings;
 * - no raw client values or samples are read;
 * - no BI readiness score is calculated;
 * - no findings or recommendations are generated;
 * - no canonical, mapping, staging or implementation models are used.
 */
final class DataTransformationBiDiagnosticAnalysisReadModel
{
    private const SCHEMA_VERSION = 2;

    private const SUPPORTED_EVIDENCE_SCHEMA_VERSION = 2;

    private const STATUS_SUPPORTED =
        'supported';

    private const STATUS_PARTIAL =
        'partial';

    private const STATUS_NOT_SUPPORTED =
        'not_supported_by_current_evidence';

    /**
     * Initial conservative diagnostic-analysis catalog.
     *
     * Every rule is supported directly by one structural semantic
     * signal already present in evidence V2.
     *
     * No dynamic tenant domain name is required by any rule.
     *
     * @var list<array{
     *     key:string,
     *     label:string,
     *     signal_key:string
     * }>
     */
    private const ANALYSIS_RULES = [
        [
            'key' =>
                'record_identification',

            'label' =>
                'Identificación de registros',

            'signal_key' =>
                'identifier',
        ],

        [
            'key' =>
                'geographic_segmentation',

            'label' =>
                'Segmentación geográfica',

            'signal_key' =>
                'geographic',
        ],

        [
            'key' =>
                'temporal_analysis',

            'label' =>
                'Análisis temporal',

            'signal_key' =>
                'temporal',
        ],

        [
            'key' =>
                'monetary_analysis',

            'label' =>
                'Análisis monetario',

            'signal_key' =>
                'monetary',
        ],
    ];

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

        $analyses =
            self::analyses(
                $sources
            );

        return [
            'kind' =>
                'data_bi_diagnostic_analysis',

            'schema_version' =>
                self::SCHEMA_VERSION,

            'scope' =>
                'evaluated_delivery',

            'available' =>
                true,

            /*
             * Critical interpretation boundary.
             *
             * These statuses describe only evidence contained in the
             * evaluated delivery. They are neither business-absence
             * declarations nor BI production-readiness decisions.
             */
            'interpretation_boundary' =>
                'current_evaluated_evidence_only_not_business_absence_or_production_readiness',

            'status_semantics' => [
                self::STATUS_SUPPORTED =>
                    'complete_compatible_structural_evidence_observed',

                self::STATUS_PARTIAL =>
                    'compatible_structural_evidence_observed_with_partial_coverage_only',

                self::STATUS_NOT_SUPPORTED =>
                    'no_usable_compatible_structural_evidence_observed',
            ],

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

            'semantic_diagnostic' =>
                $semanticDiagnostic,

            'domain_coverage' =>
                $domainCoverage,

            'cross_source_relationship_candidates' =>
                $relationshipCandidates,

            'analysis_count' =>
                count(
                    $analyses
                ),

            'analyses' =>
                $analyses,
        ];
    }

    /**
     * @param list<mixed> $sources
     *
     * @return list<array<string,mixed>>
     */
    private static function analyses(
        array $sources
    ): array {
        $result = [];

        foreach (
            self::ANALYSIS_RULES
            as $rule
        ) {
            $result[] =
                self::analysis(
                    $sources,
                    $rule
                );
        }

        return $result;
    }

    /**
     * @param list<mixed> $sources
     * @param array{
     *     key:string,
     *     label:string,
     *     signal_key:string
     * } $rule
     *
     * @return array<string,mixed>
     */
    private static function analysis(
        array $sources,
        array $rule
    ): array {
        $supportingEvidence = [];

        $completeColumnCount = 0;
        $partialColumnCount = 0;
        $emptyColumnCount = 0;
        $unprofiledColumnCount = 0;
        $otherCoverageColumnCount = 0;

        $observedTypeFamilies = [];

        foreach ($sources as $source) {
            if (! is_array($source)) {
                continue;
            }

            $sourceId =
                self::sourceId(
                    $source
                );

            if ($sourceId <= 0) {
                continue;
            }

            $structural =
                is_array(
                    $source[
                        'structural_semantic_signals'
                    ]
                    ?? null
                )
                    ? $source[
                        'structural_semantic_signals'
                    ]
                    : [];

            $matchedColumns = [];

            foreach (
                $structural['signals']
                ?? []
                as $signal
            ) {
                if (
                    ! is_array($signal)
                    || (string) (
                        $signal['key']
                        ?? ''
                    ) !== $rule[
                        'signal_key'
                    ]
                ) {
                    continue;
                }

                foreach (
                    $signal['columns']
                    ?? []
                    as $column
                ) {
                    if (! is_array($column)) {
                        continue;
                    }

                    $safeColumn =
                        self::safeColumn(
                            $column
                        );

                    $matchedColumns[] =
                        $safeColumn;

                    $coverage =
                        $safeColumn[
                            'coverage_status'
                        ];

                    match ($coverage) {
                        'complete' =>
                            $completeColumnCount++,

                        'partial' =>
                            $partialColumnCount++,

                        'empty' =>
                            $emptyColumnCount++,

                        'unprofiled' =>
                            $unprofiledColumnCount++,

                        default =>
                            $otherCoverageColumnCount++,
                    };

                    foreach (
                        $safeColumn[
                            'observed_type_families'
                        ]
                        as $family
                    ) {
                        $observedTypeFamilies[
                            $family
                        ] = true;
                    }
                }
            }

            if ($matchedColumns === []) {
                continue;
            }

            $coverageCounts =
                self::coverageCounts(
                    $matchedColumns
                );

            $supportingEvidence[] = [
                'source_id' =>
                    $sourceId,

                'display_name' =>
                    isset(
                        $source['display_name']
                    )
                        ? (string) $source[
                            'display_name'
                        ]
                        : null,

                'source_object_name' =>
                    isset(
                        $source[
                            'source_object_name'
                        ]
                    )
                        ? (string) $source[
                            'source_object_name'
                        ]
                        : null,

                /*
                 * Context only.
                 *
                 * These declarations belong to the source as a whole
                 * and must never be interpreted as assigning the
                 * matched columns to one of these domains.
                 */
                'declared_domains' =>
                    self::declaredDomains(
                        $source
                    ),

                'matched_column_count' =>
                    count(
                        $matchedColumns
                    ),

                'coverage_counts' =>
                    $coverageCounts,

                'columns' =>
                    $matchedColumns,
            ];
        }

        usort(
            $supportingEvidence,
            static fn (
                array $left,
                array $right
            ): int =>
                ((int) $left['source_id'])
                <=>
                ((int) $right['source_id'])
        );

        $supportingSourceIds =
            array_values(
                array_map(
                    static fn (
                        array $evidence
                    ): int =>
                        (int) $evidence[
                            'source_id'
                        ],
                    $supportingEvidence
                )
            );

        $domainContext =
            self::domainContext(
                $supportingEvidence
            );

        $status =
            self::status(
                $completeColumnCount,
                $partialColumnCount
            );

        $observedTypeFamilies =
            array_keys(
                $observedTypeFamilies
            );

        sort(
            $observedTypeFamilies,
            SORT_STRING
        );

        return [
            'key' =>
                $rule['key'],

            'label' =>
                $rule['label'],

            'kind' =>
                'structural_diagnostic_analysis',

            'status' =>
                $status,

            'status_basis' =>
                self::statusBasis(
                    $status
                ),

            'required_signal_keys' => [
                $rule['signal_key'],
            ],

            'observed_signal_keys' =>
                $supportingEvidence === []
                    ? []
                    : [
                        $rule['signal_key'],
                    ],

            'supporting_source_ids' =>
                $supportingSourceIds,

            'supporting_source_count' =>
                count(
                    $supportingSourceIds
                ),

            'evidence_column_count' =>
                $completeColumnCount
                + $partialColumnCount
                + $emptyColumnCount
                + $unprofiledColumnCount
                + $otherCoverageColumnCount,

            'coverage_counts' => [
                'complete' =>
                    $completeColumnCount,

                'partial' =>
                    $partialColumnCount,

                'empty' =>
                    $emptyColumnCount,

                'unprofiled' =>
                    $unprofiledColumnCount,

                'other' =>
                    $otherCoverageColumnCount,
            ],

            'observed_type_families' =>
                $observedTypeFamilies,

            /*
             * The domain list describes only the declared context of
             * the supporting source assets.
             */
            'domain_context_scope' =>
                'supporting_sources_not_field_to_domain_mapping',

            'declared_domain_context' =>
                $domainContext,

            'supporting_evidence' =>
                $supportingEvidence,

            'evidence_note' =>
                self::evidenceNote(
                    $status
                ),
        ];
    }

    private static function status(
        int $completeColumnCount,
        int $partialColumnCount
    ): string {
        if ($completeColumnCount > 0) {
            return self::STATUS_SUPPORTED;
        }

        if ($partialColumnCount > 0) {
            return self::STATUS_PARTIAL;
        }

        return self::STATUS_NOT_SUPPORTED;
    }

    private static function statusBasis(
        string $status
    ): string {
        return match ($status) {
            self::STATUS_SUPPORTED =>
                'complete_structural_signal_coverage',

            self::STATUS_PARTIAL =>
                'partial_structural_signal_coverage_only',

            default =>
                'no_usable_structural_signal_coverage',
        };
    }

    private static function evidenceNote(
        string $status
    ): string {
        return match ($status) {
            self::STATUS_SUPPORTED =>
                'La entrega evaluada contiene evidencia estructural completa compatible con este análisis.',

            self::STATUS_PARTIAL =>
                'La entrega evaluada contiene evidencia estructural compatible, pero la cobertura observada es parcial.',

            default =>
                'La entrega evaluada no proporciona evidencia estructural utilizable suficiente para sustentar este análisis.',
        };
    }

    /**
     * @param list<array<string,mixed>> $columns
     *
     * @return array{
     *     complete:int,
     *     partial:int,
     *     empty:int,
     *     unprofiled:int,
     *     other:int
     * }
     */
    private static function coverageCounts(
        array $columns
    ): array {
        $result = [
            'complete' =>
                0,

            'partial' =>
                0,

            'empty' =>
                0,

            'unprofiled' =>
                0,

            'other' =>
                0,
        ];

        foreach ($columns as $column) {
            $coverage =
                (string) (
                    $column[
                        'coverage_status'
                    ]
                    ?? ''
                );

            if (
                array_key_exists(
                    $coverage,
                    $result
                )
            ) {
                $result[$coverage]++;

                continue;
            }

            $result['other']++;
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $column
     *
     * @return array<string,mixed>
     */
    private static function safeColumn(
        array $column
    ): array {
        return [
            'sheet_index' =>
                isset(
                    $column['sheet_index']
                )
                    ? (int) $column[
                        'sheet_index'
                    ]
                    : 0,

            'sheet_name' =>
                isset(
                    $column['sheet_name']
                )
                    ? (
                        $column['sheet_name']
                        !== null
                            ? (string) $column[
                                'sheet_name'
                            ]
                            : null
                    )
                    : null,

            'column_key' =>
                isset(
                    $column['column_key']
                )
                    ? (
                        $column['column_key']
                        !== null
                            ? (string) $column[
                                'column_key'
                            ]
                            : null
                    )
                    : null,

            'column_index' =>
                isset(
                    $column['column_index']
                )
                    ? (int) $column[
                        'column_index'
                    ]
                    : 0,

            'header' =>
                isset(
                    $column['header']
                )
                    ? (
                        $column['header']
                        !== null
                            ? (string) $column[
                                'header'
                            ]
                            : null
                    )
                    : null,

            'matched_term' =>
                isset(
                    $column['matched_term']
                )
                    ? (
                        $column['matched_term']
                        !== null
                            ? (string) $column[
                                'matched_term'
                            ]
                            : null
                    )
                    : null,

            'coverage_status' =>
                strtolower(
                    trim(
                        (string) (
                            $column[
                                'coverage_status'
                            ]
                            ?? ''
                        )
                    )
                ),

            'non_empty_percent' =>
                isset(
                    $column[
                        'non_empty_percent'
                    ]
                )
                && is_numeric(
                    $column[
                        'non_empty_percent'
                    ]
                )
                    ? (float) $column[
                        'non_empty_percent'
                    ]
                    : null,

            'observed_type_families' =>
                self::typeFamilies(
                    $column[
                        'observed_type_families'
                    ]
                    ?? []
                ),
        ];
    }

    /**
     * @param mixed $families
     *
     * @return list<string>
     */
    private static function typeFamilies(
        mixed $families
    ): array {
        if (! is_array($families)) {
            return [];
        }

        $result = [];

        foreach ($families as $family) {
            $family =
                trim(
                    (string) $family
                );

            if ($family !== '') {
                $result[$family] =
                    true;
            }
        }

        $result =
            array_keys(
                $result
            );

        sort(
            $result,
            SORT_STRING
        );

        return $result;
    }

    /**
     * @param array<string,mixed> $source
     *
     * @return list<array{
     *     domain:string,
     *     group:string
     * }>
     */
    private static function declaredDomains(
        array $source
    ): array {
        $declarations =
            is_array(
                $source[
                    'business_domains'
                ]
                ?? null
            )
                ? array_values(
                    $source[
                        'business_domains'
                    ]
                )
                : [];

        $result = [];

        foreach (
            $declarations
            as $declaration
        ) {
            if (! is_array($declaration)) {
                continue;
            }

            $domain =
                trim(
                    (string) (
                        $declaration['domain']
                        ?? ''
                    )
                );

            $group =
                strtolower(
                    trim(
                        (string) (
                            $declaration['group']
                            ?? ''
                        )
                    )
                );

            if (
                $domain === ''
                || ! in_array(
                    $group,
                    [
                        'operaciones',
                        'gestion',
                        'finanzas',
                    ],
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

    /**
     * @param list<array<string,mixed>> $supportingEvidence
     *
     * @return list<array{
     *     domain:string,
     *     group:string,
     *     source_ids:list<int>
     * }>
     */
    private static function domainContext(
        array $supportingEvidence
    ): array {
        $index = [];

        foreach (
            $supportingEvidence
            as $evidence
        ) {
            $sourceId =
                (int) (
                    $evidence['source_id']
                    ?? 0
                );

            if ($sourceId <= 0) {
                continue;
            }

            foreach (
                $evidence['declared_domains']
                ?? []
                as $declaration
            ) {
                if (! is_array($declaration)) {
                    continue;
                }

                $domain =
                    trim(
                        (string) (
                            $declaration['domain']
                            ?? ''
                        )
                    );

                $group =
                    trim(
                        (string) (
                            $declaration['group']
                            ?? ''
                        )
                    );

                if (
                    $domain === ''
                    || $group === ''
                ) {
                    continue;
                }

                $key =
                    mb_strtolower(
                        $group
                        .'|'
                        .$domain,
                        'UTF-8'
                    );

                if (! isset($index[$key])) {
                    $index[$key] = [
                        'domain' =>
                            $domain,

                        'group' =>
                            $group,

                        'source_ids' =>
                            [],
                    ];
                }

                $index[$key][
                    'source_ids'
                ][$sourceId] =
                    true;
            }
        }

        ksort(
            $index,
            SORT_STRING
        );

        $result = [];

        foreach ($index as $item) {
            $sourceIds =
                array_map(
                    'intval',
                    array_keys(
                        $item[
                            'source_ids'
                        ]
                    )
                );

            sort(
                $sourceIds,
                SORT_NUMERIC
            );

            $result[] = [
                'domain' =>
                    $item['domain'],

                'group' =>
                    $item['group'],

                'source_ids' =>
                    $sourceIds,
            ];
        }

        return $result;
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

            'interpretation_boundary' =>
                'current_evaluated_evidence_only_not_business_absence_or_production_readiness',

            'status_semantics' => [
                self::STATUS_SUPPORTED =>
                    'complete_compatible_structural_evidence_observed',

                self::STATUS_PARTIAL =>
                    'compatible_structural_evidence_observed_with_partial_coverage_only',

                self::STATUS_NOT_SUPPORTED =>
                    'no_usable_compatible_structural_evidence_observed',
            ],

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

    /**
     * @param array<string,mixed> $source
     */
    private static function sourceId(
        array $source
    ): int {
        if (isset($source['id'])) {
            return (int) $source['id'];
        }

        if (
            isset(
                $source['source_asset_id']
            )
        ) {
            return (int) $source[
                'source_asset_id'
            ];
        }

        return 0;
    }
}
