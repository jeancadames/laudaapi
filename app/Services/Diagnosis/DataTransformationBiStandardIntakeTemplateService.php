<?php

namespace App\Services\Diagnosis;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;
use ZipArchive;

final class DataTransformationBiStandardIntakeTemplateService
{
    public function xlsxFilename(): string
    {
        return sprintf(
            'lauda-standard-data-intake-v%d.xlsx',
            DataTransformationBiStandardIntakeSchema::VERSION
        );
    }

    public function csvZipFilename(): string
    {
        return sprintf(
            'lauda-standard-data-intake-csv-v%d.zip',
            DataTransformationBiStandardIntakeSchema::VERSION
        );
    }

    public function createXlsxTemporaryFile(): string
    {
        $path =
            $this->temporaryPath(
                '.xlsx'
            );

        try {
            $spreadsheet =
                $this->buildSpreadsheet();

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
        } catch (Throwable $exception) {
            @unlink($path);

            throw $exception;
        }
    }

    public function createCsvZipTemporaryFile(): string
    {
        $path =
            $this->temporaryPath(
                '.zip'
            );

        $zip =
            new ZipArchive();

        $result =
            $zip->open(
                $path,
                ZipArchive::CREATE
                | ZipArchive::OVERWRITE
            );

        if ($result !== true) {
            @unlink($path);

            throw new RuntimeException(
                'No fue posible crear el paquete ZIP del intake estándar.'
            );
        }

        try {
            $zip->addFromString(
                'README.csv',
                $this->readmeCsv()
            );

            foreach (
                DataTransformationBiStandardIntakeSchema::domains()
                as $domainKey => $domain
            ) {
                $headers =
                    array_map(
                        static fn (array $field): string =>
                            $field['name'],
                        $domain['fields']
                    );

                $zip->addFromString(
                    "{$domainKey}.csv",
                    $this->csvContent([
                        $headers,
                    ])
                );
            }

            if (! $zip->close()) {
                throw new RuntimeException(
                    'No fue posible finalizar el paquete ZIP del intake estándar.'
                );
            }

            return $path;
        } catch (Throwable $exception) {
            $zip->close();

            @unlink($path);

            throw $exception;
        }
    }

