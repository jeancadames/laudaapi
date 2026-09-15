<?php

function p18SignatureSource(): string
{
    return file_get_contents(
        dirname(__DIR__, 3)
        .'/app/Services/Diagnosis/'
        .'DataTransformationBiCanonicalRowSignatureReader.php'
    );
}

test(
    'p18 signature reader uses an already pinned dataset',
    function () {
        $source =
            p18SignatureSource();

        expect($source)
            ->toContain(
                'iterateDomainSignaturesInDataset('
            )
            ->toContain(
                '$this->datasetIdentity('
            )
            ->not
            ->toContain(
                '->forRequest('
            );
    }
);

test(
    'p18 signature query is scoped to company run batch and domain',
    function () {
        $source =
            p18SignatureSource();

        foreach ([
            "'company_id'",
            "'data_transformation_bi_processing_run_id'",
            "'data_transformation_bi_intake_batch_id'",
            "'domain_key'",
        ] as $required) {
            expect($source)
                ->toContain($required);
        }
    }
);

test(
    'p18 signature consists of canonical identity and normalized sha256',
    function () {
        $source =
            p18SignatureSource();

        expect($source)
            ->toContain(
                "'canonical_identity_hash'"
            )
            ->toContain(
                "'normalized_sha256'"
            )
            ->toContain(
                "'/^[a-f0-9]{64}$/'"
            );
    }
);

test(
    'p18 signature reader never reads normalized payload',
    function () {
        $source =
            p18SignatureSource();

        /*
         * The phrase may appear in explanatory prose, therefore assert
         * specifically that it is never selected as a quoted field.
         */
        expect($source)
            ->not
            ->toContain(
                "'normalized_payload'"
            )
            ->not
            ->toContain(
                "'normalization_meta'"
            )
            ->not
            ->toContain(
                "'source_row_sha256'"
            )
            ->not
            ->toContain(
                "'identity_hash'"
            );
    }
);

test(
    'p18 signatures are ordered by canonical identity',
    function () {
        $source =
            p18SignatureSource();

        expect($source)
            ->toContain(
                '->orderBy('
            )
            ->toContain(
                "'canonical_identity_hash'"
            )
            ->toContain(
                'strcmp('
            )
            ->toContain(
                'en orden estricto'
            );
    }
);

test(
    'p18 signature pagination is bounded canonical hash keyset pagination',
    function () {
        $source =
            p18SignatureSource();

        expect($source)
            ->toContain(
                '$afterCanonicalHash'
            )
            ->toContain(
                "'>'"
            )
            ->toContain(
                '$pageSize + 1'
            )
            ->toContain(
                '->limit('
            )
            ->toContain(
                'no avanzó de forma válida'
            );
    }
);

test(
    'p18 signature output exposes only technical comparison values',
    function () {
        $source =
            p18SignatureSource();

        expect($source)
            ->toContain(
                "'normalized_row_id'"
            )
            ->toContain(
                "'canonical_identity_hash'"
            )
            ->toContain(
                "'normalized_sha256'"
            )
            ->not
            ->toContain(
                "'payload'"
            );
    }
);

test(
    'p18 signature reader performs no writes',
    function () {
        $source =
            p18SignatureSource();

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
    'p18 signature metadata does not leak into p14 reader',
    function () {
        $reader =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiPreparedDatasetReader.php'
            );

        expect($reader)
            ->not
            ->toContain(
                'normalized_sha256'
            )
            ->not
            ->toContain(
                'iterateDomainSignaturesInDataset('
            );
    }
);

test(
    'p18 signature access remains internal only',
    function () {
        $needles = [
            'DataTransformationBiCanonicalRowSignatureReader',
            'iterateDomainSignaturesInDataset',
        ];

        $paths = [
            'app/Http/Controllers',
            'resources/js',
            'routes',
        ];

        foreach ($paths as $relative) {
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

                $source =
                    file_get_contents(
                        $file->getPathname()
                    );

                foreach ($needles as $needle) {
                    expect($source)
                        ->not
                        ->toContain($needle);
                }
            }
        }
    }
);

test(
    'p18 b does not start analytical model',
    function () {
        $source =
            p18SignatureSource();

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
    }
);
