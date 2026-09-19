<?php

use Tests\TestCase;

uses(TestCase::class);

test(
    'admin routes expose authenticated request scoped standard intake downloads',
    function () {
        $source =
            file_get_contents(
                base_path(
                    'routes/admin.php'
                )
            );

        expect($source)
            ->toContain(
                '/standard-intake-template/xlsx'
            )
            ->toContain(
                '/standard-intake-template/csv'
            )
            ->toContain(
                'downloadStandardIntakeXlsx'
            )
            ->toContain(
                'downloadStandardIntakeCsv'
            )
            ->toContain(
                'transformation360.implementation_requests.standard_intake_template.xlsx'
            )
            ->toContain(
                'transformation360.implementation_requests.standard_intake_template.csv'
            );
    }
);

test(
    'admin request controller protects and downloads generated templates',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Http/Controllers/Admin/AdminTransformationImplementationRequestController.php'
                )
            );

        expect($source)
            ->toContain(
                'downloadStandardIntakeXlsx'
            )
            ->toContain(
                'downloadStandardIntakeCsv'
            )
            ->toContain(
                'createXlsxTemporaryFile'
            )
            ->toContain(
                'createCsvZipTemporaryFile'
            )
            ->toContain(
                '$this->authorizeAdmin'
            )
            ->toContain(
                'deleteFileAfterSend'
            );
    }
);

test(
    'data bi admin ui keeps canonical references beside the dynamic source workspace',
    function () {
        $source =
            file_get_contents(
                resource_path(
                    'js/pages/Admin/Transformation360/ImplementationRequests/Show.vue'
                )
            );

        expect($source)
            ->toContain(
                'D17_DYNAMIC_SOURCE_WORKSPACE_UI'
            )
            ->toContain(
                'Fuentes de datos'
            )
            ->toContain(
                '+ Agregar fuente'
            )
            ->toContain(
                'Referencia canónica Excel'
            )
            ->toContain(
                'Referencia canónica CSV'
            )
            ->toContain(
                '/standard-intake-template/xlsx'
            )
            ->toContain(
                '/standard-intake-template/csv'
            )
            ->toContain(
                'Modelo objetivo LAUDA · procesamiento interno'
            )
            ->not->toContain(
                'Evidencia de insumos'
            )
            ->not->toContain(
                'Evidencia de entrega de datos'
            )
            ->not->toContain(
                'input-evidence-${index}'
            );
    }
);
