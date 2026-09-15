<?php

use App\Services\Diagnosis\DataTransformationBiProjectedDatasetReader;

function p21bMethodSource(
    string $method
): string {
    $source =
        file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Services/Diagnosis/'
            .'DataTransformationBiProjectedDatasetReader.php'
        );

    $needle =
        "public function {$method}(";

    $start =
        strpos(
            $source,
            $needle
        );

    if ($start === false) {
        throw new RuntimeException(
            "Method {$method} not found."
        );
    }

    $brace =
        strpos(
            $source,
            '{',
            $start
        );

    if ($brace === false) {
        throw new RuntimeException(
            "Method {$method} opening brace not found."
        );
    }

    $depth = 0;
    $length =
        strlen(
            $source
        );

    for (
        $i = $brace;
        $i < $length;
        $i++
    ) {
        if ($source[$i] === '{') {
            $depth++;
        } elseif ($source[$i] === '}') {
            $depth--;

            if ($depth === 0) {
                return substr(
                    $source,
                    $start,
                    $i - $start + 1
                );
            }
        }
    }

    throw new RuntimeException(
        "Method {$method} boundary not found."
    );
}

test(
    'p21 b exposes current pinned and prepared row projection apis',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiProjectedDatasetReader.php'
            );

        expect($source)
            ->toContain(
                'public function projectPreparedRow('
            )
            ->toContain(
                'public function iterateDomain('
            )
            ->toContain(
                'public function iterateDomainInDataset('
            );
    }
);

test(
    'p21 b prepared row adapter ends p14 storage envelope',
    function () {
        $reader =
            app(
                DataTransformationBiProjectedDatasetReader::class
            );

        $result =
            $reader->projectPreparedRow(
                'customers',
                [
                    'normalized_row_id' =>
                        101,

                    'identity_hash' =>
                        str_repeat(
                            'a',
                            64
                        ),

                    'payload' => [
                        'customer_id' =>
                            'C-001',

                        'customer_name' =>
                            'Cliente Uno',

                        'tax_id' =>
                            null,

                        'customer_type' =>
                            null,

                        'city' =>
                            'Santo Domingo',

                        'country' =>
                            'DO',

                        'credit_limit' =>
                            '1250.5',

                        'created_at' =>
                            '2026-09-15 12:00:00',

                        'active' =>
                            true,
                    ],
                ]
            );

        expect(
            array_keys($result)
        )->toBe([
            'domain',
            'identity',
            'fields',
        ]);

        expect(
            $result['domain']
        )->toBe('customers');

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
        )->toBe('1250.5');

        expect($result)
            ->not
            ->toHaveKey(
                'normalized_row_id'
            )
            ->not
            ->toHaveKey(
                'identity_hash'
            )
            ->not
            ->toHaveKey(
                'payload'
            );
    }
);

test(
    'p21 b prepared row adapter fails closed on malformed p14 envelope',
    function () {
        $reader =
            app(
                DataTransformationBiProjectedDatasetReader::class
            );

        expect(
            fn () =>
                $reader->projectPreparedRow(
                    'customers',
                    [
                        'identity_hash' =>
                            str_repeat(
                                'a',
                                64
                            ),

                        'payload' => [
                            'customer_id' =>
                                'C-001',

                            'customer_name' =>
                                'Cliente Uno',
                        ],
                    ]
                )
        )->toThrow(
            RuntimeException::class
        );

        expect(
            fn () =>
                $reader->projectPreparedRow(
                    'customers',
                    [
                        'normalized_row_id' =>
                            1,

                        'identity_hash' =>
                            '',

                        'payload' => [
                            'customer_id' =>
                                'C-001',

                            'customer_name' =>
                                'Cliente Uno',
                        ],
                    ]
                )
        )->toThrow(
            RuntimeException::class
        );

        expect(
            fn () =>
                $reader->projectPreparedRow(
                    'customers',
                    [
                        'normalized_row_id' =>
                            1,

                        'identity_hash' =>
                            str_repeat(
                                'a',
                                64
                            ),

                        'payload' =>
                            'invalid',
                    ]
                )
        )->toThrow(
            RuntimeException::class
        );
    }
);

test(
    'p21 b current iterator delegates to p14 current frozen iterator',
    function () {
        $method =
            p21bMethodSource(
                'iterateDomain'
            );

        expect(
            substr_count(
                $method,
                '->iterateDomain('
            )
        )->toBe(1);

        expect(
            substr_count(
                $method,
                '->projectPreparedRow('
            )
        )->toBe(1);

        expect($method)
            ->not
            ->toContain(
                '->forRequest('
            )
            ->not
            ->toContain(
                '->forProcessingRun('
            )
            ->not
            ->toContain(
                '->pair('
            );
    }
);

test(
    'p21 b pinned iterator delegates only to p14 pinned access',
    function () {
        $method =
            p21bMethodSource(
                'iterateDomainInDataset'
            );

        expect(
            substr_count(
                $method,
                '->iterateDomainInDataset('
            )
        )->toBe(1);

        expect(
            substr_count(
                $method,
                '->projectPreparedRow('
            )
        )->toBe(1);

        foreach ([
            '->forRequest(',
            '->forProcessingRun(',
            '->pair(',
        ] as $forbidden) {
            expect($method)
                ->not
                ->toContain(
                    $forbidden
                );
        }
    }
);

test(
    'p21 b consumer contract exposes only canonical projection',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiProjectedDatasetReader.php'
            );

        expect($source)
            ->toContain(
                'DataTransformationBiPreparedDatasetReader'
            )
            ->toContain(
                'DataTransformationBiCanonicalDomainProjection'
            );

        expect(
            p21bMethodSource(
                'projectPreparedRow'
            )
        )->toContain(
            'return $this->projection'
        );
    }
);

test(
    'p21 b has no normalized storage model or direct database access',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiProjectedDatasetReader.php'
            );

        foreach ([
            'DataTransformationBiNormalizedRow',
            'normalized_payload',
            'DB::',
            '::query()',
            '->where(',
            '->get(',
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

test(
    'p21 b does not duplicate canonical field registry',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiProjectedDatasetReader.php'
            );

        foreach ([
            "'customer_id'",
            "'product_id'",
            "'supplier_id'",
            "'quantity_on_hand'",
            "'outstanding_amount'",
            "'unit_price'",
        ] as $field) {
            expect($source)
                ->not
                ->toContain(
                    $field
                );
        }
    }
);
