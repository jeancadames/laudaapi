<?php

function p17BulkIdentitySource(
    string $path
): string {
    return file_get_contents(
        dirname(__DIR__, 3).'/'.$path
    );
}

function p17BulkIdentityMethodSource(): string
{
    $source =
        p17BulkIdentitySource(
            'app/Services/Diagnosis/'
            .'DataTransformationBiPreparedDatasetReader.php'
        );

    $start =
        strpos(
            $source,
            'public function '
            .'findDomainRowsByCanonicalIdentityHashesInDataset('
        );

    $end =
        strpos(
            $source,
            'public function '
            .'findDomainRowByCanonicalIdentityHashInDataset(',
            $start
        );

    expect($start)
        ->not
        ->toBeFalse();

    expect($end)
        ->not
        ->toBeFalse();

    return substr(
        $source,
        $start,
        $end - $start
    );
}

test(
    'p17 bulk canonical lookup is public and dataset pinned',
    function () {
        $source =
            p17BulkIdentityMethodSource();

        expect($source)
            ->toContain(
                'findDomainRowsByCanonicalIdentityHashesInDataset('
            )
            ->toContain(
                '$this->datasetIdentity('
            )
            ->toContain(
                "\$dataset['processing_run_id']"
            )
            ->toContain(
                "\$dataset['intake_batch_id']"
            )
            ->not
            ->toContain(
                '->forRequest('
            );
    }
);

test(
    'p17 bulk lookup passes only the frozen dataset to dataset identity',
    function () {
        $source =
            p17BulkIdentityMethodSource();

        $start =
            strpos(
                $source,
                '$this->datasetIdentity('
            );

        expect($start)
            ->not
            ->toBeFalse();

        $end =
            strpos(
                $source,
                ');',
                $start
            );

        expect($end)
            ->not
            ->toBeFalse();

        $call =
            substr(
                $source,
                $start,
                $end - $start + 2
            );

        expect($call)
            ->toContain(
                '$dataset'
            )
            ->not
            ->toContain(
                '$companyId'
            );
    }
);

test(
    'p17 bulk lookup is scoped to company run batch and domain',
    function () {
        $source =
            p17BulkIdentityMethodSource();

        foreach ([
            "'company_id'",
            "'data_transformation_bi_processing_run_id'",
            "'data_transformation_bi_intake_batch_id'",
            "'domain_key'",
            "'canonical_identity_hash'",
        ] as $required) {
            expect($source)
                ->toContain($required);
        }
    }
);

test(
    'p17 bulk lookup uses one bounded indexed where in query',
    function () {
        $source =
            p17BulkIdentityMethodSource();

        expect($source)
            ->toContain(
                '->whereIn('
            )
            ->toContain(
                "'canonical_identity_hash'"
            )
            ->toContain(
                'count($hashes) > 500'
            )
            ->toContain(
                '->limit('
            )
            ->toContain(
                'count($hashes)'
            )
            ->toContain(
                '->get(['
            );
    }
);

test(
    'p17 bulk lookup validates and deduplicates sha256 identities',
    function () {
        $source =
            p17BulkIdentityMethodSource();

        expect($source)
            ->toContain(
                "'/^[a-f0-9]{64}$/'"
            )
            ->toContain(
                'strtolower('
            )
            ->toContain(
                '$hashes[$normalizedHash]'
            )
            ->toContain(
                'array_keys('
            )
            ->toContain(
                'array_fill_keys('
            );
    }
);

test(
    'p17 bulk lookup returns canonical rows keyed by requested hash',
    function () {
        $source =
            p17BulkIdentityMethodSource();

        expect($source)
            ->toContain(
                '$this->canonicalRow('
            )
            ->toContain(
                '$found[$canonicalHash]'
            )
            ->toContain(
                '$ordered[$hash]'
            )
            ->toContain(
                'return $ordered;'
            );
    }
);

test(
    'p17 reader layer permits missing identities but rejects duplicates',
    function () {
        $source =
            p17BulkIdentityMethodSource();

        /*
         * Missing identities are intentionally omitted:
         * only hashes actually present in $found are copied
         * into the ordered response.
         */
        expect($source)
            ->toContain(
                '$ordered = [];'
            )
            ->toContain(
                'array_key_exists('
            )
            ->toContain(
                '$found'
            )
            ->toContain(
                '$ordered[$hash]'
            )
            ->toContain(
                'return $ordered;'
            )
            ->toContain(
                'identidad duplicada'
            )
            ->not
            ->toContain(
                'target_found'
            );
    }
);

test(
    'p17 bulk lookup performs no writes',
    function () {
        $source =
            p17BulkIdentityMethodSource();

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
    'p17 does not expose bulk canonical lookup through public surfaces',
    function () {
        $needle =
            'findDomainRowsByCanonicalIdentityHashesInDataset';

        $paths = [
            'app/Http/Controllers',
            'resources/js',
            'routes',
        ];

        foreach ($paths as $relative) {
            $root =
                dirname(__DIR__, 3).'/'.$relative;

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

                $source =
                    file_get_contents(
                        $file->getPathname()
                    );

                expect($source)
                    ->not
                    ->toContain($needle);
            }
        }
    }
);
