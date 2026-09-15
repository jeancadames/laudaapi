<?php

function p15RelationshipSource(
    string $path
): string {
    return file_get_contents(
        dirname(__DIR__, 3).'/'.$path
    );
}

test(
    'relationship navigator uses the canonical schema relationship catalog',
    function () {
        $source =
            p15RelationshipSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalRelationshipNavigator.php'
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
                'relationshipsFrom('
            )
            ->toContain(
                'resolveTarget('
            )
            ->toContain(
                'resolveTargets('
            );
    }
);

test(
    'relationship navigator resolves p13 and stays on that dataset snapshot',
    function () {
        $source =
            p15RelationshipSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalRelationshipNavigator.php'
            );

        expect($source)
            ->toContain(
                'DataTransformationBiUsableDatasetResolver'
            )
            ->toContain(
                '$this->usableDatasetResolver->forRequest('
            )
            ->toContain(
                '$this->resolveAgainstDataset('
            )
            ->toContain(
                '$dataset'
            );
    }
);

test(
    'target identity is derived through normalized canonical identity',
    function () {
        $identity =
            p15RelationshipSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalIdentity.php'
            );

        $navigator =
            p15RelationshipSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalRelationshipNavigator.php'
            );

        expect($identity)
            ->toContain(
                '$this->normalizer->normalize('
            )
            ->toContain(
                'hashForNormalizedPayload('
            );

        expect($navigator)
            ->toContain(
                '->hashForIdentityValues('
            );
    }
);

test(
    'p14 identity lookup is pinned to company run batch domain and canonical hash',
    function () {
        $source =
            p15RelationshipSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparedDatasetReader.php'
            );

        expect($source)
            ->toContain(
                'findDomainRowByCanonicalIdentityHashInDataset('
            )
            ->toContain(
                "'company_id'"
            )
            ->toContain(
                "'data_transformation_bi_processing_run_id'"
            )
            ->toContain(
                "'data_transformation_bi_intake_batch_id'"
            )
            ->toContain(
                "'domain_key'"
            )
            ->toContain(
                "'canonical_identity_hash'"
            );
    }
);

test(
    'navigator supports the five canonical relationships',
    function () {
        $source =
            p15RelationshipSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiStandardIntakeSchema.php'
            );

        foreach ([
            "'from_domain' => 'inventory'",
            "'from_domain' => 'sales'",
            "'from_domain' => 'accounts_receivable'",
            "'from_domain' => 'accounts_payable'",
            "'to_domain' => 'products'",
            "'to_domain' => 'customers'",
            "'to_domain' => 'suppliers'",
        ] as $required) {
            expect($source)
                ->toContain($required);
        }
    }
);

test(
    'non blank broken canonical relations fail closed',
    function () {
        $source =
            p15RelationshipSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalRelationshipNavigator.php'
            );

        expect($source)
            ->toContain(
                '$target === null'
            )
            ->toContain(
                'no pudo resolverse'
            )
            ->toContain(
                'RuntimeException'
            );
    }
);

test(
    'unsupported or ambiguous relations fail closed',
    function () {
        $source =
            p15RelationshipSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalRelationshipNavigator.php'
            );

        expect($source)
            ->toContain(
                'No existe una relación canónica'
            )
            ->toContain(
                'es ambigua'
            )
            ->toContain(
                'no apunta a una identidad canónica simple soportada'
            );
    }
);

test(
    'relationship access exposes no source row or normalization metadata',
    function () {
        $sources = [
            p15RelationshipSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalRelationshipNavigator.php'
            ),
            p15RelationshipSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparedDatasetReader.php'
            ),
        ];

        foreach ($sources as $source) {
            foreach ([
                'source_row_number',
                'source_row_sha256',
                'normalized_sha256',
                'normalization_meta',
                'source_path',
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
    'p15 b remains internal and does not start analytical model',
    function () {
        $navigator =
            p15RelationshipSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalRelationshipNavigator.php'
            );

        foreach ([
            '->create(',
            '->update(',
            '->delete(',
            '->save(',
            '->insert(',
            '->upsert(',
            'FactSales',
            'DimCustomer',
            'DimProduct',
            'star_schema',
        ] as $forbidden) {
            expect($navigator)
                ->not
                ->toContain($forbidden);
        }

        $externalSources = [
            p15RelationshipSource(
                'app/Http/Controllers/Admin/'
                .'AdminTransformation360OverviewController.php'
            ),
            p15RelationshipSource(
                'app/Http/Controllers/'
                .'AppHubDataTransformationBiController.php'
            ),
            p15RelationshipSource(
                'resources/js/pages/Admin/'
                .'Transformation360/DataBi.vue'
            ),
            p15RelationshipSource(
                'resources/js/pages/App/'
                .'DataTransformationBi.vue'
            ),
        ];

        foreach ($externalSources as $source) {
            expect($source)
                ->not
                ->toContain(
                    'DataTransformationBiCanonicalRelationshipNavigator'
                );
        }
    }
);
