<?php

use App\Services\Diagnosis\DataTransformationBiProjectedDatasetReader;
use App\Services\Diagnosis\DataTransformationBiProjectedRelationshipNavigator;
use InvalidArgumentException;
use RuntimeException;

function p21cMethodSource(
    string $file,
    string $method
): string {
    $source =
        file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Services/Diagnosis/'
            .$file
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

function p21cProjectedSalesRow(
    string $documentId = 'F-001',
    int $lineNumber = 1
): array {
    return [
        'domain' =>
            'sales',

        'identity' => [
            'document_id' =>
                $documentId,

            'line_number' =>
                $lineNumber,
        ],

        'fields' => [
            'document_id' =>
                $documentId,

            'line_number' =>
                $lineNumber,

            'document_date' =>
                '2026-09-15',

            'customer_id' =>
                'C-001',

            'product_id' =>
                'P-001',

            'quantity' =>
                '1',

            'unit_price' =>
                '100',

            'branch' =>
                null,

            'salesperson' =>
                null,

            'discount_amount' =>
                null,

            'tax_amount' =>
                null,

            'cost_amount' =>
                null,

            'net_amount' =>
                null,

            'currency' =>
                'DOP',
        ],
    ];
}

test(
    'p21 c1 projected reader exposes pinned projected bulk identity lookup',
    function () {
        $method =
            p21cMethodSource(
                'DataTransformationBiProjectedDatasetReader.php',
                'findDomainRowsByCanonicalIdentityHashesInDataset'
            );

        expect(
            substr_count(
                $method,
                '->findDomainRowsByCanonicalIdentityHashesInDataset('
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
            'DataTransformationBiNormalizedRow',
            'normalized_payload',
            'DB::',
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
    'p21 c2 exposes projected relationships bulk resolution and stream',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiProjectedRelationshipNavigator.php'
            );

        expect($source)
            ->toContain(
                'public function relationshipsFrom('
            )
            ->toContain(
                'public function resolveBulkTargetsInDataset('
            )
            ->toContain(
                'public function iterateResolvedRelationship('
            );
    }
);

test(
    'p21 c uses canonical relationship catalog and projected fields',
    function () {
        $method =
            p21cMethodSource(
                'DataTransformationBiProjectedRelationshipNavigator.php',
                'resolveBulkTargetsInDataset'
            );

        expect($method)
            ->toContain(
                '$canonicalSource'
            )
            ->toContain(
                "'fields'"
            )
            ->toContain(
                "'identity'"
            )
            ->toContain(
                '->hashForIdentityValues('
            )
            ->toContain(
                '->findDomainRowsByCanonicalIdentityHashesInDataset('
            );
    }
);

test(
    'p21 c projected navigator contains no raw p14 row envelope dependency',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiProjectedRelationshipNavigator.php'
            );

        foreach ([
            "'payload'",
            "'normalized_row_id'",
            "'identity_hash'",
            'DataTransformationBiPreparedDatasetReader',
            'DataTransformationBiNormalizedRow',
            'normalized_payload',
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
    'p21 c bulk source rows are limited to five hundred',
    function () {
        $navigator =
            app(
                DataTransformationBiProjectedRelationshipNavigator::class
            );

        $rows =
            array_fill(
                0,
                501,
                p21cProjectedSalesRow()
            );

        expect(
            fn () =>
                $navigator
                    ->resolveBulkTargetsInDataset(
                        19,
                        [
                            'processing_run_id' =>
                                100,

                            'intake_batch_id' =>
                                200,
                        ],
                        'sales',
                        $rows,
                        'customers'
                    )
        )->toThrow(
            InvalidArgumentException::class
        );
    }
);

test(
    'p21 c rejects malformed projected source row before target resolution',
    function () {
        $navigator =
            app(
                DataTransformationBiProjectedRelationshipNavigator::class
            );

        expect(
            fn () =>
                $navigator
                    ->resolveBulkTargetsInDataset(
                        19,
                        [
                            'processing_run_id' =>
                                100,

                            'intake_batch_id' =>
                                200,
                        ],
                        'sales',
                        [
                            [
                                'domain' =>
                                    'sales',

                                'identity' => [
                                    'document_id' =>
                                        'F-001',

                                    'line_number' =>
                                        1,
                                ],

                                'fields' => [
                                    'document_id' =>
                                        'F-001',
                                ],
                            ],
                        ],
                        'customers'
                    )
        )->toThrow(
            InvalidArgumentException::class
        );
    }
);

test(
    'p21 c rejects duplicate projected logical source rows',
    function () {
        $navigator =
            app(
                DataTransformationBiProjectedRelationshipNavigator::class
            );

        $row =
            p21cProjectedSalesRow();

        expect(
            fn () =>
                $navigator
                    ->resolveBulkTargetsInDataset(
                        19,
                        [
                            'processing_run_id' =>
                                100,

                            'intake_batch_id' =>
                                200,
                        ],
                        'sales',
                        [
                            $row,
                            $row,
                        ],
                        'customers'
                    )
        )->toThrow(
            InvalidArgumentException::class
        );
    }
);

test(
    'p21 c preserves projected output contract',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiProjectedRelationshipNavigator.php'
            );

        foreach ([
            "'source_identity'",
            "'relationship'",
            "'source_relation_value_missing'",
            "'target_found'",
            "'target'",
        ] as $field) {
            expect($source)
                ->toContain(
                    $field
                );
        }

        foreach ([
            "'source_normalized_row_id'",
            "'canonical_identity_hash'",
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
    'p21 c relationship iterator resolves p13 exactly once and freezes dataset',
    function () {
        $method =
            p21cMethodSource(
                'DataTransformationBiProjectedRelationshipNavigator.php',
                'iterateResolvedRelationship'
            );

        expect(
            substr_count(
                $method,
                '->forRequest('
            )
        )->toBe(1);

        expect(
            substr_count(
                $method,
                '->iterateDomainInDataset('
            )
        )->toBe(1);

        expect(
            substr_count(
                $method,
                '->yieldResolvedChunk('
            )
        )->toBeGreaterThanOrEqual(1);

        expect($method)
            ->not
            ->toContain(
                '->iterateDomain('
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
    'p21 c remains internal read only and analytical model free',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiProjectedRelationshipNavigator.php'
            );

        foreach ([
            'DB::',
            '::query()',
            '->create(',
            '->update(',
            '->delete(',
            '->save(',
            '->insert(',
            '->upsert(',
            'FactSales',
            'DimCustomer',
            'DimProduct',
            'star_schema',
        ] as $forbidden) {
            expect($source)
                ->not
                ->toContain(
                    $forbidden
                );
        }
    }
);
