<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiSourceAsset;

/**
 * Conservative cross-source structural relationship candidates.
 *
 * A candidate means only that two DIFFERENT delivered sources expose
 * structurally qualified identifier columns with the same normalized
 * qualifier.
 *
 * Examples:
 * - ClienteId / CodigoCliente => qualifier "cliente"
 * - ProductoId / CodigoProducto => qualifier "producto"
 *
 * Generic identifiers such as "Id", "Codigo", "Code" or "Key" are
 * intentionally insufficient.
 *
 * This projection deliberately does NOT:
 * - read raw client values;
 * - inspect samples;
 * - calculate confidence or scores;
 * - confirm joins;
 * - infer referential integrity;
 * - map fields to canonical entities;
 * - infer BI capabilities;
 * - depend on staging, mapping or implementation models.
 */
final class DataTransformationBiCrossSourceRelationshipCandidatesReadModel
{
    private const SCHEMA_VERSION = 1;

    private const STATUS =
        'structural_candidate';

    private const EVIDENCE_TYPE =
        'shared_qualified_identifier_structure';

    /**
     * Generic structural identifier tokens.
     *
     * Removing these tokens must leave a meaningful qualifier.
     *
     * @var list<string>
     */
    private const IDENTIFIER_MARKERS = [
        'id',
        'uuid',
        'codigo',
        'code',
        'clave',
        'key',
        'identificador',
        'identifier',
        'pk',
        'fk',
        'primary',
        'foreign',
    ];

    /**
     * @param iterable<int,array<string,mixed>> $sources
     *
     * @return array<string,mixed>
     */
    public static function fromSources(
        iterable $sources
    ): array {
        $sourceIndex = [];

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

            $sourceIndex[$sourceId] =
                self::sourceDescriptor(
                    $source
                );
        }

        ksort(
            $sourceIndex,
            SORT_NUMERIC
        );

        $sourceIds =
            array_map(
                'intval',
                array_keys(
                    $sourceIndex
                )
            );

        $candidates = [];

        $sourceCount =
            count(
                $sourceIds
            );

        for (
            $leftPosition = 0;
            $leftPosition < $sourceCount;
            $leftPosition++
        ) {
            for (
                $rightPosition =
                    $leftPosition + 1;
                $rightPosition < $sourceCount;
                $rightPosition++
            ) {
                $leftId =
                    $sourceIds[
                        $leftPosition
                    ];

                $rightId =
                    $sourceIds[
                        $rightPosition
                    ];

                $left =
                    $sourceIndex[
                        $leftId
                    ];

                $right =
                    $sourceIndex[
                        $rightId
                    ];

                $sharedQualifiers =
                    array_values(
                        array_intersect(
                            array_keys(
                                $left[
                                    'identifier_groups'
                                ]
                            ),
                            array_keys(
                                $right[
                                    'identifier_groups'
                                ]
                            )
                        )
                    );

                sort(
                    $sharedQualifiers,
                    SORT_STRING
                );

                foreach (
                    $sharedQualifiers
                    as $qualifier
                ) {
                    $leftColumns =
                        $left[
                            'identifier_groups'
                        ][$qualifier];

                    $rightColumns =
                        $right[
                            'identifier_groups'
                        ][$qualifier];

                    $candidates[] = [
                        'candidate_key' =>
                            self::candidateKey(
                                $leftId,
                                $rightId,
                                $qualifier
                            ),

                        'status' =>
                            self::STATUS,

                        'evidence_type' =>
                            self::EVIDENCE_TYPE,

                        /*
                         * This qualifier is structural normalization
                         * only, never a canonical business entity.
                         */
                        'shared_identifier_qualifier' =>
                            $qualifier,

                        'source_ids' => [
                            $leftId,
                            $rightId,
                        ],

                        'join_confirmed' =>
                            false,

                        'requires_implementation_validation' =>
                            true,

                        'sources' => [
                            self::candidateSource(
                                $left,
                                $leftColumns
                            ),

                            self::candidateSource(
                                $right,
                                $rightColumns
                            ),
                        ],
                    ];
                }
            }
        }

        usort(
            $candidates,
            static function (
                array $left,
                array $right
            ): int {
                $qualifierCompare =
                    strcmp(
                        (string) $left[
                            'shared_identifier_qualifier'
                        ],
                        (string) $right[
                            'shared_identifier_qualifier'
                        ]
                    );

                if ($qualifierCompare !== 0) {
                    return $qualifierCompare;
                }

                $leftIds =
                    $left['source_ids'];

                $rightIds =
                    $right['source_ids'];

                return (
                    ((int) $leftIds[0])
                    <=>
                    ((int) $rightIds[0])
                ) ?: (
                    ((int) $leftIds[1])
                    <=>
                    ((int) $rightIds[1])
                );
            }
        );

