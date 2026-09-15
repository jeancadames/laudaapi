<?php

function p20bMethodSource(
    string $method
): string {
    $source =
        file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Services/Diagnosis/'
            .'DataTransformationBiCanonicalDatasetChangeSet.php'
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
    'p20 b exposes dataset wide and resolved pair iterators',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalDatasetChangeSet.php'
            );

        expect($source)
            ->toContain(
                'public function iterateDatasetDelta('
            )
            ->toContain(
                'public function iterateResolvedPair('
            );
    }
);

test(
    'p20 b top level resolves p19 pair exactly once',
    function () {
        $method =
            p20bMethodSource(
                'iterateDatasetDelta'
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
                '->iterateResolvedPair('
            )
        )->toBe(1);

        expect($method)
            ->not
            ->toContain(
                '->iterateDomainDeltaInDatasets('
            );
    }
);

test(
    'p20 b resolved pair never reresolves datasets',
    function () {
        $method =
            p20bMethodSource(
                'iterateResolvedPair'
            );

        foreach ([
            '->pair(',
            '->forProcessingRun(',
            '->forRequest(',
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
    'p20 b resolved pair streams canonical domains through pinned p20 a primitive',
    function () {
        $method =
            p20bMethodSource(
                'iterateResolvedPair'
            );

        expect($method)
            ->toContain(
                'DataTransformationBiCanonicalDatasetContext'
            )
            ->toContain(
                '::domains()'
            )
            ->toContain(
                '->iterateDomainDeltaInDatasets('
            )
            ->toContain(
                "'domain'"
            );
    }
);

test(
    'p20 b dataset rows preserve canonical delta metadata',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalDatasetChangeSet.php'
            );

        foreach ([
            'canonical_identity_hash',
            'change_type',
            'base_normalized_sha256',
            'target_normalized_sha256',
        ] as $field) {
            expect($source)
                ->toContain(
                    $field
                );
        }

        foreach ([
            'base_normalized_row_id',
            'target_normalized_row_id',
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
    'p20 b validates frozen pair manifests and same content',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalDatasetChangeSet.php'
            );

        expect($source)
            ->toContain(
                "'manifest'"
            )
            ->toContain(
                "'dataset_fingerprint'"
            )
            ->toContain(
                "'same_content'"
            )
            ->toContain(
                'hash_equals('
            )
            ->toContain(
                "'compatibility'"
            );
    }
);

test(
    'p20 b stays payload free read only and internal',
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
