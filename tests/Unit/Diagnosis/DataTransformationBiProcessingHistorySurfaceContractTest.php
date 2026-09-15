<?php

function p12SurfaceSource(
    string $path
): string {
    return file_get_contents(
        dirname(__DIR__, 3).'/'.$path
    );
}

test(
    'admin and tenant hydrate history through dedicated safe read model',
    function () {
        $admin =
            p12SurfaceSource(
                'app/Http/Controllers/Admin/'
                .'AdminTransformation360OverviewController.php'
            );

        $tenant =
            p12SurfaceSource(
                'app/Http/Controllers/'
                .'AppHubDataTransformationBiController.php'
            );

        foreach ([$admin, $tenant] as $source) {
            expect($source)
                ->toContain(
                    'DataTransformationBiProcessingHistoryReadModel::class'
                )
                ->toContain(
                    'forRequest('
                )
                ->toContain(
                    "'processing_history'"
                );
        }
    }
);

test(
    'admin surface exposes compact aggregate history summary',
    function () {
        $source =
            p12SurfaceSource(
                'resources/js/pages/Admin/'
                .'Transformation360/DataBi.vue'
            );

        expect($source)
            ->toContain(
                'P12_PROCESSING_HISTORY_SUMMARY'
            )
            ->toContain(
                'row.processing_history'
            )
            ->toContain(
                '.total_batches'
            )
            ->toContain(
                '.total_runs'
            )
            ->toContain(
                'Historial ·'
            );
    }
);

test(
    'tenant surface exposes collapsible batch and run history',
    function () {
        $source =
            p12SurfaceSource(
                'resources/js/pages/App/'
                .'DataTransformationBi.vue'
            );

        foreach ([
            'P12_PROCESSING_HISTORY',
            'processing_history.entries.length > 0',
            'Historial de procesamiento',
            'batch.batch_id',
            'batch.is_latest',
            'batch.domain_count',
            'batch.staged_row_count',
            'run.run_id',
            'run.profiled_row_count',
            'run.normalized_row_count',
            'run.issue_count',
            'run.blocking_issue_count',
            'run.warning_issue_count',
            'run.informational_issue_count',
        ] as $required) {
            expect($source)
                ->toContain($required);
        }
    }
);

test(
    'history surfaces do not expose private source or row metadata',
    function () {
        $sources = [
            p12SurfaceSource(
                'resources/js/pages/Admin/'
                .'Transformation360/DataBi.vue'
            ),
            p12SurfaceSource(
                'resources/js/pages/App/'
                .'DataTransformationBi.vue'
            ),
        ];

        foreach ($sources as $source) {
            foreach ([
                'source_path',
                'source_sha256',
                'validation_snapshot',
                'failure_message',
                'identity_hash',
                'source_row_number',
                'normalized_payload',
                'normalization_meta',
            ] as $forbidden) {
                expect($source)
                    ->not
                    ->toContain($forbidden);
            }
        }
    }
);

test(
    'p12 surfaces do not start analytical facts or dimensions',
    function () {
        $sources = [
            p12SurfaceSource(
                'app/Http/Controllers/Admin/'
                .'AdminTransformation360OverviewController.php'
            ),
            p12SurfaceSource(
                'app/Http/Controllers/'
                .'AppHubDataTransformationBiController.php'
            ),
            p12SurfaceSource(
                'resources/js/pages/Admin/'
                .'Transformation360/DataBi.vue'
            ),
            p12SurfaceSource(
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
