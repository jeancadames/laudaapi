<?php

use Tests\TestCase;

uses(TestCase::class);

test(
    'bi admin ui uses dynamic source assets as the data intake surface',
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
                'Modelo objetivo LAUDA · procesamiento interno'
            );
    }
);

test(
    'legacy evidence editors and manual data validation confirmations are not rendered',
    function () {
        $source =
            file_get_contents(
                resource_path(
                    'js/pages/Admin/Transformation360/ImplementationRequests/Show.vue'
                )
            );

        expect($source)
            ->not->toContain(
                'Evidencia de insumos'
            )
            ->not->toContain(
                'Agregar evidencia de origen'
            )
            ->not->toContain(
                'Evidencia de entrega de datos'
            )
            ->not->toContain(
                'v-model="humanReviewForm.readiness.inputs_validated"'
            )
            ->not->toContain(
                'v-model="humanReviewForm.readiness.accesses_validated"'
            )
            ->not->toContain(
                'function addInputValidationEvidence(): void'
            )
            ->not->toContain(
                'function addAccessValidationEvidence(): void'
            );
    }
);

test(
    'data bi review keeps machine readiness server owned',
    function () {
        $controller =
            file_get_contents(
                app_path(
                    'Http/Controllers/Admin/'
                    .'AdminTransformationImplementationRequestDefinitionActionController.php'
                )
            );

        $review =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'TransformationImplementationRequestDefinitionReviewService.php'
                )
            );

        expect($controller)
            ->toContain(
                "'readiness.scope_confirmed'"
            )
            ->toContain(
                "'readiness.deliverables_confirmed'"
            )
            ->toContain(
                "'readiness.dependencies_confirmed'"
            )
            ->toContain(
                "'readiness.responsibilities_confirmed'"
            )
            ->not->toContain(
                "'readiness.inputs_validated'"
            )
            ->not->toContain(
                "'readiness.accesses_validated'"
            )
            ->not->toContain(
                "'readiness.validation_evidence'"
            );

        expect($review)
            ->toContain(
                'DataTransformationBiSourceReadinessService'
            )
            ->toContain(
                '$sourceReadiness'
            )
            ->toContain(
                "'inputs_validated'"
            )
            ->toContain(
                "'accesses_validated'"
            )
            ->toContain(
                '$historicalValidationEvidence'
            )
            ->not->toContain(
                'assertSupportsConfirmations'
            );
    }
);
