<?php

use App\Services\Diagnosis\DataTransformationBiCanonicalNormalizer;

test(
    'canonical normalizer trims text and converts blank optional values to null',
    function () {
        $normalizer =
            new DataTransformationBiCanonicalNormalizer();

        $result =
            $normalizer->normalize(
                'customers',
                [
                    'customer_id' =>
                        '  C-001  ',

                    'customer_name' =>
                        ' Cliente Uno ',

                    'tax_id' =>
                        '   ',

                    'customer_type' =>
                        null,

                    'city' =>
                        ' Santo Domingo ',

                    'country' =>
                        'DO',

                    'credit_limit' =>
                        '001000.5000',

                    'created_at' =>
                        '2026-09-14 18:30:00',

                    'active' =>
                        'true',
                ]
            );

        expect(
            $result['payload']['customer_id']
        )
            ->toBe('C-001')
            ->and(
                $result['payload']['customer_name']
            )
            ->toBe('Cliente Uno')
            ->and(
                $result['payload']['tax_id']
            )
            ->toBeNull()
            ->and(
                $result['payload']['credit_limit']
            )
            ->toBe('1000.5')
            ->and(
                $result['payload']['active']
            )
            ->toBeTrue()
            ->and(
                $result['change_count']
            )
            ->toBeGreaterThan(0);
    }
);

test(
    'canonical normalizer preserves date and datetime contract',
    function () {
        $normalizer =
            new DataTransformationBiCanonicalNormalizer();

        $inventory =
            $normalizer->normalize(
                'inventory',
                [
                    'snapshot_date' =>
                        '2026-09-14',

                    'product_id' =>
                        'P-1',

                    'branch' =>
                        null,

                    'warehouse' =>
                        null,

                    'quantity_on_hand' =>
                        '10.000',

                    'quantity_available' =>
                        null,

                    'quantity_committed' =>
                        null,

                    'unit_cost' =>
                        null,
                ]
            );

        expect(
            $inventory['payload']['snapshot_date']
        )
            ->toBe('2026-09-14')
            ->and(
                $inventory['payload']['quantity_on_hand']
            )
            ->toBe('10');
    }
);

test(
    'canonical normalizer produces deterministic canonical json',
    function () {
        $normalizer =
            new DataTransformationBiCanonicalNormalizer();

        $first =
            $normalizer->normalize(
                'suppliers',
                [
                    'supplier_id' => 'S-1',
                    'supplier_name' => 'Proveedor',
                    'tax_id' => null,
                    'city' => null,
                    'country' => null,
                    'payment_terms_days' => '30',
                    'active' => 'false',
                ]
            );

        $second =
            $normalizer->normalize(
                'suppliers',
                [
                    'active' => 'false',
                    'payment_terms_days' => '30',
                    'country' => null,
                    'city' => null,
                    'tax_id' => null,
                    'supplier_name' => 'Proveedor',
                    'supplier_id' => 'S-1',
                ]
            );

        expect(
            $normalizer->canonicalJson(
                $first['payload']
            )
        )
            ->toBe(
                $normalizer->canonicalJson(
                    $second['payload']
                )
            );
    }
);

test(
    'normalization metadata never requires raw before and after samples',
    function () {
        $normalizer =
            new DataTransformationBiCanonicalNormalizer();

        $result =
            $normalizer->normalize(
                'products',
                [
                    'product_id' => ' P-1 ',
                    'product_name' => ' Producto ',
                    'sku' => null,
                    'category' => null,
                    'brand' => null,
                    'unit_of_measure' => null,
                    'unit_cost' => null,
                    'unit_price' => null,
                    'active' => null,
                ]
            );

        expect(
            $result
        )
            ->toHaveKeys([
                'payload',
                'change_count',
                'changed_fields',
            ])
            ->not
            ->toHaveKey(
                'raw_values'
            )
            ->not
            ->toHaveKey(
                'before'
            )
            ->not
            ->toHaveKey(
                'after'
            );
    }
);

test(
    'canonical json hash is independent of associative key order',
    function () {
        $normalizer =
            new DataTransformationBiCanonicalNormalizer();

        $first = [
            'z' => 'last',
            'a' => [
                'two' => 2,
                'one' => 1,
            ],
            'list' => [
                'b',
                'a',
            ],
        ];

        $second = [
            'list' => [
                'b',
                'a',
            ],
            'a' => [
                'one' => 1,
                'two' => 2,
            ],
            'z' => 'last',
        ];

        $firstJson =
            $normalizer->canonicalJson(
                $first
            );

        $secondJson =
            $normalizer->canonicalJson(
                $second
            );

        expect($firstJson)
            ->toBe($secondJson)
            ->and(
                hash(
                    'sha256',
                    $firstJson
                )
            )
            ->toBe(
                hash(
                    'sha256',
                    $secondJson
                )
            )
            ->and(
                json_decode(
                    $firstJson,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                )['list']
            )
            ->toBe([
                'b',
                'a',
            ]);
    }
);

