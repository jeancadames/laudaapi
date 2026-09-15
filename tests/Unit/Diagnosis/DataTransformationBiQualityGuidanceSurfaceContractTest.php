<?php

function p10Source(
    string $path
): string {
    return file_get_contents(
        dirname(__DIR__, 3).'/'.$path
    );
}

test(
    'quality guidance catalog covers current profiler taxonomy',
    function () {
        $source =
            p10Source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiQualityGuidanceCatalog.php'
            );

        foreach ([
            'required_field_incomplete',
            'optional_field_incomplete',
            'missing_identity_hash',
            'duplicate_identity_in_staging',
            'staging_validation_contract_violation',
            'staging_relation_contract_violation',
        ] as $code) {
            expect($source)
                ->toContain($code);
        }
    }
);

test(
    'quality issue projection uses aggregate technical taxonomy only',
    function () {
        $source =
            p10Source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparationStatusReadModel.php'
            );

        expect($source)
            ->toContain(
                "'issue_code'"
            )
            ->toContain(
                "'severity'"
            )
            ->toContain(
                'COUNT(*) as issue_occurrence_count'
            )
            ->toContain(
                "'field_key'"
            )
            ->toContain(
                "'domain_key'"
            );

        foreach ([
            "'message'",
            "'meta'",
            "'source_row_number'",
            "'identity_hash'",
            "'data_transformation_bi_intake_row_id'",
        ] as $forbidden) {
            expect($source)
                ->not
                ->toContain($forbidden);
        }
    }
);

test(
    'issue guidance is attached to fields and domains',
    function () {
        $source =
            p10Source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparationStatusReadModel.php'
            );

        expect($source)
            ->toContain(
                "'issues' =>"
            )
            ->toContain(
                'issueGuidance('
            )
            ->toContain(
                'DataTransformationBiQualityGuidanceCatalog'
            )
            ->toContain(
                "'severity_label'"
            )
            ->toContain(
                "'guidance'"
            );
    }
);

test(
    'tenant presents remediation guidance without raw issue messages',
    function () {
        $source =
            p10Source(
                'resources/js/pages/App/'
                .'DataTransformationBi.vue'
            );

        foreach ([
            'P10_QUALITY_GUIDANCE',
            'domain.issues.length > 0',
            'field.issues.length > 0',
            'issue.label',
            'issue.severity_label',
            'issue.count',
            'issue.guidance',
        ] as $required) {
            expect($source)
                ->toContain($required);
        }
    }
);

test(
    'p10 does not claim field invalidity unsupported by current profiler',
    function () {
        $catalog =
            p10Source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiQualityGuidanceCatalog.php'
            );

        expect($catalog)
            ->not
            ->toContain(
                'invalid_field_value'
            )
            ->not
            ->toContain(
                'document_date'
            );
    }
);

test(
    'p10 remains read only and does not start analytical model',
    function () {
        $source =
            p10Source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparationStatusReadModel.php'
            );

        foreach ([
            '->create(',
            '->update(',
            '->delete(',
            '->save(',
            '->insert(',
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

// P10_R3_FIELD_SEVERITY_SUM_CONTRACT
test(
    'field severity counters consume issue occurrence aggregate consistently',
    function () {
        $source =
            p10Source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparationStatusReadModel.php'
            );

        $sumCount =
            preg_match_all(
                "/->sum\\(\\s*'issue_occurrence_count'\\s*\\)/",
                $source
            );

        expect($sumCount)
            ->toBe(3);

        expect($source)
            ->not
            ->toContain(
                "'severity_count'"
            );
    }
);
