<?php

it('presents definition created events with functional spanish copy', function () {
    $projectRoot = dirname(__DIR__, 3);

    $controller = file_get_contents(
        $projectRoot
        . '/app/Http/Controllers/Admin/AdminTransformationImplementationRequestController.php'
    );

    $service = file_get_contents(
        $projectRoot
        . '/app/Services/Diagnosis/TransformationImplementationRequestDefinitionService.php'
    );

    expect($controller)
        ->toContain("'definition_created' =>")
        ->toContain("'Borrador funcional creado'")
        ->toContain('eventNotesLabel(')
        ->toContain(
            "'LAUDA creó la Definition funcional inicial para la capability solicitada.'"
        )
        ->toContain(
            "'LAUDA creó el borrador funcional inicial de la Definición para la capacidad solicitada.'"
        );

    expect($service)
        ->toContain("'definition_created'")
        ->toContain(
            'LAUDA creó el borrador funcional inicial de la Definición para la capacidad solicitada.'
        )
        ->not->toContain(
            'LAUDA creó la Definition funcional inicial para la capability solicitada.'
        );
});
