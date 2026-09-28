<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataTransformationBiDomainCoverageReadModel;
use PHPUnit\Framework\TestCase;

final class DataTransformationBiDomainCoverageReadModelTest
    extends TestCase
{
    public function test_empty_delivery_keeps_three_groups_without_business_absence_claim(): void
    {
        $result =
            DataTransformationBiDomainCoverageReadModel
                ::fromSources([]);

        self::assertSame(
            'declared_delivery_evidence',
            $result['scope']
        );

        self::assertSame(
            3,
            count(
                $result['groups']
            )
        );

        foreach ($result['groups'] as $group) {
            self::assertFalse(
                $group[
                    'has_declared_evidence'
                ]
            );

            self::assertSame(
                [],
                $group['domains']
            );
        }
    }

    public function test_domain_aggregates_supporting_sources_and_structural_signals(): void
    {
        $result =
            DataTransformationBiDomainCoverageReadModel
                ::fromSources([
                    $this->source(
                        10,
                        [
                            [
                                'domain' =>
                                    'Clientes',
                                'group' =>
                                    'gestion',
                            ],
                        ],
                        [
                            $this->signal(
                                'identifier',
                                'Identificación',
                                'CodigoCliente',
                                'complete',
                                ['text']
                            ),
                            $this->signal(
                                'contact',
                                'Contacto',
                                'Email',
                                'partial',
                                ['text']
                            ),
                        ]
                    ),

                    $this->source(
                        20,
                        [
                            [
                                'domain' =>
                                    'Clientes',
                                'group' =>
                                    'gestion',
                            ],
                        ],
                        [
                            $this->signal(
                                'geographic',
                                'Geografía',
                                'Ciudad',
                                'partial',
                                ['text']
                            ),
                            $this->signal(
                                'identifier',
                                'Identificación',
                                'ClienteId',
                                'complete',
                                ['numeric']
                            ),
                        ]
                    ),
                ]);

        $gestion =
            $this->group(
                $result,
                'gestion'
            );

        self::assertSame(
            1,
            $gestion['domain_count']
        );

        $clientes =
            $gestion['domains'][0];

        self::assertSame(
            'Clientes',
            $clientes['domain']
        );

        self::assertSame(
            [10, 20],
            $clientes['source_ids']
        );

        self::assertSame(
            2,
            $clientes['source_count']
        );

        self::assertSame(
            'profiled',
            $clientes[
                'technical_evidence_status'
            ]
        );

        self::assertSame(
            'supporting_sources_not_domain_field_assignment',
            $clientes['signal_scope']
        );

        $signalKeys =
            array_column(
                $clientes[
                    'observed_structural_signals'
                ],
                'key'
            );

        self::assertSame(
            [
                'contact',
                'geographic',
                'identifier',
            ],
            $signalKeys
        );

        $identifier =
            $this->signalByKey(
                $clientes[
                    'observed_structural_signals'
                ],
                'identifier'
            );

        self::assertSame(
            [10, 20],
            $identifier['source_ids']
        );

        self::assertSame(
            2,
            $identifier['column_count']
        );

        self::assertSame(
            2,
            $identifier[
                'coverage_counts'
            ]['complete']
        );

        self::assertSame(
            ['numeric', 'text'],
            $identifier[
                'observed_type_families'
            ]
        );
    }

    public function test_one_source_with_multiple_domains_does_not_become_field_to_domain_mapping(): void
    {
        $result =
            DataTransformationBiDomainCoverageReadModel
                ::fromSources([
                    $this->source(
                        30,
                        [
                            [
                                'domain' =>
                                    'Clientes',
                                'group' =>
                                    'gestion',
                            ],
                            [
                                'domain' =>
                                    'Crédito',
                                'group' =>
                                    'finanzas',
                            ],
                        ],
                        [
                            $this->signal(
                                'contact',
                                'Contacto',
                                'Email',
                                'complete',
                                ['text']
                            ),
                            $this->signal(
                                'monetary',
                                'Valores monetarios',
                                'Saldo',
                                'partial',
                                ['numeric']
                            ),
                        ]
                    ),
                ]);

        $clientes =
            $this->group(
                $result,
                'gestion'
            )['domains'][0];

        $credito =
            $this->group(
                $result,
                'finanzas'
            )['domains'][0];

        self::assertSame(
            'supporting_sources_not_domain_field_assignment',
            $clientes['signal_scope']
        );

        self::assertSame(
            'supporting_sources_not_domain_field_assignment',
            $credito['signal_scope']
        );

        /*
         * Same source-level signals appear as supporting-source
         * evidence for both declarations. The model deliberately
         * does not claim which field belongs to which domain.
         */
        self::assertSame(
            array_column(
                $clientes[
                    'observed_structural_signals'
                ],
                'key'
            ),
            array_column(
                $credito[
                    'observed_structural_signals'
                ],
                'key'
            )
        );
    }

    public function test_unclassified_source_does_not_create_a_domain(): void
    {
        $result =
            DataTransformationBiDomainCoverageReadModel
                ::fromSources([
                    $this->source(
                        40,
                        [],
                        [
                            $this->signal(
                                'identifier',
                                'Identificación',
                                'Id',
                                'complete',
                                ['numeric']
                            ),
                        ]
                    ),
                ]);

        self::assertSame(
            [40],
            $result[
                'unclassified_source_ids'
            ]
        );

        self::assertSame(
            0,
            $result['classification']
                ['unique_domain_count']
        );

        foreach ($result['groups'] as $group) {
            self::assertSame(
                [],
                $group['domains']
            );
        }
    }

    public function test_group_conflict_is_preserved_not_resolved(): void
    {
        $result =
            DataTransformationBiDomainCoverageReadModel
                ::fromSources([
                    $this->source(
                        50,
                        [
                            [
                                'domain' =>
                                    'Clientes',
                                'group' =>
                                    'gestion',
                            ],
                        ],
                        []
                    ),
                    $this->source(
                        60,
                        [
                            [
                                'domain' =>
                                    'clientes',
                                'group' =>
                                    'operaciones',
                            ],
                        ],
                        []
                    ),
                ]);

        self::assertSame(
            1,
            $result['classification']
                ['group_conflict_count']
        );

        self::assertCount(
            1,
            $result[
                'classification_conflicts'
            ]
        );
    }

    public function test_projection_has_no_capability_relationship_scoring_or_future_pipeline_dependency(): void
    {
        $source =
            (string) file_get_contents(
                dirname(
                    __DIR__,
                    3
                )
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiDomainCoverageReadModel.php'
            );

        foreach ([
            'DataTransformationBiSourceDomainRegistry',
            'DataTransformationBiStaging',
            'DataTransformationBiCanonical',
            'DataTransformationBiPreparedDataset',
            'DataTransformationBiSourceAssetMapping',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $source
            );
        }

        foreach ([
            "'score' =>",
            '"score" =>',
            "'capability_status' =>",
            '"capability_status" =>',
            "'join_confirmed' =>",
            '"join_confirmed" =>',
            "'relationship_candidates' =>",
            '"relationship_candidates" =>',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                strtolower(
                    $source
                )
            );
        }
    }

    /**
     * @param list<array{domain:string,group:string}> $domains
     * @param list<array<string,mixed>> $signals
     *
     * @return array<string,mixed>
     */
    private function source(
        int $id,
        array $domains,
        array $signals
    ): array {
        $matchedColumnCount = 0;

        foreach ($signals as $signal) {
            $matchedColumnCount +=
                count(
                    $signal['columns']
                    ?? []
                );
        }

        return [
            'id' =>
                $id,

            'display_name' =>
                'Source '.$id,

            'source_object_name' =>
                'source_'.$id,

            'business_domains' =>
                $domains,

            'diagnostic_summary' => [
                'available' =>
                    true,
            ],

            'structural_semantic_signals' => [
                'kind' =>
                    'data_bi_structural_semantic_signals',

                'schema_version' =>
                    1,

                'available' =>
                    true,

                'signal_count' =>
                    count(
                        $signals
                    ),

                'matched_column_count' =>
                    $matchedColumnCount,

                'unmatched_column_count' =>
                    0,

                'signals' =>
                    $signals,

                'matched_columns' =>
                    [],
            ],
        ];
    }

    /**
     * @param list<string> $families
     *
     * @return array<string,mixed>
     */
    private function signal(
        string $key,
        string $label,
        string $header,
        string $coverageStatus,
        array $families
    ): array {
        return [
            'key' =>
                $key,

            'label' =>
                $label,

            'column_count' =>
                1,

            'columns' => [
                [
                    'sheet_index' =>
                        0,

                    'sheet_name' =>
                        'Sheet 1',

                    'column_key' =>
                        strtolower(
                            str_replace(
                                ' ',
                                '_',
                                $header
                            )
                        ),

                    'column_index' =>
                        0,

                    'header' =>
                        $header,

                    'matched_term' =>
                        $key,

                    'coverage_status' =>
                        $coverageStatus,

                    'non_empty_percent' =>
                        $coverageStatus === 'complete'
                            ? 100.0
                            : 80.0,

                    'observed_type_families' =>
                        $families,
                ],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $result
     *
     * @return array<string,mixed>
     */
    private function group(
        array $result,
        string $key
    ): array {
        foreach ($result['groups'] as $group) {
            if (
                ($group['key'] ?? null)
                === $key
            ) {
                return $group;
            }
        }

        self::fail(
            'Missing group '.$key
        );
    }

    /**
     * @param list<array<string,mixed>> $signals
     *
     * @return array<string,mixed>
     */
    private function signalByKey(
        array $signals,
        string $key
    ): array {
        foreach ($signals as $signal) {
            if (
                ($signal['key'] ?? null)
                === $key
            ) {
                return $signal;
            }
        }

        self::fail(
            'Missing signal '.$key
        );
    }
}
