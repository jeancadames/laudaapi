<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataTransformationBiDiagnosticAnalysisReadModel;
use App\Services\Diagnosis\DataTransformationBiSemanticDiagnosticReadModel;
use PHPUnit\Framework\TestCase;

final class DataTransformationBiDiagnosticAnalysisRulesTest
    extends TestCase
{
    public function test_first_four_analyses_are_deterministic_and_evidence_based(): void
    {
        $result =
            DataTransformationBiDiagnosticAnalysisReadModel
                ::fromEvidence(
                    $this->evidence(),
                    7,
                    str_repeat(
                        'a',
                        64
                    )
                );

        self::assertTrue(
            $result['available']
        );

        self::assertSame(
            3,
            $result['schema_version']
        );

        self::assertSame(
            9,
            $result['analysis_count']
        );

        self::assertSame(
            [
                'record_identification',
                'geographic_segmentation',
                'temporal_analysis',
                'monetary_analysis',
            ],
            array_slice(
                array_column(
                    $result['analyses'],
                    'key'
                ),
                0,
                4
            )
        );

        $statuses = [];

        foreach (
            $result['analyses']
            as $analysis
        ) {
            $statuses[
                $analysis['key']
            ] =
                $analysis['status'];
        }

        self::assertSame(
            'supported',
            $statuses[
                'record_identification'
            ]
        );

        self::assertSame(
            'supported',
            $statuses[
                'geographic_segmentation'
            ]
        );

        self::assertSame(
            'partial',
            $statuses[
                'temporal_analysis'
            ]
        );

        self::assertSame(
            'not_supported_by_current_evidence',
            $statuses[
                'monetary_analysis'
            ]
        );
    }

    public function test_supported_requires_complete_structural_coverage(): void
    {
        $analysis =
            $this->analysis(
                'record_identification'
            );

        self::assertSame(
            'supported',
            $analysis['status']
        );

        self::assertSame(
            'complete_structural_signal_coverage',
            $analysis['status_basis']
        );

        self::assertSame(
            1,
            $analysis[
                'coverage_counts'
            ]['complete']
        );
    }

    public function test_partial_means_signal_exists_but_only_partial_coverage_is_observed(): void
    {
        $analysis =
            $this->analysis(
                'temporal_analysis'
            );

        self::assertSame(
            'partial',
            $analysis['status']
        );

        self::assertSame(
            'partial_structural_signal_coverage_only',
            $analysis['status_basis']
        );

        self::assertSame(
            1,
            $analysis[
                'coverage_counts'
            ]['partial']
        );
    }

    public function test_not_supported_means_only_current_delivery_lacks_usable_evidence(): void
    {
        $result =
            DataTransformationBiDiagnosticAnalysisReadModel
                ::fromEvidence(
                    $this->evidence(),
                    7,
                    str_repeat(
                        'b',
                        64
                    )
                );

        $analysis =
            collect(
                $result['analyses']
            )->firstWhere(
                'key',
                'monetary_analysis'
            );

        self::assertIsArray(
            $analysis
        );

        self::assertSame(
            'not_supported_by_current_evidence',
            $analysis['status']
        );

        self::assertSame(
            'current_evaluated_evidence_only_not_business_absence_or_production_readiness',
            $result[
                'interpretation_boundary'
            ]
        );

        self::assertSame(
            'La entrega evaluada no proporciona evidencia estructural utilizable suficiente para sustentar este análisis.',
            $analysis[
                'evidence_note'
            ]
        );
    }

    public function test_domain_context_remains_source_level_and_never_field_to_domain_mapping(): void
    {
        $analysis =
            $this->analysis(
                'geographic_segmentation'
            );

        self::assertSame(
            'supporting_sources_not_field_to_domain_mapping',
            $analysis[
                'domain_context_scope'
            ]
        );

        self::assertSame(
            [
                [
                    'domain' =>
                        'Crédito',

                    'group' =>
                        'finanzas',

                    'source_ids' => [
                        10,
                    ],
                ],

                [
                    'domain' =>
                        'Clientes',

                    'group' =>
                        'gestion',

                    'source_ids' => [
                        10,
                    ],
                ],
            ],
            $analysis[
                'declared_domain_context'
            ]
        );

        self::assertSame(
            [
                'Clientes',
                'Crédito',
            ],
            array_column(
                $analysis[
                    'supporting_evidence'
                ][0][
                    'declared_domains'
                ],
                'domain'
            )
        );
    }

    public function test_safe_structural_evidence_is_explainable_without_raw_values(): void
    {
        $analysis =
            $this->analysis(
                'geographic_segmentation'
            );

        $column =
            $analysis[
                'supporting_evidence'
            ][0][
                'columns'
            ][0];

        self::assertSame(
            'Ciudad',
            $column['header']
        );

        self::assertSame(
            'complete',
            $column[
                'coverage_status'
            ]
        );

        self::assertSame(
            100.0,
            $column[
                'non_empty_percent'
            ]
        );

        self::assertSame(
            ['text'],
            $column[
                'observed_type_families'
            ]
        );

        $json =
            json_encode(
                $analysis,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
            );

        self::assertStringNotContainsString(
            'SECRET_RAW_VALUE',
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

    public function test_empty_or_unprofiled_signal_does_not_become_supported(): void
    {
        $analysis =
            $this->analysis(
                'monetary_analysis'
            );

        self::assertSame(
            1,
            $analysis[
                'coverage_counts'
            ]['empty']
        );

        self::assertSame(
            0,
            $analysis[
                'coverage_counts'
            ]['complete']
        );

        self::assertSame(
            0,
            $analysis[
                'coverage_counts'
            ]['partial']
        );

        self::assertSame(
            'not_supported_by_current_evidence',
            $analysis['status']
        );
    }

    public function test_rule_engine_has_no_scores_findings_recommendations_or_future_pipeline_dependency(): void
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
            'DataTransformationBiSourceDomainRegistry',
            'DataTransformationBiStaging',
            'DataTransformationBiCanonical',
            'DataTransformationBiSourceAssetMapping',
            'DataTransformationBiPreparedDataset',
            '::query()',
            'DB::',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $source
            );
        }

        $lower =
            strtolower(
                $source
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
                $lower
            );
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function analysis(
        string $key
    ): array {
        $result =
            DataTransformationBiDiagnosticAnalysisReadModel
                ::fromEvidence(
                    $this->evidence(),
                    7,
                    str_repeat(
                        'c',
                        64
                    )
                );

        foreach (
            $result['analyses']
            as $analysis
        ) {
            if (
                $analysis['key']
                === $key
            ) {
                return $analysis;
            }
        }

        self::fail(
            'Analysis not found: '.$key
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function evidence(): array
    {
        $sources = [
            [
                'source_asset_id' =>
                    10,

                'display_name' =>
                    'Clientes',

                'source_object_name' =>
                    'dbo.Clientes',

                'diagnostic_summary' => [
                    'available' =>
                        true,
                ],

                'business_domains' => [
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

                'structural_semantic_signals' => [
                    'kind' =>
                        'data_bi_structural_semantic_signals',

                    'schema_version' =>
                        1,

                    'available' =>
                        true,

                    'signals' => [
                        $this->signal(
                            'identifier',
                            'Identificación',
                            'ClienteId',
                            'complete',
                            100.0,
                            ['text']
                        ),

                        $this->signal(
                            'geographic',
                            'Geografía',
                            'Ciudad',
                            'complete',
                            100.0,
                            ['text']
                        ),

                        $this->signal(
                            'temporal',
                            'Temporal',
                            'FechaRegistro',
                            'partial',
                            80.0,
                            ['text']
                        ),

                        $this->signal(
                            'monetary',
                            'Monetario',
                            'Saldo',
                            'empty',
                            0.0,
                            []
                        ),
                    ],
                ],
            ],
        ];

        return [
            'kind' =>
                'data_bi_diagnostic_evaluation_evidence',

            'schema_version' =>
                2,

            'session_id' =>
                1,

            'company_id' =>
                1,

            'transformation_implementation_request_id' =>
                1,

            'submission_manifest_sha256' =>
                str_repeat(
                    'f',
                    64
                ),

            'source_count' =>
                1,

            'sources' =>
                $sources,

            'semantic_diagnostic' =>
                DataTransformationBiSemanticDiagnosticReadModel
                    ::fromSources(
                        $sources
                    ),
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
        string $coverage,
        float $nonEmptyPercent,
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
                        'Datos',

                    'column_key' =>
                        strtolower(
                            $header
                        ),

                    'column_index' =>
                        0,

                    'header' =>
                        $header,

                    'matched_term' =>
                        strtolower(
                            $header
                        ),

                    'coverage_status' =>
                        $coverage,

                    'non_empty_percent' =>
                        $nonEmptyPercent,

                    'observed_type_families' =>
                        $families,

                    /*
                     * Deliberately ignored by the read model.
                     */
                    'sample' =>
                        'SECRET_RAW_VALUE',

                    'values' => [
                        'SECRET_RAW_VALUE',
                    ],
                ],
            ],
        ];
    }
}
