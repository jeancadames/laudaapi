<?php

use Tests\TestCase;

uses(TestCase::class);

test(
    'bi admin ui exposes file based standard intake',
    function () {
        $source =
            file_get_contents(
                resource_path(
                    'js/pages/Admin/Transformation360/ImplementationRequests/Show.vue'
                )
            );

        expect($source)
            ->toContain(
                'Sistema de origen (informativo)'
            )
            ->toContain('item.source_role')
            ->toContain('item.delivery_format')
            ->toContain(
                'item.extraction_assistance_required'
            )
            ->toContain('Rol de los datos')
            ->toContain(
                'Formato de entrega a LAUDA'
            )
            ->toContain(
                'Asistencia de extracción'
            )
            ->toContain(
                'archivos CSV/XLSX'
            )
            ->toContain('CSV')
            ->toContain('Excel (.xlsx)');

        expect($source)
            ->not
            ->toContain(
                'v-model="item.source_type"'
            );

        expect($source)
            ->not
            ->toContain('Tipo de fuente');
    }
);

test(
    'standard intake ui treats access as file delivery mechanism',
    function () {
        $source =
            file_get_contents(
                resource_path(
                    'js/pages/Admin/Transformation360/ImplementationRequests/Show.vue'
                )
            );

        expect($source)
            ->toContain(
                'Evidencia de entrega de datos'
            )
            ->toContain(
                'Mecanismo de entrega'
            )
            ->toContain(
                'no requiere'
            )
            ->toContain(
                'conexión directa al sistema fuente'
            )
            ->toContain(
                'Agregar entrega'
            )
            ->toContain(
                'Entrega autorizada'
            )
            ->toContain(
                'Entrega verificada'
            )
            ->toContain(
                'Entrega de datos validada'
            );
    }
);

test(
    'human review keeps source type only for legacy compatibility',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Http/Controllers/Admin/AdminTransformationImplementationRequestDefinitionActionController.php'
                )
            );

        expect($source)
            ->toContain(
                'readiness.validation_evidence.inputs.*.source_type'
            )
            ->toContain(
                'Compatibilidad histórica solamente.'
            )
            ->toContain(
                'readiness.validation_evidence.inputs.*.source_role'
            )
            ->toContain(
                'readiness.validation_evidence.inputs.*.delivery_format'
            )
            ->toContain(
                'readiness.validation_evidence.inputs.*.extraction_assistance_required'
            )
            ->toContain(
                'in:primary,historical,complementary,derived'
            )
            ->toContain(
                'in:csv,xlsx'
            );
    }
);
