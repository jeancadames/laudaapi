<?php

use App\Services\Diagnosis\DataTransformationBiStandardIntakeSchema;
use App\Services\Diagnosis\DataTransformationBiStandardIntakeTemplateService;
use App\Services\Diagnosis\DataTransformationBiStandardIntakeValidationService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function qa2I15D5CanonicalRows(): array
{
    return [
        'customers' => [
            [
                'customer_id' => 'C001',
                'customer_name' => 'Cliente Uno',
            ],
        ],

        'products' => [
            [
                'product_id' => 'P001',
                'product_name' => 'Producto Uno',
            ],
        ],

        'inventory' => [
            [
                'snapshot_date' => '2026-09-14',
                'product_id' => 'P001',
                'quantity_on_hand' => '10',
            ],
        ],

        'sales' => [
            [
                'document_id' => 'V001',
                'line_number' => '1',
                'document_date' => '2026-09-14',
                'customer_id' => 'C001',
                'product_id' => 'P001',
                'quantity' => '2',
                'unit_price' => '100.00',
            ],
        ],

        'accounts_receivable' => [
            [
                'document_id' => 'AR001',
                'customer_id' => 'C001',
                'issue_date' => '2026-09-14',
                'due_date' => '2026-10-14',
                'original_amount' => '100.00',
                'outstanding_amount' => '50.00',
            ],
        ],

        'suppliers' => [
            [
                'supplier_id' => 'S001',
                'supplier_name' => 'Suplidor Uno',
            ],
        ],

        'accounts_payable' => [
            [
                'document_id' => 'AP001',
                'supplier_id' => 'S001',
                'issue_date' => '2026-09-14',
                'due_date' => '2026-10-14',
                'original_amount' => '200.00',
                'outstanding_amount' => '125.00',
            ],
        ],
    ];
}

function qa2I15D5PopulateXlsx(
    string $path,
    array $rowsByDomain
): void {
    $book =
        IOFactory::load(
            $path
        );

    try {
        foreach (
            $rowsByDomain
            as $domain => $rows
        ) {
            $sheet =
                $book->getSheetByName(
                    $domain
                );

            if ($sheet === null) {
                throw new RuntimeException(
                    "Missing sheet {$domain}"
                );
            }

            $highestColumn =
                $sheet->getHighestColumn();

            $headers =
                $sheet->rangeToArray(
                    "A1:{$highestColumn}1",
                    null,
                    true,
                    false
                )[0] ?? [];

            foreach (
                array_values($rows)
                as $rowOffset => $row
            ) {
                $excelRow =
                    $rowOffset + 2;

                foreach (
                    $headers
                    as $columnOffset => $header
                ) {
                    $header =
                        trim(
                            (string) $header
                        );

                    if (
                        $header === ''
                        || ! array_key_exists(
                            $header,
                            $row
                        )
                    ) {
                        continue;
                    }

                    $cell =
                        Coordinate::stringFromColumnIndex(
                            $columnOffset + 1
                        )
                        .$excelRow;

                    $sheet->setCellValue(
                        $cell,
                        $row[$header]
                    );
                }
            }
        }

        (new Xlsx($book))
            ->save(
                $path
            );
    } finally {
        $book
            ->disconnectWorksheets();
    }
}

