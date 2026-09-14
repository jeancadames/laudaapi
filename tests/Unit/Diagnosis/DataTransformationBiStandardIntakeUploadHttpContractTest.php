<?php

use App\Http\Controllers\Admin\AdminTransformationImplementationRequestController;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use App\Services\Diagnosis\DataTransformationBiStandardIntakeTemplateService;
use App\Services\Diagnosis\DataTransformationBiStandardIntakeValidationService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(\Tests\TestCase::class);

function qa2I15D9Request(
    UploadedFile $file,
    string $role = 'admin'
): Request {
    $request =
        Request::create(
            '/admin/transformation-360/implementation-requests/1/standard-intake/validate',
            'POST',
            [],
            [],
            [
                'file' =>
                    $file,
            ],
            [
                'HTTP_ACCEPT' =>
                    'application/json',
            ]
        );

    $user =
        new User();

    $user->setAttribute(
        'role',
        $role
    );

    $request->setUserResolver(
        static fn (): User =>
            $user
    );

    return $request;
}

function qa2I15D9BiRequestModel(): TransformationImplementationRequest
{
    return new TransformationImplementationRequest([
        'capability_key' =>
            'data_transformation_bi',
    ]);
}

it(
    'validates a temporary standard intake xlsx through the admin controller',
    function () {
        $templates =
            new DataTransformationBiStandardIntakeTemplateService();

        $path =
            $templates
                ->createXlsxTemporaryFile();

        try {
            $upload =
                new UploadedFile(
                    $path,
                    'standard-intake-v1.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true
                );

            $response =
                (new AdminTransformationImplementationRequestController())
                    ->validateStandardIntakeUpload(
                        qa2I15D9Request(
                            $upload
                        ),
                        qa2I15D9BiRequestModel(),
                        new DataTransformationBiStandardIntakeValidationService()
                    );

            $payload =
                $response
                    ->getData(
                        true
                    );

            expect(
                $response->getStatusCode()
            )
                ->toBe(200)
                ->and($payload['ok'])
                ->toBeTrue()
                ->and(
                    $payload['file']
                        ['extension']
                )
                ->toBe('xlsx')
                ->and(
                    $payload['validation']
                        ['valid']
                )
                ->toBeTrue()
                ->and(
                    $payload['validation']
                        ['content']
                        ['executed']
                )
                ->toBeTrue();
        } finally {
            @unlink($path);
        }
    }
);

it(
    'rejects unsupported raw csv uploads at the package endpoint',
    function () {
        $path =
            tempnam(
                sys_get_temp_dir(),
                'lauda-intake-http-'
            );

        if ($path === false) {
            throw new RuntimeException(
                'Could not create temporary file.'
            );
        }

        try {
            file_put_contents(
                $path,
                "customer_id,customer_name\nC001,Cliente\n"
            );

            $upload =
                new UploadedFile(
                    $path,
                    'customers.csv',
                    'text/csv',
                    null,
                    true
                );

            $response =
                (new AdminTransformationImplementationRequestController())
                    ->validateStandardIntakeUpload(
                        qa2I15D9Request(
                            $upload
                        ),
                        qa2I15D9BiRequestModel(),
                        new DataTransformationBiStandardIntakeValidationService()
                    );

            $payload =
                $response
                    ->getData(
                        true
                    );

            expect(
                $response->getStatusCode()
            )
                ->toBe(422)
                ->and($payload['ok'])
                ->toBeFalse()
                ->and(
                    $payload['errors']
                )
                ->toHaveKey('file');
        } finally {
            @unlink($path);
        }
    }
);

it(
    'rejects a text file renamed as xlsx',
    function () {
        $path =
            tempnam(
                sys_get_temp_dir(),
                'lauda-intake-spoof-'
            );

        if ($path === false) {
            throw new RuntimeException(
                'Could not create temporary file.'
            );
        }

        try {
            file_put_contents(
                $path,
                'plain text pretending to be excel'
            );

            $upload =
                new UploadedFile(
                    $path,
                    'fake.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true
                );

            $response =
                (new AdminTransformationImplementationRequestController())
                    ->validateStandardIntakeUpload(
                        qa2I15D9Request(
                            $upload
                        ),
                        qa2I15D9BiRequestModel(),
                        new DataTransformationBiStandardIntakeValidationService()
                    );

            $payload =
                $response
                    ->getData(
                        true
                    );

            expect(
                $response->getStatusCode()
            )
                ->toBe(422)
                ->and($payload['ok'])
                ->toBeFalse()
                ->and(
                    $payload['errors']
                )
                ->toHaveKey('file');
        } finally {
            @unlink($path);
        }
    }
);

