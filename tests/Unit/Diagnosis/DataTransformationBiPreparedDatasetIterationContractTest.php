<?php

function p14IterationSource(
    string $path
): string {
    return file_get_contents(
        dirname(__DIR__, 3).'/'.$path
    );
}

test(
    'domain counts return every canonical domain including zero counts',
    function () {
        $source =
            p14IterationSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparedDatasetReader.php'
            );

        expect($source)
            ->toContain(
                'public function domainCounts('
            )
            ->toContain(
                'array_fill_keys('
            )
            ->toContain(
                '$this->supportedDomains()'
            )
            ->toContain(
                "'domains'"
            )
            ->toContain(
                "'total_rows'"
            );
    }
);

test(
    'domain counts are pinned to company run and batch',
    function () {
        $source =
            p14IterationSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparedDatasetReader.php'
            );

        expect($source)
            ->toContain(
                "selectRaw("
            )
            ->toContain(
                "'domain_key, COUNT(*) AS aggregate_count'"
            )
            ->toContain(
                "->groupBy("
            )
            ->toContain(
                "'data_transformation_bi_processing_run_id'"
            )
            ->toContain(
                "'data_transformation_bi_intake_batch_id'"
            );
    }
);

test(
    'domain totals must equal usable dataset normalized row count',
    function () {
        $source =
            p14IterationSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparedDatasetReader.php'
            );

        expect($source)
            ->toContain(
                '$totalRows !== $expectedRows'
            )
            ->toContain(
                "'normalized_row_count'"
            )
            ->toContain(
                'Los conteos por dominio no coinciden'
            );
    }
);

test(
    'iterator resolves p13 once and then remains pinned to resolved dataset',
    function () {
        $source =
            p14IterationSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparedDatasetReader.php'
            );

        expect($source)
            ->toContain(
                'public function iterateDomain('
            )
            ->toContain(
                'resolve exactly once'
            )
            ->toContain(
                '$this->readResolvedDomainPage('
            )
            ->toContain(
                'This method MUST NOT re-resolve the P13 pointer.'
            );
    }
);

test(
    'iterator uses bounded keyset pages and validates cursor advancement',
    function () {
        $source =
            p14IterationSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparedDatasetReader.php'
            );

        expect($source)
            ->toContain(
                '$pageSize + 1'
            )
            ->toContain(
                "->orderBy('id')"
            )
            ->toContain(
                "'next_after_id'"
            )
            ->toContain(
                '$nextAfterId <= $afterId'
            )
            ->toContain(
                'La paginación del dataset preparado'
            );
    }
);

test(
    'iterator yields only canonical internal rows',
    function () {
        $source =
            p14IterationSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparedDatasetReader.php'
            );

        expect($source)
            ->toContain(
                'yield $row'
            )
            ->toContain(
                "'normalized_row_id'"
            )
            ->toContain(
                "'identity_hash'"
            )
            ->toContain(
                "'payload'"
            );

        foreach ([
            'source_row_number',
            'source_row_sha256',
            'normalized_sha256',
            'normalization_meta',
            'source_path',
            'validation_snapshot',
            'failure_message',
        ] as $forbidden) {
            expect($source)
                ->not
                ->toContain($forbidden);
        }
    }
);

test(
    'p14 b remains read only and internal',
    function () {
        $source =
            p14IterationSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparedDatasetReader.php'
            );

        foreach ([
            '->create(',
            '->update(',
            '->delete(',
            '->save(',
            '->insert(',
            '->upsert(',
            '->updateOrCreate(',
        ] as $forbidden) {
            expect($source)
                ->not
                ->toContain($forbidden);
        }

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
