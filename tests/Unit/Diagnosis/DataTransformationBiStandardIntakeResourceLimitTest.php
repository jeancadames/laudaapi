<?php

use App\Services\Diagnosis\DataTransformationBiStandardIntakeFileReader;
use App\Services\Diagnosis\DataTransformationBiStandardIntakeSchema;
use App\Services\Diagnosis\DataTransformationBiStandardIntakeTemplateService;
use App\Services\Diagnosis\DataTransformationBiStandardIntakeValidationService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it(
    'rejects an xlsx domain whose highest row exceeds the validation cap',
    function () {
        $templates =
            new DataTransformationBiStandardIntakeTemplateService();

        $path =
            $templates
                ->createXlsxTemporaryFile();

        try {
            $book =
                IOFactory::load(
                    $path
                );

            $sheet =
                $book->getSheetByName(
                    'customers'
                );

            if ($sheet === null) {
                throw new RuntimeException(
                    'customers sheet missing.'
                );
            }

            $row =
                DataTransformationBiStandardIntakeFileReader
                    ::MAX_ROWS_PER_DOMAIN
                + 2;

            $sheet->setCellValue(
                "A{$row}",
                'CAP'
            );

            (new Xlsx($book))
                ->save(
                    $path
                );

            $book
                ->disconnectWorksheets();

            $result =
                (new DataTransformationBiStandardIntakeValidationService())
                    ->validate(
                        $path,
                        'row-cap.xlsx'
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
                    'supera el máximo de '
                    .DataTransformationBiStandardIntakeFileReader
                        ::MAX_ROWS_PER_DOMAIN
                    .' filas'
                );
        } finally {
            @unlink($path);
        }
    }
);

it(
    'rejects a csv domain after the configured row validation cap',
    function () {
        $templates =
            new DataTransformationBiStandardIntakeTemplateService();

        $path =
            $templates
                ->createCsvZipTemporaryFile();

        try {
            $zip =
                new \ZipArchive();

            if (
                $zip->open($path)
                !== true
            ) {
                throw new RuntimeException(
                    'Could not open template ZIP.'
                );
            }

            try {
                $fields =
                    DataTransformationBiStandardIntakeSchema
                        ::domains()['customers']['fields'];

                $headers =
                    array_column(
                        $fields,
                        'name'
                    );

                $stream =
                    fopen(
                        'php://temp',
                        'w+'
                    );

                if ($stream === false) {
                    throw new RuntimeException(
                        'Could not create CSV stream.'
                    );
                }

                try {
                    fputcsv(
                        $stream,
                        $headers,
                        ',',
                        '"',
                        '\\'
                    );

                    for (
                        $i = 1;
                        $i
                            <= DataTransformationBiStandardIntakeFileReader
                                ::MAX_ROWS_PER_DOMAIN + 1;
                        $i++
                    ) {
                        $row = [];

                        foreach (
                            $headers
                            as $header
                        ) {
                            $row[] =
                                match ($header) {
                                    'customer_id' =>
                                        "C{$i}",

                                    'customer_name' =>
                                        "Cliente {$i}",

                                    default =>
                                        '',
                                };
                        }

                        fputcsv(
                            $stream,
                            $row,
                            ',',
                            '"',
                            '\\'
                        );
                    }

                    rewind(
                        $stream
                    );

                    $csv =
                        stream_get_contents(
                            $stream
                        );

                    if ($csv === false) {
                        throw new RuntimeException(
                            'Could not read CSV stream.'
                        );
                    }
                } finally {
                    fclose(
                        $stream
                    );
                }

                $zip->addFromString(
                    'customers.csv',
                    $csv
                );
            } finally {
                $zip->close();
            }

            $result =
                (new DataTransformationBiStandardIntakeValidationService())
                    ->validate(
                        $path,
                        'row-cap.zip'
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
                    'supera el máximo de '
                    .DataTransformationBiStandardIntakeFileReader
                        ::MAX_ROWS_PER_DOMAIN
                    .' filas'
                );
        } finally {
            @unlink($path);
        }
    }
);
