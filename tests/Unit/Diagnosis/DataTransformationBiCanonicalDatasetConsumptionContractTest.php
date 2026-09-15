<?php

use App\Services\Diagnosis\DataTransformationBiCanonicalDatasetConsumption;
use App\Services\Diagnosis\DataTransformationBiCanonicalDatasetContext;
use App\Services\Diagnosis\DataTransformationBiCanonicalDatasetManifest;

function p22Source(): string
{
    return file_get_contents(
        dirname(__DIR__, 3)
        .'/app/Services/Diagnosis/'
        .'DataTransformationBiCanonicalDatasetConsumption.php'
    );
}

function p22Method(
    string $name,
    string $visibility = 'public'
): string {
    $source =
        p22Source();

    $needle =
        "{$visibility} function {$name}(";

    $start =
        strpos(
            $source,
            $needle
        );

    if ($start === false) {
        throw new RuntimeException(
            "Method {$name} not found."
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
            "Opening brace not found for {$name}."
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
        "Method boundary not found: {$name}."
    );
}

function p22EmptyDataset(): array
{
    return [
        'processing_run_id' =>
            2147482201,

        'intake_batch_id' =>
            2147482202,

        'definition_version' =>
            1,

        'schema_version' =>
            '1',

        'profiling_version' =>
            '1',

        'normalization_version' =>
            '1',

        'normalized_row_count' =>
            0,

        'has_rows' =>
            false,

        'completed_at' =>
            '2026-09-15 12:00:00',
    ];
}

function p22EmptyResolvedSnapshot(): array
{
    $dataset =
        p22EmptyDataset();

    $context =
        DataTransformationBiCanonicalDatasetContext
            ::fromResolved(
                19,
                1,
                $dataset
            );

    $domains = [];

    foreach (
        DataTransformationBiCanonicalDatasetContext
            ::domains()
        as $domain
    ) {
        $domains[] =
            DataTransformationBiCanonicalDatasetManifest
                ::fingerprintDomain(
                    $domain,
                    []
                );
    }

    $manifest = [
        'fingerprint_version' =>
            1,

        'fingerprint_algorithm' =>
            'sha256',

        'company_id' =>
            19,

        'implementation_request_id' =>
            1,

        'dataset' =>
            $dataset,

        'total_rows' =>
            0,

        'domains' =>
            $domains,

        'dataset_fingerprint' =>
            DataTransformationBiCanonicalDatasetManifest
                ::fingerprintDataset(
                    $dataset,
                    $domains
                ),
    ];

    return [
        'available' =>
            true,

        'reason' =>
            'synthetic_empty_snapshot',

        'context' =>
            $context,

        'manifest' =>
            $manifest,
    ];
}

test(
    'p22 exposes current historical and resolved snapshot stream apis',
    function () {
        $source =
            p22Source();

        expect($source)
            ->toContain(
                'public function current('
            )
            ->toContain(
                'public function forProcessingRun('
            )
            ->toContain(
                'public function iterateResolvedSnapshot('
            );
    }
);

test(
    'p22 current resolves p19 exactly once',
    function () {
        $method =
            p22Method(
                'current'
            );

        expect(
            substr_count(
                $method,
                '->current('
            )
        )->toBe(1);

        foreach ([
            '->forRequest(',
            '->iterateDomain(',
            '->iterateDomainInDataset(',
            'DataTransformationBiUsableDatasetResolver',
            'DataTransformationBiVersionedDatasetResolver',
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
    'p22 historical resolves p19 exactly once',
    function () {
        $method =
            p22Method(
                'forProcessingRun'
            );

        expect(
            substr_count(
                $method,
                '->forProcessingRun('
            )
        )->toBe(1);

        foreach ([
            '->forRequest(',
            '->iterateDomain(',
            '->iterateDomainInDataset(',
            'DataTransformationBiUsableDatasetResolver',
            'DataTransformationBiVersionedDatasetResolver',
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
    'p22 resolved stream uses only pinned p21 domain access',
    function () {
        $method =
            p22Method(
                'iterateResolvedSnapshot'
            );

        expect(
            substr_count(
                $method,
                '->iterateDomainInDataset('
            )
        )->toBe(1);

        expect($method)
            ->toContain(
                'DataTransformationBiCanonicalDatasetContext'
            )
            ->toContain(
                '::identity('
            )
            ->toContain(
                '::dataset('
            )
            ->toContain(
                '::domains()'
            );

        foreach ([
            '->iterateDomain(',
            '->current(',
            '->forProcessingRun(',
            '->forRequest(',
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
    'p22 normalized snapshot boundary has exact four keys',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalDatasetConsumption::class
            );

        $method =
            new ReflectionMethod(
                DataTransformationBiCanonicalDatasetConsumption::class,
                'resolvedSnapshot'
            );

        $snapshot =
            $method->invoke(
                $service,
                p22EmptyResolvedSnapshot()
            );

        expect(
            array_keys($snapshot)
        )->toBe([
            'available',
            'reason',
            'context',
            'manifest',
        ]);

        expect(
            $snapshot['available']
        )->toBeTrue();

        expect(
            $snapshot['context']
        )->toBeArray();

        expect(
            $snapshot['manifest']
        )->toBeArray();
    }
);

test(
    'p22 unavailable snapshot keeps exact null boundary',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalDatasetConsumption::class
            );

        $method =
            new ReflectionMethod(
                DataTransformationBiCanonicalDatasetConsumption::class,
                'resolvedSnapshot'
            );

        $snapshot =
            $method->invoke(
                $service,
                [
                    'available' =>
                        false,

                    'reason' =>
                        'no_successful_normalized_dataset',

                    'context' =>
                        null,

                    'manifest' =>
                        null,
                ]
            );

        expect($snapshot)
            ->toBe([
                'available' =>
                    false,

                'reason' =>
                    'no_successful_normalized_dataset',

                'context' =>
                    null,

                'manifest' =>
                    null,
            ]);
    }
);

test(
    'p22 empty resolved snapshot preserves zero row boundary without database access',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalDatasetConsumption::class
            );

        $method =
            new ReflectionMethod(
                DataTransformationBiCanonicalDatasetConsumption::class,
                'resolvedSnapshot'
            );

        $snapshot =
            $method->invoke(
                $service,
                p22EmptyResolvedSnapshot()
            );

        expect(
            $snapshot['available']
        )->toBeTrue();

        expect(
            $snapshot['manifest']['total_rows']
        )->toBe(0);

        $dataset =
            DataTransformationBiCanonicalDatasetContext
                ::dataset(
                    $snapshot['context']
                );

        expect(
            $dataset['normalized_row_count']
        )->toBe(0);

        expect(
            $dataset['has_rows']
        )->toBeFalse();

        /*
         * Streaming delegation itself is a static unit contract above.
         * Do not invoke the real P21/P14 reader here with synthetic run ids:
         * Unit tests must remain database independent.
         */
        $stream =
            p22Method(
                'iterateResolvedSnapshot'
            );

        expect($stream)
            ->toContain(
                '->iterateDomainInDataset('
            )
            ->not
            ->toContain(
                '->iterateDomain('
            )
            ->not
            ->toContain(
                '->current('
            )
            ->not
            ->toContain(
                '->forProcessingRun('
            );
    }
);

test(
    'p22 rejects mismatched context and manifest',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalDatasetConsumption::class
            );

        $snapshot =
            p22EmptyResolvedSnapshot();

        $snapshot[
            'manifest'
        ][
            'company_id'
        ] =
            999;

        expect(
            fn () =>
                iterator_to_array(
                    $service
                        ->iterateResolvedSnapshot(
                            $snapshot,
                            500
                        ),
                    false
                )
        )->toThrow(
            RuntimeException::class
        );
    }
);

test(
    'p22 rejects unavailable snapshot streaming',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalDatasetConsumption::class
            );

        expect(
            fn () =>
                iterator_to_array(
                    $service
                        ->iterateResolvedSnapshot(
                            [
                                'available' =>
                                    false,

                                'reason' =>
                                    'no_dataset',

                                'context' =>
                                    null,

                                'manifest' =>
                                    null,
                            ]
                        ),
                    false
                )
        )->toThrow(
            RuntimeException::class
        );
    }
);

