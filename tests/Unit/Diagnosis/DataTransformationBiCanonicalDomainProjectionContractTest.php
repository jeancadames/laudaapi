<?php

use App\Services\Diagnosis\DataTransformationBiCanonicalDomainProjection;
use App\Services\Diagnosis\DataTransformationBiStandardIntakeSchema;

function p21aCanonicalValue(
    string $type,
    string $field
): mixed {
    return match ($type) {
        'text',
        'string',
        'identifier' =>
            strtoupper($field).'-VALUE',

        'decimal',
        'number',
        'numeric',
        'money' =>
            '12.5',

        'integer',
        'int' =>
            2,

        'date' =>
            '2026-09-15',

        'datetime' =>
            '2026-09-15 12:34:56',

        'boolean',
        'bool' =>
            true,

        default =>
            throw new \RuntimeException(
                "Unsupported test type: {$type}"
            ),
    };
}

function p21aRequiredPayload(
    string $domain
): array {
    $definition =
        DataTransformationBiStandardIntakeSchema
            ::domains()[$domain];

    $payload = [];

    foreach (
        $definition['fields']
        as $field
    ) {
        if (
            ($field['required'] ?? false)
            !== true
        ) {
            continue;
        }

        $payload[
            $field['name']
        ] =
            p21aCanonicalValue(
                $field['type'],
                $field['name']
            );
    }

    return $payload;
}

test(
    'p21 a projects every canonical domain from authoritative schema',
    function () {
        $projection =
            app(
                DataTransformationBiCanonicalDomainProjection::class
            );

        $domains =
            DataTransformationBiStandardIntakeSchema
                ::domains();

        $identityKeys =
            DataTransformationBiStandardIntakeSchema
                ::identityKeys();

        expect(
            array_keys($domains)
        )->toBe([
            'customers',
            'products',
            'inventory',
            'sales',
            'accounts_receivable',
            'suppliers',
            'accounts_payable',
        ]);

        foreach (
            $domains
            as $domain => $definition
        ) {
            $payload =
                p21aRequiredPayload(
                    $domain
                );

            $result =
                $projection->project(
                    $domain,
                    $payload
                );

            $fieldNames =
                array_map(
                    static fn (
                        array $field
                    ): string =>
                        $field['name'],
                    $definition['fields']
                );

            expect(
                $result['domain']
            )->toBe($domain);

            expect(
                array_keys(
                    $result['fields']
                )
            )->toBe(
                $fieldNames
            );

            expect(
                array_keys(
                    $result['identity']
                )
            )->toBe(
                $identityKeys[$domain]
            );

            foreach (
                $definition['fields']
                as $field
            ) {
                if (
                    ($field['required'] ?? false)
                    === true
                ) {
                    expect(
                        $result['fields'][
                            $field['name']
                        ]
                    )->not->toBeNull();
                } else {
                    expect(
                        $result['fields'][
                            $field['name']
                        ]
                    )->toBeNull();
                }
            }
        }
    }
);

test(
    'p21 a preserves canonical runtime value types',
    function () {
        $projection =
            app(
                DataTransformationBiCanonicalDomainProjection::class
            );

        $result =
            $projection->project(
                'customers',
                [
                    'customer_id' =>
                        'C-001',

                    'customer_name' =>
                        'Cliente Uno',

                    'tax_id' =>
                        '131000001',

                    'customer_type' =>
                        'empresa',

                    'city' =>
                        'Santo Domingo',

                    'country' =>
                        'DO',

                    'credit_limit' =>
                        '1234.5',

                    'created_at' =>
                        '2026-09-15 12:34:56',

                    'active' =>
                        true,
                ]
            );

        expect(
            $result['identity']
        )->toBe([
            'customer_id' =>
                'C-001',
        ]);

        expect(
            $result['fields'][
                'credit_limit'
            ]
        )->toBe('1234.5');

        expect(
            $result['fields'][
                'created_at'
            ]
        )->toBe(
            '2026-09-15 12:34:56'
        );

        expect(
            $result['fields'][
                'active'
            ]
        )->toBeTrue();
    }
);

test(
    'p21 a exposes integer date and decimal canonical forms',
    function () {
        $projection =
            app(
                DataTransformationBiCanonicalDomainProjection::class
            );

        $result =
            $projection->project(
                'sales',
                [
                    'document_id' =>
                        'F-001',

                    'line_number' =>
                        2,

                    'document_date' =>
                        '2026-09-15',

                    'customer_id' =>
                        'C-001',

                    'product_id' =>
                        'P-001',

                    'quantity' =>
                        '3.5',

                    'unit_price' =>
                        '250.75',
                ]
            );

        expect(
            $result['fields'][
                'line_number'
            ]
        )->toBe(2);

        expect(
            $result['fields'][
                'document_date'
            ]
        )->toBe(
            '2026-09-15'
        );

        expect(
            $result['fields'][
                'quantity'
            ]
        )->toBe('3.5');

        expect(
            $result['fields'][
                'unit_price'
            ]
        )->toBe('250.75');
    }
);

