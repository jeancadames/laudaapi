<?php

use App\Services\Diagnosis\DataTransformationBiStagingNormalizationService;

function postI16D3Source(): string
{
    return file_get_contents(
        dirname(__DIR__, 3)
        .'/app/Services/Diagnosis/'
        .'DataTransformationBiStagingNormalizationService.php'
    );
}

test(
    'normalization requires admin and processing ownership traceability',
    function () {
        $source =
            postI16D3Source();

        expect($source)
            ->toContain(
                "(string) \$actor->role !== 'admin'"
            )
            ->toContain(
                'data_transformation_bi_intake_batch_id'
            )
            ->toContain(
                'transformation_implementation_request_id'
            )
            ->toContain(
                'schema_version'
            );
    }
);

test(
    'blocking quality issues prevent normalization completion',
    function () {
        $source =
            postI16D3Source();

        expect($source)
            ->toContain(
                'DataTransformationBiQualityIssue::SEVERITY_BLOCKING'
            )
            ->toContain(
                'El run contiene issues bloqueantes'
            );
    }
);

test(
    'normalization creates one derived row per immutable staging row',
    function () {
        $source =
            postI16D3Source();

        expect($source)
            ->toContain(
                'DataTransformationBiIntakeRow::query()'
            )
            ->toContain(
                'DataTransformationBiNormalizedRow::query()'
            )
            ->toContain(
                "'data_transformation_bi_intake_row_id' =>"
            )
            ->toContain(
                "'source_row_sha256' =>"
            )
            ->toContain(
                "'normalized_sha256' =>"
            )
            ->toContain(
                "'change_count' =>"
            );
    }
);

test(
    'normalization does not mutate intake staging',
    function () {
        $source =
            postI16D3Source();

        expect($source)
            ->not
            ->toContain(
                'DataTransformationBiIntakeRow::query()->update'
            )
            ->not
            ->toContain(
                'DataTransformationBiIntakeRow::query()->delete'
            )
            ->not
            ->toContain(
                '$batch->save('
            );
    }
);

test(
    'normalization metadata contains field names but not duplicated raw values',
    function () {
        $source =
            postI16D3Source();

        expect($source)
            ->toContain(
                "'changed_fields' =>"
            )
            ->not
            ->toContain(
                "'before_value'"
            )
            ->not
            ->toContain(
                "'after_value'"
            )
            ->not
            ->toContain(
                "'raw_value'"
            );
    }
);

test(
    'completed run requires matching source profiled and normalized counts',
    function () {
        $source =
            postI16D3Source();

        expect($source)
            ->toContain(
                'source_row_count'
            )
            ->toContain(
                'profiled_row_count'
            )
            ->toContain(
                'normalized_row_count'
            )
            ->toContain(
                'STATUS_COMPLETED'
            );
    }
);

test(
    'retry clears only derived normalized rows from the same run',
    function () {
        $source =
            postI16D3Source();

        expect($source)
            ->toContain(
                "'data_transformation_bi_processing_run_id'"
            )
            ->toContain(
                '->delete()'
            )
            ->toContain(
                'Deterministic retry'
            );
    }
);

test(
    'd3 does not create final analytical model entities',
    function () {
        $source =
            postI16D3Source();

        expect($source)
            ->not
            ->toContain('bi_customers')
            ->not
            ->toContain('bi_products')
            ->not
            ->toContain('fact_sales')
            ->not
            ->toContain('dim_customer')
            ->not
            ->toContain('dim_product');
    }
);
