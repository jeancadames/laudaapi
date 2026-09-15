<?php

function dataBiPersistedVisibilityUi(): string
{
    return file_get_contents(
        dirname(__DIR__, 3)
        .'/resources/js/pages/Admin/Transformation360/'
        .'ImplementationRequests/Show.vue'
    );
}

test(
    'persistent staging is outside temporary validation visibility gate',
    function () {
        $source =
            dataBiPersistedVisibilityUi();

        $boundary =
            strpos(
                $source,
                'P6_R4_PERSISTED_VISIBILITY_BOUNDARY'
            );

        $processing =
            strpos(
                $source,
                'Procesamiento posterior a staging',
                $boundary
            );

        $result =
            strpos(
                $source,
                'Resultado por dominio',
                $processing
            );

        expect($boundary)
            ->not
            ->toBeFalse()
            ->and($processing)
            ->not
            ->toBeFalse()
            ->and($result)
            ->not
            ->toBeFalse();

        $persistentSurface =
            substr(
                $source,
                $boundary,
                $result - $boundary
            );

        expect($persistentSurface)
            ->toContain(
                'standardIntakeIngestionReport'
            )
            ->toContain(
                'Procesamiento posterior a staging'
            );
    }
);

test(
    'validation results remain temporary and validation gated',
    function () {
        $source =
            dataBiPersistedVisibilityUi();

        $processing =
            strpos(
                $source,
                'Procesamiento posterior a staging'
            );

        $result =
            strpos(
                $source,
                'Resultado por dominio',
                $processing
            );

        $gate =
            strrpos(
                substr(
                    $source,
                    $processing,
                    $result - $processing
                ),
                '&& standardIntakeReport.validation'
            );

        expect($gate)
            ->not
            ->toBeFalse();
    }
);

test(
    'persisted hydration contract remains present',
    function () {
        $source =
            dataBiPersistedVisibilityUi();

        expect($source)
            ->toContain(
                'standard_intake_persisted_state:'
            )
            ->toContain(
                'props.standard_intake_persisted_state'
            )
            ->toContain(
                'standardIntakeInformationalIssueCount()'
            );
    }
);