        return [
            'kind' =>
                'data_bi_cross_source_relationship_candidates',

            'schema_version' =>
                self::SCHEMA_VERSION,

            'scope' =>
                'declared_delivery_evidence',

            'method' =>
                self::EVIDENCE_TYPE,

            'source_count' =>
                count(
                    $sourceIndex
                ),

            'candidate_count' =>
                count(
                    $candidates
                ),

            /*
             * Important diagnostic boundary:
             * candidates require commercial implementation validation.
             */
            'join_confirmation' =>
                'not_performed',

            'candidates' =>
                $candidates,
        ];
    }

    /**
     * @param array<string,mixed> $source
     *
     * @return array<string,mixed>
     */
    private static function sourceDescriptor(
        array $source
    ): array {
        $sourceId =
            self::sourceId(
                $source
            );

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

        $identifierGroups = [];

        foreach (
            $structural['signals']
            ?? []
            as $signal
        ) {
            if (
                ! is_array($signal)
                || ($signal['key'] ?? null)
                    !== 'identifier'
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

                $qualifier =
                    self::identifierQualifier(
                        $column
                    );

                if ($qualifier === null) {
                    continue;
                }

                $identifierGroups[
                    $qualifier
                ][] =
                    self::safeIdentifierColumn(
                        $column
                    );
            }
        }

        foreach (
            $identifierGroups
            as &$columns
        ) {
            usort(
                $columns,
                static function (
                    array $left,
                    array $right
                ): int {
                    $sheetCompare =
                        ((int) (
                            $left['sheet_index']
                            ?? 0
                        ))
                        <=>
                        ((int) (
                            $right['sheet_index']
                            ?? 0
                        ));

                    if ($sheetCompare !== 0) {
                        return $sheetCompare;
                    }

                    $columnCompare =
                        ((int) (
                            $left['column_index']
                            ?? 0
                        ))
                        <=>
                        ((int) (
                            $right['column_index']
                            ?? 0
                        ));

                    if ($columnCompare !== 0) {
                        return $columnCompare;
                    }

                    return strcmp(
                        (string) (
                            $left['header']
                            ?? ''
                        ),
                        (string) (
                            $right['header']
                            ?? ''
                        )
                    );
                }
            );
        }

        unset($columns);

        ksort(
            $identifierGroups,
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

            /*
             * Domain declarations are explanatory context only.
             * They are NOT used to create the candidate.
             */
            'declared_domains' =>
                self::declaredDomains(
                    $source
                ),

            'identifier_groups' =>
                $identifierGroups,
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @param list<array<string,mixed>> $columns
     *
     * @return array<string,mixed>
     */
    private static function candidateSource(
        array $source,
        array $columns
    ): array {
        return [
            'source_id' =>
                (int) $source[
                    'source_id'
                ],

            'display_name' =>
                $source[
                    'display_name'
                ],

            'source_object_name' =>
                $source[
                    'source_object_name'
                ],

            'declared_domains' =>
                $source[
                    'declared_domains'
                ],

            'identifier_column_count' =>
                count(
                    $columns
                ),

            'identifier_columns' =>
                $columns,
        ];
    }

    /**
     * @param array<string,mixed> $column
     */
    private static function identifierQualifier(
        array $column
    ): ?string {
        $label =
            isset($column['header'])
            && trim(
                (string) $column['header']
            ) !== ''
                ? (string) $column[
                    'header'
                ]
                : (
                    isset(
                        $column['column_key']
                    )
                        ? (string) $column[
                            'column_key'
                        ]
                        : ''
                );

        $normalized =
            self::normalizeLabel(
                $label
            );

        if ($normalized === '') {
            return null;
        }

        $tokens =
            preg_split(
                '/\s+/',
                $normalized
            )
            ?: [];

        $qualifierTokens = [];

        foreach ($tokens as $token) {
            if (
                $token === ''
                || in_array(
                    $token,
                    self::IDENTIFIER_MARKERS,
                    true
                )
            ) {
                continue;
            }

            $qualifierTokens[] =
                $token;
        }

        if ($qualifierTokens === []) {
            /*
             * Generic Id / Codigo / Code / Key / UUID, etc.
             */
            return null;
        }

        $qualifier =
            implode(
                ' ',
                $qualifierTokens
            );

        /*
         * Keep this deliberately conservative.
         * No one/two-character residual qualifier creates a relation.
         */
        if (
            mb_strlen(
                $qualifier,
                'UTF-8'
            ) < 3
        ) {
            return null;
        }

        if (
            preg_match(
                '/[a-z]/',
                $qualifier
            ) !== 1
        ) {
            return null;
        }

        return $qualifier;
    }

    /**
     * @param array<string,mixed> $column
     *
     * @return array<string,mixed>
     */
    private static function safeIdentifierColumn(
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
                    ? (string) $column[
                        'matched_term'
                    ]
                    : null,

            'coverage_status' =>
                isset(
                    $column['coverage_status']
                )
                    ? (string) $column[
                        'coverage_status'
                    ]
                    : null,

            'non_empty_percent' =>
                isset(
                    $column['non_empty_percent']
                )
                && is_numeric(
                    $column['non_empty_percent']
                )
                    ? (float) $column[
                        'non_empty_percent'
                    ]
                    : null,

            'observed_type_families' =>
                self::safeTypeFamilies(
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
    private static function safeTypeFamilies(
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
                $source['business_domains']
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

    private static function normalizeLabel(
        string $value
    ): string {
        $value =
            preg_replace(
                '/([a-z0-9])([A-Z])/u',
                '$1 $2',
                $value
            )
            ?? $value;

        $value =
            preg_replace(
                '/([^\d\s])(\d)/u',
                '$1 $2',
                $value
            )
            ?? $value;

        $value =
            preg_replace(
                '/(\d)([^\d\s])/u',
                '$1 $2',
                $value
            )
            ?? $value;

        $value =
            mb_strtolower(
                $value,
                'UTF-8'
            );

        $value =
            strtr(
                $value,
                [
                    'á' => 'a',
                    'é' => 'e',
                    'í' => 'i',
                    'ó' => 'o',
                    'ú' => 'u',
                    'ü' => 'u',
                    'ñ' => 'n',
                ]
            );

        $value =
            preg_replace(
                '/[^a-z0-9]+/',
                ' ',
                $value
            )
            ?? '';

        return trim(
            preg_replace(
                '/\s+/',
                ' ',
                $value
            )
            ?? ''
        );
    }

    private static function candidateKey(
        int $leftId,
        int $rightId,
        string $qualifier
    ): string {
        return $leftId
            .':'
            .$rightId
            .':'
            .str_replace(
                ' ',
                '-',
                $qualifier
            );
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
