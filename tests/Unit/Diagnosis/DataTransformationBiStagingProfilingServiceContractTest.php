<?php

use App\Services\Diagnosis\DataTransformationBiStagingProfilingService;

function postI16D2Source(): string
{
    return file_get_contents(
        dirname(__DIR__, 3)
        .'/app/Services/Diagnosis/'
        .'DataTransformationBiStagingProfilingService.php'
    );
}

test(
    'profiling versions are explicit and deterministic',
    function () {
        expect(
            DataTransformationBiStagingProfilingService
                ::PROFILING_VERSION
        )
            ->toBe(1)
            ->and(
                DataTransformationBiStagingProfilingService
                    ::NORMALIZATION_VERSION
            )
            ->toBe(1);
    }
);

test(
    'profiling accepts only persisted admin execution and completed staging',
    function () {
        $source =
            postI16D2Source();

        expect($source)
            ->toContain(
                "(string) \$actor->role !== 'admin'"
            )
            ->toContain(
                'DataTransformationBiIntakeBatch::STATUS_COMPLETED'
            )
            ->toContain(
                'DataTransformationBiStandardIntakeSchema::VERSION'
            );
    }
);

test(
    'profiling reuses the canonical schema and row validator',
    function () {
        $source =
            postI16D2Source();

        expect($source)
            ->toContain(
                'DataTransformationBiStandardIntakeSchema'
            )
            ->toContain(
                '::domains()'
            )
            ->toContain(
                'DataTransformationBiStandardIntakeRowValidator'
            )
            ->toContain(
                '->validate('
            );
    }
);

test(
    'profiling is derived and never mutates intake staging',
    function () {
        $source =
            postI16D2Source();

        expect($source)
            ->toContain(
                'DataTransformationBiIntakeRow::query()'
            )
            ->not
            ->toContain(
                'DataTransformationBiIntakeRow::query()->delete'
            )
            ->not
            ->toContain(
                'DataTransformationBiIntakeRow::query()->update'
            )
            ->not
            ->toContain(
                '$batch->save('
            );
    }
);

test(
    'field profiles persist aggregate metrics without raw samples',
    function () {
        $source =
            postI16D2Source();

        expect($source)
            ->toContain(
                "'completeness_percent'"
            )
            ->toContain(
                "'distinct_count'"
            )
            ->toContain(
                "'null_count'"
            )
            ->toContain(
                "'blank_count'"
            )
            ->not
            ->toContain(
                "'sample_value'"
            )
            ->not
            ->toContain(
                "'raw_value'"
            )
            ->not
            ->toContain(
                "'min_value'"
            )
            ->not
            ->toContain(
                "'max_value'"
            );
    }
);

test(
    'optional missing data is informational rather than a business failure',
    function () {
        $source =
            postI16D2Source();

        expect($source)
            ->toContain(
                "'optional_field_incomplete'"
            )
            ->toContain(
                '::SEVERITY_INFO'
            )
            ->toContain(
                "'required_field_incomplete'"
            )
            ->toContain(
                '::SEVERITY_BLOCKING'
            );
    }
);

test(
    'quality engine detects staging identity and relationship contract violations',
    function () {
        $source =
            postI16D2Source();

        expect($source)
            ->toContain(
                "'missing_identity_hash'"
            )
            ->toContain(
                "'duplicate_identity_in_staging'"
            )
            ->toContain(
                "'staging_validation_contract_violation'"
            )
            ->toContain(
                "'staging_relation_contract_violation'"
            );
    }
);

test(
    'd2 leaves the processing run open for d3 normalization',
    function () {
        $source =
            postI16D2Source();

        expect($source)
            ->toContain(
                'D3 normalization will complete the run'
            )
            ->toContain(
                "'normalized_row_count' =>"
            )
            ->toContain(
                '::STATUS_PROCESSING'
            );
    }
);

test(
    'profiling has no final bi model responsibility',
    function () {
        $source =
            postI16D2Source();

        expect($source)
            ->not
            ->toContain(
                'bi_customers'
            )
            ->not
            ->toContain(
                'bi_products'
            )
            ->not
            ->toContain(
                'fact_sales'
            )
            ->not
            ->toContain(
                'dim_customer'
            )
            ->not
            ->toContain(
                'dim_product'
            );
    }
);
