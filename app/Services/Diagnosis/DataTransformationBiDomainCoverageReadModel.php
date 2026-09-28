<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiSourceAsset;

/**
 * Delivery-scoped coverage of tenant-declared business domains.
 *
 * This projection combines:
 * - tenant-declared dynamic domains/groups;
 * - source-level technical diagnostic availability;
 * - structural semantic signals observed in supporting sources.
 *
 * Important:
 * Structural signals are attributed to the SOURCES supporting a
 * declared domain. They are not field-to-domain mappings.
 *
 * This read model deliberately does NOT:
 * - infer BI capabilities;
 * - confirm source joins or relationships;
 * - map source columns to business domains;
 * - read raw values or samples;
 * - calculate readiness or scores;
 * - depend on canonical/staging/future implementation models.
 */
final class DataTransformationBiDomainCoverageReadModel
{
    private const SCHEMA_VERSION = 1;

    /**
     * @var array<string,string>
     */
    private const GROUP_LABELS = [
        DataTransformationBiSourceAsset::BUSINESS_GROUP_OPERATIONS =>
            'Operaciones',

        DataTransformationBiSourceAsset::BUSINESS_GROUP_MANAGEMENT =>
            'Gestión',

        DataTransformationBiSourceAsset::BUSINESS_GROUP_FINANCE =>
            'Finanzas',
    ];

    /**
     * @param iterable<int,array<string,mixed>> $sources
     *
     * @return array<string,mixed>
     */
    public static function fromSources(
        iterable $sources
    ): array {
        $sourcePayloads = [];

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

            $sourcePayloads[] =
                $source;
        }

        $semantic =
            DataTransformationBiSemanticDiagnosticReadModel
                ::fromSources(
                    $sourcePayloads
                );

        $sourceIndex = [];

        foreach ($sourcePayloads as $source) {
            $sourceId =
                self::sourceId(
                    $source
                );

            $sourceIndex[$sourceId] =
                $source;
        }

        $groups = [];

        foreach (
            DataTransformationBiSourceAsset::BUSINESS_GROUPS
            as $groupKey
        ) {
            $semanticGroup =
                self::semanticGroup(
                    $semantic,
                    $groupKey
                );

            $domains = [];

            foreach (
                $semanticGroup['domains'] ?? []
                as $domain
            ) {
                if (! is_array($domain)) {
                    continue;
                }

                $sourceIds =
                    array_values(
                        array_map(
                            'intval',
                            is_array(
                                $domain['source_ids']
                                ?? null
                            )
                                ? $domain['source_ids']
                                : []
                        )
                    );

                sort(
                    $sourceIds,
                    SORT_NUMERIC
                );

                $supportingSources = [];

                foreach ($sourceIds as $sourceId) {
                    $source =
                        $sourceIndex[$sourceId]
                        ?? null;

                    if (! is_array($source)) {
                        continue;
                    }

                    $supportingSources[] =
                        self::supportingSource(
                            $source
                        );
                }

                usort(
                    $supportingSources,
                    static fn (
                        array $left,
                        array $right
                    ): int =>
                        ((int) $left['source_id'])
                        <=>
                        ((int) $right['source_id'])
                );

                $domains[] = [
                    'domain' =>
                        (string) (
                            $domain['domain']
                            ?? ''
                        ),

                    'group' =>
                        $groupKey,

                    'source_ids' =>
                        $sourceIds,

                    'source_count' =>
                        count(
                            $sourceIds
                        ),

                    'profiled_source_count' =>
                        (int) (
                            $domain[
                                'profiled_source_count'
                            ]
                            ?? 0
                        ),

                    'technical_evidence_status' =>
                        (string) (
                            $domain[
                                'technical_evidence_status'
                            ]
                            ?? 'declared_only'
                        ),

                    /*
                     * Signals are observed across the sources that
                     * declared this domain.
                     *
                     * They are not field-to-domain assignments.
                     */
                    'signal_scope' =>
                        'supporting_sources_not_domain_field_assignment',

                    'supporting_sources' =>
                        $supportingSources,

                    'observed_structural_signals' =>
                        self::aggregateSignals(
                            $supportingSources
                        ),
                ];
            }

            usort(
                $domains,
                static fn (
                    array $left,
                    array $right
                ): int =>
                    strnatcasecmp(
                        (string) $left['domain'],
                        (string) $right['domain']
                    )
            );

            $groups[] = [
                'key' =>
                    $groupKey,

                'label' =>
                    self::GROUP_LABELS[
                        $groupKey
                    ],

                /*
                 * Empty means only that the evaluated delivery did
                 * not declare evidence in this group.
                 */
                'has_declared_evidence' =>
                    $domains !== [],

                'domain_count' =>
                    count(
                        $domains
                    ),

                'domains' =>
                    $domains,
            ];
        }

