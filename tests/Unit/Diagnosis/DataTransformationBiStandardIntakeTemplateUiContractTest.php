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
    'data bi admin ui exposes both template download actions',
    function () {
        $source =
            file_get_contents(
                resource_path(
                    'js/pages/Admin/Transformation360/ImplementationRequests/Show.vue'
                )
            );

        expect($source)
            ->toContain(
                'Descargar plantilla Excel'
            )
            ->toContain(
                'Descargar paquete CSV'
            )
            ->toContain(
                'Agregar otra fuente de datos'
            )
            ->toContain(
                'cursor-pointer'
            )
            ->toContain(
                'disabled:cursor-not-allowed'
            )
            ->toContain(
                'nextTick'
            )
            ->toContain(
                'scrollIntoView'
            )
            ->toContain(
                ':id="`input-evidence-${index}`"'
            )
            ->toContain(
                '/standard-intake-template/xlsx'
            )
            ->toContain(
                '/standard-intake-template/csv'
            );
    }
);
