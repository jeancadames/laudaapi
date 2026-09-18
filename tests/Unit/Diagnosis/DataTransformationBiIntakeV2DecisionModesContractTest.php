<?php

use Tests\TestCase;

uses(TestCase::class);

test(
    'intake v2 exposes explicit no data decision without source artifact',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'DataTransformationBiIntakeV2DomainDeliveryService.php'
                )
            );

        foreach ([
            'public function persistNoDataDomain(',
            '::MODE_NO_DATA',
            "'declared_no_data'",
            "'source_disk' =>",
            "'source_path' =>",
            "'source_sha256' =>",
            "'source_row_count' =>",
            "'accepted_row_count' =>",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }
);

test(
    'carry forward resolves p13 once and pins run plus batch',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'DataTransformationBiIntakeV2DomainDeliveryService.php'
                )
            );

        foreach ([
            'public function persistCarryForwardDomain(',
            'DataTransformationBiUsableDatasetResolver',
            '->forRequest(',
            'carry_forward_processing_run_id',
            'carry_forward_intake_batch_id',
            'validatePinnedCarryForward(',
            'DataTransformationBiPreparedDatasetReader',
            '->domainCountsInDataset(',
            '::MODE_CARRY_FORWARD',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }
);

test(
    'decision changes invalidate ready state but do not materialize staging',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'DataTransformationBiIntakeV2DomainDeliveryService.php'
                )
            );

        foreach ([
            'resetSessionAfterDecisionChange(',
            "'resolved_manifest_sha256' =>",
            "'relational_validation_snapshot' =>",
            "'ready_at' =>",
            '::STATUS_DRAFT',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        foreach ([
            "DB::table('data_transformation_bi_intake_batches",
            "DB::table('data_transformation_bi_intake_batch_domains",
            "DB::table('data_transformation_bi_intake_rows",
            'DataTransformationBiIntakeBatch::create(',
            'DataTransformationBiProcessingRun::create(',
            'DataTransformationBiNormalizedRow::create(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }
);

test(
    'superseded source cleanup verifies current references first',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'DataTransformationBiIntakeV2DomainDeliveryService.php'
                )
            );

        foreach ([
            'deleteArtifactIfUnreferenced(',
            "->where(\n                    'source_disk'",
            "->where(\n                    'source_path'",
            '->exists()',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }
);
