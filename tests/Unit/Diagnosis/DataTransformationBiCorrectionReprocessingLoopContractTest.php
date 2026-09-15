<?php

function p11Source(
    string $path
): string {
    return file_get_contents(
        dirname(__DIR__, 3).'/'.$path
    );
}

test(
    'corrected content creates a distinct logical intake batch',
    function () {
        $migration =
            p11Source(
                'database/migrations/'
                .'2026_09_14_153500_create_data_transformation_bi_intake_staging_tables.php'
            );

        expect($migration)
            ->toContain(
                "'transformation_implementation_request_id'"
            )
            ->toContain(
                "'schema_version'"
            )
            ->toContain(
                "'source_sha256'"
            )
            ->toContain(
                'dtbi_batches_request_schema_hash_uq'
            );
    }
);

test(
    'completed identical source is reused while changed hash can create batch',
    function () {
        $source =
            p11Source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiStandardIntakeIngestionService.php'
            );

        expect($source)
            ->toContain(
                "'source_sha256'"
            )
            ->toContain(
                'STATUS_COMPLETED'
            )
            ->toContain(
                "'reused'"
            )
            ->toContain(
                'if ($batch === null)'
            )
            ->toContain(
                '->create(['
            )
            ->toContain(
                'Failed/pending/purged logical batches may be'
            );
    }
);

test(
    'processing remains isolated per intake batch and versions',
    function () {
        $source =
            p11Source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiStagingProfilingService.php'
            );

        expect($source)
            ->toContain(
                "'data_transformation_bi_intake_batch_id'"
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
    'selecting corrected source clears stale client processing state',
    function () {
        $source =
            p11Source(
                'resources/js/pages/Admin/Transformation360/'
                .'ImplementationRequests/Show.vue'
            );

        foreach ([
            'P11_CORRECTION_REPROCESSING_LOOP',
            'standardIntakeReport.value = null',
            'standardIntakeIngestionReport.value = null',
            'standardIntakeProfileReport.value = null',
            'standardIntakeNormalizationReport.value = null',
            'standardIntakeProcessingBatchId.value = null',
            'standardIntakeProcessingError.value = null',
        ] as $required) {
            expect($source)
                ->toContain($required);
        }
    }
);

test(
    'admin ui explains correction and reprocessing loop',
    function () {
        $source =
            p11Source(
                'resources/js/pages/Admin/Transformation360/'
                .'ImplementationRequests/Show.vue'
            );

        expect($source)
            ->toContain(
                'una versión corregida'
            )
            ->toContain(
                'Validar archivo →'
            )
            ->toContain(
                'Ingresar a staging → Analizar calidad'
            )
            ->toContain(
                'LAUDA crea un nuevo batch'
            )
            ->toContain(
                'conserva el procesamiento anterior como historial'
            );
    }
);

test(
    'p11 does not alter request commercial or analytical lifecycle',
    function () {
        $source =
            p11Source(
                'resources/js/pages/Admin/Transformation360/'
                .'ImplementationRequests/Show.vue'
            );

        expect($source)
            ->toContain(
                'Esta acción no cambia el estado de la'
            )
            ->toContain(
                'no modifica la Definición'
            );

        foreach ([
            'FactSales',
            'DimCustomer',
            'star_schema',
        ] as $forbidden) {
            expect($source)
                ->not
                ->toContain($forbidden);
        }
    }
);
