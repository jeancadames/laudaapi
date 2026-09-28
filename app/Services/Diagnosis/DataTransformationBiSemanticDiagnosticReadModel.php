<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiSourceAsset;

/**
 * Read-only semantic inventory of the information declared in one
 * tenant delivery.
 *
 * Important boundaries:
 * - domains remain completely dynamic tenant language;
 * - only Operaciones / Gestión / Finanzas are controlled;
 * - absence of a declared domain never means that the business
 *   does not have that domain;
 * - this projection does not infer BI capabilities, scores,
 *   findings, recommendations or implementation decisions;
 * - no raw client values or samples are accepted or exposed;
 * - canonical domains and the future implementation pipeline are
 *   deliberately outside this read model.
 */
final class DataTransformationBiSemanticDiagnosticReadModel
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
        $sourceCount = 0;
        $declaredSourceCount = 0;
        $declarationCount = 0;

        $unclassifiedSourceIds = [];

        /**
         * @var array<string,array{
         *     domain:string,
         *     groups:array<string,bool>,
         *     source_ids:array<int,bool>
         * }>
         */
        $domainIndex = [];

        /**
         * @var array<string,array<string,array{
         *     domain:string,
         *     source_ids:array<int,bool>,
         *     profiled_source_ids:array<int,bool>
         * }>>
         */
        $groupIndex = [];

        foreach (
            DataTransformationBiSourceAsset::BUSINESS_GROUPS
            as $group
        ) {
            $groupIndex[$group] = [];
        }

        foreach ($sources as $source) {
            if (! is_array($source)) {
                continue;
            }

            $sourceId =
                isset($source['id'])
                    ? (int) $source['id']
                    : (
                        isset($source['source_asset_id'])
                            ? (int) $source['source_asset_id']
                            : 0
                    );

            if ($sourceId <= 0) {
                continue;
            }

            $sourceCount++;

            $diagnosticSummary =
                is_array(
                    $source['diagnostic_summary']
                    ?? null
                )
                    ? $source['diagnostic_summary']
                    : [];

            $hasTechnicalEvidence =
                ($diagnosticSummary['available'] ?? false)
                === true;

            $declarations =
                is_array(
                    $source['business_domains']
                    ?? null
                )
                    ? array_values(
                        $source['business_domains']
                    )
                    : [];

            $validDeclarationCount = 0;

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
                        DataTransformationBiSourceAsset::BUSINESS_GROUPS,
                        true
                    )
                ) {
                    continue;
                }

                $validDeclarationCount++;
                $declarationCount++;

                /*
                 * Case-insensitive grouping is only a presentation /
                 * consistency mechanism.
                 *
                 * It does NOT canonicalize the tenant's business domain.
                 */
                $domainKey =
                    mb_strtolower(
                        $domain,
                        'UTF-8'
                    );

                if (! isset($domainIndex[$domainKey])) {
                    $domainIndex[$domainKey] = [
                        'domain' =>
                            $domain,

                        'groups' =>
                            [],

                        'source_ids' =>
                            [],
                    ];
                }

                $domainIndex[$domainKey]
                    ['groups'][$group] = true;

                $domainIndex[$domainKey]
                    ['source_ids'][$sourceId] = true;

                if (
                    ! isset(
                        $groupIndex[$group][$domainKey]
                    )
                ) {
                    $groupIndex[$group][$domainKey] = [
                        'domain' =>
                            $domain,

                        'source_ids' =>
                            [],

                        'profiled_source_ids' =>
                            [],
                    ];
                }

                $groupIndex[$group][$domainKey]
                    ['source_ids'][$sourceId] = true;

                if ($hasTechnicalEvidence) {
                    $groupIndex[$group][$domainKey]
                        ['profiled_source_ids'][$sourceId] = true;
                }
            }

            if ($validDeclarationCount > 0) {
                $declaredSourceCount++;
            } else {
                $unclassifiedSourceIds[] =
                    $sourceId;
            }
        }

        $groups = [];

        foreach (
            DataTransformationBiSourceAsset::BUSINESS_GROUPS
            as $group
        ) {
            $domains = [];

            foreach (
                $groupIndex[$group]
                as $item
            ) {
                $sourceIds =
                    array_map(
                        'intval',
                        array_keys(
                            $item['source_ids']
                        )
                    );

                $profiledSourceIds =
                    array_map(
                        'intval',
                        array_keys(
                            $item['profiled_source_ids']
                        )
                    );

                sort(
                    $sourceIds,
                    SORT_NUMERIC
                );

                sort(
                    $profiledSourceIds,
                    SORT_NUMERIC
                );

                $sourceTotal =
                    count(
                        $sourceIds
                    );

                $profiledTotal =
                    count(
                        $profiledSourceIds
                    );

                $technicalEvidenceStatus =
                    $profiledTotal === 0
                        ? 'declared_only'
                        : (
                            $profiledTotal === $sourceTotal
                                ? 'profiled'
                                : 'partial'
                        );

                $domains[] = [
                    'domain' =>
                        $item['domain'],

                    'source_ids' =>
                        $sourceIds,

                    'source_count' =>
                        $sourceTotal,

                    'profiled_source_count' =>
                        $profiledTotal,

                    'technical_evidence_status' =>
                        $technicalEvidenceStatus,
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
                    $group,

                'label' =>
                    self::GROUP_LABELS[$group],

                /*
                 * Empty means only that this delivery did not declare
                 * evidence in this group.
                 *
                 * It is never a statement that the company lacks the
                 * corresponding business activity.
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

        $domains = [];
        $classificationConflicts = [];

        foreach (
            $domainIndex
            as $item
        ) {
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

            $groupsForDomain = [];

            foreach (
                DataTransformationBiSourceAsset::BUSINESS_GROUPS
                as $group
            ) {
                if (
                    isset(
                        $item['groups'][$group]
                    )
                ) {
                    $groupsForDomain[] =
                        $group;
                }
            }

            $hasConflict =
                count(
                    $groupsForDomain
                ) > 1;

            $domainPayload = [
                'domain' =>
                    $item['domain'],

                'groups' =>
                    $groupsForDomain,

                'source_ids' =>
                    $sourceIds,

                'has_group_conflict' =>
                    $hasConflict,
            ];

            $domains[] =
                $domainPayload;

            if ($hasConflict) {
                $classificationConflicts[] =
                    $domainPayload;
            }
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

        usort(
            $classificationConflicts,
            static fn (
                array $left,
                array $right
            ): int =>
                strnatcasecmp(
                    (string) $left['domain'],
                    (string) $right['domain']
                )
        );

        $unclassifiedSourceIds =
            array_values(
                array_unique(
                    array_map(
                        'intval',
                        $unclassifiedSourceIds
                    )
                )
            );

        sort(
            $unclassifiedSourceIds,
            SORT_NUMERIC
        );

        return [
            'kind' =>
                'data_bi_semantic_diagnostic',

            'schema_version' =>
                self::SCHEMA_VERSION,

            /*
             * This scope wording is intentional:
             * the projection describes only the evidence delivered
             * for this evaluation.
             */
            'scope' =>
                'declared_delivery_evidence',

            'classification' => [
                'source_count' =>
                    $sourceCount,

                'declared_source_count' =>
                    $declaredSourceCount,

                'unclassified_source_count' =>
                    count(
                        $unclassifiedSourceIds
                    ),

                'declaration_count' =>
                    $declarationCount,

                'unique_domain_count' =>
                    count(
                        $domains
                    ),

                'group_conflict_count' =>
                    count(
                        $classificationConflicts
                    ),
            ],

            'groups' =>
                $groups,

            'domains' =>
                $domains,

            'unclassified_source_ids' =>
                $unclassifiedSourceIds,

            /*
             * Conflicts are descriptive review signals only.
             * They are not readiness blockers.
             */
            'classification_conflicts' =>
                $classificationConflicts,
        ];
    }
}
