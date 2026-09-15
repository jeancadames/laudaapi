<?php

use App\Services\Diagnosis\DataTransformationBiCanonicalDatasetDelta;

function p18DeltaSource(): string
{
    return file_get_contents(
        dirname(__DIR__, 3)
        .'/app/Services/Diagnosis/'
        .'DataTransformationBiCanonicalDatasetDelta.php'
    );
}

function p18Signature(
    int $id,
    string $identity,
    string $content
): array {
    return [
        'normalized_row_id' =>
            $id,

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
    'p18 c classifies added removed modified and unchanged',
    function () {
        $base = [
            p18Signature(
                1,
                '1',
                'a'
            ),
            p18Signature(
                2,
                '2',
                'b'
            ),
            p18Signature(
                4,
                '4',
                'd'
            ),
        ];

        $target = [
            p18Signature(
                10,
                '1',
                'a'
            ),
            p18Signature(
                30,
                '3',
                'c'
            ),
            p18Signature(
                40,
                '4',
                'e'
            ),
        ];

        $delta =
            iterator_to_array(
                DataTransformationBiCanonicalDatasetDelta
                    ::mergeSignatureStreams(
                        $base,
                        $target
                    ),
                false
            );

        expect(
            array_column(
                $delta,
                'change_type'
            )
        )
            ->toBe([
                'unchanged',
                'removed',
                'added',
                'modified',
            ]);

        expect(
            array_column(
                $delta,
                'canonical_identity_hash'
            )
        )
            ->toBe([
                str_repeat('1', 64),
                str_repeat('2', 64),
                str_repeat('3', 64),
                str_repeat('4', 64),
            ]);
    }
);

test(
    'p18 c preserves directional base and target row identities',
    function () {
        $delta =
            iterator_to_array(
                DataTransformationBiCanonicalDatasetDelta
                    ::mergeSignatureStreams(
                        [
                            p18Signature(
                                2,
                                '2',
                                'b'
                            ),
                            p18Signature(
                                4,
                                '4',
                                'd'
                            ),
                        ],
                        [
                            p18Signature(
                                30,
                                '3',
                                'c'
                            ),
                            p18Signature(
                                40,
                                '4',
                                'e'
                            ),
                        ]
                    ),
                false
            );

        expect($delta[0])
            ->toMatchArray([
                'change_type' =>
                    'removed',
                'base_normalized_row_id' =>
                    2,
                'target_normalized_row_id' =>
                    null,
            ]);

        expect($delta[1])
            ->toMatchArray([
                'change_type' =>
                    'added',
                'base_normalized_row_id' =>
                    null,
                'target_normalized_row_id' =>
                    30,
            ]);

        expect($delta[2])
            ->toMatchArray([
                'change_type' =>
                    'modified',
                'base_normalized_row_id' =>
                    4,
                'target_normalized_row_id' =>
                    40,
            ]);
    }
);

test(
    'p18 c unchanged requires equal canonical content fingerprint',
    function () {
        $hash =
            str_repeat(
                '5',
                64
            );

        $content =
            str_repeat(
                'f',
                64
            );

        $delta =
            iterator_to_array(
                DataTransformationBiCanonicalDatasetDelta
                    ::mergeSignatureStreams(
                        [[
                            'normalized_row_id' => 5,
                            'canonical_identity_hash' => $hash,
                            'normalized_sha256' => $content,
                        ]],
                        [[
                            'normalized_row_id' => 50,
                            'canonical_identity_hash' => $hash,
                            'normalized_sha256' => $content,
                        ]]
                    ),
                false
            );

        expect($delta)
            ->toHaveCount(1);

        expect($delta[0]['change_type'])
            ->toBe('unchanged');
    }
);

test(
    'p18 c rejects duplicate or unordered base signatures',
    function () {
        $stream = static function (): void {
            iterator_to_array(
                DataTransformationBiCanonicalDatasetDelta
                    ::mergeSignatureStreams(
                        [
                            p18Signature(
                                1,
                                '2',
                                'a'
                            ),
                            p18Signature(
                                2,
                                '2',
                                'b'
                            ),
                        ],
                        []
                    ),
                false
            );
        };

        expect($stream)
            ->toThrow(
                RuntimeException::class
            );
    }
);

test(
    'p18 c rejects malformed signatures',
    function () {
        $stream = static function (): void {
            iterator_to_array(
                DataTransformationBiCanonicalDatasetDelta
                    ::mergeSignatureStreams(
                        [[
                            'normalized_row_id' => 1,
                            'canonical_identity_hash' => 'invalid',
                            'normalized_sha256' => str_repeat('a', 64),
                        ]],
                        []
                    ),
                false
            );
        };

        expect($stream)
            ->toThrow(
                RuntimeException::class
            );
    }
);

test(
    'p18 c resolves versioned pair exactly once',
    function () {
        $source =
            p18DeltaSource();

        expect(
            substr_count(
                $source,
                '->pair('
            )
        )
            ->toBe(1);

        expect($source)
            ->toContain(
                '$baseDataset'
            )
            ->toContain(
                '$targetDataset'
            );
    }
);

test(
    'p18 c creates two pinned signature streams',
    function () {
        $source =
            p18DeltaSource();

        expect(
            substr_count(
                $source,
                '->iterateDomainSignaturesInDataset('
            )
        )
            ->toBe(2);

        expect($source)
            ->toContain(
                '$baseDataset'
            )
            ->toContain(
                '$targetDataset'
            );
    }
);

test(
    'p18 c uses streaming merge without loading complete domains',
    function () {
        $source =
            p18DeltaSource();

        expect($source)
            ->toContain(
                'mergeSignatureStreams('
            )
            ->toContain(
                'yield'
            )
            ->toContain(
                'strcmp('
            )
            ->not
            ->toContain(
                '->get('
            )
            ->not
            ->toContain(
                '->all('
            )
            ->not
            ->toContain(
                'collect('
            );
    }
);

test(
    'p18 c never reads normalized payload',
    function () {
        $source =
            p18DeltaSource();

        expect($source)
            ->not
            ->toContain(
                'normalized_payload'
            )
            ->not
            ->toContain(
                'normalization_meta'
            )
            ->not
            ->toContain(
                'source_row_sha256'
            );
    }
);

test(
    'p18 c is read only',
    function () {
        $source =
            p18DeltaSource();

        foreach ([
            '->create(',
            '->update(',
            '->delete(',
            '->save(',
            '->insert(',
            '->upsert(',
            'forceFill(',
        ] as $forbidden) {
            expect($source)
                ->not
                ->toContain($forbidden);
        }
    }
);

test(
    'p18 c remains internal and does not start analytical model',
    function () {
        $source =
            p18DeltaSource();

        foreach ([
            'FactSales',
            'DimCustomer',
            'DimProduct',
            'star_schema',
        ] as $forbidden) {
            expect($source)
                ->not
                ->toContain($forbidden);
        }

        $needle =
            'DataTransformationBiCanonicalDatasetDelta';

        foreach ([
            'app/Http/Controllers',
            'resources/js',
            'routes',
        ] as $relative) {
            $root =
                dirname(__DIR__, 3)
                .'/'.$relative;

            $iterator =
                new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator(
                        $root,
                        FilesystemIterator::SKIP_DOTS
                    )
                );

            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                expect(
                    file_get_contents(
                        $file->getPathname()
                    )
                )
                    ->not
                    ->toContain($needle);
            }
        }
    }
);
