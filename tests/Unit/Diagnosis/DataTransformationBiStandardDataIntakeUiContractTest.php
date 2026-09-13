<?php

use Tests\TestCase;

uses(TestCase::class);

test(
    'bi admin ui exposes standard multiorigin intake',
    function () {
        $source =
            file_get_contents(
                resource_path(
                    'js/pages/Admin/Transformation360/ImplementationRequests/Show.vue'
                )
            );

        expect($source)
            ->toContain('item.source_type')
            ->toContain('item.source_role')
            ->toContain('item.delivery_format')
            ->toContain(
                'item.extraction_assistance_required'
            )
            ->toContain('Tipo de fuente')
            ->toContain('Rol de la fuente')
            ->toContain('Formato de entrega a LAUDA')
            ->toContain(
                'Requiere asistencia de extracción'
            )
            ->toContain(
                'Evidencia de entrega / acceso'
            )
            ->toContain('CSV o XLSX');
    }
);

test(
    'standard intake does not require direct source connection',
    function () {
        $source =
            file_get_contents(
                resource_path(
                    'js/pages/Admin/Transformation360/ImplementationRequests/Show.vue'
                )
            );

        expect($source)
            ->toContain(
                'no es obligatorio conectarse directamente'
            )
            ->toContain(
                'necesidad funcional separada'
            );
    }
);

test(
    'human review http accepts standard intake metadata',
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
                'readiness.validation_evidence.inputs.*.source_role'
            )
            ->toContain(
                'readiness.validation_evidence.inputs.*.delivery_format'
            )
            ->toContain(
                'readiness.validation_evidence.inputs.*.extraction_assistance_required'
            )
            ->toContain(
                'in:sql_server,mysql,postgresql,dbf,quickbooks,excel,csv,api,other'
            )
            ->toContain(
                'in:primary,historical,complementary,derived'
            )
            ->toContain('in:csv,xlsx');
    }
);
