<?php

use Tests\TestCase;

uses(TestCase::class);

test(
    'session resolution requires all seven valid logical decisions',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'DataTransformationBiIntakeV2SessionResolutionService.php'
                )
            );

        foreach ([
            'DataTransformationBiStandardIntakeSchema',
            '::domainKeys()',
            '::STATUS_VALID',
            '::MODE_UPLOADED',
            '::MODE_NO_DATA',
            '::MODE_CARRY_FORWARD',
            'exactamente',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }
);

test(
    'uploaded resolution rechecks private artifact integrity and d5 validation',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'DataTransformationBiIntakeV2SessionResolutionService.php'
                )
            );

        foreach ([
            'Storage::disk(',
            '->readStream(',
            "hash_init(\n                'sha256'",
            'hash_equals(',
            '$this->domainValidationService',
            '->validate(',
            '$this->domainFileReader',
            '->read(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }
);

test(
    'session resolution uses authoritative full dataset row validation',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'DataTransformationBiIntakeV2SessionResolutionService.php'
                )
            );

        $this->assertStringContainsString(
            '$this->rowValidator',
            $source
        );

        $this->assertStringContainsString(
            "->validate(\n                    \$rowsByDomain",
            $source
        );

        $this->assertStringNotContainsString(
            '->validateDomain(',
            $source
        );
    }
);

test(
    'carry forward resolution remains pinned to stored run and batch',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'DataTransformationBiIntakeV2SessionResolutionService.php'
                )
            );

        foreach ([
            'carry_forward_processing_run_id',
            'carry_forward_intake_batch_id',
            'pinnedDataset(',
            '->domainCountsInDataset(',
            '->iterateDomainInDataset(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        $this->assertStringNotContainsString(
            'DataTransformationBiUsableDatasetResolver::class',
            $source
        );

        $this->assertStringNotContainsString(
            '->forRequest(',
            $source
        );
    }
);

test(
    'optimistic manifest prevents a changed decision cut from becoming ready',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'DataTransformationBiIntakeV2SessionResolutionService.php'
                )
            );

        foreach ([
            'captureResolvedDecisionSet(',
            'manifest_sha256',
            'hash_equals(',
            'Las decisiones del intake cambiaron',
            '::STATUS_READY',
            "'relational_validation_snapshot'",
            "'ready_at'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }
);

test(
    'session resolution does not materialize staging',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'DataTransformationBiIntakeV2SessionResolutionService.php'
                )
            );

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
