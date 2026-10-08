<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DataTransformationBiCanonicalField;
use App\Models\DataTransformationBiSourceAssetFieldMapping;
use App\Services\Diagnosis\DataTransformationBiMappingProjector;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DataTransformationBiMappingProjectorContractTest
    extends TestCase
{
    public function test_projects_direct_default_and_unmapped_fields(): void
    {
        $projector =
            new DataTransformationBiMappingProjector();

        $result =
            $projector->project(
                [
                    'column_1' =>
                        ' C-001 ',

                    'column_2' =>
                        ' Cliente Uno ',
                ],
                [
                    [
                        'field_key' =>
                            'customer_id',

                        'data_type' =>
                            DataTransformationBiCanonicalField
                                ::TYPE_TEXT,
                    ],
                    [
                        'field_key' =>
                            'name',

                        'data_type' =>
                            DataTransformationBiCanonicalField
                                ::TYPE_TEXT,
                    ],
                    [
                        'field_key' =>
                            'country',

                        'data_type' =>
                            DataTransformationBiCanonicalField
                                ::TYPE_TEXT,
                    ],
                    [
                        'field_key' =>
                            'notes',

                        'data_type' =>
                            DataTransformationBiCanonicalField
                                ::TYPE_TEXT,
                    ],
                ],
                [
                    [
                        'canonical_field_key' =>
                            'customer_id',

                        'mapping_type' =>
                            DataTransformationBiSourceAssetFieldMapping
                                ::TYPE_DIRECT,

                        'source_column_key' =>
                            'column_1',
                    ],
                    [
                        'canonical_field_key' =>
                            'name',

                        'mapping_type' =>
                            DataTransformationBiSourceAssetFieldMapping
                                ::TYPE_DIRECT,

                        'source_column_key' =>
                            'column_2',
                    ],
                    [
                        'canonical_field_key' =>
                            'country',

                        'mapping_type' =>
                            DataTransformationBiSourceAssetFieldMapping
                                ::TYPE_DEFAULT,

                        'default_value' =>
                            'DO',
                    ],
                    [
                        'canonical_field_key' =>
                            'notes',

                        'mapping_type' =>
                            DataTransformationBiSourceAssetFieldMapping
                                ::TYPE_UNMAPPED,
                    ],
                ]
            );

        $this->assertSame(
            [
                'customer_id' =>
                    'C-001',

                'name' =>
                    'Cliente Uno',

                'country' =>
                    'DO',

                'notes' =>
                    null,
            ],
            $result['payload']
        );

        $this->assertSame(
            [
                'customer_id',
                'name',
            ],
            $result[
                'projection_meta'
            ]['mapped_fields']
        );

        $this->assertSame(
            [
                'country',
            ],
            $result[
                'projection_meta'
            ]['defaulted_fields']
        );

        $this->assertSame(
            [
                'notes',
            ],
            $result[
                'projection_meta'
            ]['unmapped_fields']
        );

        $this->assertSame(
            [
                'notes',
            ],
            $result[
                'projection_meta'
            ]['null_fields']
        );
    }

    public function test_coerces_all_supported_canonical_types(): void
    {
        $projector =
            new DataTransformationBiMappingProjector();

        $result =
            $projector->project(
                [
                    'column_1' =>
                        '  Texto  ',

                    'column_2' =>
                        '42',

                    'column_3' =>
                        '0012.3400',

                    'column_4' =>
                        'sí',

                    'column_5' =>
                        '2026-10-07',

                    'column_6' =>
                        '2026-10-07T19:30:45',
                ],
                [
                    [
                        'field_key' =>
                            'text_value',

                        'data_type' =>
                            DataTransformationBiCanonicalField
                                ::TYPE_TEXT,
                    ],
                    [
                        'field_key' =>
                            'integer_value',

                        'data_type' =>
                            DataTransformationBiCanonicalField
                                ::TYPE_INTEGER,
                    ],
                    [
                        'field_key' =>
                            'decimal_value',

                        'data_type' =>
                            DataTransformationBiCanonicalField
                                ::TYPE_DECIMAL,
                    ],
                    [
                        'field_key' =>
                            'boolean_value',

                        'data_type' =>
                            DataTransformationBiCanonicalField
                                ::TYPE_BOOLEAN,
                    ],
                    [
                        'field_key' =>
                            'date_value',

                        'data_type' =>
                            DataTransformationBiCanonicalField
                                ::TYPE_DATE,
                    ],
                    [
                        'field_key' =>
                            'datetime_value',

                        'data_type' =>
                            DataTransformationBiCanonicalField
                                ::TYPE_DATETIME,
                    ],
                ],
                array_map(
                    static fn (
                        int $index,
                        string $fieldKey
                    ): array => [
                        'canonical_field_key' =>
                            $fieldKey,

                        'mapping_type' =>
                            DataTransformationBiSourceAssetFieldMapping
                                ::TYPE_DIRECT,

                        'source_column_key' =>
                            'column_'.$index,
                    ],
                    [
                        1,
                        2,
                        3,
                        4,
                        5,
                        6,
                    ],
                    [
                        'text_value',
                        'integer_value',
                        'decimal_value',
                        'boolean_value',
                        'date_value',
                        'datetime_value',
                    ]
                )
            );

        $this->assertSame(
            'Texto',
            $result['payload']['text_value']
        );

        $this->assertSame(
            42,
            $result['payload']['integer_value']
        );

        $this->assertSame(
            '12.34',
            $result['payload']['decimal_value']
        );

        $this->assertTrue(
            $result['payload']['boolean_value']
        );

        $this->assertSame(
            '2026-10-07',
            $result['payload']['date_value']
        );

        $this->assertSame(
            '2026-10-07 19:30:45',
            $result['payload']['datetime_value']
        );
    }

    public function test_empty_values_become_null_before_type_coercion(): void
    {
        $projector =
            new DataTransformationBiMappingProjector();

        $result =
            $projector->project(
                [
                    'column_1' =>
                        '   ',
                ],
                [
                    [
                        'field_key' =>
                            'amount',

                        'data_type' =>
                            DataTransformationBiCanonicalField
                                ::TYPE_DECIMAL,
                    ],
                ],
                [
                    [
                        'canonical_field_key' =>
                            'amount',

                        'mapping_type' =>
                            DataTransformationBiSourceAssetFieldMapping
                                ::TYPE_DIRECT,

                        'source_column_key' =>
                            'column_1',
                    ],
                ]
            );

        $this->assertNull(
            $result['payload']['amount']
        );
    }

    public function test_missing_direct_source_column_is_rejected(): void
    {
        $projector =
            new DataTransformationBiMappingProjector();

        $this->expectException(
            ValidationException::class
        );

        $projector->project(
            [
                'column_1' =>
                    'X',
            ],
            [
                [
                    'field_key' =>
                        'customer_id',

                    'data_type' =>
                        DataTransformationBiCanonicalField
                            ::TYPE_TEXT,
                ],
            ],
            [
                [
                    'canonical_field_key' =>
                        'customer_id',

                    'mapping_type' =>
                        DataTransformationBiSourceAssetFieldMapping
                            ::TYPE_DIRECT,

                    'source_column_key' =>
                        'column_99',
                ],
            ]
        );
    }

    public function test_transform_mapping_is_explicitly_blocked(): void
    {
        $projector =
            new DataTransformationBiMappingProjector();

        $this->expectException(
            ValidationException::class
        );

        $projector->project(
            [
                'column_1' =>
                    'ABC',
            ],
            [
                [
                    'field_key' =>
                        'customer_id',

                    'data_type' =>
                        DataTransformationBiCanonicalField
                            ::TYPE_TEXT,
                ],
            ],
            [
                [
                    'canonical_field_key' =>
                        'customer_id',

                    'mapping_type' =>
                        DataTransformationBiSourceAssetFieldMapping
                            ::TYPE_TRANSFORM,

                    'source_column_key' =>
                        'column_1',

                    'transformation_key' =>
                        'trim_text',
                ],
            ]
        );
    }

    public function test_missing_mapping_decision_is_rejected(): void
    {
        $projector =
            new DataTransformationBiMappingProjector();

        $this->expectException(
            ValidationException::class
        );

        $projector->project(
            [],
            [
                [
                    'field_key' =>
                        'customer_id',

                    'data_type' =>
                        DataTransformationBiCanonicalField
                            ::TYPE_TEXT,
                ],
            ],
            []
        );
    }

    public function test_duplicate_mapping_decisions_are_rejected(): void
    {
        $projector =
            new DataTransformationBiMappingProjector();

        $this->expectException(
            ValidationException::class
        );

        $projector->project(
            [
                'column_1' =>
                    'C-1',
            ],
            [
                [
                    'field_key' =>
                        'customer_id',

                    'data_type' =>
                        DataTransformationBiCanonicalField
                            ::TYPE_TEXT,
                ],
            ],
            [
                [
                    'canonical_field_key' =>
                        'customer_id',

                    'mapping_type' =>
                        DataTransformationBiSourceAssetFieldMapping
                            ::TYPE_DIRECT,

                    'source_column_key' =>
                        'column_1',
                ],
                [
                    'canonical_field_key' =>
                        'customer_id',

                    'mapping_type' =>
                        DataTransformationBiSourceAssetFieldMapping
                            ::TYPE_DEFAULT,

                    'default_value' =>
                        'C-2',
                ],
            ]
        );
    }

    public function test_invalid_type_coercion_is_rejected(): void
    {
        $projector =
            new DataTransformationBiMappingProjector();

        $this->expectException(
            ValidationException::class
        );

        $projector->project(
            [
                'column_1' =>
                    '12.5',
            ],
            [
                [
                    'field_key' =>
                        'quantity',

                    'data_type' =>
                        DataTransformationBiCanonicalField
                            ::TYPE_INTEGER,
                ],
            ],
            [
                [
                    'canonical_field_key' =>
                        'quantity',

                    'mapping_type' =>
                        DataTransformationBiSourceAssetFieldMapping
                            ::TYPE_DIRECT,

                    'source_column_key' =>
                        'column_1',
                ],
            ]
        );
    }
}
