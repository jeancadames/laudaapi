<?php

use Tests\TestCase;

uses(TestCase::class);

test(
    'intake batch exposes domain session manifest format',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Models/DataTransformationBiIntakeBatch.php'
                )
            );

        $this->assertStringContainsString(
            'FORMAT_DOMAIN_SESSION_MANIFEST',
            $source
        );

        $this->assertStringContainsString(
            "'domain_session_manifest'",
            $source
        );
    }
);

test(
    'd10 materializes only a ready payload produced by d9',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'DataTransformationBiIntakeV2StagingMaterializationService.php'
                )
            );

        foreach ([
            'prepareReadyPayload(',
            '::STATUS_READY',
            'resolved_manifest_sha256',
            'relational_validation_snapshot',
            'FORMAT_DOMAIN_SESSION_MANIFEST',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }
);

test(
    'd10 preserves canonical staging row hash semantics',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'DataTransformationBiIntakeV2StagingMaterializationService.php'
                )
            );

        foreach ([
            'source_row_number',
            'identity_hash',
            'row_sha256',
            'row_payload',
            '::identityKeys()',
            "'domain' =>",
            "'identity' =>",
            "'row' =>",
            'JSON_PRESERVE_ZERO_FRACTION',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }
);

test(
    'd10 creates seven batch domains and finalizes session',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'DataTransformationBiIntakeV2StagingMaterializationService.php'
                )
            );

        foreach ([
            'DataTransformationBiStandardIntakeSchema::domainKeys()',
            'DataTransformationBiIntakeBatchDomain::query()',
            '::STATUS_COMPLETED',
            '::STATUS_FINALIZED',
            "'resulting_intake_batch_id'",
            "'finalized_at'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }
);

test(
    'd10 does not start profiling or normalization',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'DataTransformationBiIntakeV2StagingMaterializationService.php'
                )
            );

        foreach ([
            'DataTransformationBiStagingProfilingService',
            'DataTransformationBiStagingNormalizationService',
            'DataTransformationBiProcessingRun::query()->create',
            'DataTransformationBiNormalizedRow::query()->create',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }
);
