<?php

use App\Services\Diagnosis\DataTransformationBiDomainIntakeValidationService;
use App\Services\Diagnosis\DataTransformationBiStandardIntakeSchema;
use App\Services\Diagnosis\DataTransformationBiStandardIntakeTemplateService;
use PhpOffice\PhpSpreadsheet\IOFactory;

function d15eDomainFieldNames(
    string $domain
): array {
    $definition =
        DataTransformationBiStandardIntakeSchema
            ::domains()[$domain];

    return array_values(
        array_map(
            static fn (array $field): string =>
                (string) $field['name'],
            $definition['fields']
        )
    );
}

test(
    'd15 e creates one canonical csv template per domain',
    function () {
        $templates =
            new DataTransformationBiStandardIntakeTemplateService();

        $validator =
            new DataTransformationBiDomainIntakeValidationService();

        foreach (
            DataTransformationBiStandardIntakeSchema
                ::domainKeys()
            as $domain
        ) {
            $path =
                $templates
                    ->createDomainCsvTemporaryFile(
                        $domain
                    );

            try {
                expect(is_file($path))
                    ->toBeTrue();

                $handle =
                    fopen(
                        $path,
                        'rb'
                    );

                expect($handle)
                    ->not
                    ->toBeFalse();

                $headers =
                    fgetcsv(
                        $handle,
                        0,
                        ',',
                        '"',
                        ''
                    );

                fclose($handle);

                expect($headers)
                    ->toBe(
                        d15eDomainFieldNames(
                            $domain
                        )
                    );

                $validation =
                    $validator
                        ->validate(
                            $domain,
                            $path,
                            $templates
                                ->domainCsvFilename(
                                    $domain
                                )
                        );

                expect(
                    $validation['valid']
                )->toBeTrue();
            } finally {
                @unlink($path);
            }
        }
    }
);

test(
    'd15 e creates one canonical single sheet xlsx per domain',
    function () {
        $templates =
            new DataTransformationBiStandardIntakeTemplateService();

        $validator =
            new DataTransformationBiDomainIntakeValidationService();

        foreach (
            DataTransformationBiStandardIntakeSchema
                ::domainKeys()
            as $domain
        ) {
            $path =
                $templates
                    ->createDomainXlsxTemporaryFile(
                        $domain
                    );

            try {
                expect(is_file($path))
                    ->toBeTrue();

                $book =
                    IOFactory::load(
                        $path
                    );

                try {
                    expect(
                        $book->getSheetNames()
                    )->toBe([
                        $domain,
                    ]);

                    $sheet =
                        $book
                            ->getActiveSheet();

                    $highestColumn =
                        $sheet
                            ->getHighestColumn();

                    $headers =
                        $sheet
                            ->rangeToArray(
                                "A1:{$highestColumn}1",
                                null,
                                true,
                                true,
                                false
                            )[0];

                    expect($headers)
                        ->toBe(
                            d15eDomainFieldNames(
                                $domain
                            )
                        );
                } finally {
                    $book
                        ->disconnectWorksheets();
                }

                $validation =
                    $validator
                        ->validate(
                            $domain,
                            $path,
                            $templates
                                ->domainXlsxFilename(
                                    $domain
                                )
                        );

                expect(
                    $validation['valid']
                )->toBeTrue();
            } finally {
                @unlink($path);
            }
        }
    }
);

test(
    'd15 e filenames are domain and schema version specific',
    function () {
        $templates =
            new DataTransformationBiStandardIntakeTemplateService();

        expect(
            $templates
                ->domainCsvFilename(
                    'customers'
                )
        )->toBe(
            'lauda-standard-data-intake-customers-v1.csv'
        );

        expect(
            $templates
                ->domainXlsxFilename(
                    'suppliers'
                )
        )->toBe(
            'lauda-standard-data-intake-suppliers-v1.xlsx'
        );
    }
);

test(
    'd15 e rejects unsupported template domains',
    function () {
        $templates =
            new DataTransformationBiStandardIntakeTemplateService();

        expect(
            fn () =>
                $templates
                    ->createDomainCsvTemporaryFile(
                        'unknown_domain'
                    )
        )->toThrow(
            InvalidArgumentException::class
        );
    }
);

test(
    'd15 e exposes domain template routes and ui links',
    function () {
        $routes =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/routes/admin.php'
            );

        $controller =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Http/Controllers/Admin/'
                .'AdminDataTransformationBiIntakeV2Controller.php'
            );

        $ui =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/resources/js/pages/Admin/'
                .'Transformation360/ImplementationRequests/'
                .'Show.vue'
            );

        expect($routes)
            ->toContain(
                'standard-intake-v2/templates/{domain}/csv'
            )
            ->toContain(
                'standard-intake-v2/templates/{domain}/xlsx'
            );

        expect($controller)
            ->toContain(
                'downloadDomainCsvTemplate'
            )
            ->toContain(
                'downloadDomainXlsxTemplate'
            );

        expect($ui)
            ->toContain(
                'Plantilla CSV'
            )
            ->toContain(
                'Plantilla XLSX'
            )
            ->toContain(
                '/templates/${encodeURIComponent(domain.key)}/csv'
            )
            ->toContain(
                '/templates/${encodeURIComponent(domain.key)}/xlsx'
            );
    }
);
