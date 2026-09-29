<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DataTransformationBiEvaluation;
use App\Services\Diagnosis\DataTransformationBiDiagnosticAnalysisReadModel;
use App\Services\Diagnosis\DataTransformationBiTenantPublishedEvaluationProjection;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class DataTransformationBiDiagnosticAnalysisV3ContractTest
    extends TestCase
{
    public function test_current_diagnostic_schema_is_v3_with_exact_nine_rule_catalog(): void
    {
        $reflection =
            new ReflectionClass(
                DataTransformationBiDiagnosticAnalysisReadModel::class
            );

        self::assertSame(
            3,
            $reflection->getConstant(
                'SCHEMA_VERSION'
            )
        );

        $rules =
            $reflection->getConstant(
                'ANALYSIS_RULES'
            );

        self::assertIsArray(
            $rules
        );

        self::assertCount(
            9,
            $rules
        );

        $actual = [];

        foreach ($rules as $rule) {
            self::assertIsArray(
                $rule
            );

            $actual[
                (string) $rule['key']
            ] =
                (string) $rule[
                    'signal_key'
                ];
        }

        self::assertSame(
            [
                'record_identification' =>
                    'identifier',

                'geographic_segmentation' =>
                    'geographic',

                'temporal_analysis' =>
                    'temporal',

                'monetary_analysis' =>
                    'monetary',

                'contact_analysis' =>
                    'contact',

                'quantity_analysis' =>
                    'quantity',

                'product_reference_analysis' =>
                    'product_reference',

                'financial_terms_analysis' =>
                    'financial_terms',

                'classification_analysis' =>
                    'classification_status',
            ],
            $actual
        );
    }

    public function test_v2_and_v3_are_known_tenant_schemas(): void
    {
        $reflection =
            new ReflectionClass(
                DataTransformationBiTenantPublishedEvaluationProjection::class
            );

        self::assertSame(
            [
                2,
                3,
            ],
            $reflection->getConstant(
                'KNOWN_DIAGNOSTIC_ANALYSIS_SCHEMA_VERSIONS'
            )
        );
    }

    public function test_tenant_accepts_matching_frozen_v2_and_v3_without_recomputation(): void
    {
        foreach (
            [
                2,
                3,
            ]
            as $schema
        ) {
            $evaluation =
                new DataTransformationBiEvaluation();

            $evaluation
                ->diagnostic_analysis_schema_version =
                $schema;

            $evaluation
                ->diagnostic_analysis_snapshot = [
                    'kind' =>
                        'data_bi_diagnostic_analysis',

                    'schema_version' =>
                        $schema,

                    'available' =>
                        true,

                    'analyses' => [
                        [
                            'key' =>
                                'FROZEN_SCHEMA_'.$schema,

                            'label' =>
                                'Frozen schema '.$schema,

                            'status' =>
                                'supported',

                            'status_basis' =>
                                'must_not_escape',

                            'required_signal_keys' => [
                                'identifier',
                            ],

                            'observed_signal_keys' => [
                                'identifier',
                            ],

                            'supporting_source_ids' => [
                                777,
                            ],

                            'supporting_source_count' =>
                                1,

                            'evidence_column_count' =>
                                1,

                            'coverage_counts' => [
                                'complete' =>
                                    1,
                            ],

                            'declared_domain_context' =>
                                [],

                            'supporting_evidence' => [
                                [
                                    'source_id' =>
                                        777,

                                    'display_name' =>
                                        'Fuente segura',

                                    'source_object_name' =>
                                        'dbo.SECRET',

                                    'declared_domains' =>
                                        [],

                                    'matched_column_count' =>
                                        1,

                                    'coverage_counts' => [
                                        'complete' =>
                                            1,
                                    ],

                                    'columns' => [
                                        [
                                            'column_key' =>
                                                'cliente_id',

                                            'header' =>
                                                'ClienteId',

                                            'coverage_status' =>
                                                'complete',

                                            'observed_type_families' => [
                                                'integer',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];

            $result =
                $this->tenantDiagnosticAnalysis(
                    $evaluation
                );

            self::assertTrue(
                $result['available']
            );

            self::assertCount(
                1,
                $result['analyses']
            );

            self::assertSame(
                'FROZEN_SCHEMA_'.$schema,
                $result[
                    'analyses'
                ][0][
                    'key'
                ]
            );

            self::assertArrayNotHasKey(
                'status_basis',
                $result['analyses'][0]
            );

            self::assertArrayNotHasKey(
                'supporting_source_ids',
                $result['analyses'][0]
            );

            self::assertArrayNotHasKey(
                'source_id',
                $result[
                    'analyses'
                ][0][
                    'supporting_evidence'
                ][0]
            );

            self::assertArrayNotHasKey(
                'source_object_name',
                $result[
                    'analyses'
                ][0][
                    'supporting_evidence'
                ][0]
            );
        }
    }

    public function test_unknown_schema_and_schema_mismatch_fail_closed(): void
    {
        $unknown =
            new DataTransformationBiEvaluation();

        $unknown
            ->diagnostic_analysis_schema_version =
            91;

        $unknown
            ->diagnostic_analysis_snapshot = [
                'kind' =>
                    'data_bi_diagnostic_analysis',

                'schema_version' =>
                    91,

                'available' =>
                    true,

                'analyses' => [
                    [
                        'key' =>
                            'DO_NOT_EXPOSE',

                        'label' =>
                            'Unknown',

                        'status' =>
                            'supported',
                    ],
                ],
            ];

        self::assertSame(
            [
                'available' =>
                    false,

                'analyses' =>
                    [],
            ],
            $this->tenantDiagnosticAnalysis(
                $unknown
            )
        );

        $mismatch =
            new DataTransformationBiEvaluation();

        $mismatch
            ->diagnostic_analysis_schema_version =
            2;

        $mismatch
            ->diagnostic_analysis_snapshot = [
                'kind' =>
                    'data_bi_diagnostic_analysis',

                'schema_version' =>
                    3,

                'available' =>
                    true,

                'analyses' =>
                    [],
            ];

        self::assertSame(
            [
                'available' =>
                    false,

                'analyses' =>
                    [],
            ],
            $this->tenantDiagnosticAnalysis(
                $mismatch
            )
        );
    }

    public function test_v3_catalog_remains_simple_single_signal_only(): void
    {
        $reflection =
            new ReflectionClass(
                DataTransformationBiDiagnosticAnalysisReadModel::class
            );

        $rules =
            $reflection->getConstant(
                'ANALYSIS_RULES'
            );

        $keys =
            array_map(
                static fn (array $rule): string =>
                    (string) $rule['key'],
                $rules
            );

        foreach (
            [
                'margin_analysis',
                'portfolio_analysis',
                'aging_analysis',
                'credit_risk_analysis',
                'recurrence_analysis',
                'cohort_analysis',
                'churn_analysis',
                'inventory_rotation_analysis',
                'supplier_reliability_analysis',
            ]
            as $forbidden
        ) {
            self::assertNotContains(
                $forbidden,
                $keys
            );
        }

        foreach ($rules as $rule) {
            self::assertIsString(
                $rule['signal_key']
            );

            self::assertNotSame(
                '',
                trim(
                    $rule['signal_key']
                )
            );
        }
    }

    private function tenantDiagnosticAnalysis(
        DataTransformationBiEvaluation $evaluation
    ): array {
        $projectionReflection =
            new ReflectionClass(
                DataTransformationBiTenantPublishedEvaluationProjection::class
            );

        $projection =
            $projectionReflection
                ->newInstanceWithoutConstructor();

        $method =
            new ReflectionMethod(
                DataTransformationBiTenantPublishedEvaluationProjection::class,
                'tenantDiagnosticAnalysis'
            );

        $method->setAccessible(
            true
        );

        /** @var array<string,mixed> $result */
        $result =
            $method->invoke(
                $projection,
                $evaluation
            );

        return $result;
    }
}