        return [
            'kind' =>
                'data_bi_domain_coverage',

            'schema_version' =>
                self::SCHEMA_VERSION,

            'scope' =>
                'declared_delivery_evidence',

            /*
             * Explicitly documents the interpretation boundary.
             */
            'signal_scope' =>
                'supporting_sources_not_domain_field_assignment',

            'classification' => [
                'source_count' =>
                    (int) (
                        $semantic['classification']
                            ['source_count']
                        ?? 0
                    ),

                'declared_source_count' =>
                    (int) (
                        $semantic['classification']
                            ['declared_source_count']
                        ?? 0
                    ),

                'unclassified_source_count' =>
                    (int) (
                        $semantic['classification']
                            ['unclassified_source_count']
                        ?? 0
                    ),

                'unique_domain_count' =>
                    (int) (
                        $semantic['classification']
                            ['unique_domain_count']
                        ?? 0
                    ),

                'group_conflict_count' =>
                    (int) (
                        $semantic['classification']
                            ['group_conflict_count']
                        ?? 0
                    ),
            ],

            'groups' =>
                $groups,

            /*
             * Classification conflicts remain descriptive and are
             * inherited from the B1 semantic inventory.
             */
            'classification_conflicts' =>
                is_array(
                    $semantic[
                        'classification_conflicts'
                    ]
                    ?? null
                )
                    ? $semantic[
                        'classification_conflicts'
                    ]
                    : [],

            'unclassified_source_ids' =>
                is_array(
                    $semantic[
                        'unclassified_source_ids'
                    ]
                    ?? null
                )
                    ? $semantic[
                        'unclassified_source_ids'
                    ]
                    : [],
        ];
    }

    /**
     * @param array<string,mixed> $source
     *
     * @return array<string,mixed>
     */
    private static function supportingSource(
        array $source
    ): array {
        $sourceId =
            self::sourceId(
                $source
            );

        $diagnostic =
            is_array(
                $source['diagnostic_summary']
                ?? null
            )
                ? $source[
                    'diagnostic_summary'
                ]
                : [];

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

        $signals =
            is_array(
                $structural['signals']
                ?? null
            )
                ? array_values(
                    $structural['signals']
                )
                : [];

        $signalKeys = [];

        foreach ($signals as $signal) {
            if (
                ! is_array($signal)
                || ! isset(
                    $signal['key']
                )
            ) {
                continue;
            }

            $key =
                trim(
                    (string) $signal['key']
                );

            if ($key !== '') {
                $signalKeys[$key] =
                    true;
            }
        }

        $signalKeys =
            array_keys(
                $signalKeys
            );

        sort(
            $signalKeys,
            SORT_STRING
        );

        return [
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

            'technical_evidence_available' =>
                (
                    $diagnostic['available']
                    ?? false
                ) === true,

            'structural_signals_available' =>
                (
                    $structural['available']
                    ?? false
                ) === true,

            'matched_column_count' =>
                max(
                    0,
                    (int) (
                        $structural[
                            'matched_column_count'
                        ]
                        ?? 0
                    )
                ),

            'unmatched_column_count' =>
                max(
                    0,
                    (int) (
                        $structural[
                            'unmatched_column_count'
                        ]
                        ?? 0
                    )
                ),

            'signal_keys' =>
                $signalKeys,

            /*
             * Keep the safe B2 signal payload for explainability.
             * No raw source values exist in this projection.
             */
            'signals' =>
                $signals,
        ];
    }

    /**
     * @param list<array<string,mixed>> $supportingSources
     *
     * @return list<array<string,mixed>>
     */
    private static function aggregateSignals(
        array $supportingSources
    ): array {
        $index = [];

        foreach (
            $supportingSources
            as $source
        ) {
            $sourceId =
                (int) (
                    $source['source_id']
                    ?? 0
                );

            foreach (
                $source['signals'] ?? []
                as $signal
            ) {
                if (
                    ! is_array($signal)
                    || ! isset(
                        $signal['key']
                    )
                ) {
                    continue;
                }

                $key =
                    trim(
                        (string) $signal['key']
                    );

                if ($key === '') {
                    continue;
                }

                if (! isset($index[$key])) {
                    $index[$key] = [
                        'key' =>
                            $key,

                        'label' =>
                            isset(
                                $signal['label']
                            )
                                ? (string) $signal[
                                    'label'
                                ]
                                : $key,

                        'source_ids' =>
                            [],

                        'column_count' =>
                            0,

                        'coverage_counts' => [
                            'complete' => 0,
                            'partial' => 0,
                            'empty' => 0,
                            'unprofiled' => 0,
                        ],

                        'observed_type_families' =>
                            [],
                    ];
                }

                if ($sourceId > 0) {
                    $index[$key][
                        'source_ids'
                    ][$sourceId] =
                        true;
                }

                $columns =
                    is_array(
                        $signal['columns']
                        ?? null
                    )
                        ? $signal[
                            'columns'
                        ]
                        : [];

                foreach ($columns as $column) {
                    if (! is_array($column)) {
                        continue;
                    }

                    $index[$key][
                        'column_count'
                    ]++;

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
                            $index[$key][
                                'coverage_counts'
                            ]
                        )
                    ) {
                        $index[$key]
                            ['coverage_counts']
                            [$coverage]++;
                    }

                    $families =
                        is_array(
                            $column[
                                'observed_type_families'
                            ]
                            ?? null
                        )
                            ? $column[
                                'observed_type_families'
                            ]
                            : [];

                    foreach (
                        $families
                        as $family
                    ) {
                        $family =
                            trim(
                                (string) $family
                            );

                        if ($family !== '') {
                            $index[$key]
                                ['observed_type_families']
                                [$family] =
                                true;
                        }
                    }
                }
            }
        }

        $signals = [];

        foreach ($index as $item) {
            $sourceIds =
                array_map(
                    'intval',
                    array_keys(
                        $item['source_ids']
                    )
                );

            sort(
                $sourceIds,
                SORT_NUMERIC
            );

            $families =
                array_keys(
                    $item[
                        'observed_type_families'
                    ]
                );

            sort(
                $families,
                SORT_STRING
            );

            $signals[] = [
                'key' =>
                    $item['key'],

                'label' =>
                    $item['label'],

                'source_ids' =>
                    $sourceIds,

                'source_count' =>
                    count(
                        $sourceIds
                    ),

                'column_count' =>
                    (int) $item[
                        'column_count'
                    ],

                'coverage_counts' =>
                    $item[
                        'coverage_counts'
                    ],

                'observed_type_families' =>
                    $families,
            ];
        }

        usort(
            $signals,
            static fn (
                array $left,
                array $right
            ): int =>
                strcmp(
                    (string) $left['key'],
                    (string) $right['key']
                )
        );

        return $signals;
    }

    /**
     * @param array<string,mixed> $semantic
     *
     * @return array<string,mixed>
     */
    private static function semanticGroup(
        array $semantic,
        string $groupKey
    ): array {
        foreach (
            $semantic['groups'] ?? []
            as $group
        ) {
            if (
                is_array($group)
                && ($group['key'] ?? null)
                    === $groupKey
            ) {
                return $group;
            }
        }

        return [
            'key' =>
                $groupKey,

            'domains' =>
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
