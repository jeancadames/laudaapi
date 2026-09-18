<?php

use Tests\TestCase;

uses(TestCase::class);

function qa2I16D4UiSource(): string
{
    $source =
        file_get_contents(
            resource_path(
                'js/pages/Admin/Transformation360/ImplementationRequests/Show.vue'
            )
        );

    if ($source === false) {
        throw new RuntimeException(
            'Could not read Data BI admin request UI.'
        );
    }

    return $source;
}

test(
    'staging action is separate from temporary validation',
    function () {
        $source =
            qa2I16D4UiSource();

        expect($source)
            ->toContain(
                '/standard-intake/validate'
            )
            ->toContain(
                '/standard-intake/ingest'
            )
            ->toContain(
                'validateStandardIntakeFile'
            )
            ->toContain(
                'ingestStandardIntakeFile'
            )
            ->toContain(
                'Ingresar a staging'
            );
    }
);

test(
    'ingestion is never triggered automatically after validation',
    function () {
        $source =
            qa2I16D4UiSource();

        $start =
            strpos(
                $source,
                'async function validateStandardIntakeFile()'
            );

        $end =
            strpos(
                $source,
                'async function ingestStandardIntakeFile()',
                $start
            );

        expect($start)
            ->not
            ->toBeFalse()
            ->and($end)
            ->not
            ->toBeFalse();

        $validationMethod =
            substr(
                $source,
                $start,
                $end - $start
            );

        expect($validationMethod)
            ->toContain(
                'standardIntakeValidationUrl'
            )
            ->not
            ->toContain(
                'standardIntakeIngestionUrl'
            )
            ->not
            ->toContain(
                'ingestStandardIntakeFile('
            );
    }
);

test(
    'staging action requires current validation pass',
    function () {
        $source =
            qa2I16D4UiSource();

        $start =
            strpos(
                $source,
                'async function ingestStandardIntakeFile()'
            );

        $end =
            strpos(
                $source,
                'function assessmentStatusLabel(',
                $start
            );

        $method =
            substr(
                $source,
                $start,
                $end - $start
            );

        expect($method)
            ->toContain(
                'validation?.valid !== true'
            )
            ->toContain(
                'standardIntakeFile.value'
            )
            ->toContain(
                'new FormData()'
            )
            ->toContain(
                "formData.append("
            )
            ->toContain(
                'standardIntakeIngestionUrl'
            );
    }
);

test(
    'changing selected file invalidates prior validation and ingestion results',
    function () {
        $source =
            qa2I16D4UiSource();

        $start =
            strpos(
                $source,
                'function selectStandardIntakeFile('
            );

        $end =
            strpos(
                $source,
                'async function validateStandardIntakeFile()',
                $start
            );

        $method =
            substr(
                $source,
                $start,
                $end - $start
            );

        expect($method)
            ->toContain(
                'standardIntakeReport.value = null'
            )
            ->toContain(
                'standardIntakeIngestionReport.value = null'
            )
            ->toContain(
                'standardIntakeIngestionError.value = null'
            );
    }
);

test(
    'ui exposes batch and row ingestion report',
    function () {
        $source =
            qa2I16D4UiSource();

        expect($source)
            ->toContain(
                'Batch #'
            )
            ->toContain(
                'Ingreso a staging completado'
            )
            ->toContain(
                'Batch existente reutilizado'
            )
            ->toContain(
                'Filas de origen'
            )
            ->toContain(
                'Filas en staging'
            )
            ->toContain(
                'Filas rechazadas'
            )
            ->toContain(
                'Staging por dominio'
            )
            ->toContain(
                'source_row_count'
            )
            ->toContain(
                'staged_row_count'
            )
            ->toContain(
                'rejected_row_count'
            );
    }
);

test(
    'ui explains persistence boundary before explicit ingestion',
    function () {
        $source =
            qa2I16D4UiSource();

        expect($source)
            ->toContain(
                'La validación anterior fue temporal'
            )
            ->toContain(
                'almacenamiento privado'
            )
            ->toContain(
                'No crea todavía'
            )
            ->toContain(
                'el modelo BI final'
            )
            ->toContain(
                'no cambia el estado de la'
            )
            ->toContain(
                'no modifica la Definición'
            );
    }
);

test(
    'validation and ingestion operations cannot overlap in the browser',
    function () {
        $source =
            qa2I16D4UiSource();

        expect($source)
            ->toContain(
                'standardIntakeValidating.value'
            )
            ->toContain(
                'standardIntakeIngesting.value'
            )
            ->toContain(
                '|| standardIntakeValidating'
            )
            ->toContain(
                '|| standardIntakeIngesting'
            )
            ->and(
                substr_count(
                    $source,
                    'standardIntakeIngesting'
                )
            )
            ->toBeGreaterThanOrEqual(6);
    }
);

test(
    'ui does not expose storage paths or final bi entities',
    function () {
        $source =
            qa2I16D4UiSource();

        expect($source)
            ->not
            ->toContain(
                'source_path'
            )
            ->not
            ->toContain(
                'bi_fact_'
            )
            ->not
            ->toContain(
                'bi_dimension_'
            );
    }
);
