<?php

function p15CanonicalIdentitySource(
    string $path
): string {
    return file_get_contents(
        dirname(__DIR__, 3).'/'.$path
    );
}

test(
    'canonical normalized identity uses schema identity keys',
    function () {
        $source =
            p15CanonicalIdentitySource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalIdentity.php'
            );

        expect($source)
            ->toContain(
                'DataTransformationBiStandardIntakeSchema'
            )
            ->toContain(
                '::identityKeys()'
            )
            ->toContain(
                'hashForNormalizedPayload('
            )
            ->toContain(
                'hashForIdentityValues('
            )
            ->toContain(
                "'sha256'"
            );
    }
);

test(
    'canonical identity hashes canonical normalized values',
    function () {
        $source =
            p15CanonicalIdentitySource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalIdentity.php'
            );

        expect($source)
            ->toContain(
                'DataTransformationBiCanonicalNormalizer'
            )
            ->toContain(
                '->canonicalJson('
            )
            ->toContain(
                "'domain'"
            )
            ->toContain(
                "'identity'"
            );
    }
);

test(
    'normalization persists canonical identity separately from staging identity',
    function () {
        $source =
            p15CanonicalIdentitySource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiStagingNormalizationService.php'
            );

        expect($source)
            ->toContain(
                'DataTransformationBiCanonicalIdentity::class'
            )
            ->toContain(
                'hashForNormalizedPayload('
            )
            ->toContain(
                "'identity_hash'"
            )
            ->toContain(
                "'canonical_identity_hash'"
            );
    }
);

test(
    'migration provides unique run domain canonical identity lookup',
    function () {
        $source =
            p15CanonicalIdentitySource(
                'database/migrations/2026_09_15_142609_add_canonical_identity_hash_to_data_transformation_bi_normalized_rows.php'
            );

        foreach ([
            "'data_transformation_bi_processing_run_id'",
            "'domain_key'",
            "'canonical_identity_hash'",
            "'dtbi_nr_run_domain_canonical_identity_uq'",
        ] as $required) {
            expect($source)
                ->toContain($required);
        }

        expect($source)
            ->toContain(
                '->char('
            )
            ->toContain(
                '->nullable()'
            )
            ->toContain(
                '->unique('
            );
    }
);

test(
    'canonical normalized identity remains internal',
    function () {
        $sources = [
            p15CanonicalIdentitySource(
                'app/Http/Controllers/Admin/'
                .'AdminTransformation360OverviewController.php'
            ),
            p15CanonicalIdentitySource(
                'app/Http/Controllers/'
                .'AppHubDataTransformationBiController.php'
            ),
            p15CanonicalIdentitySource(
                'resources/js/pages/Admin/'
                .'Transformation360/DataBi.vue'
            ),
            p15CanonicalIdentitySource(
                'resources/js/pages/App/'
                .'DataTransformationBi.vue'
            ),
        ];

        foreach ($sources as $source) {
            expect($source)
                ->not
                ->toContain(
                    'canonical_identity_hash'
                );
        }
    }
);

test(
    'p15 a does not start analytical model',
    function () {
        $sources = [
            p15CanonicalIdentitySource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalIdentity.php'
            ),
            p15CanonicalIdentitySource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiStagingNormalizationService.php'
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