test(
    'p22 page size is bounded to five hundred',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalDatasetConsumption::class
            );

        foreach ([0, 501] as $pageSize) {
            expect(
                fn () =>
                    iterator_to_array(
                        $service
                            ->iterateResolvedSnapshot(
                                p22EmptyResolvedSnapshot(),
                                $pageSize
                            ),
                        false
                    )
            )->toThrow(
                InvalidArgumentException::class
            );
        }
    }
);

test(
    'p22 does not invent schema manifest identity or storage authority',
    function () {
        $source =
            p22Source();

        foreach ([
            'DataTransformationBiCanonicalDatasetContext',
            'DataTransformationBiCanonicalDatasetManifest',
            'DataTransformationBiCanonicalDatasetManifestResolver',
            'DataTransformationBiProjectedDatasetReader',
        ] as $required) {
            expect($source)
                ->toContain(
                    $required
                );
        }

        foreach ([
            'DataTransformationBiNormalizedRow',
            'DataTransformationBiPreparedDatasetReader',
            'normalized_payload',
            "'payload'",
            "'normalized_row_id'",
            "'identity_hash'",
            'DB::',
            '::query()',
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
    'p22 remains streaming read only and analytical model free',
    function () {
        $source =
            p22Source();

        foreach ([
            'iterator_to_array(',
            'collect(',
            '->toArray(',
            '->create(',
            '->update(',
            '->delete(',
            '->save(',
            '->insert(',
            '->upsert(',
            'ConsumerCheckpoint',
            'DatasetPublication',
            'DatasetRegistry',
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
