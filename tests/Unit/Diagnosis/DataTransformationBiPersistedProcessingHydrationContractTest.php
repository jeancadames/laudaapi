<?php

function persistedHydrationController(): string
{
    return file_get_contents(
        dirname(__DIR__, 3)
        .'/app/Http/Controllers/Admin/'
        .'AdminTransformationImplementationRequestController.php'
    );
}

function persistedHydrationUi(): string
{
    return file_get_contents(
        dirname(__DIR__, 3)
        .'/resources/js/pages/Admin/Transformation360/'
        .'ImplementationRequests/Show.vue'
    );
}

test(
    'show hydrates latest completed staging batch for same company and request',
    function () {
        $source =
            persistedHydrationController();

        expect($source)
            ->toContain(
                'P6_PERSISTED_DATA_BI_STATE'
            )
            ->toContain(
                "=== 'data_transformation_bi'"
            )
            ->toContain(
                'DataTransformationBiIntakeBatch::query()'
            )
            ->toContain(
                "'company_id'"
            )
            ->toContain(
                "'transformation_implementation_request_id'"
            )
            ->toContain(
                '::STATUS_COMPLETED'
            )
            ->toContain(
                "->orderByDesc('id')"
            );
    }
);

test(
    'hydrated staging state includes domain counts without exposing source path',
    function () {
        $source =
            persistedHydrationController();

        $start =
            strpos(
                $source,
                'P6_PERSISTED_DATA_BI_STATE'
            );

        $end =
            strpos(
                $source,
                'return Inertia::render(',
                $start
            );

        expect($start)
            ->not
            ->toBeFalse()
            ->and($end)
            ->not
            ->toBeFalse();

        $surface =
            substr(
                $source,
                $start,
                $end - $start
            );

        expect($surface)
            ->toContain(
                "'batch_id'"
            )
            ->toContain(
                "'schema_version'"
            )
            ->toContain(
                "'original_filename'"
            )
            ->toContain(
                "'source_row_count'"
            )
            ->toContain(
                "'staged_row_count'"
            )
            ->toContain(
                "'rejected_row_count'"
            )
            ->toContain(
                "'domains'"
            )
            ->not
            ->toContain(
                "'source_path'"
            )
            ->not
            ->toContain(
                "'validation_snapshot'"
            );
    }
);

test(
    'show hydrates latest processing run and completed normalization summary',
    function () {
        $source =
            persistedHydrationController();

        expect($source)
            ->toContain(
                'DataTransformationBiProcessingRun::query()'
            )
            ->toContain(
                "'domain_profile_count'"
            )
            ->toContain(
                "'field_profile_count'"
            )
            ->toContain(
                "'issue_count'"
            )
            ->toContain(
                "'blocking_issue_count'"
            )
            ->toContain(
                "'warning_issue_count'"
            )
            ->toContain(
                'DataTransformationBiNormalizedRow::query()'
            )
            ->toContain(
                "'normalization_change_count'"
            );
    }
);

test(
    'hydration is exposed as a dedicated inertia prop',
    function () {
        $source =
            persistedHydrationController();

        expect($source)
            ->toContain(
                "'standard_intake_persisted_state'"
            )
            ->toContain(
                '$standardIntakePersistedState'
            );
    }
);

test(
    'vue initializes staging and processing refs from persisted state',
    function () {
        $source =
            persistedHydrationUi();

        expect($source)
            ->toContain(
                'standard_intake_persisted_state:'
            )
            ->toContain(
                'props.standard_intake_persisted_state'
            )
            ->toContain(
                '?.ingestion'
            )
            ->toContain(
                '?.profile'
            )
            ->toContain(
                '?.normalization'
            )
            ->toContain(
                '?.batch_id'
            );
    }
);

test(
    'hydration does not automatically rerun profiling or normalization',
    function () {
        $source =
            persistedHydrationUi();

        $propsPosition =
            strpos(
                $source,
                'standard_intake_persisted_state:'
            );

        $profileFunction =
            strpos(
                $source,
                'async function profileStandardIntakeBatch()'
            );

        expect($propsPosition)
            ->not
            ->toBeFalse()
            ->and($profileFunction)
            ->not
            ->toBeFalse();

        $initialization =
            substr(
                $source,
                $propsPosition,
                $profileFunction - $propsPosition
            );

        expect($initialization)
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

test(
    'controller hydration remains read only',
    function () {
        $source =
            persistedHydrationController();

        $start =
            strpos(
                $source,
                'P6_PERSISTED_DATA_BI_STATE'
            );

        $end =
            strpos(
                $source,
                'return Inertia::render(',
                $start
            );

        $surface =
            substr(
                $source,
                $start,
                $end - $start
            );

        expect($surface)
            ->not
            ->toContain('->create(')
            ->not
            ->toContain('->update(')
            ->not
            ->toContain('->delete(')
            ->not
            ->toContain('->save(')
            ->not
            ->toContain('->insert(');
    }
);
