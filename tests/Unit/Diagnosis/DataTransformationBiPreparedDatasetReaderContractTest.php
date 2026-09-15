<?php

function p14PreparedReaderSource(
    string $path
): string {
    return file_get_contents(
        dirname(__DIR__, 3).'/'.$path
    );
}

test(
    'prepared reader always resolves the usable dataset through p13',
    function () {
        $source =
            p14PreparedReaderSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparedDatasetReader.php'
            );

        expect($source)
            ->toContain(
                'DataTransformationBiUsableDatasetResolver'
            )
            ->toContain(
                '$this->usableDatasetResolver->forRequest('
            )
            ->toContain(
                "'processing_run_id'"
            )
            ->toContain(
                "'intake_batch_id'"
            );
    }
);

test(
    'prepared reader uses the canonical standard intake domain catalog',
    function () {
        $source =
            p14PreparedReaderSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparedDatasetReader.php'
            );

        expect($source)
            ->toContain(
                'DataTransformationBiStandardIntakeSchema::domains()'
            )
            ->toContain(
                'supportedDomains()'
            )
            ->toContain(
                'InvalidArgumentException'
            );
    }
);

test(
    'normalized row query is locked to company run batch and domain',
    function () {
        $source =
            p14PreparedReaderSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparedDatasetReader.php'
            );

        foreach ([
            "'company_id'",
            "'data_transformation_bi_processing_run_id'",
            "'data_transformation_bi_intake_batch_id'",
            "'domain_key'",
        ] as $required) {
            expect($source)
                ->toContain($required);
        }
    }
);

test(
    'prepared rows use bounded keyset pagination',
    function () {
        $source =
            p14PreparedReaderSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparedDatasetReader.php'
            );

        expect($source)
            ->toContain(
                'DEFAULT_PAGE_SIZE = 500'
            )
            ->toContain(
                'MAX_PAGE_SIZE = 5000'
            )
            ->toContain(
                "'id',"
            )
            ->toContain(
                "'>',"
            )
            ->toContain(
                "->orderBy('id')"
            )
            ->toContain(
                '$pageSize + 1'
            )
            ->toContain(
                "'next_after_id'"
            );
    }
);

test(
    'reader exposes canonical payload and identity but no source metadata',
    function () {
        $source =
            p14PreparedReaderSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparedDatasetReader.php'
            );

        expect($source)
            ->toContain(
                "'identity_hash'"
            )
            ->toContain(
                "'normalized_payload'"
            )
            ->toContain(
                "'payload'"
            );

        foreach ([
            'source_row_number',
            'source_row_sha256',
            'normalized_sha256',
            'normalization_meta',
            'source_path',
            'source_disk',
            'original_filename',
            'validation_snapshot',
            'failure_message',
        ] as $forbidden) {
            expect($source)
                ->not
                ->toContain($forbidden);
        }
    }
);

test(
    'reader fails closed on invalid canonical rows',
    function () {
        $source =
            p14PreparedReaderSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparedDatasetReader.php'
            );

        expect($source)
            ->toContain(
                '! is_array($payload)'
            )
            ->toContain(
                "\$identityHash === ''"
            )
            ->toContain(
                'RuntimeException'
            );
    }
);

test(
    'reader performs no writes',
    function () {
        $source =
            p14PreparedReaderSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparedDatasetReader.php'
            );

        foreach ([
            '->create(',
            '->update(',
            '->delete(',
            '->save(',
            '->insert(',
            '->upsert(',
            '->updateOrCreate(',
        ] as $forbidden) {
            expect($source)
                ->not
                ->toContain($forbidden);
        }
    }
);

test(
    'p14 reader remains internal and is not exposed by current controllers or ui',
    function () {
        $sources = [
            p14PreparedReaderSource(
                'app/Http/Controllers/Admin/'
                .'AdminTransformation360OverviewController.php'
            ),
            p14PreparedReaderSource(
                'app/Http/Controllers/'
                .'AppHubDataTransformationBiController.php'
            ),
            p14PreparedReaderSource(
                'resources/js/pages/Admin/'
                .'Transformation360/DataBi.vue'
            ),
            p14PreparedReaderSource(
                'resources/js/pages/App/'
                .'DataTransformationBi.vue'
            ),
        ];

        foreach ($sources as $source) {
            expect($source)
                ->not
                ->toContain(
                    'DataTransformationBiPreparedDatasetReader'
                );
        }
    }
);

test(
    'p14 does not start facts dimensions or analytical model',
    function () {
        $source =
            p14PreparedReaderSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparedDatasetReader.php'
            );

        foreach ([
            'FactSales',
            'DimCustomer',
            'DimProduct',
            'star_schema',
            'fact_',
            'dimension_',
        ] as $forbidden) {
            expect($source)
                ->not
                ->toContain($forbidden);
        }
    }
);
