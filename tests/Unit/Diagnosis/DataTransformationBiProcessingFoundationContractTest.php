<?php

use App\Models\DataTransformationBiNormalizedRow;
use App\Models\DataTransformationBiProcessingRun;
use App\Models\DataTransformationBiQualityIssue;

test(
    'post intake processing exposes explicit run lifecycle',
    function () {
        expect([
            DataTransformationBiProcessingRun::STATUS_PENDING,
            DataTransformationBiProcessingRun::STATUS_PROCESSING,
            DataTransformationBiProcessingRun::STATUS_COMPLETED,
            DataTransformationBiProcessingRun::STATUS_FAILED,
        ])->toBe([
            'pending',
            'processing',
            'completed',
            'failed',
        ]);
    }
);

test(
    'processing foundation remains traceable to immutable intake staging',
    function () {
        $migration = file_get_contents(
            dirname(__DIR__, 3)
            .'/database/migrations/'
            .'2026_09_14_223000_'
            .'create_data_transformation_bi_processing_foundation_tables.php'
        );

        expect($migration)
            ->toContain(
                'data_transformation_bi_intake_batch_id'
            )
            ->toContain(
                'transformation_implementation_request_id'
            )
            ->toContain(
                'transformation_implementation_definition_id'
            )
            ->toContain(
                'definition_version'
            )
            ->toContain(
                'schema_version'
            );
    }
);

test(
    'processing is idempotent per intake batch and processing versions',
    function () {
        $migration = file_get_contents(
            dirname(__DIR__, 3)
            .'/database/migrations/'
            .'2026_09_14_223000_'
            .'create_data_transformation_bi_processing_foundation_tables.php'
        );

        expect($migration)
            ->toContain(
                'dtbi_pr_batch_versions_uq'
            )
            ->toContain(
                "'profiling_version'"
            )
            ->toContain(
                "'normalization_version'"
            );
    }
);

test(
    'quality issue severities are explicit',
    function () {
        expect([
            DataTransformationBiQualityIssue::SEVERITY_INFO,
            DataTransformationBiQualityIssue::SEVERITY_WARNING,
            DataTransformationBiQualityIssue::SEVERITY_BLOCKING,
        ])->toBe([
            'info',
            'warning',
            'blocking',
        ]);
    }
);

test(
    'field profiling does not persist raw value samples',
    function () {
        $migration = file_get_contents(
            dirname(__DIR__, 3)
            .'/database/migrations/'
            .'2026_09_14_223000_'
            .'create_data_transformation_bi_processing_foundation_tables.php'
        );

        expect($migration)
            ->not
            ->toContain(
                "'raw_value'"
            )
            ->not
            ->toContain(
                "'sample_value'"
            )
            ->not
            ->toContain(
                "'original_value'"
            );
    }
);

test(
    'normalized rows are derived and source row traceable',
    function () {
        $model = new DataTransformationBiNormalizedRow();

        expect($model->getTable())
            ->toBe(
                'data_transformation_bi_normalized_rows'
            )
            ->and(
                $model->getHidden()
            )
            ->toContain(
                'normalized_payload'
            );

        $migration = file_get_contents(
            dirname(__DIR__, 3)
            .'/database/migrations/'
            .'2026_09_14_223000_'
            .'create_data_transformation_bi_processing_foundation_tables.php'
        );

        expect($migration)
            ->toContain(
                'data_transformation_bi_intake_row_id'
            )
            ->toContain(
                'source_row_sha256'
            )
            ->toContain(
                'normalized_sha256'
            )
            ->toContain(
                'change_count'
            );
    }
);

test(
    'processing foundation does not create final analytical entities',
    function () {
        $migration = file_get_contents(
            dirname(__DIR__, 3)
            .'/database/migrations/'
            .'2026_09_14_223000_'
            .'create_data_transformation_bi_processing_foundation_tables.php'
        );

        expect($migration)
            ->not
            ->toContain('bi_customers')
            ->not
            ->toContain('bi_products')
            ->not
            ->toContain('bi_sales')
            ->not
            ->toContain('fact_sales')
            ->not
            ->toContain('dim_customer')
            ->not
            ->toContain('dim_product');
    }
);
