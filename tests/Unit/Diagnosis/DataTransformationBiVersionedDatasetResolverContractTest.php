<?php

function p18VersionedResolverSource(): string
{
    return file_get_contents(
        dirname(__DIR__, 3)
        .'/app/Services/Diagnosis/'
        .'DataTransformationBiVersionedDatasetResolver.php'
    );
}

test(
    'p18 resolves one explicit completed processing run',
    function () {
        $source =
            p18VersionedResolverSource();

        expect($source)
            ->toContain(
                'public function forProcessingRun('
            )
            ->toContain(
                "'company_id'"
            )
            ->toContain(
                "'transformation_implementation_request_id'"
            )
            ->toContain(
                "'status'"
            )
            ->toContain(
                'DataTransformationBiProcessingRun::STATUS_COMPLETED'
            )
            ->toContain(
                "'blocking_issue_count'"
            );
    }
);

test(
    'p18 requires completed batch in same company and request',
    function () {
        $source =
            p18VersionedResolverSource();

        expect($source)
            ->toContain(
                '->whereHas('
            )
            ->toContain(
                "'batch'"
            )
            ->toContain(
                'DataTransformationBiIntakeBatch'
            )
            ->toContain(
                '::STATUS_COMPLETED'
            );
    }
);

test(
    'p18 verifies recorded and physical normalized counts',
    function () {
        $source =
            p18VersionedResolverSource();

        expect($source)
            ->toContain(
                '->withCount('
            )
            ->toContain(
                "'normalizedRows'"
            )
            ->toContain(
                '$persistedCount'
            )
            ->toContain(
                '$recordedCount'
            )
            ->toContain(
                'normalized_row_count_mismatch'
            );
    }
);

test(
    'p18 validates exact company run batch row scope',
    function () {
        $source =
            p18VersionedResolverSource();

        expect($source)
            ->toContain(
                'DataTransformationBiNormalizedRow::query()'
            )
            ->toContain(
                "'data_transformation_bi_processing_run_id'"
            )
            ->toContain(
                "'data_transformation_bi_intake_batch_id'"
            )
            ->toContain(
                'normalized_dataset_scope_mismatch'
            );
    }
);

test(
    'p18 pair is directional from older base to newer target',
    function () {
        $source =
            p18VersionedResolverSource();

        expect($source)
            ->toContain(
                'public function pair('
            )
            ->toContain(
                '$targetProcessingRunId'
            )
            ->toContain(
                '<= $baseProcessingRunId'
            )
            ->toContain(
                'invalid_dataset_pair_order'
            );
    }
);

test(
    'p18 pair requires schema and normalization compatibility',
    function () {
        $source =
            p18VersionedResolverSource();

        expect($source)
            ->toContain(
                "'schema_version'"
            )
            ->toContain(
                'schema_version_mismatch'
            )
            ->toContain(
                "'normalization_version'"
            )
            ->toContain(
                'normalization_version_mismatch'
            );
    }
);

test(
    'p18 returns only safe dataset descriptor metadata',
    function () {
        $source =
            p18VersionedResolverSource();

        expect($source)
            ->toContain(
                "'processing_run_id'"
            )
            ->toContain(
                "'intake_batch_id'"
            )
            ->toContain(
                "'normalized_row_count'"
            )
            ->toContain(
                "'completed_at'"
            )
            ->not
            ->toContain(
                "'normalized_payload'"
            )
            ->not
            ->toContain(
                "'source_row_sha256'"
            )
            ->not
            ->toContain(
                "'failure_message'"
            );
    }
);

test(
    'p18 versioned resolver performs no writes',
    function () {
        $source =
            p18VersionedResolverSource();

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
    'p18 does not resolve latest dataset implicitly',
    function () {
        $source =
            p18VersionedResolverSource();

        expect($source)
            ->not
            ->toContain(
                'orderByDesc('
            )
            ->not
            ->toContain(
                'CANDIDATE_LIMIT'
            )
            ->not
            ->toContain(
                'currentProcessingRunId('
            );
    }
);

test(
    'p18 a does not start public or analytical surfaces',
    function () {
        $source =
            p18VersionedResolverSource();

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
