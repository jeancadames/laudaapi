<?php

use App\Services\Diagnosis\DataTransformationBiCanonicalDatasetManifest;
use RuntimeException;

function p19bDescriptor(
    int $rows = 3
): array {
    return [
        'processing_run_id' =>
            101,

        'intake_batch_id' =>
            51,

        'definition_version' =>
            3,

        'schema_version' =>
            1,

        'profiling_version' =>
            1,

        'normalization_version' =>
            1,

        'normalized_row_count' =>
            $rows,

        'has_rows' =>
            $rows > 0,

        'completed_at' =>
            '2026-09-15T18:00:00+00:00',
    ];
}

function p19bSignature(
    string $identity,
    string $content
): array {
    return [
        'normalized_row_id' =>
            999,

        'canonical_identity_hash' =>
            str_repeat(
                $identity,
                64
            ),

        'normalized_sha256' =>
            str_repeat(
                $content,
                64
            ),
    ];
}

test(
    'p19 b adds pinned domain counts without p13 reresolution',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiPreparedDatasetReader.php'
            );

        expect($source)
            ->toContain(
                'public function domainCountsInDataset('
            )
            ->toContain(
                '$this->domainCountsInDataset('
            );

        $start =
            strpos(
                $source,
                'public function domainCountsInDataset('
            );

        expect($start)
            ->not
            ->toBeFalse();

        $tail =
            substr(
                $source,
                $start
            );

        $next =
            strpos(
                $tail,
                "\n    public function ",
                20
            );

        $method =
            $next === false
                ? $tail
                : substr(
                    $tail,
                    0,
                    $next
                );

        expect($method)
            ->toContain(
                "'company_id'"
            )
            ->toContain(
                "'data_transformation_bi_processing_run_id'"
            )
            ->toContain(
                "'data_transformation_bi_intake_batch_id'"
            )
            ->toContain(
                "'domain_key, COUNT(*) AS aggregate_count'"
            )
            ->not
            ->toContain(
                '->forRequest('
            );
    }
);

test(
    'p19 b domain fingerprint uses canonical identity and normalized content only',
    function () {
        $manifest =
            DataTransformationBiCanonicalDatasetManifest
                ::fingerprintDomain(
                    'customers',
                    [
                        p19bSignature(
                            '1',
                            'a'
                        ),
                        p19bSignature(
                            '2',
                            'b'
                        ),
                    ]
                );

        expect($manifest['domain'])
            ->toBe(
                'customers'
            );

        expect($manifest['row_count'])
            ->toBe(2);

        expect(
            $manifest[
                'fingerprint'
            ]
        )->toMatch(
            '/^[a-f0-9]{64}$/'
        );
    }
);

test(
    'p19 b domain fingerprint ignores normalized row storage identity',
    function () {
        $first = [
            [
                'normalized_row_id' => 1,
                'canonical_identity_hash' =>
                    str_repeat('1', 64),
                'normalized_sha256' =>
                    str_repeat('a', 64),
            ],
        ];

        $second = [
            [
                'normalized_row_id' => 99999,
                'canonical_identity_hash' =>
                    str_repeat('1', 64),
                'normalized_sha256' =>
                    str_repeat('a', 64),
            ],
        ];

        $a =
            DataTransformationBiCanonicalDatasetManifest
                ::fingerprintDomain(
                    'customers',
                    $first
                );

        $b =
            DataTransformationBiCanonicalDatasetManifest
                ::fingerprintDomain(
                    'customers',
                    $second
                );

        expect(
            $a['fingerprint']
        )->toBe(
            $b['fingerprint']
        );
    }
);

test(
    'p19 b domain fingerprint changes when normalized canonical content changes',
    function () {
        $a =
            DataTransformationBiCanonicalDatasetManifest
                ::fingerprintDomain(
                    'customers',
                    [
                        p19bSignature(
                            '1',
                            'a'
                        ),
                    ]
                );

        $b =
            DataTransformationBiCanonicalDatasetManifest
                ::fingerprintDomain(
                    'customers',
                    [
                        p19bSignature(
                            '1',
                            'b'
                        ),
                    ]
                );

        expect(
            $a['fingerprint']
        )->not->toBe(
            $b['fingerprint']
        );
    }
);

