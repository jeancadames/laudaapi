<?php

it('presents the definition creation block with user facing spanish labels', function () {
    $projectRoot = dirname(__DIR__, 3);

    $page = file_get_contents(
        $projectRoot
        . '/resources/js/pages/Admin/Transformation360/ImplementationRequests/Show.vue'
    );

    expect($page)
        ->toContain('Definición funcional')
        ->toContain('Alcance de la capacidad solicitada')
        ->toContain('capacidad solicitada.')
        ->toContain('Ninguna envía la Definición a la empresa ni')
        ->toContain('Crear borrador funcional de Definición')
        ->toContain('Preparar contenido de la Definición')
        ->toContain('Ya existe la Definición V{{ props.definition.version }}')
        ->toContain('Definición V{{ props.definition.version }} creada')
        ->toContain('{{ capability.label }}')
        ->toContain('definitionStatusLabel(props.definition.status)')
        ->toContain("draft: 'Borrador'")
        ->toContain("prepared_for_review: 'Preparada para revisión'")
        ->toContain("under_review: 'En revisión'")
        ->toContain("ready: 'Lista'")
        ->not->toContain('capability solicitada.')
        ->not->toContain('Definition al tenant')
        ->not->toContain('Crear borrador funcional de Definition')
        ->not->toContain('Preparar contenido de la Definition')
        ->not->toContain('{{ props.definition.capability_key }}')
        ->not->toContain('{{ props.definition.status }}');
});
