<?php

function d15IntakeV2Source(
    string $relativePath
): string {
    $path =
        dirname(__DIR__, 3)
        .'/'
        .$relativePath;

    $source =
        file_get_contents(
            $path
        );

    if (! is_string($source)) {
        throw new RuntimeException(
            "No se pudo leer {$relativePath}."
        );
    }

    return $source;
}

test(
    'd15 exposes dedicated intake v2 http routes',
    function () {
        $routes =
            d15IntakeV2Source(
                'routes/admin.php'
            );

        expect($routes)
            ->toContain(
                'D15_INTAKE_V2_HTTP_ROUTES'
            )
            ->toContain(
                'standard-intake-v2/session'
            )
            ->toContain(
                '/domains/{domain}/upload'
            )
            ->toContain(
                '/domains/{domain}/no-data'
            )
            ->toContain(
                '/domains/{domain}/carry-forward'
            )
            ->toContain(
                '/sessions/{sessionId}/resolve'
            )
            ->toContain(
                '/sessions/{sessionId}/materialize'
            )
            ->toContain(
                'AdminDataTransformationBiIntakeV2Controller'
            );
    }
);

test(
    'd15 controller delegates only to intake v2 domain services',
    function () {
        $controller =
            d15IntakeV2Source(
                'app/Http/Controllers/Admin/'
                .'AdminDataTransformationBiIntakeV2Controller.php'
            );

        expect($controller)
            ->toContain(
                'DataTransformationBiIntakeV2SessionService'
            )
            ->toContain(
                'DataTransformationBiIntakeV2DomainDeliveryService'
            )
            ->toContain(
                'DataTransformationBiIntakeV2SessionResolutionService'
            )
            ->toContain(
                'DataTransformationBiIntakeV2StagingMaterializationService'
            )
            ->toContain(
                'DataTransformationBiIntakeV2StateService'
            )
            ->toContain(
                "'max:2048'"
            )
            ->toContain(
                "'company_id'"
            )
            ->toContain(
                "'transformation_implementation_request_id'"
            )
            ->not
            ->toContain(
                'DataTransformationBiStagingProfilingService'
            )
            ->not
            ->toContain(
                'DataTransformationBiStagingNormalizationService'
            );
    }
);

test(
    'd15 v2 state projection is read only and hides private source path',
    function () {
        $state =
            d15IntakeV2Source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiIntakeV2StateService.php'
            );

        expect($state)
            ->toContain(
                'DataTransformationBiStandardIntakeSchema::domains()'
            )
            ->toContain(
                'DataTransformationBiUsableDatasetResolver'
            )
            ->toContain(
                "'usable_dataset'"
            )
            ->toContain(
                "'deliveries'"
            )
            ->toContain(
                "'can_resolve'"
            )
            ->toContain(
                "'can_materialize'"
            )
            ->not
            ->toContain(
                "'source_path'"
            )
            ->not
            ->toContain(
                '->save()'
            )
            ->not
            ->toContain(
                '->create('
            )
            ->not
            ->toContain(
                '->delete()'
            );
    }
);

test(
    'd15 show hydrates intake v2 alongside legacy persisted processing',
    function () {
        $controller =
            d15IntakeV2Source(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        expect($controller)
            ->toContain(
                'D15_INTAKE_V2_STATE'
            )
            ->toContain(
                'DataTransformationBiIntakeV2StateService::class'
            )
            ->toContain(
                "'standard_intake_v2_state'"
            )
            ->toContain(
                "'standard_intake_persisted_state'"
            );
    }
);

test(
    'd15 leaves existing profile and normalize endpoints intact',
    function () {
        $routes =
            d15IntakeV2Source(
                'routes/admin.php'
            );

        expect($routes)
            ->toContain(
                "'profileStandardIntakeBatch'"
            )
            ->toContain(
                "'normalizeStandardIntakeProcessingRun'"
            )
            ->toContain(
                "'transformation360.implementation_requests.standard_intake.profile'"
            )
            ->toContain(
                "'transformation360.implementation_requests.standard_intake.normalize'"
            );
    }
);
