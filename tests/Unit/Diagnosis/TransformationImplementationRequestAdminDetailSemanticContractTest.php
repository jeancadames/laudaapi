<?php

it('presents implementation request administrative detail without internal stage terminology', function () {
    $projectRoot = dirname(__DIR__, 3);

    $page = file_get_contents(
        $projectRoot
        . '/resources/js/pages/Admin/Transformation360/ImplementationRequests/Show.vue'
    );

    expect($page)
        ->toContain('assessmentStatusLabel(assessment.status)')
        ->toContain("reviewed: 'Revisado'")
        ->toContain('phaseDisplayLabel(phase.sequence, phase.name)')
        ->toContain(
            'Los cambios de estado de esta etapa corresponden a revisión'
        )
        ->toContain(
            'funcional. Por sí solos no crean una Definición ni'
        )
        ->toContain(
            'inician implementación, contratación, facturación o'
        )
        ->toContain(
            'Esta solicitud no tiene acciones administrativas disponibles'
        )
        ->toContain('Recibir e iniciar revisión')
        ->toContain('Iniciar preparación de definición')
        ->not->toContain("· {{ assessment.status ?? '—' }}")
        ->not->toContain('Esa vinculación pertenece a F5.')
        ->not->toContain('habilitada en F4C.')
        ->not->toContain(
            '“Preparación de definición” solo cambia el estado'
        );
});
