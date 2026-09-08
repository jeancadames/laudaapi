<?php

it('supports explicit repreparation only inside the generate action', function () {
    $root = dirname(__DIR__, 3);

    $action = file_get_contents(
        $root
        . '/app/Http/Controllers/Admin/AdminTransformationImplementationRequestDefinitionActionController.php'
    );

    $vue = file_get_contents(
        $root
        . '/resources/js/pages/Admin/Transformation360/ImplementationRequests/Show.vue'
    );

    $generateStart = strpos(
        $action,
        'public function generate('
    );

    $reviewStart = strpos(
        $action,
        'public function review(',
        $generateStart
    );

    expect($generateStart)->not->toBeFalse();
    expect($reviewStart)->not->toBeFalse();

    $generateMethod = substr(
        $action,
        $generateStart,
        $reviewStart - $generateStart
    );

    expect($generateMethod)
        ->toContain("\$request->boolean(")
        ->toContain("'reprepare'")
        ->toContain('$contentPrepared')
        ->toContain('&& ! $reprepare')
        ->toContain('&& ! $contentPrepared')
        ->toContain(
            'TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION'
        )
        ->toContain(
            'TransformationImplementationDefinition::STATUS_DRAFT'
        )
        ->toContain(
            '$autogenerator->generate('
        )
        ->toContain(
            'preparado nuevamente para revisión de LAUDA.'
        )
        ->not->toContain(
            'createRevision('
        )
        ->not->toContain(
            'readyForCommercial'
        );

    expect($vue)
        ->toContain(
            'function generateImplementationDefinition(reprepare = false): void'
        )
        ->toContain(
            '? { reprepare: true }'
        )
        ->toContain(
            '@click="generateImplementationDefinition(true)"'
        )
        ->toContain(
            'Volver a preparar contenido'
        )
        ->toContain(
            "props.definition.status === 'draft'"
        )
        ->toContain(
            "props.implementation_request.status === 'definition_preparation'"
        );
});

it('keeps the ordinary second generate post idempotent', function () {
    $root = dirname(__DIR__, 3);

    $action = file_get_contents(
        $root
        . '/app/Http/Controllers/Admin/AdminTransformationImplementationRequestDefinitionActionController.php'
    );

    $generateStart = strpos(
        $action,
        'public function generate('
    );

    $reviewStart = strpos(
        $action,
        'public function review(',
        $generateStart
    );

    $generateMethod = substr(
        $action,
        $generateStart,
        $reviewStart - $generateStart
    );

    expect($generateMethod)
        ->toContain('$contentPrepared')
        ->toContain('&& ! $reprepare')
        ->toContain(
            'El contenido funcional de esta Definición ya está preparado.'
        );
});
