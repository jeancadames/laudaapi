<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataTransformationBiStructuralSemanticSignalsReadModel;
use PHPUnit\Framework\TestCase;

final class DataTransformationBiStructuralSemanticSignalsReadModelTest
    extends TestCase
{
    public function test_missing_profile_is_safe_and_unavailable(): void
    {
        $result =
            DataTransformationBiStructuralSemanticSignalsReadModel
                ::fromSnapshot(
                    null
                );

        self::assertFalse(
            $result['available']
        );

        self::assertSame(
            0,
            $result['signal_count']
        );

        self::assertSame(
            [],
            $result['signals']
        );

        self::assertSame(
            [],
            $result['matched_columns']
        );
    }

    public function test_column_headers_generate_explainable_structural_signals(): void
    {
        $result =
            DataTransformationBiStructuralSemanticSignalsReadModel
                ::fromSnapshot([
                    'version' => 1,
                    'kind' =>
                        'source_asset_value_profile',
                    'format' =>
                        'xlsx',

                    'sheets' => [
                        [
                            'index' => 0,
                            'name' => 'Clientes',
                            'columns' => [
                                [
                                    'key' => 'col_1',
                                    'index' => 0,
                                    'header' =>
                                        'CodigoCliente',

                                    'profiled_value_count' =>
                                        100,

                                    'non_empty_count' =>
                                        100,

                                    'empty_count' =>
                                        0,

                                    'primitive_types' => [
                                        'integer' => 0,
                                        'decimal' => 0,
                                        'text' => 100,
                                        'boolean' => 0,
                                        'other' => 0,
                                    ],
                                ],
                                [
                                    'key' => 'col_2',
                                    'index' => 1,
                                    'header' =>
                                        'fecha_nacimiento',

                                    'profiled_value_count' =>
                                        100,

                                    'non_empty_count' =>
                                        95,

                                    'empty_count' =>
                                        5,

                                    'primitive_types' => [
                                        'integer' => 0,
                                        'decimal' => 0,
                                        'text' => 95,
                                        'boolean' => 0,
                                        'other' => 0,
                                    ],
                                ],
                                [
                                    'key' => 'col_3',
                                    'index' => 2,
                                    'header' =>
                                        'Ciudad',

                                    'profiled_value_count' =>
                                        100,

                                    'non_empty_count' =>
                                        80,

                                    'empty_count' =>
                                        20,

                                    'primitive_types' => [
                                        'integer' => 0,
                                        'decimal' => 0,
                                        'text' => 80,
                                        'boolean' => 0,
                                        'other' => 0,
                                    ],
                                ],
                                [
                                    'key' => 'col_4',
                                    'index' => 3,
                                    'header' =>
                                        'Correo Electronico',

                                    'profiled_value_count' =>
                                        100,

                                    'non_empty_count' =>
                                        90,

                                    'empty_count' =>
                                        10,

                                    'primitive_types' => [
                                        'integer' => 0,
                                        'decimal' => 0,
                                        'text' => 90,
                                        'boolean' => 0,
                                        'other' => 0,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);

        self::assertTrue(
            $result['available']
        );

        $keys =
            array_column(
                $result['signals'],
                'key'
            );

        self::assertContains(
            'identifier',
            $keys
        );

        self::assertContains(
            'temporal',
            $keys
        );

        self::assertContains(
            'geographic',
            $keys
        );

        self::assertContains(
            'contact',
            $keys
        );

        self::assertSame(
            4,
            $result['matched_column_count']
        );

        $date =
            collect(
                $result['matched_columns']
            )->firstWhere(
                'header',
                'fecha_nacimiento'
            );

        self::assertSame(
            'partial',
            $date['coverage_status']
        );

        self::assertSame(
            95.0,
            $date['non_empty_percent']
        );

        self::assertSame(
            ['text'],
            $date['observed_type_families']
        );
    }

    public function test_financial_quantity_and_product_signals_are_structural_only(): void
    {
        $result =
            DataTransformationBiStructuralSemanticSignalsReadModel
                ::fromSnapshot([
                    'kind' =>
                        'source_asset_value_profile',

                    'sheets' => [
                        [
                            'index' => 0,
                            'columns' => [
                                [
                                    'key' => 'sku',
                                    'header' => 'SKU',
                                    'profiled_value_count' => 10,
                                    'non_empty_count' => 10,
                                    'empty_count' => 0,
                                    'primitive_types' => [
                                        'text' => 10,
                                    ],
                                ],
                                [
                                    'key' => 'stock',
                                    'header' => 'Stock',
                                    'profiled_value_count' => 10,
                                    'non_empty_count' => 10,
                                    'empty_count' => 0,
                                    'primitive_types' => [
                                        'integer' => 10,
                                    ],
                                ],
                                [
                                    'key' => 'saldo',
                                    'header' => 'Saldo',
                                    'profiled_value_count' => 10,
                                    'non_empty_count' => 8,
                                    'empty_count' => 2,
                                    'primitive_types' => [
                                        'decimal' => 8,
                                    ],
                                ],
                                [
                                    'key' => 'limite_credito',
                                    'header' => 'Limite_Credito',
                                    'profiled_value_count' => 10,
                                    'non_empty_count' => 7,
                                    'empty_count' => 3,
                                    'primitive_types' => [
                                        'decimal' => 7,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);

        $keys =
            array_column(
                $result['signals'],
                'key'
            );

        self::assertContains(
            'product_reference',
            $keys
        );

        self::assertContains(
            'quantity',
            $keys
        );

        self::assertContains(
            'monetary',
            $keys
        );

        self::assertContains(
            'financial_terms',
            $keys
        );
    }

    public function test_matching_uses_token_boundaries_not_arbitrary_substrings(): void
    {
        $result =
            DataTransformationBiStructuralSemanticSignalsReadModel
                ::fromSnapshot([
                    'kind' =>
                        'source_asset_value_profile',

                    'sheets' => [
                        [
                            'index' => 0,
                            'columns' => [
                                [
                                    /*
                                     * "candidate" contains characters
                                     * resembling "date", but it must not
                                     * become a temporal signal.
                                     */
                                    'key' => 'candidate',
                                    'header' =>
                                        'candidate',

                                    'profiled_value_count' =>
                                        5,

                                    'non_empty_count' =>
                                        5,

                                    'empty_count' =>
                                        0,

                                    'primitive_types' => [
                                        'text' => 5,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);

        self::assertSame(
            0,
            $result['matched_column_count']
        );

        self::assertSame(
            [],
            $result['signals']
        );
    }

    public function test_raw_values_and_samples_are_never_projected(): void
    {
        $result =
            DataTransformationBiStructuralSemanticSignalsReadModel
                ::fromSnapshot([
                    'kind' =>
                        'source_asset_value_profile',

                    'sheets' => [
                        [
                            'index' => 0,
                            'columns' => [
                                [
                                    'key' => 'email',
                                    'header' => 'Email',
                                    'profiled_value_count' => 1,
                                    'non_empty_count' => 1,
                                    'empty_count' => 0,
                                    'primitive_types' => [
                                        'text' => 1,
                                    ],

                                    /*
                                     * Deliberately injected unsafe keys.
                                     * The read model must ignore them.
                                     */
                                    'sample' =>
                                        'persona@example.com',

                                    'values' => [
                                        'persona@example.com',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);

        $encoded =
            json_encode(
                $result,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            );

        self::assertStringNotContainsString(
            'persona@example.com',
            $encoded
        );

        self::assertStringNotContainsString(
            '"sample"',
            $encoded
        );

        self::assertStringNotContainsString(
            '"values"',
            $encoded
        );
    }

    public function test_read_model_has_no_capability_scoring_or_future_pipeline_dependency(): void
    {
        $source =
            (string) file_get_contents(
                dirname(
                    __DIR__,
                    3
                )
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiStructuralSemanticSignalsReadModel.php'
            );

        foreach ([
            'DataTransformationBiSourceDomainRegistry',
            'DataTransformationBiStaging',
            'DataTransformationBiCanonical',
            'DataTransformationBiPreparedDataset',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $source
            );
        }

        foreach ([
            "'score' =>",
            '"score" =>',
            "'readiness_score' =>",
            '"readiness_score" =>',
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
}
