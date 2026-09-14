<?php

use Tests\TestCase;

uses(TestCase::class);

function qa2I16D3ControllerSource(): string
{
    $source =
        file_get_contents(
            app_path(
                'Http/Controllers/Admin/AdminTransformationImplementationRequestController.php'
            )
        );

    if ($source === false) {
        throw new RuntimeException(
            'Could not read admin controller.'
        );
    }

    return $source;
}

function qa2I16D3RoutesSource(): string
{
    $source =
        file_get_contents(
            base_path(
                'routes/admin.php'
            )
        );

    if ($source === false) {
        throw new RuntimeException(
            'Could not read admin routes.'
        );
    }

    return $source;
}

test(
    'admin route exposes a separate explicit standard intake ingestion action',
    function () {
        $routes =
            qa2I16D3RoutesSource();

        expect($routes)
            ->toContain(
                '/standard-intake/validate'
            )
            ->toContain(
                '/standard-intake/ingest'
            )
            ->toContain(
                "'validateStandardIntakeUpload'"
            )
            ->toContain(
                "'ingestStandardIntakeUpload'"
            )
            ->toContain(
                'transformation360.implementation_requests.standard_intake.ingest'
            );
    }
);

test(
    'ingestion http action stays admin and data bi capability scoped',
    function () {
        $controller =
            qa2I16D3ControllerSource();

        $start =
            strpos(
                $controller,
                'public function ingestStandardIntakeUpload('
            );

        $end =
            strpos(
                $controller,
                'public function show(',
                $start
            );

        expect($start)
            ->not
            ->toBeFalse()
            ->and($end)
            ->not
            ->toBeFalse();

        $method =
            substr(
                $controller,
                $start,
                $end - $start
            );

        expect($method)
            ->toContain(
                '$this->authorizeAdmin('
            )
            ->toContain(
                "'data_transformation_bi'"
            )
            ->toContain(
                "'max:2048'"
            )
            ->toContain(
                '$request->user()'
            );
    }
);

test(
    'ingestion http action delegates persistence only to controlled ingestion service',
    function () {
        $controller =
            qa2I16D3ControllerSource();

        $start =
            strpos(
                $controller,
                'public function ingestStandardIntakeUpload('
            );

        $end =
            strpos(
                $controller,
                'public function show(',
                $start
            );

        $method =
            substr(
                $controller,
                $start,
                $end - $start
            );

        expect($method)
            ->toContain(
                'DataTransformationBiStandardIntakeIngestionService'
            )
            ->toContain(
                '->ingest('
            )
            ->not
            ->toContain(
                'Storage::'
            )
            ->not
            ->toContain(
                'DB::'
            )
            ->not
            ->toContain(
                'DataTransformationBiIntakeBatch::create'
            );
    }
);

test(
    'ingestion http response distinguishes newly created and reused batches',
    function () {
        $controller =
            qa2I16D3ControllerSource();

        $start =
            strpos(
                $controller,
                'public function ingestStandardIntakeUpload('
            );

        $end =
            strpos(
                $controller,
                'public function show(',
                $start
            );

        $method =
            substr(
                $controller,
                $start,
                $end - $start
            );

        expect($method)
            ->toContain(
                "(\$result['reused'] ?? false)"
            )
            ->toContain(
                '? 200'
            )
            ->toContain(
                ': 201'
            )
            ->toContain(
                "'ingestion'"
            );
    }
);

test(
    'ingestion http action returns validation errors without converting them into server errors',
    function () {
        $controller =
            qa2I16D3ControllerSource();

        $start =
            strpos(
                $controller,
                'public function ingestStandardIntakeUpload('
            );

        $end =
            strpos(
                $controller,
                'public function show(',
                $start
            );

        $method =
            substr(
                $controller,
                $start,
                $end - $start
            );

        expect($method)
            ->toContain(
                'ValidationException'
            )
            ->toContain(
                '$exception->errors()'
            )
            ->toContain(
                '422'
            );
    }
);

test(
    'temporary validation endpoint remains persistence free after adding ingestion',
    function () {
        $controller =
            qa2I16D3ControllerSource();

        $start =
            strpos(
                $controller,
                'public function validateStandardIntakeUpload('
            );

        $end =
            strpos(
                $controller,
                'public function ingestStandardIntakeUpload(',
                $start
            );

        expect($start)
            ->not
            ->toBeFalse()
            ->and($end)
            ->not
            ->toBeFalse();

        $method =
            substr(
                $controller,
                $start,
                $end - $start
            );

        expect($method)
            ->toContain(
                'getRealPath()'
            )
            ->toContain(
                'DataTransformationBiStandardIntakeValidationService'
            )
            ->not
            ->toContain(
                'IngestionService'
            )
            ->not
            ->toContain(
                'Storage::'
            )
            ->not
            ->toContain(
                'DB::'
            )
            ->not
            ->toContain(
                '->store('
            );
    }
);

test(
    'ingestion endpoint does not mutate implementation lifecycle directly',
    function () {
        $controller =
            qa2I16D3ControllerSource();

        $start =
            strpos(
                $controller,
                'public function ingestStandardIntakeUpload('
            );

        $end =
            strpos(
                $controller,
                'public function show(',
                $start
            );

        $method =
            substr(
                $controller,
                $start,
                $end - $start
            );

        expect($method)
            ->not
            ->toContain(
                'ready_for_commercial_at'
            )
            ->not
            ->toContain(
                'definition_agreed_at'
            )
            ->not
            ->toContain(
                'TransformationCapabilityActivation'
            )
            ->not
            ->toContain(
                'TransformationImplementationExecution'
            )
            ->not
            ->toContain(
                '->update(['
            )
            ->not
            ->toContain(
                '->save()'
            );
    }
);
