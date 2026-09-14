<?php

use App\Services\Diagnosis\DataTransformationBiStandardIntakeTemplateService;
use App\Services\Diagnosis\DataTransformationBiStandardIntakeValidator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ZipArchive;

it(
    'accepts the generated standard xlsx template',
    function () {
        $templates =
            new DataTransformationBiStandardIntakeTemplateService();

        $validator =
            new DataTransformationBiStandardIntakeValidator();

        $path =
            $templates
                ->createXlsxTemporaryFile();

        try {
            $result =
                $validator->validate(
                    $path,
                    $templates
                        ->xlsxFilename()
                );

            expect($result['valid'])
                ->toBeTrue()
                ->and($result['format'])
                ->toBe('xlsx')
                ->and($result['schema_version'])
                ->toBe(1)
                ->and($result['errors'])
                ->toBe([])
                ->and(array_keys($result['domains']))
                ->toBe([
                    'customers',
                    'products',
                    'inventory',
                    'sales',
                    'accounts_receivable',
                    'suppliers',
                    'accounts_payable',
                ]);
        } finally {
            @unlink($path);
        }
    }
);

it(
    'accepts the generated standard csv zip package',
    function () {
        $templates =
            new DataTransformationBiStandardIntakeTemplateService();

        $validator =
            new DataTransformationBiStandardIntakeValidator();

        $path =
            $templates
                ->createCsvZipTemporaryFile();

        try {
            $result =
                $validator->validate(
                    $path,
                    $templates
                        ->csvZipFilename()
                );

            expect($result['valid'])
                ->toBeTrue()
                ->and($result['format'])
                ->toBe('csv_zip')
                ->and($result['schema_version'])
                ->toBe(1)
                ->and($result['errors'])
                ->toBe([])
                ->and(count($result['domains']))
                ->toBe(7);
        } finally {
            @unlink($path);
        }
    }
);

it(
    'rejects xlsx when a required domain sheet is missing',
    function () {
        $templates =
            new DataTransformationBiStandardIntakeTemplateService();

        $validator =
            new DataTransformationBiStandardIntakeValidator();

        $path =
            $templates
                ->createXlsxTemporaryFile();

        try {
            $book =
                IOFactory::load($path);

            $sheet =
                $book
                    ->getSheetByName(
                        'suppliers'
                    );

            expect($sheet)
                ->not
                ->toBeNull();

            $book->removeSheetByIndex(
                $book->getIndex(
                    $sheet
                )
            );

            (new Xlsx($book))
                ->save($path);

            $book
                ->disconnectWorksheets();

            $result =
                $validator->validate(
                    $path,
                    'broken.xlsx'
                );

            expect($result['valid'])
                ->toBeFalse()
                ->and(
                    implode(
                        ' ',
                        $result['errors']
                    )
                )
                ->toContain(
                    'Falta la hoja requerida suppliers'
                )
                ->and(
                    $result['domains']['suppliers']['present']
                )
                ->toBeFalse();
        } finally {
            @unlink($path);
        }
    }
);

it(
    'rejects xlsx when a required canonical column is missing',
    function () {
        $templates =
            new DataTransformationBiStandardIntakeTemplateService();

        $validator =
            new DataTransformationBiStandardIntakeValidator();

        $path =
            $templates
                ->createXlsxTemporaryFile();

        try {
            $book =
                IOFactory::load($path);

            $book
                ->getSheetByName(
                    'customers'
                )
                ->setCellValue(
                    'A1',
                    'wrong_customer_id'
                );

            (new Xlsx($book))
                ->save($path);

            $book
                ->disconnectWorksheets();

            $result =
                $validator->validate(
                    $path,
                    'broken.xlsx'
                );

            expect($result['valid'])
                ->toBeFalse()
                ->and(
                    $result['domains']
                        ['customers']
                        ['missing_required']
                )
                ->toContain(
                    'customer_id'
                )
                ->and(
                    $result['domains']
                        ['customers']
                        ['unknown_columns']
                )
                ->toContain(
                    'wrong_customer_id'
                );
        } finally {
            @unlink($path);
        }
    }
);

it(
    'rejects unsafe paths inside a csv zip package',
    function () {
        $templates =
            new DataTransformationBiStandardIntakeTemplateService();

        $validator =
            new DataTransformationBiStandardIntakeValidator();

        $path =
            $templates
                ->createCsvZipTemporaryFile();

        try {
            $zip =
                new ZipArchive();

            expect(
                $zip->open($path)
            )->toBeTrue();

            $zip->addFromString(
                '../unsafe.csv',
                "unsafe\n"
            );

            $zip->close();

            $result =
                $validator->validate(
                    $path,
                    'unsafe.zip'
                );

            expect($result['valid'])
                ->toBeFalse()
                ->and(
                    implode(
                        ' ',
                        $result['errors']
                    )
                )
                ->toContain(
                    'Entrada ZIP insegura'
                );
        } finally {
            @unlink($path);
        }
    }
);

it(
    'rejects unsupported intake file extensions',
    function () {
        $validator =
            new DataTransformationBiStandardIntakeValidator();

        $path =
            tempnam(
                sys_get_temp_dir(),
                'lauda-intake-test-'
            );

        try {
            file_put_contents(
                $path,
                'test'
            );

            $result =
                $validator->validate(
                    $path,
                    'intake.csv'
                );

            expect($result['valid'])
                ->toBeFalse()
                ->and($result['format'])
                ->toBe('unsupported');
        } finally {
            @unlink($path);
        }
    }
);