test(
    'p19 b rejects unordered or duplicate canonical signatures',
    function () {
        expect(
            fn () =>
                DataTransformationBiCanonicalDatasetManifest
                    ::fingerprintDomain(
                        'customers',
                        [
                            p19bSignature(
                                '2',
                                'a'
                            ),
                            p19bSignature(
                                '1',
                                'b'
                            ),
                        ]
                    )
        )->toThrow(
            RuntimeException::class
        );

        expect(
            fn () =>
                DataTransformationBiCanonicalDatasetManifest
                    ::fingerprintDomain(
                        'customers',
                        [
                            p19bSignature(
                                '1',
                                'a'
                            ),
                            p19bSignature(
                                '1',
                                'a'
                            ),
                        ]
                    )
        )->toThrow(
            RuntimeException::class
        );
    }
);

test(
    'p19 b global fingerprint excludes execution identity and timestamps',
    function () {
        $domains = [];

        foreach ([
            'customers',
            'products',
            'inventory',
            'sales',
            'accounts_receivable',
            'suppliers',
            'accounts_payable',
        ] as $domain) {
            $domains[] =
                DataTransformationBiCanonicalDatasetManifest
                    ::fingerprintDomain(
                        $domain,
                        []
                    );
        }

        $first =
            p19bDescriptor(
                0
            );

        $second =
            $first;

        $second[
            'processing_run_id'
        ] = 999;

        $second[
            'intake_batch_id'
        ] = 888;

        $second[
            'completed_at'
        ] =
            '2027-01-01T00:00:00+00:00';

        expect(
            DataTransformationBiCanonicalDatasetManifest
                ::fingerprintDataset(
                    $first,
                    $domains
                )
        )->toBe(
            DataTransformationBiCanonicalDatasetManifest
                ::fingerprintDataset(
                    $second,
                    $domains
                )
        );
    }
);

test(
    'p19 b global fingerprint changes when pipeline version changes',
    function () {
        $domains = [];

        foreach ([
            'customers',
            'products',
            'inventory',
            'sales',
            'accounts_receivable',
            'suppliers',
            'accounts_payable',
        ] as $domain) {
            $domains[] =
                DataTransformationBiCanonicalDatasetManifest
                    ::fingerprintDomain(
                        $domain,
                        []
                    );
        }

        $first =
            p19bDescriptor(
                0
            );

        $second =
            $first;

        $second[
            'normalization_version'
        ] = 2;

        expect(
            DataTransformationBiCanonicalDatasetManifest
                ::fingerprintDataset(
                    $first,
                    $domains
                )
        )->not->toBe(
            DataTransformationBiCanonicalDatasetManifest
                ::fingerprintDataset(
                    $second,
                    $domains
                )
        );
    }
);

test(
    'p19 b requires canonical domain order for dataset fingerprint',
    function () {
        $domains = [];

        foreach ([
            'customers',
            'products',
            'inventory',
            'sales',
            'accounts_receivable',
            'suppliers',
            'accounts_payable',
        ] as $domain) {
            $domains[] =
                DataTransformationBiCanonicalDatasetManifest
                    ::fingerprintDomain(
                        $domain,
                        []
                    );
        }

        [$domains[0], $domains[1]] = [
            $domains[1],
            $domains[0],
        ];

        expect(
            fn () =>
                DataTransformationBiCanonicalDatasetManifest
                    ::fingerprintDataset(
                        p19bDescriptor(
                            0
                        ),
                        $domains
                    )
        )->toThrow(
            RuntimeException::class
        );
    }
);

test(
    'p19 b manifest uses pinned counts and one signature stream per canonical domain',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalDatasetManifest.php'
            );

        expect(
            substr_count(
                $source,
                '->domainCountsInDataset('
            )
        )->toBe(1);

        expect(
            substr_count(
                $source,
                '->iterateDomainSignaturesInDataset('
            )
        )->toBe(1);

        expect($source)
            ->toContain(
                'foreach ($domains as $domain)'
            )
            ->toContain(
                'DataTransformationBiCanonicalDatasetContext'
            )
            ->not
            ->toContain(
                '->forRequest('
            )
            ->not
            ->toContain(
                'normalized_payload'
            );
    }
);

test(
    'p19 b remains internal read only and does not start analytical model',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalDatasetManifest.php'
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
            'forceFill(',
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
