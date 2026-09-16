<?php

use App\Services\Diagnosis\DataTransformationBiCanonicalIdentity;
use App\Services\Diagnosis\DataTransformationBiCanonicalIncrementalConsumption;

function p23Source(): string
{
    return file_get_contents(
        dirname(__DIR__, 3)
        .'/app/Services/Diagnosis/'
        .'DataTransformationBiCanonicalIncrementalConsumption.php'
    );
}

function p23PublicMethod(
    string $name
): string {
    $source =
        p23Source();

    $needle =
        "public function {$name}(";

    $start =
        strpos(
            $source,
            $needle
        );

    if ($start === false) {
        throw new \RuntimeException(
            "Method {$name} not found."
        );
    }

    $brace =
        strpos(
            $source,
            '{',
            $start
        );

    $depth = 0;

    for (
        $i = $brace;
        $i < strlen($source);
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

    throw new \RuntimeException(
        "Method boundary missing: {$name}."
    );
}

function p23Change(
    string $type,
    string $hash,
    ?string $baseSha,
    ?string $targetSha
): array {
    return [
        'domain' =>
            'customers',

        'canonical_identity_hash' =>
            $hash,

        'change_type' =>
            $type,

        'base_normalized_sha256' =>
            $baseSha,

        'target_normalized_sha256' =>
            $targetSha,
    ];
}

function p23Row(
    string $customerId,
    string $name
): array {
    return [
        'domain' =>
            'customers',

        'identity' => [
            'customer_id' =>
                $customerId,
        ],

        'fields' => [
            'customer_id' =>
                $customerId,

            'customer_name' =>
                $name,
        ],
    ];
}

test(
    'p23 exposes resolved and resolving incremental stream apis',
    function () {
        $source =
            p23Source();

        expect($source)
            ->toContain(
                'public function iterateDatasetChanges('
            )
            ->toContain(
                'public function iterateResolvedPair('
            );
    }
);

test(
    'p23 resolves p19 pair exactly once before resolved stream',
    function () {
        $method =
            p23PublicMethod(
                'iterateDatasetChanges'
            );

        expect(
            substr_count(
                $method,
                '->pair('
            )
        )->toBe(1);

        expect($method)
            ->toContain(
                'yield from $this->iterateResolvedPair('
            )
            ->not
            ->toContain(
                '->forRequest('
            )
            ->not
            ->toContain(
                '->forProcessingRun('
            );
    }
);

test(
    'p23 resolved stream reuses p20 and never resolves datasets',
    function () {
        $method =
            p23PublicMethod(
                'iterateResolvedPair'
            );

        expect(
            substr_count(
                $method,
                '->iterateResolvedPair('
            )
        )->toBe(1);

        foreach ([
            '->pair(',
            '->current(',
            '->forRequest(',
            '->forProcessingRun(',
            '->iterateDatasetDelta(',
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
    'p23 bulk projects base and target without n plus one',
    function () {
        $source =
            p23Source();

        expect(
            substr_count(
                $source,
                '->findDomainRowsByCanonicalIdentityHashesInDataset('
            )
        )->toBe(2);

        expect($source)
            ->toContain(
                'private function projectChunk('
            )
            ->toContain(
                'count($chunk)'
            )
            ->toContain(
                'self::MAX_PAGE_SIZE'
            );
    }
);

test(
    'p23 semantic output does not expose technical hashes',
    function () {
        $source =
            p23Source();

        $needle =
            "private function semanticChangeRecord(";

        $start =
            strpos(
                $source,
                $needle
            );

        expect($start)
            ->not
            ->toBeFalse();

        $returnStart =
            strpos(
                $source,
                'return [',
                $start
            );

        $returnEnd =
            strpos(
                $source,
                '];',
                $returnStart
            );

        $returnBlock =
            substr(
                $source,
                $returnStart,
                $returnEnd - $returnStart + 2
            );

        expect($returnBlock)
            ->toContain("'domain'")
            ->toContain("'change_type'")
            ->toContain("'identity'")
            ->toContain("'before'")
            ->toContain("'after'")
            ->not
            ->toContain(
                "'canonical_identity_hash'"
            )
            ->not
            ->toContain(
                "'base_normalized_sha256'"
            )
            ->not
            ->toContain(
                "'target_normalized_sha256'"
            );
    }
);

test(
    'p23 semantic record implements added removed modified unchanged',
    function (
        string $type,
        ?array $before,
        ?array $after
    ) {
        $service =
            app(
                DataTransformationBiCanonicalIncrementalConsumption::class
            );

        $identity =
            app(
                DataTransformationBiCanonicalIdentity::class
            );

        $hash =
            $identity
                ->hashForIdentityValues(
                    'customers',
                    [
                        'customer_id' =>
                            'C-1',
                    ]
                );

        $baseSha =
            in_array(
                $type,
                [
                    'removed',
                    'modified',
                    'unchanged',
                ],
                true
            )
                ? str_repeat('b', 64)
                : null;

        $targetSha =
            in_array(
                $type,
                [
                    'added',
                    'modified',
                    'unchanged',
                ],
                true
            )
                ? (
                    $type === 'modified'
                        ? str_repeat('c', 64)
                        : str_repeat('b', 64)
                )
                : null;

        $method =
            new ReflectionMethod(
                DataTransformationBiCanonicalIncrementalConsumption::class,
                'semanticChangeRecord'
            );

        $record =
            $method->invoke(
                $service,
                p23Change(
                    $type,
                    $hash,
                    $baseSha,
                    $targetSha
                ),
                $before,
                $after
            );

        expect(
            array_keys($record)
        )->toBe([
            'domain',
            'change_type',
            'identity',
            'before',
            'after',
        ]);

        expect(
            $record['domain']
        )->toBe(
            'customers'
        );

        expect(
            $record['change_type']
        )->toBe(
            $type
        );

        expect(
            $record['identity']
        )->toBe([
            'customer_id' =>
                'C-1',
        ]);

        expect(
            $record['before']
        )->toBe(
            $before
        );

        expect(
            $record['after']
        )->toBe(
            $after
        );

        expect($record)
            ->not
            ->toHaveKey(
                'canonical_identity_hash'
            )
            ->not
            ->toHaveKey(
                'base_normalized_sha256'
            )
            ->not
            ->toHaveKey(
                'target_normalized_sha256'
            );
    }
)->with([
    'added' => [
        'added',
        null,
        p23Row(
            'C-1',
            'Nuevo'
        ),
    ],

    'removed' => [
        'removed',
        p23Row(
            'C-1',
            'Anterior'
        ),
        null,
    ],

    'modified' => [
        'modified',
        p23Row(
            'C-1',
            'Anterior'
        ),
        p23Row(
            'C-1',
            'Actual'
        ),
    ],

    'unchanged' => [
        'unchanged',
        p23Row(
            'C-1',
            'Igual'
        ),
        p23Row(
            'C-1',
            'Igual'
        ),
    ],
]);

test(
    'p23 rejects semantic identity that does not match p20 hash',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalIncrementalConsumption::class
            );

        $identity =
            app(
                DataTransformationBiCanonicalIdentity::class
            );

        $hash =
            $identity
                ->hashForIdentityValues(
                    'customers',
                    [
                        'customer_id' =>
                            'C-1',
                    ]
                );

        $method =
            new ReflectionMethod(
                DataTransformationBiCanonicalIncrementalConsumption::class,
                'semanticChangeRecord'
            );

        expect(
            fn () =>
                $method->invoke(
                    $service,
                    p23Change(
                        'added',
                        $hash,
                        null,
                        str_repeat('b', 64)
                    ),
                    null,
                    p23Row(
                        'C-OTHER',
                        'Incorrecto'
                    )
                )
        )->toThrow(
            \RuntimeException::class
        );
    }
);

test(
    'p23 rejects before and after with different semantic identities',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalIncrementalConsumption::class
            );

        $identity =
            app(
                DataTransformationBiCanonicalIdentity::class
            );

        $hash =
            $identity
                ->hashForIdentityValues(
                    'customers',
                    [
                        'customer_id' =>
                            'C-1',
                    ]
                );

        $method =
            new ReflectionMethod(
                DataTransformationBiCanonicalIncrementalConsumption::class,
                'semanticChangeRecord'
            );

        expect(
            fn () =>
                $method->invoke(
                    $service,
                    p23Change(
                        'modified',
                        $hash,
                        str_repeat('b', 64),
                        str_repeat('c', 64)
                    ),
                    p23Row(
                        'C-1',
                        'Anterior'
                    ),
                    p23Row(
                        'C-OTHER',
                        'Actual'
                    )
                )
        )->toThrow(
            \RuntimeException::class
        );
    }
);

test(
    'p23 has no raw storage db writes or deferred delivery state',
    function () {
        $source =
            p23Source();

        foreach ([
            'DataTransformationBiNormalizedRow',
            'DataTransformationBiPreparedDatasetReader',
            'normalized_payload',
            "'payload'",
            "'normalized_row_id'",
            "'identity_hash'",
            'DB::',
            '::query()',
            '->create(',
            '->update(',
            '->delete(',
            '->save(',
            '->insert(',
            '->upsert(',
            'ConsumerCheckpoint',
            'DatasetPublication',
            'DatasetRegistry',
            'publication_registry',
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
