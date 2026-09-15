<?php

use App\Services\Diagnosis\DataTransformationBiCanonicalDatasetChangeSet;
use RuntimeException;

function p20cManifest(
    array $counts,
    string $datasetFingerprint
): array {
    $domains = [
        'customers',
        'products',
        'inventory',
        'sales',
        'accounts_receivable',
        'suppliers',
        'accounts_payable',
    ];

    $entries = [];

    foreach (
        $domains
        as $index => $domain
    ) {
        $entries[] = [
            'domain' =>
                $domain,

            'row_count' =>
                $counts[$domain]
                ?? 0,

            'fingerprint' =>
                hash(
                    'sha256',
                    "domain:{$index}:{$domain}"
                ),
        ];
    }

    return [
        'total_rows' =>
            array_sum(
                $counts
            ),

        'domains' =>
            $entries,

        'dataset_fingerprint' =>
            $datasetFingerprint,
    ];
}

function p20cRow(
    string $domain,
    string $identity,
    string $type
): array {
    return [
        'domain' =>
            $domain,

        'canonical_identity_hash' =>
            str_repeat(
                $identity,
                64
            ),

        'change_type' =>
            $type,

        'base_normalized_row_id' =>
            null,

        'target_normalized_row_id' =>
            null,

        'base_normalized_sha256' =>
            null,

        'target_normalized_sha256' =>
            null,
    ];
}

test(
    'p20 c exposes top level resolved and pure summary apis',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalDatasetChangeSet.php'
            );

        expect($source)
            ->toContain(
                'public function summarizeDatasetDelta('
            )
            ->toContain(
                'public function summarizeResolvedPair('
            )
            ->toContain(
                'public function summarizeRows('
            );
    }
);

test(
    'p20 c top level summary resolves p19 pair exactly once',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalDatasetChangeSet.php'
            );

        $start =
            strpos(
                $source,
                'public function summarizeDatasetDelta('
            );

        $end =
            strpos(
                $source,
                'public function summarizeResolvedPair('
            );

        $method =
            substr(
                $source,
                $start,
                $end - $start
            );

        expect(
            substr_count(
                $method,
                '->pair('
            )
        )->toBe(1);

        expect(
            substr_count(
                $method,
                '->summarizeResolvedPair('
            )
        )->toBe(1);
    }
);

test(
    'p20 c resolved summary never reresolves pair',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalDatasetChangeSet.php'
            );

        $start =
            strpos(
                $source,
                'public function summarizeResolvedPair('
            );

        $end =
            strpos(
                $source,
                'public function summarizeRows('
            );

        $method =
            substr(
                $source,
                $start,
                $end - $start
            );

        expect($method)
            ->not
            ->toContain(
                '->pair('
            )
            ->not
            ->toContain(
                '->forProcessingRun('
            )
            ->not
            ->toContain(
                '->forRequest('
            )
            ->toContain(
                '->iterateResolvedPair('
            )
            ->toContain(
                '->summarizeRows('
            );
    }
);

test(
    'p20 c summarizes added removed modified and unchanged globally and by domain',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalDatasetChangeSet::class
            );

        $base =
            p20cManifest(
                [
                    'customers' => 2,
                    'products' => 1,
                ],
                str_repeat('a', 64)
            );

        $target =
            p20cManifest(
                [
                    'customers' => 3,
                    'products' => 0,
                ],
                str_repeat('b', 64)
            );

        $rows = [
            p20cRow(
                'customers',
                '1',
                'unchanged'
            ),
            p20cRow(
                'customers',
                '2',
                'modified'
            ),
            p20cRow(
                'customers',
                '3',
                'added'
            ),
            p20cRow(
                'products',
                '4',
                'removed'
            ),
        ];

        $summary =
            $service->summarizeRows(
                $base,
                $target,
                false,
                $rows
            );

        expect(
            $summary['counts']
        )->toBe([
            'added' => 1,
            'removed' => 1,
            'modified' => 1,
            'unchanged' => 1,
        ]);

        expect(
            $summary['changed_rows']
        )->toBe(3);

        expect(
            $summary['base_total_rows']
        )->toBe(3);

        expect(
            $summary['target_total_rows']
        )->toBe(3);

        expect(
            $summary['domains']['customers']
        )->toMatchArray([
            'base_rows' => 2,
            'target_rows' => 3,
            'added' => 1,
            'removed' => 0,
            'modified' => 1,
            'unchanged' => 1,
            'changed_rows' => 2,
        ]);

        expect(
            $summary['domains']['products']
        )->toMatchArray([
            'base_rows' => 1,
            'target_rows' => 0,
            'added' => 0,
            'removed' => 1,
            'modified' => 0,
            'unchanged' => 0,
            'changed_rows' => 1,
        ]);
    }
);

