<?php

function p20aMethodSource(
    string $method
): string {
    $source =
        file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Services/Diagnosis/'
            .'DataTransformationBiCanonicalDatasetDelta.php'
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
    'p20 a exposes pinned domain delta primitive',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalDatasetDelta.php'
            );

        expect($source)
            ->toContain(
                'public function iterateDomainDeltaInDatasets('
            );
    }
);

test(
    'p20 a pinned primitive never resolves dataset pair',
    function () {
        $method =
            p20aMethodSource(
                'iterateDomainDeltaInDatasets'
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
            );
    }
);

test(
    'p20 a pinned primitive freezes exactly two signature streams',
    function () {
        $method =
            p20aMethodSource(
                'iterateDomainDeltaInDatasets'
            );

        expect(
            substr_count(
                $method,
                '->iterateDomainSignaturesInDataset('
            )
        )->toBe(2);

        expect($method)
            ->toContain(
                '$baseDataset'
            )
            ->toContain(
                '$targetDataset'
            )
            ->toContain(
                'yield from self::mergeSignatureStreams('
            );
    }
);

test(
    'p20 a existing p18 entry point still resolves pair exactly once',
    function () {
        $method =
            p20aMethodSource(
                'iterateDomainDelta'
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
                '->iterateDomainDeltaInDatasets('
            )
        )->toBe(1);
    }
);

test(
    'p20 a existing p18 entry point no longer creates duplicate signature streams',
    function () {
        $method =
            p20aMethodSource(
                'iterateDomainDelta'
            );

        expect($method)
            ->not
            ->toContain(
                '->iterateDomainSignaturesInDataset('
            );
    }
);

test(
    'p20 a preserves canonical domain and page validation',
    function () {
        $method =
            p20aMethodSource(
                'iterateDomainDeltaInDatasets'
            );

        expect($method)
            ->toContain(
                '$this->canonicalDomain('
            )
            ->toContain(
                '$this->pageSize('
            );
    }
);

test(
    'p20 a remains payload free and persistence agnostic',
    function () {
        $method =
            p20aMethodSource(
                'iterateDomainDeltaInDatasets'
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
        ] as $forbidden) {
            expect($method)
                ->not
                ->toContain(
                    $forbidden
                );
        }
    }
);
