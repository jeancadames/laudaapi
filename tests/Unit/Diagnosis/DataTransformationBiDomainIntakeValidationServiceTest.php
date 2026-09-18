<?php

use App\Services\Diagnosis\DataTransformationBiDomainIntakeValidationService;
use App\Services\Diagnosis\DataTransformationBiStandardIntakeRowValidator;
use App\Services\Diagnosis\DataTransformationBiStandardIntakeSchema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(Tests\TestCase::class);

function d5DomainHeaders(
    string $domain
): array {
    return array_values(
        array_map(
            static fn (array $field): string =>
                (string) $field['name'],
            DataTransformationBiStandardIntakeSchema
                ::domains()[$domain]['fields']
        )
    );
}

function d5AccountsReceivableRow(
    string $documentId = 'AR-001',
    string $customerId = 'CUSTOMER-NOT-LOADED-YET'
): array {
    return [
        'document_id' =>
            $documentId,

        'customer_id' =>
            $customerId,

        'issue_date' =>
            '2026-09-01',

        'due_date' =>
            '2026-09-30',

        'original_amount' =>
            '1000.50',

        'outstanding_amount' =>
            '750.25',

        'document_type' =>
            'invoice',

        'currency' =>
            'DOP',

        'status' =>
            'open',
    ];
}

function d5Csv(
    string $domain,
    array $rows,
    ?array $headers = null
): string {
    $path =
        tempnam(
            sys_get_temp_dir(),
            'dtbi-d5-csv-'
        );

    if ($path === false) {
        throw new RuntimeException(
            'No se pudo crear CSV temporal.'
        );
    }

    $stream =
        fopen(
            $path,
            'wb'
        );

    if ($stream === false) {
        throw new RuntimeException(
            'No se pudo abrir CSV temporal.'
        );
    }

    $headers =
        $headers
        ?? d5DomainHeaders(
            $domain
        );

    try {
        fputcsv(
            $stream,
            $headers,
            ',',
            '"',
            ''
        );

        foreach ($rows as $row) {
            fputcsv(
                $stream,
                array_map(
                    static fn (string $header): mixed =>
                        $row[$header]
                        ?? null,
                    $headers
                ),
                ',',
                '"',
                ''
            );
        }
    } finally {
        fclose(
            $stream
        );
    }

    return $path;
}

function d5Xlsx(
    string $domain,
    array $rows,
    string $sheetName = 'Data'
): string {
    $spreadsheet =
        new Spreadsheet();

    $sheet =
        $spreadsheet
            ->getActiveSheet();

    $sheet->setTitle(
        $sheetName
    );

    $headers =
        d5DomainHeaders(
            $domain
        );

    foreach (
        $headers as $offset => $header
    ) {
        $sheet->setCellValue(
            [
                $offset + 1,
                1,
            ],
            $header
        );
    }

    foreach (
        array_values($rows)
        as $rowOffset => $row
    ) {
        foreach (
            $headers
            as $columnOffset => $header
        ) {
            $sheet->setCellValue(
                [
                    $columnOffset + 1,
                    $rowOffset + 2,
                ],
                $row[$header]
                    ?? null
            );
        }
    }

    $path =
        tempnam(
            sys_get_temp_dir(),
            'dtbi-d5-xlsx-'
        );

    if ($path === false) {
        throw new RuntimeException(
            'No se pudo crear XLSX temporal.'
        );
    }

    $writer =
        new Xlsx(
            $spreadsheet
        );

    $writer->save(
        $path
    );

    $spreadsheet
        ->disconnectWorksheets();

    return $path;
}

test(
    'row validator exposes intradomain validation without relationships',
    function () {
        $validator =
            new DataTransformationBiStandardIntakeRowValidator();

        $result =
            $validator->validateDomain(
                'accounts_receivable',
                [
                    d5AccountsReceivableRow(),
                ]
            );

        expect($result['valid'])
            ->toBeTrue()
            ->and(
                $result['domain']
            )
            ->toBe(
                'accounts_receivable'
            )
            ->and(
                $result['domain_report']['relation_errors']
            )
            ->toBe([]);
    }
);

