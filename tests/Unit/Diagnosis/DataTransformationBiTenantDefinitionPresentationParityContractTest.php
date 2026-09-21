<?php

use Tests\TestCase;

uses(TestCase::class);

test(
    'tenant definition projection exposes the reviewed functional scope',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Http/Controllers/'
                    .'AppHubDataTransformationBiController.php'
                )
            );

        expect($source)
            ->toContain(
                "'purpose' =>"
            )
            ->toContain(
                "data_get(\n"
                ."                        \$scope,\n"
                ."                        'purpose'"
            )
            ->toContain(
                "'includes' =>"
            )
            ->toContain(
                "'excludes' =>"
            )
            ->toContain(
                "'phases' =>"
            )
            ->not->toContain(
                "'source_snapshot' =>"
            );
    }
);

test(
    'tenant reviews definition purpose instead of generic phase objective',
    function () {
        $source =
            file_get_contents(
                resource_path(
                    'js/pages/App/'
                    .'DataTransformationBi.vue'
                )
            );

        expect($source)
            ->toContain(
                'tenantDefinitionReview'
            )
            ->toContain(
                '.scope'
            )
            ->toContain(
                '.purpose'
            )
            ->toContain(
                'No incluye esta etapa'
            )
            ->toContain(
                '.scope'
            )
            ->toContain(
                '.excludes'
            );
    }
);

test(
    'tenant sees the same general data bi responsibility duties reviewed by lauda',
    function () {
        $source =
            file_get_contents(
                resource_path(
                    'js/pages/App/'
                    .'DataTransformationBi.vue'
                )
            );

        foreach ([
            'Identificar y registrar las fuentes',
            'Definir el responsable de cada fuente',
            'Ejecutar o coordinar localmente la',
            'Entregar CSV/XLSX y atender',
            'Inspeccionar y perfilar los datos.',
            'Definir el mapeo canónico aplicable.',
            'Normalizar, relacionar y materializar',
            'Documentar calidad, reglas y resultados',
        ] as $expected) {
            expect($source)
                ->toContain($expected);
        }
    }
);

test(
    'tenant functional decision copy is business facing',
    function () {
        $source =
            file_get_contents(
                resource_path(
                    'js/pages/App/'
                    .'DataTransformationBi.vue'
                )
            );

        foreach ([
            'Definición funcional presentada',
            '¿Esta definición representa lo acordado?',
            'Acordar esta definición no activa el',
            "'Acordar esta definición'",
            'esta versión de la definición.',
            'esta definición..."',
            'espacio de trabajo de fuentes.',
            'espacio de trabajo activo.',
        ] as $expected) {
            expect($source)
                ->toContain($expected);
        }

        foreach ([
            'Definition funcional presentada',
            '¿Esta Definition representa lo acordado?',
            'Acordar esta Definition',
            'esta versión de la Definition.',
            'esta Definition..."',
            'Prepara primero el workspace de fuentes.',
            'workspace activo.',
        ] as $forbidden) {
            expect($source)
                ->not
                ->toContain($forbidden);
        }
    }
);

test(
    'historical capability wording is normalized and future roadmap copy is current',
    function () {
        $ui =
            file_get_contents(
                resource_path(
                    'js/pages/App/'
                    .'DataTransformationBi.vue'
                )
            );

        $generator =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'DiagnosisDetailedRoadmapGenerator.php'
                )
            );

        expect($ui)
            ->toContain(
                "replaceAll(\n"
                ."            'pricing',\n"
                ."            'precios'"
            )
            ->toContain(
                "replaceAll(\n"
                ."            'scores explicables',\n"
                ."            'indicadores explicables'"
            );

        expect($generator)
            ->toContain(
                'CRM, precios, inventario'
            )
            ->toContain(
                'mediante indicadores explicables y señales internas/externas.'
            )
            ->not
            ->toContain(
                'CRM, pricing, inventario'
            )
            ->not
            ->toContain(
                'mediante scores explicables y señales internas/externas.'
            );
    }
);
