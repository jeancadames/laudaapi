<?php

function p16IntegritySource(
    string $path
): string {
    return file_get_contents(
        dirname(__DIR__, 3).'/'.$path
    );
}

test(
    'post normalization gate is scoped to company run and batch',
    function () {
        $source =
            p16IntegritySource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPostNormalizationIntegrityGate.php'
            );

        foreach ([
            "'company_id'",
            "'data_transformation_bi_processing_run_id'",
            "'data_transformation_bi_intake_batch_id'",
        ] as $required) {
            expect($source)
                ->toContain($required);
        }

        expect($source)
            ->toContain(
                'transformation_implementation_request_id'
            )
            ->toContain(
                'staged_row_count'
            );
    }
);

test(
    'gate recomputes every normalized canonical identity',
    function () {
        $source =
            p16IntegritySource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPostNormalizationIntegrityGate.php'
            );

        expect($source)
            ->toContain(
                "'canonical_identity_hash'"
            )
            ->toContain(
                "'normalized_payload'"
            )
            ->toContain(
                'hashForNormalizedPayload('
            )
            ->toContain(
                'hash_equals('
            )
            ->toContain(
                "'/^[a-f0-9]{64}$/'"
            );
    }
);

test(
    'gate rejects canonical identity duplicates',
    function () {
        $source =
            p16IntegritySource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPostNormalizationIntegrityGate.php'
            );

        expect($source)
            ->toContain(
                "'canonical_identity_hash'"
            )
            ->toContain(
                'COUNT(*) > 1'
            )
            ->toContain(
                'identidades '
            )
            ->toContain(
                'canónicas duplicadas.'
            );
    }
);

test(
    'gate validates every normalized relationship against target identity',
    function () {
        $source =
            p16IntegritySource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPostNormalizationIntegrityGate.php'
            );

        expect($source)
            ->toContain(
                'DataTransformationBiStandardIntakeSchema'
            )
            ->toContain(
                '::relationships()'
            )
            ->toContain(
                '::identityKeys()'
            )
            ->toContain(
                'hashForIdentityValues('
            )
            ->toContain(
                "'canonical_identity_hash'"
            )
            ->toContain(
                '->whereIn('
            )
            ->toContain(
                'relaciones canónicas no resolubles'
            );
    }
);

test(
    'blank optional relationship values are not treated as broken',
    function () {
        $source =
            p16IntegritySource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPostNormalizationIntegrityGate.php'
            );

        expect($source)
            ->toContain(
                '$this->blank($value)'
            )
            ->toContain(
                'continue;'
            );
    }
);

test(
    'integrity scans are bounded by chunks',
    function () {
        $source =
            p16IntegritySource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPostNormalizationIntegrityGate.php'
            );

        expect($source)
            ->toContain(
                'CHUNK_SIZE = 500'
            )
            ->toContain(
                '->chunkById('
            )
            ->not
            ->toContain(
                '->get()'
            );
    }
);

test(
    'normalization completion invokes p16 inside completed run assertion',
    function () {
        $source =
            p16IntegritySource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiStagingNormalizationService.php'
            );

        expect($source)
            ->toContain(
                'private function assertCompletedRun('
            )
            ->toContain(
                'DataTransformationBiPostNormalizationIntegrityGate::class'
            )
            ->toContain(
                '->assertRun('
            )
            ->toContain(
                'P16 · Post-normalization integrity gate.'
            );

        $assertStart =
            strpos(
                $source,
                'private function assertCompletedRun('
            );

        $gate =
            strpos(
                $source,
                'DataTransformationBiPostNormalizationIntegrityGate::class'
            );

        $markFailed =
            strpos(
                $source,
                'private function markFailed('
            );

        expect($assertStart)
            ->not
            ->toBeFalse();

        expect($gate)
            ->not
            ->toBeFalse();

        expect($markFailed)
            ->not
            ->toBeFalse();

        expect($gate)
            ->toBeGreaterThan($assertStart);

        expect($gate)
            ->toBeLessThan($markFailed);
    }
);

test(
    'p16 introduces no writes of its own',
    function () {
        $source =
            p16IntegritySource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPostNormalizationIntegrityGate.php'
            );

        foreach ([
            '->create(',
            '->update(',
            '->delete(',
            '->save(',
            '->insert(',
            '->upsert(',
            '->updateOrCreate(',
            'forceFill(',
        ] as $forbidden) {
            expect($source)
                ->not
                ->toContain($forbidden);
        }
    }
);

test(
    'p16 does not expose rows or start analytical model',
    function () {
        $source =
            p16IntegritySource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPostNormalizationIntegrityGate.php'
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

        $external = [
            p16IntegritySource(
                'app/Http/Controllers/Admin/'
                .'AdminTransformation360OverviewController.php'
            ),
            p16IntegritySource(
                'app/Http/Controllers/'
                .'AppHubDataTransformationBiController.php'
            ),
            p16IntegritySource(
                'resources/js/pages/Admin/'
                .'Transformation360/DataBi.vue'
            ),
            p16IntegritySource(
                'resources/js/pages/App/'
                .'DataTransformationBi.vue'
            ),
        ];

        foreach ($external as $candidate) {
            expect($candidate)
                ->not
                ->toContain(
                    'DataTransformationBiPostNormalizationIntegrityGate'
                );
        }
    }
);
