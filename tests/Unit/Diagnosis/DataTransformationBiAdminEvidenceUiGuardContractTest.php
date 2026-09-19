<?php

use Tests\TestCase;

uses(TestCase::class);

it('uses the dynamic source workspace instead of editable legacy BI evidence', function () {
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
            'props.capability.key'
        )
        ->toContain(
            "=== 'data_transformation_bi'"
        )
        ->toContain(
            'D17_DYNAMIC_SOURCE_WORKSPACE_UI'
        )
        ->toContain(
            'Fuentes de datos'
        )
        ->toContain(
            'Modelo objetivo LAUDA · procesamiento interno'
        )
        ->not->toContain(
            'Evidencia de insumos'
        )
        ->not->toContain(
            'Evidencia de entrega de datos'
        )
        ->not->toContain(
            'Agregar evidencia de origen'
        )
        ->not->toContain(
            'props.implementation_request.capability_key'
        );
});
