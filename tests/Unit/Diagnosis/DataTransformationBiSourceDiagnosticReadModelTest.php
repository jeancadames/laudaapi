<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataTransformationBiSourceDiagnosticReadModel;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class DataTransformationBiSourceDiagnosticReadModelTest
    extends TestCase
{
    public function test_empty_snapshot_is_safe_and_unavailable(): void
    {
        $result =
            DataTransformationBiSourceDiagnosticReadModel
                ::fromSnapshot(
                    null
                );

        self::assertFalse(
            $result['available']
        );

        self::assertSame(
            0,
            $result['volume']['column_count']
        );

        self::assertSame(
            0,
            $result['coverage']['profiled_cell_count']
        );

        self::assertNull(
            $result['coverage']['completeness_percent']
        );
    }

    public function test_dynamic_multi_sheet_profile_is_aggregated(): void
    {
        $result =
            DataTransformationBiSourceDiagnosticReadModel
                ::fromSnapshot([
                    'version' => 1,
                    'kind' =>
                        'source_asset_value_profile',
                    'format' => 'xlsx',
                    'source_row_count' => 5,
                    'profiled_row_count' => 5,
                    'sheet_count' => 2,
                    'full_scan' => true,

                    'sheets' => [
                        [
                            'name' => 'Anything A',
                            'columns' => [
                                [
                                    'header' => 'Alpha',
                                    'profiled_value_count' => 5,
                                    'non_empty_count' => 5,
                                    'empty_count' => 0,
                                    'primitive_types' => [
                                        'integer' => 5,
                                        'decimal' => 0,
                                        'text' => 0,
                                        'boolean' => 0,
                                        'other' => 0,
                                    ],
                                ],
                                [
                                    'header' => 'Beta',
                                    'profiled_value_count' => 5,
                                    'non_empty_count' => 3,
                                    'empty_count' => 2,
                                    'primitive_types' => [
                                        'integer' => 0,
                                        'decimal' => 0,
                                        'text' => 3,
                                        'boolean' => 0,
                                        'other' => 0,
                                    ],
                                ],
                            ],
                        ],

                        [
                            'name' => 'Anything B',
                            'columns' => [
                                [
                                    'header' => 'Gamma',
                                    'profiled_value_count' => 5,
                                    'non_empty_count' => 0,
                                    'empty_count' => 5,
                                    'primitive_types' => [
                                        'integer' => 0,
                                        'decimal' => 0,
                                        'text' => 0,
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

        self::assertSame(
            2,
            $result['volume']['sheet_count']
        );

        self::assertSame(
            3,
            $result['volume']['column_count']
        );

        self::assertSame(
            15,
            $result['coverage']['profiled_cell_count']
        );

        self::assertSame(
            8,
            $result['coverage']['non_empty_cell_count']
        );

        self::assertSame(
            7,
            $result['coverage']['empty_cell_count']
        );

        self::assertSame(
            53.33,
            $result['coverage']['completeness_percent']
        );

        self::assertSame(
            46.67,
            $result['coverage']['missing_percent']
        );

        self::assertSame(
            1,
            $result['columns']['complete_count']
        );

        self::assertSame(
            2,
            $result['columns']['with_missing_count']
        );

        self::assertSame(
            1,
            $result['columns']['fully_empty_count']
        );
    }

    public function test_integer_and_decimal_are_one_numeric_family(): void
    {
        $result =
            DataTransformationBiSourceDiagnosticReadModel
                ::fromSnapshot(
                    $this->snapshotWithTypes([
                        'integer' => 4,
                        'decimal' => 6,
                        'text' => 0,
                        'boolean' => 0,
                        'other' => 0,
                    ])
                );

        self::assertSame(
            0,
            $result['columns']['mixed_type_count']
        );
    }

    public function test_text_and_numeric_values_are_mixed_types(): void
    {
        $result =
            DataTransformationBiSourceDiagnosticReadModel
                ::fromSnapshot(
                    $this->snapshotWithTypes([
                        'integer' => 2,
                        'decimal' => 0,
                        'text' => 8,
                        'boolean' => 0,
                        'other' => 0,
                    ])
                );

        self::assertSame(
            1,
            $result['columns']['mixed_type_count']
        );
    }

    public function test_projection_is_domain_agnostic(): void
    {
        $reflection =
            new ReflectionClass(
                DataTransformationBiSourceDiagnosticReadModel::class
            );

        $path =
            $reflection->getFileName();

        self::assertIsString(
            $path
        );

        $source =
            file_get_contents(
                $path
            );

        self::assertIsString(
            $source
        );

        foreach ([
            'domain_key',
            'DataTransformationBiStandardIntakeSchema',
            'DataTransformationBiCanonical',
            'DataTransformationBiStaging',
            'normalized_payload',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    /**
     * @param array<string,int> $types
     *
     * @return array<string,mixed>
     */
    private function snapshotWithTypes(
        array $types
    ): array {
        return [
            'version' => 1,
            'kind' =>
                'source_asset_value_profile',
            'format' => 'csv',
            'source_row_count' => 10,
            'profiled_row_count' => 10,
            'sheet_count' => 1,
            'full_scan' => true,

            'sheets' => [
                [
                    'name' => 'Dynamic',
                    'columns' => [
                        [
                            'header' => 'Any column',
                            'profiled_value_count' => 10,
                            'non_empty_count' => 10,
                            'empty_count' => 0,
                            'primitive_types' =>
                                $types,
                        ],
                    ],
                ],
            ],
        ];
    }
}
