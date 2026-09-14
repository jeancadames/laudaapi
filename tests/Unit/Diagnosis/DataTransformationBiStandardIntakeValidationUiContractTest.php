<?php

use Tests\TestCase;

uses(TestCase::class);

test(
    'data bi admin ui can validate a temporary xlsx or lauda csv zip',
    function () {
        $source =
            file_get_contents(
                resource_path(
                    'js/pages/Admin/Transformation360/ImplementationRequests/Show.vue'
                )
            );

        expect($source)
            ->not
            ->toBeFalse()
            ->toContain(
                'Validar archivo estándar'
            )
            ->toContain(
                'Validar archivo'
            )
            ->toContain(
                'standardIntakeValidationUrl'
            )
            ->toContain(
                '/standard-intake/validate'
            )
            ->toContain(
                'new FormData()'
            )
            ->toContain(
                "formData.append("
            )
            ->toContain(
                "method: 'POST'"
            )
            ->toContain(
                "credentials: 'same-origin'"
            )
            ->toContain(
                "Accept: 'application/json'"
            )
            ->toContain(
                "'X-Requested-With'"
            )
            ->toContain(
                'standardIntakeCsrfHeaders'
            )
            ->toContain(
                'fetch('
            );
    }
);

test(
    'data bi validation ui enforces the browser side intake boundary',
    function () {
        $source =
            file_get_contents(
                resource_path(
                    'js/pages/Admin/Transformation360/ImplementationRequests/Show.vue'
                )
            );

        expect($source)
            ->not
            ->toBeFalse()
            ->toContain(
                'STANDARD_INTAKE_MAX_BYTES = 2 * 1024 * 1024'
            )
            ->toContain(
                "accept=\".xlsx,.zip"
            )
            ->toContain(
                "'xlsx'"
            )
            ->toContain(
                "'zip'"
            )
            ->toContain(
                'El archivo supera el límite actual de 2 MB.'
            )
            ->toContain(
                'Los CSV individuales'
            )
            ->toContain(
                'El archivo se procesa temporalmente y no'
            )
            ->toContain(
                'se conserva.'
            );
    }
);

test(
    'data bi validation ui renders pass fail and domain reports',
    function () {
        $source =
            file_get_contents(
                resource_path(
                    'js/pages/Admin/Transformation360/ImplementationRequests/Show.vue'
                )
            );

        expect($source)
            ->not
            ->toBeFalse()
            ->toContain(
                'PASS · Archivo válido'
            )
            ->toContain(
                'FAIL · Requiere correcciones'
            )
            ->toContain(
                'Errores generales'
            )
            ->toContain(
                'Advertencias generales'
            )
            ->toContain(
                'Resultado por dominio'
            )
            ->toContain(
                'standardIntakeDomainEntries'
            )
            ->toContain(
                'standardIntakeDomainWarnings'
            )
            ->toContain(
                'duplicate_keys'
            )
            ->toContain(
                'relation_errors'
            )
            ->toContain(
                'Sin incidencias detectadas.'
            );
    }
);

test(
    'data bi validation ui does not mutate definition or persist uploads',
    function () {
        $source =
            file_get_contents(
                resource_path(
                    'js/pages/Admin/Transformation360/ImplementationRequests/Show.vue'
                )
            );

        $start =
            strpos(
                $source,
                'const STANDARD_INTAKE_MAX_BYTES'
            );

        $end =
            strpos(
                $source,
                'function assessmentStatusLabel(',
                $start
            );

        expect($start)
            ->not
            ->toBeFalse()
            ->and($end)
            ->not
            ->toBeFalse();

        $client =
            substr(
                $source,
                $start,
                $end - $start
            );

        expect($client)
            ->toContain(
                'FormData'
            )
            ->toContain(
                'fetch'
            )
            ->not
            ->toContain(
                'humanReviewForm.'
            )
            ->not
            ->toContain(
                'router.post'
            )
            ->not
            ->toContain(
                'router.patch'
            );
    }
);
