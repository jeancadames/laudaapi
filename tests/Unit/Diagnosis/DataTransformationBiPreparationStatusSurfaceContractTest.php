<?php

function p7Source(
    string $path
): string {
    return file_get_contents(
        dirname(__DIR__, 3).'/'.$path
    );
}

test(
    'preparation read model is request and company scoped',
    function () {
        $source =
            p7Source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparationStatusReadModel.php'
            );

        expect($source)
            ->toContain(
                'DataTransformationBiIntakeBatch::query()'
            )
            ->toContain(
                'DataTransformationBiProcessingRun::query()'
            )
            ->toContain(
                "'company_id'"
            )
            ->toContain(
                "'transformation_implementation_request_id'"
            )
            ->toContain(
                "'data_transformation_bi_intake_batch_id'"
            )
            ->toContain(
                "'informational_issue_count'"
            )
            ->toContain(
                "'normalized'"
            );
    }
);

test(
    'preparation read model never exposes row payload or private source metadata',
    function () {
        $source =
            p7Source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparationStatusReadModel.php'
            );

        foreach ([
            "'source_path'",
            "'validation_snapshot'",
            "'source_sha256'",
            "'normalized_payload'",
            "'normalization_meta'",
            "'failure_message'",
        ] as $forbidden) {
            expect($source)
                ->not
                ->toContain($forbidden);
        }
    }
);

test(
    'admin supervisor receives preparation status for its implementation request',
    function () {
        $controller =
            p7Source(
                'app/Http/Controllers/Admin/'
                .'AdminTransformation360OverviewController.php'
            );

        $page =
            p7Source(
                'resources/js/pages/Admin/'
                .'Transformation360/DataBi.vue'
            );

        expect($controller)
            ->toContain(
                'DataTransformationBiPreparationStatusReadModel'
            )
            ->toContain(
                "\$row['data_preparation']"
            )
            ->toContain(
                '$preparationStatus->forRequest('
            );

        expect($page)
            ->toContain(
                'data_preparation: DataPreparationStatus | null'
            )
            ->toContain(
                'Datos ·'
            )
            ->toContain(
                'Sin procesamiento'
            )
            ->toContain(
                'normalized_row_count'
            );
    }
);

test(
    'tenant workspace receives only preparation metadata and counts',
    function () {
        $controller =
            p7Source(
                'app/Http/Controllers/'
                .'AppHubDataTransformationBiController.php'
            );

        $page =
            p7Source(
                'resources/js/pages/App/'
                .'DataTransformationBi.vue'
            );

        expect($controller)
            ->toContain(
                'DataTransformationBiPreparationStatusReadModel'
            )
            ->toContain(
                '$dataPreparation'
            )
            ->toContain(
                "'data_preparation'"
            );

        expect($page)
            ->toContain(
                'P7_DATA_PREPARATION_STATUS'
            )
            ->toContain(
                'Estado de tus datos para BI'
            )
            ->toContain(
                'Filas en staging'
            )
            ->toContain(
                'Filas perfiladas'
            )
            ->toContain(
                'Filas normalizadas'
            )
            ->toContain(
                'Bloqueantes'
            )
            ->toContain(
                'Advertencias'
            )
            ->toContain(
                'Informativas'
            )
            ->toContain(
                'No expone registros de origen, datos'
            );
    }
);

test(
    'p7 remains read only and does not start final analytical model',
    function () {
        $service =
            p7Source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparationStatusReadModel.php'
            );

        foreach ([
            '->create(',
            '->update(',
            '->delete(',
            '->save(',
            '->insert(',
        ] as $forbidden) {
            expect($service)
                ->not
                ->toContain($forbidden);
        }

        foreach ([
            'Fact',
            'Dimension',
            'star_schema',
            'analytical_model',
        ] as $forbidden) {
            expect($service)
                ->not
                ->toContain($forbidden);
        }
    }
);
