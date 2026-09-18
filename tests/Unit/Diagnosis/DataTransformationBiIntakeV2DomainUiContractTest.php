<?php

function d15cIntakeV2UiSource(): string
{
    return file_get_contents(
        dirname(__DIR__, 3)
        .'/resources/js/pages/Admin/Transformation360/'
        .'ImplementationRequests/Show.vue'
    );
}

test(
    'd15 c hydrates intake v2 state beside legacy post staging state',
    function () {
        $source =
            d15cIntakeV2UiSource();

        expect($source)
            ->toContain(
                'standard_intake_v2_state: StandardIntakeV2State | null'
            )
            ->toContain(
                'props.standard_intake_v2_state'
            )
            ->toContain(
                'props.standard_intake_persisted_state'
            );
    }
);

test(
    'd15 c exposes all six intake v2 http operations',
    function () {
        $source =
            d15cIntakeV2UiSource();

        foreach ([
            '/standard-intake-v2',
            '/session',
            '/upload',
            '/no-data',
            '/carry-forward',
            '/resolve',
            '/materialize',
        ] as $required) {
            expect($source)
                ->toContain(
                    $required
                );
        }
    }
);

test(
    'd15 c renders the seven domain decision experience',
    function () {
        $source =
            d15cIntakeV2UiSource();

        foreach ([
            'Intake v2 por dominios',
            'Flujo oficial',
            'accept=".csv,.xlsx"',
            'Subir CSV/XLSX',
            'No tengo datos',
            'Reutilizar datos preparados',
            'Validar relaciones',
            'Preparar staging',
            'standardIntakeV2ResolvedCount()',
            'standardIntakeV2CanCarryForward()',
        ] as $required) {
            expect($source)
                ->toContain(
                    $required
                );
        }
    }
);

test(
    'd15 c hands materialized batch to existing manual processing pipeline',
    function () {
        $source =
            d15cIntakeV2UiSource();

        $start =
            strpos(
                $source,
                'async function materializeStandardIntakeV2Session('
            );

        $end =
            strpos(
                $source,
                'function standardIntakeIssueText(',
                $start
            );

        expect($start)
            ->not
            ->toBeFalse()
            ->and($end)
            ->not
            ->toBeFalse();

        $method =
            substr(
                $source,
                $start,
                $end - $start
            );

        expect($method)
            ->toContain(
                'standardIntakeIngestionReport.value'
            )
            ->toContain(
                'standardIntakeProcessingBatchId.value'
            )
            ->toContain(
                'standardIntakeProfileReport.value'
            )
            ->toContain(
                'standardIntakeNormalizationReport.value'
            )
            ->not
            ->toContain(
                'profileStandardIntakeBatch()'
            )
            ->not
            ->toContain(
                'normalizeStandardIntakeProcessingRun()'
            );
    }
);

test(
    'd15 c keeps private storage paths out of the frontend contract',
    function () {
        $source =
            d15cIntakeV2UiSource();

        expect($source)
            ->not
            ->toContain(
                'source_path'
            );
    }
);

test(
    'd15 c keeps legacy intake visibly compatibility only',
    function () {
        $source =
            d15cIntakeV2UiSource();

        expect($source)
            ->toContain(
                'Compatibilidad Intake v1:'
            )
            ->toContain(
                'El flujo oficial nuevo es Intake v2 por dominios.'
            );
    }
);
