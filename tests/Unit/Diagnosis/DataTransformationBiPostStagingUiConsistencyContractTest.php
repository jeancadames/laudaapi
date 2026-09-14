<?php

function postStagingConsistencySource(): string
{
    return file_get_contents(
        dirname(__DIR__, 3)
        .'/resources/js/pages/Admin/Transformation360/'
        .'ImplementationRequests/Show.vue'
    );
}

test(
    'quality ui exposes informational issue count separately',
    function () {
        $source =
            postStagingConsistencySource();

        expect($source)
            ->toContain(
                'function standardIntakeInformationalIssueCount()'
            )
            ->toContain(
                'Informativas'
            )
            ->toContain(
                'standardIntakeInformationalIssueCount()'
            )
            ->toContain(
                '(processing.issue_count ?? 0)'
            )
            ->toContain(
                '(processing.blocking_issue_count ?? 0)'
            )
            ->toContain(
                '(processing.warning_issue_count ?? 0)'
            );
    }
);

test(
    'processing badge reflects normalization completion when available',
    function () {
        $source =
            postStagingConsistencySource();

        expect($source)
            ->toContain(
                'standardIntakeNormalizationReport'
            )
            ->toContain(
                '?.status'
            )
            ->toContain(
                '?? standardIntakeProfileReport'
            );
    }
);

test(
    'consistency patch does not alter processing endpoints',
    function () {
        $source =
            postStagingConsistencySource();

        expect($source)
            ->toContain(
                '/standard-intake/profile'
            )
            ->toContain(
                '/standard-intake/normalize'
            )
            ->toContain(
                'Analizar calidad'
            )
            ->toContain(
                'Normalizar datos'
            );
    }
);
