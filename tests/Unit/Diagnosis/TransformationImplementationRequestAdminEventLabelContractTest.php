<?php

it('maps implementation request events to user facing transition labels', function () {
    $projectRoot = dirname(__DIR__, 3);

    $controller = file_get_contents(
        $projectRoot
        . '/app/Http/Controllers/Admin/AdminTransformationImplementationRequestController.php'
    );

    expect($controller)
        ->toContain("'status_transition'")
        ->not->toContain("'status_transitioned'")
        ->toContain('statusTransitionEventLabel')
        ->toContain("'Revisión iniciada por LAUDA'")
        ->toContain("'Preparación de definición iniciada'")
        ->toContain("'Definición enviada a revisión de la empresa'")
        ->toContain("'Ajustes solicitados por la empresa'")
        ->toContain("'Preparación de ajustes iniciada'")
        ->toContain("'Definición acordada por la empresa'")
        ->toContain("'Solicitud lista para etapa comercial'")
        ->toContain("'Solicitud cancelada'")
        ->toContain("'Estado actualizado'")
        ->toContain("'Solicitud creada'")
        ->toContain("'Responsable asignado'")
        ->toContain("'Acuerdo de Definición registrado'")
        ->toContain(
            "'Definición finalizada funcionalmente por LAUDA'"
        )
        ->not->toContain(
            "str_replace(\n                    '_',\n                    ' ',\n                    \$eventType"
        );
});