function qa2I15D5Csv(
    array $headers,
    array $rows
): string {
    $stream =
        fopen(
            'php://temp',
            'w+'
        );

    if ($stream === false) {
        throw new RuntimeException(
            'Could not create temporary CSV stream.'
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

        foreach ($rows as $row) {
            $values = [];

            foreach ($headers as $header) {
                $values[] =
                    $row[$header]
                    ?? '';
            }

            fputcsv(
                $stream,
                $values,
                ',',
                '"',
                '\\'
            );
        }

        rewind(
            $stream
        );

        $contents =
            stream_get_contents(
                $stream
            );

        if ($contents === false) {
            throw new RuntimeException(
                'Could not read temporary CSV stream.'
            );
        }

        return $contents;
    } finally {
        fclose(
            $stream
        );
    }
}

function qa2I15D5PopulateCsvZip(
    string $path,
    array $rowsByDomain
): void {
    $zip =
        new \ZipArchive();

    if (
        $zip->open($path)
        !== true
    ) {
        throw new RuntimeException(
            'Could not open test ZIP.'
        );
    }

    try {
        $schema =
            DataTransformationBiStandardIntakeSchema
                ::domains();

        foreach (
            $rowsByDomain
            as $domain => $rows
        ) {
            $headers =
                array_map(
                    static fn (
                        array $field
                    ): string =>
                        (string) $field['name'],
                    $schema[$domain]['fields']
                );

            $ok =
                $zip->addFromString(
                    "{$domain}.csv",
                    qa2I15D5Csv(
                        $headers,
                        $rows
                    )
                );

            if (! $ok) {
                throw new RuntimeException(
                    "Could not write {$domain}.csv"
                );
            }
        }
    } finally {
        $zip->close();
    }
}

it(
    'executes row validation for the generated blank xlsx template',
    function () {
        $templates =
            new DataTransformationBiStandardIntakeTemplateService();

        $service =
            new DataTransformationBiStandardIntakeValidationService();

        $path =
            $templates
                ->createXlsxTemporaryFile();

        try {
            $result =
                $service->validate(
                    $path,
                    $templates
                        ->xlsxFilename()
                );

            expect($result['valid'])
                ->toBeTrue()
                ->and(
                    $result['content']
                        ['executed']
                )
                ->toBeTrue()
                ->and(
                    $result['content']
                        ['domains']
                        ['customers']
                        ['row_count']
                )
                ->toBe(0);
        } finally {
            @unlink($path);
        }
    }
);

it(
    'reads populated xlsx rows and validates them end to end',
    function () {
        $templates =
            new DataTransformationBiStandardIntakeTemplateService();

        $service =
            new DataTransformationBiStandardIntakeValidationService();

        $path =
            $templates
                ->createXlsxTemporaryFile();

        try {
            qa2I15D5PopulateXlsx(
                $path,
                qa2I15D5CanonicalRows()
            );

            $result =
                $service->validate(
                    $path,
                    'standard-intake.xlsx'
                );

            expect($result['valid'])
                ->toBeTrue()
                ->and(
                    $result['content']
                        ['executed']
                )
                ->toBeTrue()
                ->and(
                    $result['content']
                        ['domains']
                        ['sales']
                        ['row_count']
                )
                ->toBe(1)
                ->and(
                    $result['content']
                        ['domains']
                        ['accounts_payable']
                        ['row_count']
                )
                ->toBe(1);
        } finally {
            @unlink($path);
        }
    }
);

it(
    'normalizes real excel serial dates and datetimes before row validation',
    function () {
        $templates =
            new DataTransformationBiStandardIntakeTemplateService();

        $service =
            new DataTransformationBiStandardIntakeValidationService();

        $path =
            $templates
                ->createXlsxTemporaryFile();

        try {
            qa2I15D5PopulateXlsx(
                $path,
                qa2I15D5CanonicalRows()
            );

            $book =
                IOFactory::load(
                    $path
                );

            try {
                $targets = [
                    [
                        'domain' => 'customers',
                        'field' => 'created_at',
                        'date' =>
                            new DateTimeImmutable(
                                '2026-09-14 13:25:30'
                            ),
                        'format' =>
                            'yyyy-mm-dd hh:mm:ss',
                    ],
                    [
                        'domain' => 'inventory',
                        'field' => 'snapshot_date',
                        'date' =>
                            new DateTimeImmutable(
                                '2026-09-14 00:00:00'
                            ),
                        'format' =>
                            'yyyy-mm-dd',
                    ],
                ];

                foreach ($targets as $target) {
                    $sheet =
                        $book->getSheetByName(
                            $target['domain']
                        );

                    if ($sheet === null) {
                        throw new RuntimeException(
                            "Missing sheet {$target['domain']}."
                        );
                    }

                    $highestColumn =
                        $sheet->getHighestColumn();

                    $headers =
                        $sheet->rangeToArray(
                            "A1:{$highestColumn}1",
                            null,
                            true,
                            false
                        )[0] ?? [];

                    $column = null;

                    foreach (
                        $headers
                        as $index => $header
                    ) {
                        if (
                            trim(
                                (string) $header
                            )
                            === $target['field']
                        ) {
                            $column =
                                $index + 1;

                            break;
                        }
                    }

                    if ($column === null) {
                        throw new RuntimeException(
                            "Missing field {$target['field']}."
                        );
                    }

                    $sheet->setCellValue(
                        [$column, 2],
                        ExcelDate::PHPToExcel(
                            $target['date']
                        )
                    );

                    $sheet
                        ->getCell([
                            $column,
                            2,
                        ])
                        ->getStyle()
                        ->getNumberFormat()
                        ->setFormatCode(
                            $target['format']
                        );
                }

                (new Xlsx($book))
                    ->save(
                        $path
                    );
            } finally {
                $book
                    ->disconnectWorksheets();
            }

            $result =
                $service->validate(
                    $path,
                    'real-excel-dates.xlsx'
                );

            expect($result['valid'])
                ->toBeTrue()
                ->and(
                    $result['content']
                        ['executed']
                )
                ->toBeTrue()
                ->and(
                    $result['errors']
                )
                ->toBe([]);
        } finally {
            @unlink($path);
        }
    }
);

it(
    'reports row relationship errors read from xlsx',
    function () {
        $templates =
            new DataTransformationBiStandardIntakeTemplateService();

        $service =
            new DataTransformationBiStandardIntakeValidationService();

        $rows =
            qa2I15D5CanonicalRows();

        $rows['sales'][0]
            ['customer_id'] =
            'C404';

        $path =
            $templates
                ->createXlsxTemporaryFile();

        try {
            qa2I15D5PopulateXlsx(
                $path,
                $rows
            );

            $result =
                $service->validate(
                    $path,
                    'broken.xlsx'
                );

            expect($result['valid'])
                ->toBeFalse()
                ->and(
                    $result['content']
                        ['executed']
                )
                ->toBeTrue()
                ->and(
                    implode(
                        ' ',
                        $result['errors']
                    )
                )
                ->toContain(
                    'customer_id referencia un valor inexistente'
                );
        } finally {
            @unlink($path);
        }
    }
);

it(
    'reads populated csv zip rows and validates them end to end',
    function () {
        $templates =
            new DataTransformationBiStandardIntakeTemplateService();

        $service =
            new DataTransformationBiStandardIntakeValidationService();

        $path =
            $templates
                ->createCsvZipTemporaryFile();

        try {
            qa2I15D5PopulateCsvZip(
                $path,
                qa2I15D5CanonicalRows()
            );

            $result =
                $service->validate(
                    $path,
                    'standard-intake.zip'
                );

            expect($result['valid'])
                ->toBeTrue()
                ->and($result['format'])
                ->toBe('csv_zip')
                ->and(
                    $result['content']
                        ['executed']
                )
                ->toBeTrue()
                ->and(
                    $result['content']
                        ['domains']
                        ['inventory']
                        ['row_count']
                )
                ->toBe(1)
                ->and(
                    $result['content']
                        ['domains']
                        ['suppliers']
                        ['row_count']
                )
                ->toBe(1);
        } finally {
            @unlink($path);
        }
    }
);

it(
    'reports row errors read from csv zip',
    function () {
        $templates =
            new DataTransformationBiStandardIntakeTemplateService();

        $service =
            new DataTransformationBiStandardIntakeValidationService();

        $rows =
            qa2I15D5CanonicalRows();

        $rows['suppliers'][0]
            ['supplier_name'] =
            '';

        $path =
            $templates
                ->createCsvZipTemporaryFile();

        try {
            qa2I15D5PopulateCsvZip(
                $path,
                $rows
            );

            $result =
                $service->validate(
                    $path,
                    'broken.zip'
                );

            expect($result['valid'])
                ->toBeFalse()
                ->and(
                    $result['content']
                        ['executed']
                )
                ->toBeTrue()
                ->and(
                    implode(
                        ' ',
                        $result['errors']
                    )
                )
                ->toContain(
                    'supplier_name es obligatorio'
                );
        } finally {
            @unlink($path);
        }
    }
);

it(
    'skips content validation when xlsx structure is invalid',
    function () {
        $templates =
            new DataTransformationBiStandardIntakeTemplateService();

        $service =
            new DataTransformationBiStandardIntakeValidationService();

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
                    'products'
                );

            if ($sheet === null) {
                throw new RuntimeException(
                    'products sheet missing before test mutation.'
                );
            }

            $book->removeSheetByIndex(
                $book->getIndex(
                    $sheet
                )
            );

            (new Xlsx($book))
                ->save(
                    $path
                );

            $book
                ->disconnectWorksheets();

            $result =
                $service->validate(
                    $path,
                    'structurally-broken.xlsx'
                );

            expect($result['valid'])
                ->toBeFalse()
                ->and(
                    $result['content']
                        ['executed']
                )
                ->toBeFalse()
                ->and(
                    implode(
                        ' ',
                        $result['errors']
                    )
                )
                ->toContain(
                    'Falta la hoja requerida products'
                );
        } finally {
            @unlink($path);
        }
    }
);
