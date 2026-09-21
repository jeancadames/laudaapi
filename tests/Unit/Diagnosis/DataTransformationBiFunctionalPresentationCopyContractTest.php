<?php

use Tests\TestCase;

uses(TestCase::class);

test(
    'admin data bi functional review uses business facing copy',
    function () {
        $source =
            file_get_contents(
                resource_path(
                    'js/pages/Admin/Transformation360/'
                    .'ImplementationRequests/Show.vue'
                )
            );

        $normalized =
            preg_replace(
                '/\s+/',
                ' ',
                $source
            );

        expect($normalized)
            ->toContain(
                'Confirmar definición funcional'
            )
            ->toContain(
                'Guardar esta revisión no marca la definición como lista y no la envía a la empresa.'
            )
            ->toContain(
                'La gestión concreta de las fuentes ocurre posteriormente en el espacio de trabajo de fuentes de datos.'
            )
            ->toContain(
                'La definición funcional confirma el reparto general entre la Empresa y LAUDA.'
            )
            ->toContain(
                'Fuentes de datos'
            )
            ->toContain(
                'Las fuentes no se administran desde esta definición funcional.'
            )
            ->not->toContain(
                'Confirmar Definition funcional'
            )
            ->not->toContain(
                'Fuentes dinámicas'
            )
            ->not->toContain(
                'workspace dinámico'
            );
    }
);

test(
    'historical data bi functional copy is normalized for review',
    function () {
        $source =
            file_get_contents(
                resource_path(
                    'js/pages/Admin/Transformation360/'
                    .'ImplementationRequests/Show.vue'
                )
            );

        expect($source)
            ->toContain(
                'DATA_BI_FUNCTIONAL_PRESENTATION_COPY_MAP'
            )
            ->toContain(
                'normalizeDataBiFunctionalRecordList('
            )
            ->toContain(
                'Mecanismo acordado para que la empresa extraiga y entregue las fuentes requeridas en CSV/XLSX.'
            )
            ->toContain(
                'Preparación de datos reutilizables para BI, CRM, precios, inventario, compras, CxC, planificación y alertas.'
            );
    }
);

test(
    'functional lifecycle presentation avoids internal english jargon',
    function () {
        $source =
            file_get_contents(
                resource_path(
                    'js/pages/Admin/Transformation360/'
                    .'ImplementationRequests/Show.vue'
                )
            );

        foreach ([
            'Preparar nueva versión de la Definition',
            'Definition acordada por la empresa',
            'Definition ready',
            'Acuerdo del tenant',
            'Finalizar Definition funcional',
            'Enviar Definition a la empresa',
            'Contexto adicional para la revisión de esta Definition...',
        ] as $forbidden) {
            expect($source)
                ->not
                ->toContain($forbidden);
        }
    }
);
