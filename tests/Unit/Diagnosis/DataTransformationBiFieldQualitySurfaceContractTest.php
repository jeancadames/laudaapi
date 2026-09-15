<?php

function p9Source(
    string $path
): string {
    return file_get_contents(
        dirname(__DIR__, 3).'/'.$path
    );
}

test(
    'field quality reads aggregate field profiles',
    function () {
        $source =
            p9Source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparationStatusReadModel.php'
            );

        expect($source)
            ->toContain(
                'DataTransformationBiFieldProfile::query()'
            )
            ->toContain(
                "'row_count'"
            )
            ->toContain(
                "'non_null_count'"
            )
            ->toContain(
                "'null_count'"
            )
            ->toContain(
                "'blank_count'"
            )
            ->toContain(
                "'distinct_count'"
            )
            ->toContain(
                "'invalid_count'"
            )
            ->toContain(
                "'issue_count'"
            );
    }
);

test(
    'field severity uses grouped counts only',
    function () {
        $source =
            p9Source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparationStatusReadModel.php'
            );

        expect($source)
            ->toContain(
                'DataTransformationBiQualityIssue::query()'
            )
            ->toContain(
                "'severity'"
            )
            ->toContain(
                'COUNT(*) as issue_occurrence_count'
            )
            ->toContain(
                "'issue_code'"
            )
            ->toContain(
                "->groupBy("
            )
            ->toContain(
                'SEVERITY_BLOCKING'
            )
            ->toContain(
                'SEVERITY_WARNING'
            )
            ->toContain(
                'SEVERITY_INFO'
            );

        foreach ([
            "'message'",
            "'meta'",
            "'source_row_number'",
            "'identity_hash'",
        ] as $forbidden) {
            expect($source)
                ->not
                ->toContain($forbidden);
        }
    }
);

test(
    'field projection contains readiness metrics but no business values',
    function () {
        $source =
            p9Source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparationStatusReadModel.php'
            );

        foreach ([
            "'quality_status'",
            "'quality_label'",
            "'missing_count'",
            "'completeness_percent'",
            "'blocking_issue_count'",
            "'warning_issue_count'",
            "'informational_issue_count'",
            "'required_incomplete'",
            "'with_invalid_values'",
        ] as $required) {
            expect($source)
                ->toContain($required);
        }

        foreach ([
            "'source_path'",
            "'normalized_payload'",
            "'normalization_meta'",
            "'source_sha256'",
        ] as $forbidden) {
            expect($source)
                ->not
                ->toContain($forbidden);
        }
    }
);

test(
    'field descriptions come from standard schema metadata',
    function () {
        $source =
            p9Source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparationStatusReadModel.php'
            );

        expect($source)
            ->toContain(
                'DataTransformationBiStandardIntakeSchema::domains()'
            )
            ->toContain(
                'fieldDescription('
            )
            ->toContain(
                'fieldOrder('
            );
    }
);

test(
    'tenant domain cards expose collapsible field quality metadata',
    function () {
        $source =
            p9Source(
                'resources/js/pages/App/'
                .'DataTransformationBi.vue'
            );

        foreach ([
            'P9_FIELD_QUALITY_STATUS',
            'domain.fields.length > 0',
            'field.quality_label',
            'field.completeness_percent',
            'field.missing_count',
            'field.invalid_count',
            'field.distinct_count',
            'field.blocking_issue_count',
            'field.warning_issue_count',
            'field.informational_issue_count',
        ] as $required) {
            expect($source)
                ->toContain($required);
        }
    }
);

test(
    'admin surface exposes compact aggregate field status',
    function () {
        $source =
            p9Source(
                'resources/js/pages/Admin/'
                .'Transformation360/DataBi.vue'
            );

        expect($source)
            ->toContain(
                'field_summary:'
            )
            ->toContain(
                'requieren corrección'
            )
            ->toContain(
                'con advertencias'
            );
    }
);

test(
    'p9 remains read only and does not create analytical model',
    function () {
        $source =
            p9Source(
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
