<?php

use App\Models\DataTransformationBiIntakeBatch;
use App\Models\DataTransformationBiIntakeBatchDomain;
use App\Models\DataTransformationBiIntakeRow;
use Tests\TestCase;

uses(TestCase::class);

test(
    'data bi intake staging exposes explicit lifecycle and formats',
    function () {
        expect(
            DataTransformationBiIntakeBatch::STATUS_PENDING
        )->toBe('pending')
            ->and(
                DataTransformationBiIntakeBatch::STATUS_PROCESSING
            )->toBe('processing')
            ->and(
                DataTransformationBiIntakeBatch::STATUS_COMPLETED
            )->toBe('completed')
            ->and(
                DataTransformationBiIntakeBatch::STATUS_FAILED
            )->toBe('failed')
            ->and(
                DataTransformationBiIntakeBatch::STATUS_PURGED
            )->toBe('purged')
            ->and(
                DataTransformationBiIntakeBatch::FORMAT_XLSX
            )->toBe('xlsx')
            ->and(
                DataTransformationBiIntakeBatch::FORMAT_CSV_ZIP
            )->toBe('csv_zip');
    }
);

test(
    'staging migration is company request and definition traceable',
    function () {
        $source =
            file_get_contents(
                database_path(
                    'migrations/2026_09_14_153500_create_data_transformation_bi_intake_staging_tables.php'
                )
            );

        expect($source)
            ->not
            ->toBeFalse()
            ->toContain(
                "'company_id'"
            )
            ->toContain(
                "'transformation_implementation_request_id'"
            )
            ->toContain(
                "'transformation_implementation_definition_id'"
            )
            ->toContain(
                "'definition_version'"
            )
            ->toContain(
                "'schema_version'"
            )
            ->toContain(
                "'source_sha256'"
            )
            ->toContain(
                "'dtbi_batches_request_schema_hash_uq'"
            );
    }
);

test(
    'staging foundation is private and has explicit retention boundary',
    function () {
        $migration =
            file_get_contents(
                database_path(
                    'migrations/2026_09_14_153500_create_data_transformation_bi_intake_staging_tables.php'
                )
            );

        $batch =
            file_get_contents(
                app_path(
                    'Models/DataTransformationBiIntakeBatch.php'
                )
            );

        expect($migration)
            ->not
            ->toBeFalse()
            ->toContain(
                "->default('private')"
            )
            ->toContain(
                "'source_retention_until'"
            )
            ->toContain(
                "'source_deleted_at'"
            )
            ->toContain(
                "'purged_at'"
            )
            ->and($batch)
            ->toContain(
                "'source_path'"
            )
            ->toContain(
                'protected $hidden'
            );
    }
);

test(
    'staging rows preserve canonical payload without defining final bi tables',
    function () {
        $migration =
            file_get_contents(
                database_path(
                    'migrations/2026_09_14_153500_create_data_transformation_bi_intake_staging_tables.php'
                )
            );

        expect($migration)
            ->not
            ->toBeFalse()
            ->toContain(
                "'domain_key'"
            )
            ->toContain(
                "'source_row_number'"
            )
            ->toContain(
                "'identity_hash'"
            )
            ->toContain(
                "'row_sha256'"
            )
            ->toContain(
                "'row_payload'"
            )
            ->not
            ->toContain(
                'bi_fact_'
            )
            ->not
            ->toContain(
                'bi_dimension_'
            );
    }
);

test(
    'staging models expose batch domain and row ownership relationships',
    function () {
        $batch =
            new DataTransformationBiIntakeBatch();

        $domain =
            new DataTransformationBiIntakeBatchDomain();

        $row =
            new DataTransformationBiIntakeRow();

        expect(
            $batch->getTable()
        )->toBe(
            'data_transformation_bi_intake_batches'
        )
            ->and(
                $domain->getTable()
            )->toBe(
                'data_transformation_bi_intake_batch_domains'
            )
            ->and(
                $row->getTable()
            )->toBe(
                'data_transformation_bi_intake_rows'
            );
    }
);
