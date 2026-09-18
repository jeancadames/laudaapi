<?php

uses(Tests\TestCase::class);

test(
    'data bi intake v2 foundation separates intake sessions from canonical staging',
    function () {
        $sessionModel =
            file_get_contents(
                app_path(
                    'Models/DataTransformationBiIntakeSession.php'
                )
            );

        $deliveryModel =
            file_get_contents(
                app_path(
                    'Models/DataTransformationBiIntakeDomainDelivery.php'
                )
            );

        expect($sessionModel)
            ->toContain(
                "'data_transformation_bi_intake_sessions'"
            )
            ->toContain(
                "STATUS_DRAFT = 'draft'"
            )
            ->toContain(
                "STATUS_FINALIZED = 'finalized'"
            )
            ->toContain(
                "'resulting_intake_batch_id'"
            )
            ->toContain(
                "'resolved_manifest_sha256'"
            )
            ->toContain(
                "'relational_validation_snapshot'"
            );

        expect($deliveryModel)
            ->toContain(
                "'data_transformation_bi_intake_domain_deliveries'"
            )
            ->toContain(
                "MODE_UPLOADED = 'uploaded'"
            )
            ->toContain(
                "MODE_NO_DATA = 'no_data'"
            )
            ->toContain(
                "MODE_CARRY_FORWARD = 'carry_forward'"
            )
            ->toContain(
                "FORMAT_XLSX = 'xlsx'"
            )
            ->toContain(
                "FORMAT_CSV = 'csv'"
            )
            ->toContain(
                "'carry_forward_processing_run_id'"
            )
            ->toContain(
                "'carry_forward_intake_batch_id'"
            )
            ->toContain(
                "protected \$hidden"
            )
            ->toContain(
                "'source_path'"
            );
    }
);

test(
    'data bi intake v2 foundation keeps one decision per domain per session',
    function () {
        $migrations =
            glob(
                database_path(
                    'migrations/*_create_data_transformation_bi_intake_v2_foundation_tables.php'
                )
            );

        expect($migrations)
            ->toBeArray()
            ->toHaveCount(1);

        $migration =
            file_get_contents(
                $migrations[0]
            );

        expect($migration)
            ->toContain(
                "'data_transformation_bi_intake_sessions'"
            )
            ->toContain(
                "'data_transformation_bi_intake_domain_deliveries'"
            )
            ->toContain(
                "'dtbi_domain_deliveries_session_domain_uq'"
            )
            ->toContain(
                "'data_transformation_bi_intake_session_id'"
            )
            ->toContain(
                "'domain_key'"
            )
            ->toContain(
                "'carry_forward_processing_run_id'"
            )
            ->toContain(
                "'carry_forward_intake_batch_id'"
            );

        /*
         * Intake v2 does not add a consolidated XLSX/ZIP artifact.
         * Domain uploads are represented individually.
         */
        expect($migration)
            ->not
            ->toContain(
                "'csv_zip'"
            );
    }
);
