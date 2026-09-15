<?php

function p13UsableDatasetSource(
    string $path
): string {
    return file_get_contents(
        dirname(__DIR__, 3).'/'.$path
    );
}

test(
    'usable dataset is company and request scoped',
    function () {
        $source =
            p13UsableDatasetSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiUsableDatasetResolver.php'
            );

        expect($source)
            ->toContain(
                "'company_id'"
            )
            ->toContain(
                "'transformation_implementation_request_id'"
            )
            ->toContain(
                '$companyId'
            )
            ->toContain(
                '$implementationRequestId'
            );
    }
);

test(
    'only completed non blocking runs from completed batches are eligible',
    function () {
        $source =
            p13UsableDatasetSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiUsableDatasetResolver.php'
            );

        expect($source)
            ->toContain(
                'DataTransformationBiProcessingRun::STATUS_COMPLETED'
            )
            ->toContain(
                "'blocking_issue_count'"
            )
            ->toContain(
                'DataTransformationBiIntakeBatch::STATUS_COMPLETED'
            )
            ->toContain(
                "->whereHas("
            );
    }
);

test(
    'latest candidate is resolved independently of latest attempt state',
    function () {
        $source =
            p13UsableDatasetSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiUsableDatasetResolver.php'
            );

        expect($source)
            ->toContain(
                "->orderByDesc('id')"
            )
            ->toContain(
                'CANDIDATE_LIMIT = 50'
            )
            ->toContain(
                'latest_successful_normalized_run'
            )
            ->not
            ->toContain(
                'DataTransformationBiPreparationStatusReadModel'
            );
    }
);

test(
    'completed candidate must match persisted normalized row count',
    function () {
        $source =
            p13UsableDatasetSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiUsableDatasetResolver.php'
            );

        expect($source)
            ->toContain(
                "->withCount("
            )
            ->toContain(
                "'normalizedRows'"
            )
            ->toContain(
                '$run->normalized_rows_count'
            )
            ->toContain(
                '$run->normalized_row_count'
            )
            ->toContain(
                '$persistedCount'
            )
            ->toContain(
                '$recordedCount'
            )
            ->toContain(
                'continue;'
            );
    }
);

test(
    'resolver exposes only safe dataset identity and aggregate metadata',
    function () {
        $source =
            p13UsableDatasetSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiUsableDatasetResolver.php'
            );

        foreach ([
            "'processing_run_id'",
            "'intake_batch_id'",
            "'definition_version'",
            "'schema_version'",
            "'profiling_version'",
            "'normalization_version'",
            "'normalized_row_count'",
            "'has_rows'",
            "'completed_at'",
        ] as $required) {
            expect($source)
                ->toContain($required);
        }

        foreach ([
            'normalized_payload',
            'normalization_meta',
            'identity_hash',
            'source_row_number',
            'source_row_sha256',
            'normalized_sha256',
            'source_path',
            'source_sha256',
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
    'resolver provides derived canonical processing run pointer without writes',
    function () {
        $source =
            p13UsableDatasetSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiUsableDatasetResolver.php'
            );

        expect($source)
            ->toContain(
                'currentProcessingRunId('
            )
            ->toContain(
                "'processing_run_id'"
            );

        foreach ([
            '->create(',
            '->update(',
            '->delete(',
            '->save(',
            '->insert(',
            '->forceFill(',
        ] as $forbidden) {
            expect($source)
                ->not
                ->toContain($forbidden);
        }
    }
);

test(
    'p13 does not start facts dimensions or analytical model',
    function () {
        $source =
            p13UsableDatasetSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiUsableDatasetResolver.php'
            );

        foreach ([
            'FactSales',
            'DimCustomer',
            'DimProduct',
            'star_schema',
            'fact_',
            'dimension_',
        ] as $forbidden) {
            expect($source)
                ->not
                ->toContain($forbidden);
        }
    }
);
