<?php

use App\Services\Diagnosis\TransformationProfessionalCapabilityCatalog;

it('defines canonical capability-specific dependencies for data transformation bi', function () {
    $bi = TransformationProfessionalCapabilityCatalog::get(
        'data_transformation_bi'
    );

    expect($bi)
        ->toBeArray()
        ->toHaveKey('dependencies')
        ->and($bi['dependencies'])
        ->toHaveCount(5)
        ->toContain(
            'Fuentes de datos necesarias para el alcance identificadas y disponibles para evaluación.'
        )
        ->toContain(
            'Acceso autorizado o mecanismo acordado de extracción o entrega para las fuentes requeridas.'
        )
        ->toContain(
            'Responsables o propietarios de datos identificados para validar definiciones, calidad y reglas de negocio.'
        )
        ->toContain(
            'Históricos y granularidad requeridos disponibles según los indicadores y análisis acordados.'
        )
        ->toContain(
            'Reglas de negocio, catálogos y criterios de calidad necesarios para interpretar, relacionar y validar los datos.'
        );

    expect($bi)
        ->toHaveKey('activation_policy', 'implementation_only')
        ->toHaveKey('service_key', null)
        ->toHaveKey('subscription_candidate', false);

    expect($bi['dependencies'])
        ->not->toContain('OPS-01')
        ->not->toContain('TEC-01')
        ->not->toContain('COM-01');
});

it('keeps request scoped dependency extraction capability specific', function () {
    $projectRoot = dirname(__DIR__, 3);

    $generator = file_get_contents(
        $projectRoot
        . '/app/Services/Diagnosis/TransformationImplementationDefinitionAutogenerator.php'
    );

    expect($generator)
        ->toContain("\$catalog[\n                    'dependencies'\n                ] ?? []")
        ->toContain(
            'Deliberadamente NO usamos dependencias generales'
        )
        ->toContain(
            'Revisar y confirmar el alcance de la capacidad solicitada.'
        )
        ->toContain(
            'Validar los insumos y accesos necesarios para esta capacidad.'
        )
        ->not->toContain(
            'Revisar y confirmar el alcance de la capability solicitada.'
        )
        ->not->toContain(
            'Validar los insumos y accesos necesarios para esta capability.'
        );
});