    private function buildSpreadsheet(): Spreadsheet
    {
        $spreadsheet =
            new Spreadsheet();

        $readme =
            $spreadsheet
                ->getActiveSheet();

        $readme->setTitle(
            'README'
        );

        $row = 1;

        $readme->setCellValue(
            "A{$row}",
            'LAUDA Standard Data Intake'
        );

        $readme->setCellValue(
            "B{$row}",
            'Plantilla canónica'
        );

        $row++;

        $readme->setCellValue(
            "A{$row}",
            'Schema version'
        );

        $readme->setCellValue(
            "B{$row}",
            DataTransformationBiStandardIntakeSchema::VERSION
        );

        $row += 2;

        $readme->setCellValue(
            "A{$row}",
            'REGLAS DE FORMATO'
        );

        $row++;

        $readme->fromArray(
            [
                'Regla',
                'Valor',
                'Descripción',
            ],
            null,
            "A{$row}"
        );

        $row++;

        foreach (
            DataTransformationBiStandardIntakeSchema::formatRules()
            as $key => $rule
        ) {
            $readme->fromArray(
                [
                    $key,
                    $rule['value'],
                    $rule['description'],
                ],
                null,
                "A{$row}"
            );

            $row++;
        }

        $row += 2;

        $readme->setCellValue(
            "A{$row}",
            'DICCIONARIO DE CAMPOS'
        );

        $row++;

        $readme->fromArray(
            [
                'Dominio',
                'Nombre',
                'Campo',
                'Obligatorio',
                'Tipo',
                'Descripción',
            ],
            null,
            "A{$row}"
        );

        $row++;

        foreach (
            DataTransformationBiStandardIntakeSchema::domains()
            as $domainKey => $domain
        ) {
            foreach (
                $domain['fields']
                as $field
            ) {
                $readme->fromArray(
                    [
                        $domainKey,
                        $domain['label'],
                        $field['name'],
                        $field['required']
                            ? 'Sí'
                            : 'No',
                        $field['type'],
                        $field['description'],
                    ],
                    null,
                    "A{$row}"
                );

                $row++;
            }

            $sheet =
                $spreadsheet
                    ->createSheet();

            $sheet->setTitle(
                $domainKey
            );

            $headers =
                array_map(
                    static fn (array $field): string =>
                        $field['name'],
                    $domain['fields']
                );

            $sheet->fromArray(
                $headers,
                null,
                'A1'
            );

            $lastColumn =
                Coordinate::stringFromColumnIndex(
                    count($headers)
                );

            $sheet
                ->freezePane(
                    'A2'
                );

            $sheet
                ->setAutoFilter(
                    "A1:{$lastColumn}1"
                );

            $sheet
                ->getStyle(
                    "A1:{$lastColumn}1"
                )
                ->getFont()
                ->setBold(true);

            foreach (
                $domain['fields']
                as $index => $field
            ) {
                $column =
                    Coordinate::stringFromColumnIndex(
                        $index + 1
                    );

                $sheet
                    ->getColumnDimension(
                        $column
                    )
                    ->setAutoSize(true);

                $required =
                    $field['required']
                        ? 'Obligatorio'
                        : 'Opcional';

                $sheet
                    ->getComment(
                        "{$column}1"
                    )
                    ->getText()
                    ->createTextRun(
                        "{$required} · {$field['type']}\n{$field['description']}"
                    );
            }
        }

        foreach (range('A', 'F') as $column) {
            $readme
                ->getColumnDimension(
                    $column
                )
                ->setAutoSize(true);
        }

        $readme
            ->getStyle('A1:F1')
            ->getFont()
            ->setBold(true);

        $spreadsheet
            ->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function readmeCsv(): string
    {
        $rows = [
            [
                'section',
                'domain',
                'field',
                'required',
                'type_or_rule',
                'description_or_value',
            ],

            [
                'metadata',
                '',
                '',
                '',
                'schema_version',
                (string)
                    DataTransformationBiStandardIntakeSchema::VERSION,
            ],
        ];

        foreach (
            DataTransformationBiStandardIntakeSchema::formatRules()
            as $key => $rule
        ) {
            $rows[] = [
                'format_rule',
                '',
                '',
                '',
                $key,
                "{$rule['value']} · {$rule['description']}",
            ];
        }

        foreach (
            DataTransformationBiStandardIntakeSchema::domains()
            as $domainKey => $domain
        ) {
            foreach (
                $domain['fields']
                as $field
            ) {
                $rows[] = [
                    'field',
                    $domainKey,
                    $field['name'],
                    $field['required']
                        ? 'true'
                        : 'false',
                    $field['type'],
                    $field['description'],
                ];
            }
        }

        return $this->csvContent(
            $rows
        );
    }

    /**
     * @param array<int, array<int, string>> $rows
     */
    private function csvContent(
        array $rows
    ): string {
        $stream =
            fopen(
                'php://temp',
                'w+b'
            );

        if ($stream === false) {
            throw new RuntimeException(
                'No fue posible preparar el CSV temporal.'
            );
        }

        /*
         * BOM UTF-8 para compatibilidad práctica con Excel
         * sin cambiar el contrato de codificación.
         */
        fwrite(
            $stream,
            "\xEF\xBB\xBF"
        );

        foreach ($rows as $row) {
            fputcsv(
                $stream,
                $row,
                ',',
                '"',
                ''
            );
        }

        rewind(
            $stream
        );

        $content =
            stream_get_contents(
                $stream
            );

        fclose(
            $stream
        );

        if ($content === false) {
            throw new RuntimeException(
                'No fue posible leer el CSV temporal.'
            );
        }

        return $content;
    }

    private function temporaryPath(
        string $suffix
    ): string {
        $base =
            tempnam(
                sys_get_temp_dir(),
                'lauda-bi-intake-'
            );

        if ($base === false) {
            throw new RuntimeException(
                'No fue posible crear el archivo temporal.'
            );
        }

        $path =
            $base
            .$suffix;

        if (! rename($base, $path)) {
            @unlink($base);

            throw new RuntimeException(
                'No fue posible preparar el archivo temporal.'
            );
        }

        return $path;
    }

    /*
     * D15E_DOMAIN_TEMPLATE_SERVICE
     *
     * Intake v2 templates are generated directly from the same canonical
     * schema used by validation. No second field definition is maintained.
     */
    public function domainCsvFilename(
        string $domain
    ): string {
        $domain =
            $this->domainTemplateDomain(
                $domain
            );

        return sprintf(
            'lauda-standard-data-intake-%s-v%d.csv',
            $domain,
            DataTransformationBiStandardIntakeSchema::VERSION
        );
    }

    public function domainXlsxFilename(
        string $domain
    ): string {
        $domain =
            $this->domainTemplateDomain(
                $domain
            );

        return sprintf(
            'lauda-standard-data-intake-%s-v%d.xlsx',
            $domain,
            DataTransformationBiStandardIntakeSchema::VERSION
        );
    }

    public function createDomainCsvTemporaryFile(
        string $domain
    ): string {
        $domain =
            $this->domainTemplateDomain(
                $domain
            );

        $headers =
            $this->domainTemplateFieldNames(
                $domain
            );

        $path =
            $this->temporaryPath(
                '.csv'
            );

        $handle =
            fopen(
                $path,
                'wb'
            );

        if ($handle === false) {
            @unlink($path);

            throw new RuntimeException(
                'No se pudo crear la plantilla CSV temporal.'
            );
        }

        try {
            $written =
                fputcsv(
                    $handle,
                    $headers,
                    ',',
                    '"',
                    ''
                );

            if ($written === false) {
                throw new RuntimeException(
                    'No se pudo escribir la cabecera CSV.'
                );
            }
        } catch (Throwable $exception) {
            fclose($handle);
            @unlink($path);

            throw $exception;
        }

        fclose($handle);

        return $path;
    }

    public function createDomainXlsxTemporaryFile(
        string $domain
    ): string {
        $domain =
            $this->domainTemplateDomain(
                $domain
            );

        $headers =
            $this->domainTemplateFieldNames(
                $domain
            );

        $path =
            $this->temporaryPath(
                '.xlsx'
            );

        $spreadsheet =
            new Spreadsheet();

        try {
            $sheet =
                $spreadsheet
                    ->getActiveSheet();

            $sheet
                ->setTitle(
                    $domain
                );

            $sheet
                ->fromArray(
                    $headers,
                    null,
                    'A1'
                );

            (new Xlsx($spreadsheet))
                ->save(
                    $path
                );

            return $path;
        } catch (Throwable $exception) {
            @unlink($path);

            throw $exception;
        } finally {
            $spreadsheet
                ->disconnectWorksheets();
        }
    }

    private function domainTemplateDomain(
        string $domain
    ): string {
        $domain =
            trim(
                $domain
            );

        if (
            ! in_array(
                $domain,
                DataTransformationBiStandardIntakeSchema
                    ::domainKeys(),
                true
            )
        ) {
            throw new \InvalidArgumentException(
                "Dominio canónico no soportado: {$domain}."
            );
        }

        return $domain;
    }

    /**
     * @return array<int,string>
     */
    private function domainTemplateFieldNames(
        string $domain
    ): array {
        $definition =
            DataTransformationBiStandardIntakeSchema
                ::domains()[$domain]
            ?? null;

        if (! is_array($definition)) {
            throw new RuntimeException(
                "No existe definición canónica para {$domain}."
            );
        }

        $fields =
            $definition['fields']
            ?? [];

        $names = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $name =
                trim(
                    (string) (
                        $field['name']
                        ?? ''
                    )
                );

            if ($name !== '') {
                $names[] =
                    $name;
            }
        }

        if ($names === []) {
            throw new RuntimeException(
                "El dominio {$domain} no contiene campos canónicos."
            );
        }

        return $names;
    }

}
