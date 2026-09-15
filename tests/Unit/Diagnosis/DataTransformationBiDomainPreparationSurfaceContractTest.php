<?php

function p8Source(
    string $path
): string {
    return file_get_contents(
        dirname(__DIR__, 3).'/'.$path
    );
}

test(
    'domain preparation uses aggregate domain profiles without reading normalized rows',
    function () {
        $source =
            p8Source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparationStatusReadModel.php'
            );

        expect($source)
            ->toContain(
                'DataTransformationBiDomainProfile::query()'
            )
            ->toContain(
                "'data_transformation_bi_processing_run_id'"
            )
            ->toContain(
                "'data_transformation_bi_intake_batch_id'"
            )
            ->toContain(
                "'company_id'"
            )
            ->toContain(
                "'domain_summary'"
            )
            ->toContain(
                "'domains'"
            );

        /*
         * P9 extends this shared read-model with grouped
         * field severity counts from QualityIssue.
         *
         * P8 still guarantees that domain preparation
         * never reads normalized row payloads.
         */
        expect($source)
            ->not
            ->toContain(
                'DataTransformationBiNormalizedRow::query()'
            );
    }
);

test(
    'domain preparation exposes aggregate quality and normalization counts',
    function () {
        $source =
            p8Source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparationStatusReadModel.php'
            );

        foreach ([
            "'row_count'",
            "'field_count'",
            "'identity_count'",
            "'duplicate_identity_count'",
            "'issue_count'",
            "'blocking_issue_count'",
            "'warning_issue_count'",
            "'informational_issue_count'",
            "'normalized_row_count'",
            "'quality_status'",
            "'preparation_status'",
        ] as $required) {
            expect($source)
                ->toContain($required);
        }
    }
);

test(
    'standard domains have stable business labels',
    function () {
        $source =
            p8Source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparationStatusReadModel.php'
            );

        foreach ([
            "'customers'",
            "'Clientes'",
            "'products'",
            "'Productos'",
            "'inventory'",
            "'Inventario'",
            "'sales'",
            "'Ventas'",
            "'accounts_receivable'",
            "'Cuentas por cobrar'",
            "'suppliers'",
            "'Suplidores'",
            "'accounts_payable'",
            "'Cuentas por pagar'",
        ] as $required) {
            expect($source)
                ->toContain($required);
        }
    }
);

test(
    'tenant workspace shows domain preparation without row data',
    function () {
        $source =
            p8Source(
                'resources/js/pages/App/'
                .'DataTransformationBi.vue'
            );

        expect($source)
            ->toContain(
                'P8_DOMAIN_PREPARATION_STATUS'
            )
            ->toContain(
                'Estado por dominio'
            )
            ->toContain(
                'Preparación de cada fuente'
            )
            ->toContain(
                'domain.normalized_row_count'
            )
            ->toContain(
                'domain.quality_label'
            )
            ->toContain(
                'domain.blocking_issue_count'
            )
            ->toContain(
                'domain.warning_issue_count'
            )
            ->toContain(
                'domain.informational_issue_count'
            );
    }
);

test(
    'admin supervisor receives compact domain preparation status',
    function () {
        $source =
            p8Source(
                'resources/js/pages/Admin/'
                .'Transformation360/DataBi.vue'
            );

        expect($source)
            ->toContain(
                'domain_summary:'
            )
            ->toContain(
                'dominios normalizados'
            )
            ->toContain(
                'with_blocking_issues'
            )
            ->toContain(
                'with_warnings'
            );
    }
);

test(
    'p8 does not expose private or row level fields',
    function () {
        $service =
            p8Source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparationStatusReadModel.php'
            );

        foreach ([
            "'source_path'",
            "'validation_snapshot'",
            "'normalized_payload'",
            "'normalization_meta'",
            "'identity_hash'",
            "'source_row_number'",
            "'message'",
            "'meta'",
        ] as $forbidden) {
            expect($service)
                ->not
                ->toContain($forbidden);
        }
    }
);

test(
    'admin domain summary is null safe when request has no preparation state',
    function () {
        $source =
            p8Source(
                'resources/js/pages/Admin/'
                .'Transformation360/DataBi.vue'
            );

        expect($source)
            ->toContain(
                '&& row.data_preparation'
            )
            ->toContain(
                '.domain_summary'
            )
            ->toContain(
                ".total > 0"
            );
    }
);
