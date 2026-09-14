<?php

use App\Services\Diagnosis\DataTransformationBiStandardIntakeSchema;
use App\Services\Diagnosis\DataTransformationBiStandardIntakeTemplateService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use ZipArchive;

test(
    'xlsx template contains readme and all canonical domain sheets',
    function () {
        $service =
            new DataTransformationBiStandardIntakeTemplateService();

        $path =
            $service
                ->createXlsxTemporaryFile();

        try {
            expect(
                is_file($path)
            )->toBeTrue();

            expect(
                filesize($path)
            )->toBeGreaterThan(0);

            $spreadsheet =
                IOFactory::load(
                    $path
                );

            expect(
                $spreadsheet->getSheetNames()
            )->toBe(
                [
                    'README',
                    ...DataTransformationBiStandardIntakeSchema::domainKeys(),
                ]
            );

            expect(
                $spreadsheet
                    ->getSheetByName('customers')
                    ?->getCell('A1')
                    ->getValue()
            )->toBe(
                'customer_id'
            );

            expect(
                $spreadsheet
                    ->getSheetByName('products')
                    ?->getCell('A1')
                    ->getValue()
            )->toBe(
                'product_id'
            );

            expect(
                $spreadsheet
                    ->getSheetByName('sales')
                    ?->getCell('A1')
                    ->getValue()
            )->toBe(
                'document_id'
            );

            expect(
                $spreadsheet
                    ->getSheetByName('README')
                    ?->getCell('B2')
                    ->getValue()
            )->toBe(
                DataTransformationBiStandardIntakeSchema::VERSION
            );

            $spreadsheet
                ->disconnectWorksheets();
        } finally {
            @unlink($path);
        }
    }
);

test(
    'csv package contains readme and one header-only csv per domain',
    function () {
        $service =
            new DataTransformationBiStandardIntakeTemplateService();

        $path =
            $service
                ->createCsvZipTemporaryFile();

        $zip =
            new ZipArchive();

        try {
            expect(
                is_file($path)
            )->toBeTrue();

            expect(
                filesize($path)
            )->toBeGreaterThan(0);

            expect(
                $zip->open($path)
            )->toBeTrue();

            $expected =
                [
                    'README.csv',
                    ...array_map(
                        static fn (string $domain): string =>
                            "{$domain}.csv",
                        DataTransformationBiStandardIntakeSchema::domainKeys()
                    ),
                ];

            $actual = [];

            for (
                $index = 0;
                $index < $zip->numFiles;
                $index++
            ) {
                $actual[] =
                    $zip->getNameIndex(
                        $index
                    );
            }

            sort($expected);
            sort($actual);

            expect($actual)
                ->toBe($expected);

            $customers =
                (string)
                    $zip->getFromName(
                        'customers.csv'
                    );

            $customers =
                preg_replace(
                    '/^\xEF\xBB\xBF/',
                    '',
                    $customers
                );

            expect($customers)
                ->toStartWith(
                    'customer_id,customer_name'
                );

            $sales =
                (string)
                    $zip->getFromName(
                        'sales.csv'
                    );

            $sales =
                preg_replace(
                    '/^\xEF\xBB\xBF/',
                    '',
                    $sales
                );

            expect($sales)
                ->toStartWith(
                    'document_id,line_number,document_date,customer_id,product_id,quantity,unit_price'
                );

            $readme =
                (string)
                    $zip->getFromName(
                        'README.csv'
                    );

            expect($readme)
                ->toContain(
                    'schema_version'
                )
                ->toContain(
                    'customer_id'
                )
                ->toContain(
                    'supplier_id'
                );
        } finally {
            $zip->close();

            @unlink($path);
        }
    }
);

test(
    'generated filenames are explicitly versioned',
    function () {
        $service =
            new DataTransformationBiStandardIntakeTemplateService();

        expect(
            $service->xlsxFilename()
        )->toBe(
            'lauda-standard-data-intake-v1.xlsx'
        );

        expect(
            $service->csvZipFilename()
        )->toBe(
            'lauda-standard-data-intake-csv-v1.zip'
        );
    }
);
