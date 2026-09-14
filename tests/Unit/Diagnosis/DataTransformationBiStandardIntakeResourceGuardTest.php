<?php

use App\Services\Diagnosis\DataTransformationBiStandardIntakeResourceGuard;
use App\Services\Diagnosis\DataTransformationBiStandardIntakeTemplateService;

it(
    'accepts official generated xlsx and csv zip packages',
    function () {
        $templates =
            new DataTransformationBiStandardIntakeTemplateService();

        $guard =
            new DataTransformationBiStandardIntakeResourceGuard();

        $xlsx =
            $templates
                ->createXlsxTemporaryFile();

        $zip =
            $templates
                ->createCsvZipTemporaryFile();

        try {
            expect(
                $guard->validate(
                    $xlsx,
                    'standard.xlsx'
                )['valid']
            )
                ->toBeTrue()
                ->and(
                    $guard->validate(
                        $zip,
                        'standard.zip'
                    )['valid']
                )
                ->toBeTrue();
        } finally {
            @unlink($xlsx);
            @unlink($zip);
        }
    }
);

it(
    'rejects csv zip packages with excessive entry counts',
    function () {
        $path =
            tempnam(
                sys_get_temp_dir(),
                'lauda-entry-cap-'
            );

        if ($path === false) {
            throw new RuntimeException(
                'Could not create temporary ZIP.'
            );
        }

        $zip =
            new \ZipArchive();

        if (
            $zip->open(
                $path,
                \ZipArchive::OVERWRITE
            ) !== true
        ) {
            throw new RuntimeException(
                'Could not open temporary ZIP.'
            );
        }

        try {
            for (
                $i = 0;
                $i
                    < DataTransformationBiStandardIntakeResourceGuard
                        ::MAX_CSV_ZIP_ENTRIES + 1;
                $i++
            ) {
                $zip->addFromString(
                    "entry-{$i}.csv",
                    "id\n{$i}\n"
                );
            }
        } finally {
            $zip->close();
        }

        try {
            $result =
                (new DataTransformationBiStandardIntakeResourceGuard())
                    ->validate(
                        $path,
                        'too-many.zip'
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
                    'demasiadas entradas'
                );
        } finally {
            @unlink($path);
        }
    }
);

it(
    'rejects suspicious archive compression ratios',
    function () {
        $path =
            tempnam(
                sys_get_temp_dir(),
                'lauda-ratio-cap-'
            );

        if ($path === false) {
            throw new RuntimeException(
                'Could not create temporary ZIP.'
            );
        }

        $zip =
            new \ZipArchive();

        if (
            $zip->open(
                $path,
                \ZipArchive::OVERWRITE
            ) !== true
        ) {
            throw new RuntimeException(
                'Could not open temporary ZIP.'
            );
        }

        try {
            $zip->addFromString(
                'highly-compressible.csv',
                str_repeat(
                    'A',
                    2 * 1024 * 1024
                )
            );
        } finally {
            $zip->close();
        }

        try {
            $result =
                (new DataTransformationBiStandardIntakeResourceGuard())
                    ->validate(
                        $path,
                        'ratio.zip'
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
                    'relación de compresión'
                );
        } finally {
            @unlink($path);
        }
    }
);