test(
    'p20 c reconciles same content datasets',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalDatasetChangeSet::class
            );

        $fingerprint =
            str_repeat(
                'c',
                64
            );

        $base =
            p20cManifest(
                [
                    'customers' => 2,
                ],
                $fingerprint
            );

        $target =
            p20cManifest(
                [
                    'customers' => 2,
                ],
                $fingerprint
            );

        $summary =
            $service->summarizeRows(
                $base,
                $target,
                true,
                [
                    p20cRow(
                        'customers',
                        '1',
                        'unchanged'
                    ),
                    p20cRow(
                        'customers',
                        '2',
                        'unchanged'
                    ),
                ]
            );

        expect(
            $summary['same_content']
        )->toBeTrue();

        expect(
            $summary['changed_rows']
        )->toBe(0);

        expect(
            $summary['counts']
        )->toBe([
            'added' => 0,
            'removed' => 0,
            'modified' => 0,
            'unchanged' => 2,
        ]);
    }
);

test(
    'p20 c fails closed when delta counts do not reconcile with manifests',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalDatasetChangeSet::class
            );

        $base =
            p20cManifest(
                [
                    'customers' => 2,
                ],
                str_repeat('a', 64)
            );

        $target =
            p20cManifest(
                [
                    'customers' => 2,
                ],
                str_repeat('b', 64)
            );

        expect(
            fn () =>
                $service->summarizeRows(
                    $base,
                    $target,
                    false,
                    [
                        p20cRow(
                            'customers',
                            '1',
                            'unchanged'
                        ),
                    ]
                )
        )->toThrow(
            RuntimeException::class
        );
    }
);

test(
    'p20 c fails closed when same content contradicts fingerprints',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalDatasetChangeSet::class
            );

        expect(
            fn () =>
                $service->summarizeRows(
                    p20cManifest(
                        [],
                        str_repeat('a', 64)
                    ),
                    p20cManifest(
                        [],
                        str_repeat('b', 64)
                    ),
                    true,
                    []
                )
        )->toThrow(
            RuntimeException::class
        );
    }
);

test(
    'p20 c rejects noncanonical domain ordering and duplicate identities',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalDatasetChangeSet::class
            );

        $base =
            p20cManifest(
                [
                    'customers' => 1,
                    'products' => 1,
                ],
                str_repeat('a', 64)
            );

        $target =
            p20cManifest(
                [
                    'customers' => 1,
                    'products' => 1,
                ],
                str_repeat('b', 64)
            );

        expect(
            fn () =>
                $service->summarizeRows(
                    $base,
                    $target,
                    false,
                    [
                        p20cRow(
                            'products',
                            '2',
                            'unchanged'
                        ),
                        p20cRow(
                            'customers',
                            '1',
                            'unchanged'
                        ),
                    ]
                )
        )->toThrow(
            RuntimeException::class
        );

        expect(
            fn () =>
                $service->summarizeRows(
                    p20cManifest(
                        [
                            'customers' => 2,
                        ],
                        str_repeat('a', 64)
                    ),
                    p20cManifest(
                        [
                            'customers' => 2,
                        ],
                        str_repeat('b', 64)
                    ),
                    false,
                    [
                        p20cRow(
                            'customers',
                            '1',
                            'unchanged'
                        ),
                        p20cRow(
                            'customers',
                            '1',
                            'unchanged'
                        ),
                    ]
                )
        )->toThrow(
            RuntimeException::class
        );
    }
);

test(
    'p20 c remains payload free read only and internal',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalDatasetChangeSet.php'
            );

        foreach ([
            'normalized_payload',
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
