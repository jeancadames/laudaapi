<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataTransformationBiCrossSourceRelationshipCandidatesReadModel;
use PHPUnit\Framework\TestCase;

final class DataTransformationBiCrossSourceRelationshipCandidatesReadModelTest
    extends TestCase
{
    public function test_empty_or_single_source_has_no_cross_source_candidate(): void
    {
        $empty =
            DataTransformationBiCrossSourceRelationshipCandidatesReadModel
                ::fromSources([]);

        self::assertSame(
            0,
            $empty['candidate_count']
        );

        $single =
            DataTransformationBiCrossSourceRelationshipCandidatesReadModel
                ::fromSources([
                    $this->source(
                        10,
                        [
                            $this->identifierColumn(
                                'ClienteId'
                            ),
                        ]
                    ),
                ]);

        self::assertSame(
            0,
            $single['candidate_count']
        );
    }

    public function test_qualified_identifier_creates_structural_candidate_only(): void
    {
        $result =
            DataTransformationBiCrossSourceRelationshipCandidatesReadModel
                ::fromSources([
                    $this->source(
                        10,
                        [
                            $this->identifierColumn(
                                'ClienteId',
                                ['numeric']
                            ),
                        ],
                        [
                            [
                                'domain' =>
                                    'Clientes',
                                'group' =>
                                    'gestion',
                            ],
                        ]
                    ),

                    $this->source(
                        20,
                        [
                            $this->identifierColumn(
                                'CodigoCliente',
                                ['text']
                            ),
                        ],
                        [
                            [
                                'domain' =>
                                    'Ventas',
                                'group' =>
                                    'operaciones',
                            ],
                        ]
                    ),
                ]);

        self::assertSame(
            1,
            $result['candidate_count']
        );

        $candidate =
            $result['candidates'][0];

        self::assertSame(
            'cliente',
            $candidate[
                'shared_identifier_qualifier'
            ]
        );

        self::assertSame(
            'structural_candidate',
            $candidate['status']
        );

        self::assertSame(
            'shared_qualified_identifier_structure',
            $candidate['evidence_type']
        );

        self::assertSame(
            [10, 20],
            $candidate['source_ids']
        );

        self::assertFalse(
            $candidate[
                'join_confirmed'
            ]
        );

        self::assertTrue(
            $candidate[
                'requires_implementation_validation'
            ]
        );

        /*
         * Different observed type families do not confirm or reject
         * a relationship. They remain descriptive evidence only.
         */
        self::assertSame(
            ['numeric'],
            $candidate['sources'][0]
                ['identifier_columns'][0]
                ['observed_type_families']
        );

        self::assertSame(
            ['text'],
            $candidate['sources'][1]
                ['identifier_columns'][0]
                ['observed_type_families']
        );
    }

    public function test_generic_identifier_names_are_insufficient(): void
    {
        $result =
            DataTransformationBiCrossSourceRelationshipCandidatesReadModel
                ::fromSources([
                    $this->source(
                        10,
                        [
                            $this->identifierColumn(
                                'Id'
                            ),
                            $this->identifierColumn(
                                'Codigo'
                            ),
                        ]
                    ),

                    $this->source(
                        20,
                        [
                            $this->identifierColumn(
                                'ID'
                            ),
                            $this->identifierColumn(
                                'Code'
                            ),
                        ]
                    ),
                ]);

        self::assertSame(
            0,
            $result['candidate_count']
        );

        self::assertSame(
            [],
            $result['candidates']
        );
    }

    public function test_different_qualified_identifiers_do_not_create_candidate(): void
    {
        $result =
            DataTransformationBiCrossSourceRelationshipCandidatesReadModel
                ::fromSources([
                    $this->source(
                        10,
                        [
                            $this->identifierColumn(
                                'ClienteId'
                            ),
                        ]
                    ),

                    $this->source(
                        20,
                        [
                            $this->identifierColumn(
                                'ProductoId'
                            ),
                        ]
                    ),
                ]);

        self::assertSame(
            0,
            $result['candidate_count']
        );
    }

    public function test_multiple_matching_columns_are_grouped_in_one_source_pair_candidate(): void
    {
        $result =
            DataTransformationBiCrossSourceRelationshipCandidatesReadModel
                ::fromSources([
                    $this->source(
                        10,
                        [
                            $this->identifierColumn(
                                'ClienteId'
                            ),
                            $this->identifierColumn(
                                'CodigoCliente'
                            ),
                        ]
                    ),

                    $this->source(
                        20,
                        [
                            $this->identifierColumn(
                                'Cliente_Id'
                            ),
                        ]
                    ),
                ]);

        self::assertSame(
            1,
            $result['candidate_count']
        );

        self::assertSame(
            2,
            $result['candidates'][0]
                ['sources'][0]
                ['identifier_column_count']
        );

        self::assertSame(
            1,
            $result['candidates'][0]
                ['sources'][1]
                ['identifier_column_count']
        );
    }

    public function test_raw_or_unknown_column_fields_are_not_projected(): void
    {
        $left =
            $this->identifierColumn(
                'ClienteId'
            );

        $left['sample'] =
            'SECRET_CUSTOMER_123';

        $left['values'] = [
            'SECRET_CUSTOMER_123',
        ];

        $result =
            DataTransformationBiCrossSourceRelationshipCandidatesReadModel
                ::fromSources([
                    $this->source(
                        10,
                        [$left]
                    ),

                    $this->source(
                        20,
                        [
                            $this->identifierColumn(
                                'CodigoCliente'
                            ),
                        ]
                    ),
                ]);

        $json =
            json_encode(
                $result,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
            );

        self::assertStringNotContainsString(
            'SECRET_CUSTOMER_123',
            $json
        );

        self::assertStringNotContainsString(
            '"sample"',
            $json
        );

        self::assertStringNotContainsString(
            '"values"',
            $json
        );
    }

    public function test_projection_has_no_scoring_capability_or_future_pipeline_dependency(): void
    {
        $source =
            (string) file_get_contents(
                dirname(
                    __DIR__,
                    3
                )
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiCrossSourceRelationshipCandidatesReadModel.php'
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
            "'confidence' =>",
            '"confidence" =>',
            "'capability_status' =>",
            '"capability_status" =>',
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
     * @param list<array<string,mixed>> $columns
     * @param list<array{domain:string,group:string}> $domains
     *
     * @return array<string,mixed>
     */
    private function source(
        int $id,
        array $columns,
        array $domains = []
    ): array {
        return [
            'id' =>
                $id,

            'display_name' =>
                'Source '.$id,

            'source_object_name' =>
                'source_'.$id,

            'business_domains' =>
                $domains,

            'structural_semantic_signals' => [
                'kind' =>
                    'data_bi_structural_semantic_signals',

                'schema_version' =>
                    1,

                'available' =>
                    true,

                'signal_count' =>
                    1,

                'matched_column_count' =>
                    count(
                        $columns
                    ),

                'unmatched_column_count' =>
                    0,

                'signals' => [
                    [
                        'key' =>
                            'identifier',

                        'label' =>
                            'Identificación',

                        'column_count' =>
                            count(
                                $columns
                            ),

                        'columns' =>
                            $columns,
                    ],
                ],

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
    private function identifierColumn(
        string $header,
        array $families = ['text']
    ): array {
        return [
            'sheet_index' =>
                0,

            'sheet_name' =>
                'Sheet 1',

            'column_key' =>
                strtolower(
                    str_replace(
                        [
                            ' ',
                            '-',
                        ],
                        '_',
                        $header
                    )
                ),

            'column_index' =>
                0,

            'header' =>
                $header,

            'matched_term' =>
                'id',

            'coverage_status' =>
                'complete',

            'non_empty_percent' =>
                100.0,

            'observed_type_families' =>
                $families,
        ];
    }
}
