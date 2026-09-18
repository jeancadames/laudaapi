<?php

uses(Tests\TestCase::class);

test(
    'intake v2 session service creates exactly the canonical pre staging slots',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'DataTransformationBiIntakeV2SessionService.php'
                )
            );

        expect($source)
            ->toContain(
                'DB::transaction'
            )
            ->toContain(
                '->lockForUpdate()'
            )
            ->toContain(
                'DataTransformationBiStandardIntakeSchema'
            )
            ->toContain(
                '::domainKeys()'
            )
            ->toContain(
                'DataTransformationBiIntakeSession'
            )
            ->toContain(
                'DataTransformationBiIntakeDomainDelivery'
            )
            ->toContain(
                '::STATUS_DRAFT'
            )
            ->toContain(
                '::STATUS_READY'
            )
            ->toContain(
                '::STATUS_FINALIZING'
            )
            ->toContain(
                'firstOrCreate'
            );

        expect($source)
            ->not
            ->toContain(
                'DataTransformationBiIntakeBatch::create'
            )
            ->not
            ->toContain(
                "'data_transformation_bi_intake_rows'"
            )
            ->not
            ->toContain(
                "'data_transformation_bi_normalized_rows'"
            );
    }
);

test(
    'intake v2 domain persistence validates hashes and stores only on private disk',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'DataTransformationBiIntakeV2DomainDeliveryService.php'
                )
            );

        expect($source)
            ->toContain(
                "SOURCE_DISK =\n        'private'"
            )
            ->toContain(
                'DataTransformationBiDomainIntakeValidationService'
            )
            ->toContain(
                "hash_file(\n                'sha256'"
            )
            ->toContain(
                'Storage::disk'
            )
            ->toContain(
                'hash_update_stream'
            )
            ->toContain(
                'hash_equals'
            )
            ->toContain(
                '->lockForUpdate()'
            )
            ->toContain(
                'MODE_UPLOADED'
            )
            ->toContain(
                'STATUS_VALID'
            )
            ->toContain(
                "'resolved_manifest_sha256' =>\n                                    null"
            )
            ->toContain(
                "'relational_validation_snapshot' =>\n                                    null"
            )
            ->toContain(
                "'ready_at' =>\n                                    null"
            );
    }
);

test(
    'intake v2 domain persistence is idempotent per session domain and sha',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'DataTransformationBiIntakeV2DomainDeliveryService.php'
                )
            );

        expect($source)
            ->toContain(
                "'data_transformation_bi_intake_session_id'"
            )
            ->toContain(
                "'domain_key'"
            )
            ->toContain(
                "'source_sha256'"
            )
            ->toContain(
                '$delivery->source_sha256'
            )
            ->toContain(
                "'reused' =>\n                                    true"
            )
            ->toContain(
                'session_%d'
            )
            ->toContain(
                '%s/%s.%s'
            );
    }
);

test(
    'intake v2 persistence layer cannot write canonical staging',
    function () {
        $sessionSource =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'DataTransformationBiIntakeV2SessionService.php'
                )
            );

        $deliverySource =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'DataTransformationBiIntakeV2DomainDeliveryService.php'
                )
            );

        $combined =
            $sessionSource
            ."\n"
            .$deliverySource;

        expect($combined)
            ->not
            ->toContain(
                "'data_transformation_bi_intake_batches'"
            )
            ->not
            ->toContain(
                "'data_transformation_bi_intake_batch_domains'"
            )
            ->not
            ->toContain(
                "'data_transformation_bi_intake_rows'"
            )
            ->not
            ->toContain(
                "'data_transformation_bi_processing_runs'"
            )
            ->not
            ->toContain(
                "'data_transformation_bi_normalized_rows'"
            );
    }
);
