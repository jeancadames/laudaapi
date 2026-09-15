<?php

function p12HistorySource(
    string $path
): string {
    return file_get_contents(
        dirname(__DIR__, 3).'/'.$path
    );
}

test(
    'history is exactly scoped to company and implementation request',
    function () {
        $source =
            p12HistorySource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiProcessingHistoryReadModel.php'
            );

        expect($source)
            ->toContain(
                "'company_id'"
            )
            ->toContain(
                "'transformation_implementation_request_id'"
            )
            ->toContain(
                'DataTransformationBiIntakeBatch::query()'
            )
            ->toContain(
                'DataTransformationBiProcessingRun::query()'
            );
    }
);

test(
    'history orders batches and runs newest first with bounded batch count',
    function () {
        $source =
            p12HistorySource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiProcessingHistoryReadModel.php'
            );

        expect(
            substr_count(
                $source,
                "->orderByDesc('id')"
            )
        )->toBe(2);

        expect($source)
            ->toContain(
                'DEFAULT_LIMIT = 20'
            )
            ->toContain(
                'MAX_LIMIT = 50'
            )
            ->toContain(
                '->limit('
            )
            ->toContain(
                "'is_latest'"
            );
    }
);

test(
    'history exposes only aggregate operational traceability',
    function () {
        $source =
            p12HistorySource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiProcessingHistoryReadModel.php'
            );

        foreach ([
            "'batch_id'",
            "'run_id'",
            "'status'",
            "'definition_version'",
            "'schema_version'",
            "'domain_count'",
            "'source_row_count'",
            "'staged_row_count'",
            "'rejected_row_count'",
            "'profiled_row_count'",
            "'normalized_row_count'",
            "'issue_count'",
            "'blocking_issue_count'",
            "'warning_issue_count'",
            "'informational_issue_count'",
            "'started_at'",
            "'completed_at'",
        ] as $required) {
            expect($source)
                ->toContain($required);
        }
    }
);

test(
    'history never selects private artifacts hashes messages or row payload',
    function () {
        $source =
            p12HistorySource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiProcessingHistoryReadModel.php'
            );

        foreach ([
            "'source_disk'",
            "'source_path'",
            "'original_filename'",
            "'source_mime_type'",
            "'source_size_bytes'",
            "'source_sha256'",
            "'validation_snapshot'",
            "'failure_code'",
            "'failure_message'",
            "'normalized_payload'",
            "'normalization_meta'",
            'DataTransformationBiIntakeRow',
            'DataTransformationBiNormalizedRow',
            'DataTransformationBiQualityIssue',
            'DataTransformationBiFieldProfile',
        ] as $forbidden) {
            expect($source)
                ->not
                ->toContain($forbidden);
        }
    }
);

test(
    'history remains read only',
    function () {
        $source =
            p12HistorySource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiProcessingHistoryReadModel.php'
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
    'history does not start final analytical model',
    function () {
        $source =
            p12HistorySource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiProcessingHistoryReadModel.php'
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
