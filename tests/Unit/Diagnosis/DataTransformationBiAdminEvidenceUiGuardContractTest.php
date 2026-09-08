<?php

uses(Tests\TestCase::class);

it('renders BI validation evidence from the canonical capability prop', function () {
    $path =
        resource_path(
            'js/pages/Admin/Transformation360/ImplementationRequests/Show.vue'
        );

    $source =
        file_get_contents(
            $path
        );

    expect($source)
        ->toContain(
            "props.capability.key"
        )
        ->toContain(
            "=== 'data_transformation_bi'"
        )
        ->toContain(
            'Evidencia de insumos'
        )
        ->toContain(
            'Evidencia de accesos'
        )
        ->not->toContain(
            'props.implementation_request.capability_key'
        );
});
