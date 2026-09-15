<?php

function persistedStandalonePanelUi(): string
{
    return file_get_contents(
        dirname(__DIR__, 3)
        .'/resources/js/pages/Admin/Transformation360/'
        .'ImplementationRequests/Show.vue'
    );
}

test(
    'persisted processing panel is outside temporary validation gate',
    function () {
        $source =
            persistedStandalonePanelUi();

        $panel =
            strpos(
                $source,
                'P6_R5_PERSISTED_PROCESSING_PANEL'
            );

        $validation =
            strpos(
                $source,
                '&& standardIntakeReport.validation'
            );

        expect($panel)
            ->not
            ->toBeFalse()
            ->and($validation)
            ->not
            ->toBeFalse();

        expect($panel)
            ->toBeLessThan($validation);
    }
);

test(
    'persisted panel appears only when there is no temporary validation result',
    function () {
        $source =
            persistedStandalonePanelUi();

        $panel =
            strpos(
                $source,
                'P6_R5_PERSISTED_PROCESSING_PANEL'
            );

        $validation =
            strpos(
                $source,
                '&& standardIntakeReport.validation'
            );

        $surface =
            substr(
                $source,
                $panel,
                $validation - $panel
            );

        expect($surface)
            ->toContain(
                '!standardIntakeReport'
            )
            ->toContain(
                'standardIntakeIngestionReport?.ingestion'
            );
    }
);

test(
    'persisted panel exposes staging profile and normalization state',
    function () {
        $source =
            persistedStandalonePanelUi();

        expect($source)
            ->toContain(
                'Último procesamiento persistido'
            )
            ->toContain(
                'standardIntakeProfileIsForCurrentBatch()'
            )
            ->toContain(
                'standardIntakeInformationalIssueCount()'
            )
            ->toContain(
                'standardIntakeNormalizationReport'
            )
            ->toContain(
                'normalized_row_count'
            )
            ->toContain(
                'normalization_change_count'
            );
    }
);

test(
    'original temporary validation gate remains installed',
    function () {
        $source =
            persistedStandalonePanelUi();

        expect($source)
            ->toContain(
                'standardIntakeReport'
            )
            ->toContain(
                '&& standardIntakeReport.validation'
            )
            ->toContain(
                'Archivo listo para staging'
            );
    }
);