it(
    'rejects files larger than the current two megabyte php upload contract',
    function () {
        $path =
            tempnam(
                sys_get_temp_dir(),
                'lauda-intake-large-'
            );

        if ($path === false) {
            throw new RuntimeException(
                'Could not create temporary file.'
            );
        }

        try {
            file_put_contents(
                $path,
                str_repeat(
                    'A',
                    2097153
                )
            );

            $upload =
                new UploadedFile(
                    $path,
                    'oversized.xlsx',
                    'application/octet-stream',
                    null,
                    true
                );

            $response =
                (new AdminTransformationImplementationRequestController())
                    ->validateStandardIntakeUpload(
                        qa2I15D9Request(
                            $upload
                        ),
                        qa2I15D9BiRequestModel(),
                        new DataTransformationBiStandardIntakeValidationService()
                    );

            $payload =
                $response
                    ->getData(
                        true
                    );

            expect(
                $response->getStatusCode()
            )
                ->toBe(422)
                ->and($payload['ok'])
                ->toBeFalse()
                ->and(
                    $payload['errors']
                )
                ->toHaveKey('file');
        } finally {
            @unlink($path);
        }
    }
);

it(
    'rejects standard intake validation outside data transformation bi',
    function () {
        $templates =
            new DataTransformationBiStandardIntakeTemplateService();

        $path =
            $templates
                ->createXlsxTemporaryFile();

        try {
            $upload =
                new UploadedFile(
                    $path,
                    'standard-intake.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true
                );

            $model =
                new TransformationImplementationRequest([
                    'capability_key' =>
                        'branding_identity',
                ]);

            try {
                (new AdminTransformationImplementationRequestController())
                    ->validateStandardIntakeUpload(
                        qa2I15D9Request(
                            $upload
                        ),
                        $model,
                        new DataTransformationBiStandardIntakeValidationService()
                    );

                throw new RuntimeException(
                    'Expected 404 was not thrown.'
                );
            } catch (HttpException $exception) {
                expect(
                    $exception->getStatusCode()
                )->toBe(404);
            }
        } finally {
            @unlink($path);
        }
    }
);

it(
    'keeps the intake upload endpoint admin only',
    function () {
        $templates =
            new DataTransformationBiStandardIntakeTemplateService();

        $path =
            $templates
                ->createXlsxTemporaryFile();

        try {
            $upload =
                new UploadedFile(
                    $path,
                    'standard-intake.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true
                );

            try {
                (new AdminTransformationImplementationRequestController())
                    ->validateStandardIntakeUpload(
                        qa2I15D9Request(
                            $upload,
                            'subscriber'
                        ),
                        qa2I15D9BiRequestModel(),
                        new DataTransformationBiStandardIntakeValidationService()
                    );

                throw new RuntimeException(
                    'Expected 403 was not thrown.'
                );
            } catch (HttpException $exception) {
                expect(
                    $exception->getStatusCode()
                )->toBe(403);
            }
        } finally {
            @unlink($path);
        }
    }
);

it(
    'keeps temporary intake validation free of persistence calls',
    function () {
        $controller =
            file_get_contents(
                app_path(
                    'Http/Controllers/Admin/AdminTransformationImplementationRequestController.php'
                )
            );

        expect($controller)
            ->not
            ->toBeFalse();

        $start =
            strpos(
                $controller,
                'public function validateStandardIntakeUpload('
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
                'getRealPath()'
            )
            ->toContain(
                "'max:2048'"
            )
            ->toContain(
                "'data_transformation_bi'"
            )
            ->not
            ->toContain(
                'Storage::'
            )
            ->not
            ->toContain(
                '->store('
            )
            ->not
            ->toContain(
                '->save('
            )
            ->not
            ->toContain(
                'DB::'
            );
    }
);