test(
    'domain csv validates independently before related customer domain exists',
    function () {
        $service =
            new DataTransformationBiDomainIntakeValidationService();

        $path =
            d5Csv(
                'accounts_receivable',
                [
                    d5AccountsReceivableRow(),
                ]
            );

        try {
            $result =
                $service->validate(
                    'accounts_receivable',
                    $path,
                    'accounts_receivable.csv'
                );

            expect($result['valid'])
                ->toBeTrue()
                ->and(
                    $result['format']
                )
                ->toBe(
                    'csv'
                )
                ->and(
                    $result['content']['executed']
                )
                ->toBeTrue()
                ->and(
                    $result['content']['domain_report']['row_count']
                )
                ->toBe(
                    1
                )
                ->and(
                    $result['content']['domain_report']['relation_errors']
                )
                ->toBe([]);
        } finally {
            @unlink(
                $path
            );
        }
    }
);

test(
    'domain xlsx accepts one domain specific worksheet regardless of display name',
    function () {
        $service =
            new DataTransformationBiDomainIntakeValidationService();

        $path =
            d5Xlsx(
                'accounts_receivable',
                [
                    d5AccountsReceivableRow(),
                ],
                'Export ERP'
            );

        try {
            $result =
                $service->validate(
                    'accounts_receivable',
                    $path,
                    'accounts_receivable.xlsx'
                );

            expect($result['valid'])
                ->toBeTrue()
                ->and(
                    $result['format']
                )
                ->toBe(
                    'xlsx'
                )
                ->and(
                    $result['content']['domain_report']['row_count']
                )
                ->toBe(
                    1
                );
        } finally {
            @unlink(
                $path
            );
        }
    }
);

test(
    'domain validation rejects non canonical date values',
    function () {
        $service =
            new DataTransformationBiDomainIntakeValidationService();

        $row =
            d5AccountsReceivableRow();

        $row['due_date'] =
            '30/09/2026';

        $path =
            d5Csv(
                'accounts_receivable',
                [
                    $row,
                ]
            );

        try {
            $result =
                $service->validate(
                    'accounts_receivable',
                    $path,
                    'accounts_receivable.csv'
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
                    'due_date'
                )
                ->toContain(
                    'YYYY-MM-DD'
                );
        } finally {
            @unlink(
                $path
            );
        }
    }
);

test(
    'domain validation rejects duplicate canonical identities',
    function () {
        $service =
            new DataTransformationBiDomainIntakeValidationService();

        $path =
            d5Csv(
                'accounts_receivable',
                [
                    d5AccountsReceivableRow(
                        'AR-DUP'
                    ),
                    d5AccountsReceivableRow(
                        'AR-DUP'
                    ),
                ]
            );

        try {
            $result =
                $service->validate(
                    'accounts_receivable',
                    $path,
                    'accounts_receivable.csv'
                );

            expect($result['valid'])
                ->toBeFalse()
                ->and(
                    $result['content']['domain_report']['duplicate_keys']
                )
                ->toHaveCount(
                    1
                )
                ->and(
                    implode(
                        ' ',
                        $result['errors']
                    )
                )
                ->toContain(
                    'clave duplicada'
                );
        } finally {
            @unlink(
                $path
            );
        }
    }
);

test(
    'domain validation enforces exact canonical headers',
    function () {
        $service =
            new DataTransformationBiDomainIntakeValidationService();

        $headers =
            d5DomainHeaders(
                'accounts_receivable'
            );

        array_pop(
            $headers
        );

        $path =
            d5Csv(
                'accounts_receivable',
                [
                    d5AccountsReceivableRow(),
                ],
                $headers
            );

        try {
            $result =
                $service->validate(
                    'accounts_receivable',
                    $path,
                    'accounts_receivable.csv'
                );

            expect($result['valid'])
                ->toBeFalse()
                ->and(
                    $result['content']['executed']
                )
                ->toBeFalse()
                ->and(
                    implode(
                        ' ',
                        $result['errors']
                    )
                )
                ->toContain(
                    'faltan columnas'
                );
        } finally {
            @unlink(
                $path
            );
        }
    }
);

test(
    'domain validation does not accept legacy zip packages',
    function () {
        $service =
            new DataTransformationBiDomainIntakeValidationService();

        $path =
            tempnam(
                sys_get_temp_dir(),
                'dtbi-d5-zip-'
            );

        if ($path === false) {
            throw new RuntimeException(
                'No se pudo crear archivo temporal.'
            );
        }

        try {
            file_put_contents(
                $path,
                'legacy-package'
            );

            $result =
                $service->validate(
                    'customers',
                    $path,
                    'customers.zip'
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
                    'XLSX o CSV'
                );
        } finally {
            @unlink(
                $path
            );
        }
    }
);
