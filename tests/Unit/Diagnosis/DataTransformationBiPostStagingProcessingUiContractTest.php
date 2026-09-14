<?php

function postStagingUiSource(): string
{
    return file_get_contents(
        dirname(__DIR__, 3)
        .'/resources/js/pages/Admin/Transformation360/'
        .'ImplementationRequests/Show.vue'
    );
}

test(
    'post staging ui exposes explicit quality analysis and normalization actions',
    function () {
        $source =
            postStagingUiSource();

        expect($source)
            ->toContain(
                'Procesamiento posterior a staging'
            )
            ->toContain(
                'Analizar calidad'
            )
            ->toContain(
                'Normalizar datos'
            )
            ->toContain(
                'profileStandardIntakeBatch'
            )
            ->toContain(
                'normalizeStandardIntakeBatch'
            );
    }
);

test(
    'post staging ui calls the dedicated profile and normalize endpoints',
    function () {
        $source =
            postStagingUiSource();

        expect($source)
            ->toContain(
                '/standard-intake/profile'
            )
            ->toContain(
                '/standard-intake/normalize'
            )
            ->toContain(
                'batch_id: batchId'
            )
            ->toContain(
                'processing_run_id:'
            )
            ->toContain(
                'standardIntakeCsrfHeaders()'
            );
    }
);

test(
    'quality analysis does not automatically normalize',
    function () {
        $source =
            postStagingUiSource();

        $profileStart =
            strpos(
                $source,
                'async function profileStandardIntakeBatch()'
            );

        $normalizeStart =
            strpos(
                $source,
                'async function normalizeStandardIntakeBatch()',
                $profileStart
            );

        expect($profileStart)
            ->not
            ->toBeFalse()
            ->and($normalizeStart)
            ->not
            ->toBeFalse();

        $profileSurface =
            substr(
                $source,
                $profileStart,
                $normalizeStart - $profileStart
            );

        expect($profileSurface)
            ->toContain(
                'standardIntakeProfilingUrl'
            )
            ->not
            ->toContain(
                'standardIntakeNormalizationUrl'
            );
    }
);

test(
    'normalization remains disabled when blocking issues exist',
    function () {
        $source =
            postStagingUiSource();

        expect($source)
            ->toContain(
                'function canNormalizeStandardIntake()'
            )
            ->toContain(
                "(processing.blocking_issue_count ?? 0) === 0"
            )
            ->toContain(
                '!canNormalizeStandardIntake()'
            )
            ->toContain(
                'Existen incidencias bloqueantes.'
            );
    }
);

test(
    'post staging ui shows processing metrics without claiming final bi model creation',
    function () {
        $source =
            postStagingUiSource();

        expect($source)
            ->toContain(
                'Filas perfiladas'
            )
            ->toContain(
                'Incidencias'
            )
            ->toContain(
                'Bloqueantes'
            )
            ->toContain(
                'Advertencias'
            )
            ->toContain(
                'Filas normalizadas'
            )
            ->toContain(
                'Cambios de normalización'
            )
            ->toMatch(
                '/no\\s+sobrescribirá\\s+el\\s+staging/u'
            );
    }
);

test(
    'ingestion does not automatically start quality processing',
    function () {
        $source =
            postStagingUiSource();

        $ingestStart =
            strpos(
                $source,
                'async function ingestStandardIntakeFile()'
            );

        $profileStart =
            strpos(
                $source,
                'async function profileStandardIntakeBatch()'
            );

        expect($ingestStart)
            ->not
            ->toBeFalse()
            ->and($profileStart)
            ->not
            ->toBeFalse();

        $ingestSurface =
            substr(
                $source,
                $ingestStart,
                $profileStart - $ingestStart
            );

        expect($ingestSurface)
            ->not
            ->toContain(
                'profileStandardIntakeBatch();'
            )
            ->not
            ->toContain(
                'normalizeStandardIntakeBatch();'
            );
    }
);
