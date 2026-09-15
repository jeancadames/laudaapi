<?php

function p13DatasetSurfaceSource(
    string $path
): string {
    return file_get_contents(
        dirname(__DIR__, 3).'/'.$path
    );
}

test(
    'admin and tenant resolve usable dataset separately from latest attempt',
    function () {
        $admin =
            p13DatasetSurfaceSource(
                'app/Http/Controllers/Admin/'
                .'AdminTransformation360OverviewController.php'
            );

        $tenant =
            p13DatasetSurfaceSource(
                'app/Http/Controllers/'
                .'AppHubDataTransformationBiController.php'
            );

        foreach ([$admin, $tenant] as $source) {
            expect($source)
                ->toContain(
                    'DataTransformationBiUsableDatasetResolver::class'
                )
                ->toContain(
                    'forRequest('
                )
                ->toContain(
                    'usable_dataset'
                );
        }
    }
);

test(
    'admin surface shows compact usable dataset status',
    function () {
        $source =
            p13DatasetSurfaceSource(
                'resources/js/pages/Admin/'
                .'Transformation360/DataBi.vue'
            );

        expect($source)
            ->toContain(
                'P13_USABLE_DATASET_STATUS'
            )
            ->toContain(
                'row.usable_dataset.available'
            )
            ->toContain(
                'Dataset utilizable ·'
            )
            ->toContain(
                '.normalized_row_count'
            )
            ->toContain(
                'No disponible'
            );
    }
);

test(
    'tenant surface explains usable dataset separately from latest attempt',
    function () {
        $source =
            p13DatasetSurfaceSource(
                'resources/js/pages/App/'
                .'DataTransformationBi.vue'
            );

        foreach ([
            'type UsableDatasetStatus =',
            'usable_dataset: UsableDatasetStatus',
            'P13_USABLE_DATASET_STATUS',
            'Dataset utilizable actual',
            'última versión',
            'Puede ser anterior al último intento',
            'Preparación de datos',
        ] as $required) {
            expect($source)
                ->toContain($required);
        }

        $compact =
            preg_replace(
                '/\\s+/',
                '',
                $source
            );

        expect($compact)
            ->toContain(
                'usable_dataset.dataset.processing_run_id'
            )
            ->toContain(
                'usable_dataset.dataset.intake_batch_id'
            )
            ->toContain(
                'usable_dataset.dataset.normalized_row_count'
            );
    }
);

test(
    'usable dataset surface does not expose row payload or private source metadata',
    function () {
        $sources = [
            p13DatasetSurfaceSource(
                'resources/js/pages/Admin/'
                .'Transformation360/DataBi.vue'
            ),
            p13DatasetSurfaceSource(
                'resources/js/pages/App/'
                .'DataTransformationBi.vue'
            ),
        ];

        foreach ($sources as $source) {
            foreach ([
                'normalized_payload',
                'normalization_meta',
                'identity_hash',
                'source_row_number',
                'source_row_sha256',
                'normalized_sha256',
                'source_path',
                'source_sha256',
                'validation_snapshot',
                'failure_message',
            ] as $forbidden) {
                expect($source)
                    ->not
                    ->toContain($forbidden);
            }
        }
    }
);

test(
    'p13 surface adds no mutation endpoint or analytical model',
    function () {
        $sources = [
            p13DatasetSurfaceSource(
                'resources/js/pages/Admin/'
                .'Transformation360/DataBi.vue'
            ),
            p13DatasetSurfaceSource(
                'resources/js/pages/App/'
                .'DataTransformationBi.vue'
            ),
        ];

        foreach ($sources as $source) {
            foreach ([
                'FactSales',
                'DimCustomer',
                'DimProduct',
                'star_schema',
            ] as $forbidden) {
                expect($source)
                    ->not
                    ->toContain($forbidden);
            }
        }
    }
);