test(
    'p21 a fills every omitted optional schema field with null',
    function () {
        $projection =
            app(
                DataTransformationBiCanonicalDomainProjection::class
            );

        $result =
            $projection->project(
                'customers',
                [
                    'customer_id' =>
                        'C-001',

                    'customer_name' =>
                        'Cliente Uno',
                ]
            );

        expect(
            $result['fields']
        )->toBe([
            'customer_id' =>
                'C-001',

            'customer_name' =>
                'Cliente Uno',

            'tax_id' =>
                null,

            'customer_type' =>
                null,

            'city' =>
                null,

            'country' =>
                null,

            'credit_limit' =>
                null,

            'created_at' =>
                null,

            'active' =>
                null,
        ]);
    }
);

test(
    'p21 a fails closed on missing or null required fields',
    function () {
        $projection =
            app(
                DataTransformationBiCanonicalDomainProjection::class
            );

        expect(
            fn () =>
                $projection->project(
                    'customers',
                    [
                        'customer_id' =>
                            'C-001',
                    ]
                )
        )->toThrow(
            \RuntimeException::class
        );

        expect(
            fn () =>
                $projection->project(
                    'customers',
                    [
                        'customer_id' =>
                            'C-001',

                        'customer_name' =>
                            null,
                    ]
                )
        )->toThrow(
            \RuntimeException::class
        );
    }
);

test(
    'p21 a rejects undeclared fields',
    function () {
        $projection =
            app(
                DataTransformationBiCanonicalDomainProjection::class
            );

        expect(
            fn () =>
                $projection->project(
                    'customers',
                    [
                        'customer_id' =>
                            'C-001',

                        'customer_name' =>
                            'Cliente Uno',

                        'private_storage_field' =>
                            'forbidden',
                    ]
                )
        )->toThrow(
            \RuntimeException::class
        );
    }
);

test(
    'p21 a rejects values that would still require normalization',
    function (
        mixed $value,
        string $field
    ) {
        $projection =
            app(
                DataTransformationBiCanonicalDomainProjection::class
            );

        $payload = [
            'customer_id' =>
                'C-001',

            'customer_name' =>
                'Cliente Uno',
        ];

        $payload[$field] =
            $value;

        expect(
            fn () =>
                $projection->project(
                    'customers',
                    $payload
                )
        )->toThrow(
            \RuntimeException::class
        );
    }
)->with([
    'decimal trailing zero' => [
        '1234.50',
        'credit_limit',
    ],

    'boolean text instead of bool' => [
        'true',
        'active',
    ],

    'trimmed text required' => [
        ' Cliente Uno ',
        'customer_name',
    ],
]);

test(
    'p21 a rejects string integer in normalized sales row',
    function () {
        $projection =
            app(
                DataTransformationBiCanonicalDomainProjection::class
            );

        expect(
            fn () =>
                $projection->project(
                    'sales',
                    [
                        'document_id' =>
                            'F-001',

                        'line_number' =>
                            '2',

                        'document_date' =>
                            '2026-09-15',

                        'customer_id' =>
                            'C-001',

                        'product_id' =>
                            'P-001',

                        'quantity' =>
                            '3.5',

                        'unit_price' =>
                            '250.75',
                    ]
                )
        )->toThrow(
            \RuntimeException::class
        );
    }
);

test(
    'p21 a rejects unsupported domain',
    function () {
        $projection =
            app(
                DataTransformationBiCanonicalDomainProjection::class
            );

        expect(
            fn () =>
                $projection->project(
                    'not_a_domain',
                    []
                )
        )->toThrow(
            \RuntimeException::class
        );
    }
);

test(
    'p21 a uses existing schema and normalizer instead of duplicating contracts',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalDomainProjection.php'
            );

        expect($source)
            ->toContain(
                'DataTransformationBiStandardIntakeSchema'
            )
            ->toContain(
                '::domains()'
            )
            ->toContain(
                '::identityKeys()'
            )
            ->toContain(
                'DataTransformationBiCanonicalNormalizer'
            )
            ->toContain(
                '->normalize('
            );

        foreach ([
            'customer_id',
            'product_id',
            'supplier_id',
            'document_id',
            'quantity_on_hand',
            'outstanding_amount',
        ] as $duplicatedField) {
            expect($source)
                ->not
                ->toContain(
                    "'{$duplicatedField}'"
                );
        }
    }
);

test(
    'p21 a has no storage model or persistence dependency',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalDomainProjection.php'
            );

        foreach ([
            'DataTransformationBiNormalizedRow',
            'normalized_payload',
            'normalized_row_id',
            'identity_hash',
            'DB::',
            '::query()',
            '->create(',
            '->update(',
            '->delete(',
            '->save(',
            '->insert(',
            '->upsert(',
        ] as $forbidden) {
            expect($source)
                ->not
                ->toContain(
                    $forbidden
                );
        }
    }
);
