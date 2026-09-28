<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataTransformationBiDiagnosticAnalysisReadModel;
use PHPUnit\Framework\TestCase;

final class DataTransformationBiDiagnosticAnalysisReadModelTest
    extends TestCase
{
    public function test_v2_pinned_evidence_builds_available_analysis_context(): void
    {
        $result =
            DataTransformationBiDiagnosticAnalysisReadModel
                ::fromEvidence(
                    $this->evidenceV2(),
                    3,
                    str_repeat(
                        'a',
                        64
                    )
                );

        self::assertTrue(
            $result['available']
        );

        self::assertSame(
            'data_bi_diagnostic_analysis',
            $result['kind']
        );

        self::assertSame(
            2,
            $result['schema_version']
        );

        self::assertSame(
            'evaluated_delivery',
            $result['scope']
        );

        self::assertSame(
            3,
            $result['evidence']
                ['evidence_version']
        );

        self::assertSame(
            2,
            $result['evidence']
                ['schema_version']
        );

        self::assertSame(
            2,
            $result['source_count']
        );

        self::assertIsArray(
            $result[
                'semantic_diagnostic'
            ]
        );

        self::assertIsArray(
            $result[
                'domain_coverage'
            ]
        );

        self::assertIsArray(
            $result[
                'cross_source_relationship_candidates'
            ]
        );

        self::assertSame(
            4,
            $result['analysis_count']
        );

        self::assertCount(
            4,
            $result['analyses']
        );
    }

    public function test_v1_evidence_is_not_silently_promoted_to_new_analysis_contract(): void
    {
        $evidence =
            $this->evidenceV2();

        $evidence['schema_version'] =
            1;

        unset(
            $evidence[
                'semantic_diagnostic'
            ]
        );

        foreach (
            $evidence['sources']
            as &$source
        ) {
            unset(
                $source[
                    'business_domains'
                ],
                $source[
                    'structural_semantic_signals'
                ]
            );
        }

        unset($source);

        $result =
            DataTransformationBiDiagnosticAnalysisReadModel
                ::fromEvidence(
                    $evidence,
                    1,
                    str_repeat(
                        'b',
                        64
                    )
                );

        self::assertFalse(
            $result['available']
        );

        self::assertSame(
            'unsupported_evidence_contract',
            $result[
                'unavailable_reason'
            ]
        );

        self::assertSame(
            [],
            $result['analyses']
        );
    }

    public function test_relationship_candidates_are_derived_from_pinned_source_evidence(): void
    {
        $result =
            DataTransformationBiDiagnosticAnalysisReadModel
                ::fromEvidence(
                    $this->evidenceV2(),
                    1,
                    str_repeat(
                        'c',
                        64
                    )
                );

        $relationships =
            $result[
                'cross_source_relationship_candidates'
            ];

        self::assertSame(
            1,
            $relationships[
                'candidate_count'
            ]
        );

        self::assertSame(
            'cliente',
            $relationships[
                'candidates'
            ][0][
                'shared_identifier_qualifier'
            ]
        );

        self::assertFalse(
            $relationships[
                'candidates'
            ][0][
                'join_confirmed'
            ]
        );
    }

    public function test_domain_coverage_is_derived_from_pinned_source_evidence(): void
    {
        $result =
            DataTransformationBiDiagnosticAnalysisReadModel
                ::fromEvidence(
                    $this->evidenceV2(),
                    1,
                    str_repeat(
                        'd',
                        64
                    )
                );

        $coverage =
            $result[
                'domain_coverage'
            ];

        self::assertSame(
            'declared_delivery_evidence',
            $coverage['scope']
        );

        self::assertSame(
            2,
            $coverage[
                'classification'
            ][
                'unique_domain_count'
            ]
        );
    }

    public function test_read_model_has_no_live_database_query_or_future_pipeline_dependency(): void
    {
        $source =
            (string) file_get_contents(
                dirname(
                    __DIR__,
                    3
                )
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiDiagnosticAnalysisReadModel.php'
            );

        foreach ([
            '::query()',
            'DB::',
            'DataTransformationBiSourceDomainRegistry',
            'DataTransformationBiStaging',
            'DataTransformationBiCanonical',
            'DataTransformationBiSourceAssetMapping',
            'DataTransformationBiPreparedDataset',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_read_model_does_not_generate_findings_recommendations_or_scores(): void
    {
        $source =
            strtolower(
                (string) file_get_contents(
                    dirname(
                        __DIR__,
                        3
                    )
                    .'/app/Services/Diagnosis/'
                    .'DataTransformationBiDiagnosticAnalysisReadModel.php'
                )
            );

        foreach ([
            "'readiness_score' =>",
            "'risk_score' =>",
            "'opportunity_score' =>",
            "'finding_type' =>",
            "'recommendation' =>",
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }


    /**
     * @return array<string,mixed>
     */
    private function evidenceV2(): array
    {
        $sources = [
            $this->source(
                10,
                'Clientes',
                [
                    [
                        'domain' =>
                            'Clientes',
                        'group' =>
                            'gestion',
                    ],
                ],
                'ClienteId'
            ),

            $this->source(
                20,
                'Ventas',
                [
                    [
                        'domain' =>
                            'Ventas',
                        'group' =>
                            'operaciones',
                    ],
                ],
                'CodigoCliente'
            ),
        ];

        return [
            'kind' =>
                'data_bi_diagnostic_evaluation_evidence',

            'schema_version' =>
                2,

            'session_id' =>
                999,

            'company_id' =>
                777,

            'transformation_implementation_request_id' =>
                555,

            'submission_manifest_sha256' =>
                str_repeat(
                    'f',
                    64
                ),

            'source_count' =>
                2,

            'sources' =>
                $sources,

            'semantic_diagnostic' =>
                \App\Services\Diagnosis\DataTransformationBiSemanticDiagnosticReadModel
                    ::fromSources(
                        $sources
                    ),
        ];
    }

    /**
     * @param list<array{domain:string,group:string}> $domains
     *
     * @return array<string,mixed>
     */
    private function source(
        int $id,
        string $name,
        array $domains,
        string $identifierHeader
    ): array {
        return [
            'source_asset_id' =>
                $id,

            'display_name' =>
                $name,

            'source_object_name' =>
                'dbo.'.$name,

            'origin_system' =>
                'sql_server',

            'delivery_format' =>
                'xlsx',

            'profiling_job_uuid' =>
                '11111111-2222-4333-8444-'
                .str_pad(
                    (string) $id,
                    12,
                    '0',
                    STR_PAD_LEFT
                ),

            'profiled_at' =>
                '2026-09-28T22:00:00+00:00',

            'diagnostic_summary' => [
                'available' =>
                    true,
            ],

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
                    1,

                'unmatched_column_count' =>
                    0,

                'signals' => [
                    [
                        'key' =>
                            'identifier',

                        'label' =>
                            'Identificación',

                        'column_count' =>
                            1,

                        'columns' => [
                            [
                                'sheet_index' =>
                                    0,

                                'sheet_name' =>
                                    'Datos',

                                'column_key' =>
                                    strtolower(
                                        $identifierHeader
                                    ),

                                'column_index' =>
                                    0,

                                'header' =>
                                    $identifierHeader,

                                'matched_term' =>
                                    'id',

                                'coverage_status' =>
                                    'complete',

                                'non_empty_percent' =>
                                    100.0,

                                'observed_type_families' => [
                                    'text',
                                ],
                            ],
                        ],
                    ],
                ],

                'matched_columns' =>
                    [],
            ],
        ];
    }
}
