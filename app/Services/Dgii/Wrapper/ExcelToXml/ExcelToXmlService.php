<?php

namespace App\Services\Dgii\Wrapper\ExcelToXml;

use App\Services\Dgii\Wrapper\ExcelToXml\Xsd\SchemaIndex;
use App\Services\Dgii\Wrapper\ExcelToXml\Xsd\XsdInlineParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use ZipArchive;

final class ExcelToXmlService
{
    public function __construct(
        private readonly XsdInlineParser $xsdParser = new XsdInlineParser(),
        private readonly RowBagBuilder $bagBuilder = new RowBagBuilder(),
        private readonly XmlFromXsdBuilder $xmlBuilder = new XmlFromXsdBuilder(),
        private readonly XsdBagValidator $bagValidator = new XsdBagValidator(),
    ) {}

    private const FORCE_EMPTY_XML_ELEMENT = '__FORCE_EMPTY_ELEMENT__';

    /**
     * Genera un ZIP unificado con XMLs de ambas hojas:
     * - ecf
     * - rfce
     *
     * @return string relative storage path like "output/xml_YYYYMMDD_HHMMSS_xxx.zip"
     */
    public function convertToZip(string $excelFullPath, string $mode = 'compact', int $companyId = 0): string
    {
        $disk = Storage::disk('private');

        $baseDir = "dgii/cert-ecf/company_{$companyId}";
        $legacyRfceDir = "dgii/cert-rfce/company_{$companyId}";

        if ($companyId > 0) {
            if ($disk->exists($baseDir)) {
                $disk->deleteDirectory($baseDir);
            }

            // limpieza de legado para no dejar residuos del wrapper RFCE viejo
            if ($disk->exists($legacyRfceDir)) {
                $disk->deleteDirectory($legacyRfceDir);
            }

            $disk->makeDirectory($baseDir);
        }

        $reader = IOFactory::createReaderForFile($excelFullPath);
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($excelFullPath);

        $ecfSheet = $this->getRequiredSheetByNameInsensitive($spreadsheet, 'ecf');
        $rfceSheet = $this->getRequiredSheetByNameInsensitive($spreadsheet, 'rfce');

        $items = array_merge(
            $this->buildEcfItems($ecfSheet, $mode),
            $this->buildRfceItems($rfceSheet, $mode),
        );

        if (empty($items)) {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            throw new RuntimeException('No se generó ningún XML desde el archivo Excel.');
        }

        usort($items, fn (array $a, array $b) => $this->compareItems($a, $b));

        $ts = now()->format('Ymd_His');
        $zipRel = "output/xml_{$ts}_" . bin2hex(random_bytes(4)) . ".zip";

        $disk->makeDirectory('output');
        $disk->makeDirectory('tmp');

        $zipFull = $disk->path($zipRel);

        $this->forceZipTempDir($disk->path('tmp'));

        $zip = new ZipArchive();
        if ($zip->open($zipFull, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("No se pudo crear ZIP: {$zipFull}");
        }

        $manifest = [];
        $usedZipNames = [];

        foreach ($items as $position => $item) {
            $zipName = null;

            if ($companyId > 0) {
                $storedPath = $this->storeXmlToPrivate(
                    "{$baseDir}/{$item['subdir']}",
                    $item['file_base'],
                    $item['xml'],
                    $item['row']
                );

                $zipName = basename($storedPath);
            } else {
                $zipName = $this->makeUniqueZipFilename(
                    $usedZipNames,
                    $this->sanitizeFilename($item['file_base']) . '.xml'
                );
            }

            $zip->addFromString($item['subdir'] . '/' . $zipName, $item['xml']);

            $manifest[] = [
                'order' => $position + 1,
                'name' => $zipName,
                'subdir' => $item['subdir'],
                'sheet' => $item['sheet'],
                'kind' => $item['kind'],
                'row' => $item['row'],
                'tipo_ecf' => $item['tipo_ecf'],
                'eNCF' => $item['eNCF'],
                'monto_total' => $item['monto_total'],
                'group_key' => $item['group_key'],
                'group_label' => $item['group_label'],
                'group_stage_order' => $item['group_stage_order'] ?? null,
                'group_stage_label' => $item['group_stage_label'] ?? null,
                'dgii_type_label' => $item['dgii_type_label'] ?? null,
                'workflow' => $item['workflow'],
                'pair_eNCF' => $item['pair_eNCF'],
                'has_security_code_placeholder' => $item['has_security_code_placeholder'],
            ];
        }

        $zip->close();

        if ($companyId > 0) {
            $this->storeXmlOrderManifest($baseDir, $manifest);
        }

        logger()->info('ZIP unificado terminado', [
            'zipRel' => $zipRel,
            'zipFull' => $zipFull,
            'exists' => file_exists($zipFull),
            'size' => file_exists($zipFull) ? filesize($zipFull) : null,
            'items' => count($manifest),
        ]);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $zipRel;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildEcfItems(Worksheet $sheet, string $mode): array
    {
        $headersByColIndex = $this->readHeaderRow($sheet, 1);
        $highestRow = $sheet->getHighestDataRow();

        $schemaCache = [];
        $items = [];

        for ($row = 2; $row <= $highestRow; $row++) {
            $rowValuesByColIndex = $this->readRowValuesExistingCells($sheet, $row);
            if (empty($rowValuesByColIndex)) {
                continue;
            }

            $tipo = trim((string) ($this->getValueByHeader($headersByColIndex, $rowValuesByColIndex, 'TipoeCF') ?? '31'));
            if ($tipo === '') {
                $tipo = '31';
            }

            if (!isset($schemaCache[$tipo])) {
                $schemaCache[$tipo] = $this->loadSchemaIndexForTipo($tipo);
            }

            /** @var SchemaIndex $schemaIndex */
            $schemaIndex = $schemaCache[$tipo];

            $warnings = [];
            $bag = $this->bagBuilder->build($headersByColIndex, $rowValuesByColIndex, $schemaIndex, $warnings);

            $casoPrueba = $this->getValueByHeader($headersByColIndex, $rowValuesByColIndex, 'CasoPrueba');
            $eNCF = $this->getValueByHeader($headersByColIndex, $rowValuesByColIndex, 'eNCF');
            $montoTotal = $this->normalizeAmount(
                $this->getValueByHeader($headersByColIndex, $rowValuesByColIndex, 'MontoTotal')
            );

            $classification = $this->classifyEcfItem($tipo, $montoTotal);

            $fileBase = $this->sanitizeFilename(
                $casoPrueba ?: $eNCF ?: "ecf_row_{$row}"
            );

            $this->assertBagValid(
                $schemaIndex,
                $headersByColIndex,
                $rowValuesByColIndex,
                $bag,
                'ecf',
                $row,
                $fileBase,
                $warnings,
            );

            $xml = $this->xmlBuilder->build($schemaIndex->root, $bag, $mode);

            $items[] = [
                'sheet' => 'ecf',
                'sheet_order' => 1,
                'kind' => 'ecf',
                'subdir' => 'ecf',
                'row' => $row,
                'tipo_ecf' => $tipo,
                'eNCF' => $eNCF,
                'monto_total' => $montoTotal,
                'file_base' => $fileBase,
                'xml' => $xml,

                'group_key' => $classification['group_key'],
                'group_label' => $classification['group_label'],
                'group_stage_order' => $classification['group_stage_order'],
                'group_stage_label' => $classification['group_stage_label'],
                'dgii_type_label' => $classification['dgii_type_label'],

                'sort_order' => $classification['sort_order'],
                'workflow' => $classification['workflow'],
                'pair_eNCF' => ($tipo === '32' && ($montoTotal ?? 0.0) < 250000.0) ? $eNCF : null,
                'has_security_code_placeholder' => false,
            ];

            if (!empty($warnings)) {
                logger()->warning("ExcelToXml ECF warnings (row {$row}, CasoPrueba={$fileBase}): " . implode(' || ', $warnings));
            }
        }

        return $items;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildRfceItems(Worksheet $sheet, string $mode): array
    {
        $headersByColIndex = $this->readHeaderRow($sheet, 1);
        $highestRow = $sheet->getHighestDataRow();

        $schemaIndex = $this->loadRfceSchemaIndex();
        $items = [];

        for ($row = 2; $row <= $highestRow; $row++) {
            $rowValuesByColIndex = $this->readRowValuesExistingCells($sheet, $row);
            if (empty($rowValuesByColIndex)) {
                continue;
            }

            $warnings = [];
            $bag = $this->bagBuilder->build($headersByColIndex, $rowValuesByColIndex, $schemaIndex, $warnings);

            // RFCE: aunque CodigoSeguridadeCF venga vacío / #e, el nodo debe existir
            $hasSecurityCodePlaceholder = $this->ensureRfceSecurityCodePlaceholder($bag);

            $casoPrueba = $this->getValueByHeader($headersByColIndex, $rowValuesByColIndex, 'CasoPrueba');
            $eNCF = $this->getValueByHeader($headersByColIndex, $rowValuesByColIndex, 'eNCF');
            $montoTotal = $this->normalizeAmount(
                $this->getValueByHeader($headersByColIndex, $rowValuesByColIndex, 'MontoTotal')
            );

            $fileBase = $this->sanitizeFilename(
                $casoPrueba ?: $eNCF ?: "rfce_row_{$row}"
            );

            $this->assertBagValid(
                $schemaIndex,
                $headersByColIndex,
                $rowValuesByColIndex,
                $bag,
                'rfce',
                $row,
                $fileBase,
                $warnings,
            );

            $xml = $this->xmlBuilder->build($schemaIndex->root, $bag, $mode);

            $items[] = [
                'sheet' => 'rfce',
                'sheet_order' => 2,
                'kind' => 'rfce',
                'subdir' => 'rfce',
                'row' => $row,
                'tipo_ecf' => '32',
                'eNCF' => $eNCF,
                'monto_total' => $montoTotal,
                'file_base' => $fileBase,
                'xml' => $xml,
                'group_key' => 'rfce_32_lt_250k',
                'group_label' => '32 - Resumen Factura de Consumo Electrónica Menor a RD$250,000.00',
                'group_stage_order' => 3,
                'group_stage_label' => 'Cuarto',
                'dgii_type_label' => '32 - Resumen Factura de Consumo Electrónica Menor a RD$250,000.00',
                'sort_order' => 90,
                'workflow' => 'fill_security_code_sign_send',
                'pair_eNCF' => $eNCF,
                'has_security_code_placeholder' => $hasSecurityCodePlaceholder,
            ];

            if (!empty($warnings)) {
                logger()->warning("ExcelToXml RFCE warnings (row {$row}, CasoPrueba={$fileBase}): " . implode(' || ', $warnings));
            }
        }

        return $items;
    }

    /**
     * Fuerza el nodo RFCE.Encabezado.CodigoSeguridadeCF aunque venga vacío en Excel.
     */
    private function ensureRfceSecurityCodePlaceholder(array &$bag): bool
    {
        $path = 'RFCE.Encabezado.CodigoSeguridadeCF';

        $current = $bag[$path]['1'] ?? null;
        if ($current !== null && $current !== '' && $current !== '#e') {
            return false;
        }
            $bag[$path] ??= [];
            $bag[$path]['1'] = self::FORCE_EMPTY_XML_ELEMENT;
        return true;
    }

    /**
     * En la etapa de wrapper SOLO validamos que un dato realmente poblado en el Excel
     * no se pierda al resolver headers -> bag.
     *
     * No exigimos aquí campos obligatorios del XSD que se completan después
     * (por ejemplo FechaHoraFirma o la firma XML).
     *
     * @param string[] $warnings
     */
    private function assertBagValid(
        SchemaIndex $schemaIndex,
        array $headersByColIndex,
        array $rowValuesByColIndex,
        array $bag,
        string $sheet,
        int $row,
        string $fileBase,
        array $warnings = [],
    ): void {
        $errors = $this->bagValidator->validateResolvedSourceData(
            $headersByColIndex,
            $rowValuesByColIndex,
            $schemaIndex,
            $this->bagBuilder,
            $bag,
        );

        if (empty($errors)) {
            return;
        }

        $message = sprintf(
            'Wrapper inválido para hoja=%s fila=%d archivo=%s. %s',
            $sheet,
            $row,
            $fileBase,
            implode(' || ', $errors)
        );

        if (!empty($warnings)) {
            $message .= ' || warnings=' . implode(' || ', $warnings);
        }

        throw new RuntimeException($message);
    }

    /**
     * @return array{
     *   sort_order:int,
     *   group_key:string,
     *   group_label:string,
     *   group_stage_order:int,
     *   group_stage_label:string,
     *   dgii_type_label:string,
     *   workflow:string
     * }
     */
    private function classifyEcfItem(string $tipo, ?float $montoTotal): array
    {
        return match ($tipo) {
            '31' => [
                'sort_order' => 10,
                'group_key' => 'ecf_31',
                'group_label' => '31 - Factura de Crédito Fiscal Electrónica',
                'group_stage_order' => 1,
                'group_stage_label' => 'Primero',
                'dgii_type_label' => '31 - Factura de Crédito Fiscal Electrónica',
                'workflow' => 'send',
            ],

            '32' => (($montoTotal ?? 0.0) >= 250000.0)
                ? [
                    'sort_order' => 20,
                    'group_key' => 'ecf_32_gte_250k',
                    'group_label' => '32 - Factura de Consumo Electrónica Mayor o Igual RD$250,000.00',
                    'group_stage_order' => 1,
                    'group_stage_label' => 'Primero',
                    'dgii_type_label' => '32 - Factura de Consumo Electrónica Mayor o Igual RD$250,000.00',
                    'workflow' => 'send',
                ]
                : [
                    'sort_order' => 100,
                    'group_key' => 'ecf_32_lt_250k',
                    'group_label' => '32 - Factura de Consumo Electrónica Menor a RD$250,000.00',
                    'group_stage_order' => 4,
                    'group_stage_label' => 'Tercero',
                    'dgii_type_label' => '32 - Factura de Consumo Electrónica Menor a RD$250,000.00',
                    'workflow' => 'sign_download_only',
                ],

            '41' => [
                'sort_order' => 30,
                'group_key' => 'ecf_41',
                'group_label' => '41 - Compras Electrónico',
                'group_stage_order' => 1,
                'group_stage_label' => 'Primero',
                'dgii_type_label' => '41 - Compras Electrónico',
                'workflow' => 'send',
            ],

            '43' => [
                'sort_order' => 35,
                'group_key' => 'ecf_43',
                'group_label' => '43 - Gastos Menores Electrónico',
                'group_stage_order' => 1,
                'group_stage_label' => 'Primero',
                'dgii_type_label' => '43 - Gastos Menores Electrónico',
                'workflow' => 'send',
            ],

            '44' => [
                'sort_order' => 40,
                'group_key' => 'ecf_44',
                'group_label' => '44 - Regímenes Especiales Electrónico',
                'group_stage_order' => 1,
                'group_stage_label' => 'Primero',
                'dgii_type_label' => '44 - Regímenes Especiales Electrónico',
                'workflow' => 'send',
            ],

            '45' => [
                'sort_order' => 50,
                'group_key' => 'ecf_45',
                'group_label' => '45 - Gubernamental Electrónico',
                'group_stage_order' => 1,
                'group_stage_label' => 'Primero',
                'dgii_type_label' => '45 - Gubernamental Electrónico',
                'workflow' => 'send',
            ],

            '46' => [
                'sort_order' => 60,
                'group_key' => 'ecf_46',
                'group_label' => '46 - Comprobante de Exportaciones Electrónico',
                'group_stage_order' => 1,
                'group_stage_label' => 'Primero',
                'dgii_type_label' => '46 - Comprobante de Exportaciones Electrónico',
                'workflow' => 'send',
            ],

            '47' => [
                'sort_order' => 70,
                'group_key' => 'ecf_47',
                'group_label' => '47 - Comprobante para Pagos al Exterior Electrónico',
                'group_stage_order' => 1,
                'group_stage_label' => 'Primero',
                'dgii_type_label' => '47 - Comprobante para Pagos al Exterior Electrónico',
                'workflow' => 'send',
            ],

            '33' => [
                'sort_order' => 80,
                'group_key' => 'ecf_33',
                'group_label' => '33 - Nota de Débito Electrónica',
                'group_stage_order' => 2,
                'group_stage_label' => 'Segundo',
                'dgii_type_label' => '33 - Nota de Débito Electrónica',
                'workflow' => 'send',
            ],

            '34' => [
                'sort_order' => 81,
                'group_key' => 'ecf_34',
                'group_label' => '34 - Nota de Crédito Electrónica',
                'group_stage_order' => 2,
                'group_stage_label' => 'Segundo',
                'dgii_type_label' => '34 - Nota de Crédito Electrónica',
                'workflow' => 'send',
            ],

            default => [
                'sort_order' => 999,
                'group_key' => 'ecf_other',
                'group_label' => "ECF {$tipo}",
                'group_stage_order' => 99,
                'group_stage_label' => '—',
                'dgii_type_label' => "ECF {$tipo}",
                'workflow' => 'send',
            ],
        };
    }

    private function compareItems(array $a, array $b): int
    {
        $aIsPair = $this->isLt250kPairItem($a);
        $bIsPair = $this->isLt250kPairItem($b);

        if ($aIsPair && $bIsPair) {
            $byPair = strnatcasecmp((string) ($a['pair_eNCF'] ?? ''), (string) ($b['pair_eNCF'] ?? ''));
            if ($byPair !== 0) {
                return $byPair;
            }

            // primero el XML de la hoja ecf, luego el de rfce
            return $this->pairKindRank($a['kind']) <=> $this->pairKindRank($b['kind']);
        }

        return [$a['sort_order'], $a['sheet_order'], $a['row']]
            <=> [$b['sort_order'], $b['sheet_order'], $b['row']];
    }

    private function isLt250kPairItem(array $item): bool
    {
        return in_array($item['group_key'] ?? null, [
            'ecf_32_lt_250k',
            'rfce_32_lt_250k',
        ], true);
    }

    private function pairKindRank(string $kind): int
    {
        return $kind === 'ecf' ? 0 : 1;
    }

    private function storeXmlOrderManifest(string $baseDir, array $items): void
    {
        $disk = Storage::disk('private');

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'items' => array_values($items),
        ];

        $disk->put(
            "{$baseDir}/_xml_order.json",
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );
    }

    private function storeXmlToPrivate(string $dir, string $baseName, string $xml, int $row): string
    {
        $disk = Storage::disk('private');

        $disk->makeDirectory($dir);

        $baseName = $this->sanitizeFilename($baseName);
        $filename = $baseName !== '' ? "{$baseName}.xml" : "row_{$row}.xml";

        $path = "{$dir}/{$filename}";
        if ($disk->exists($path)) {
            $i = 2;
            do {
                $filename2 = "{$baseName}_{$i}.xml";
                $path2 = "{$dir}/{$filename2}";
                $i++;
            } while ($disk->exists($path2));

            $path = $path2;
        }

        $disk->put($path, $xml);

        return $path;
    }

    private function makeUniqueZipFilename(array &$used, string $filename): string
    {
        $filename = trim($filename);
        if ($filename === '') {
            $filename = 'file.xml';
        }

        $base = pathinfo($filename, PATHINFO_FILENAME);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $ext = $ext !== '' ? ".{$ext}" : '';

        $candidate = $filename;
        $i = 2;

        while (isset($used[$candidate])) {
            $candidate = "{$base}_{$i}{$ext}";
            $i++;
        }

        $used[$candidate] = true;

        return $candidate;
    }

    private function getRequiredSheetByNameInsensitive(Spreadsheet $spreadsheet, string $wanted): Worksheet
    {
        $sheet = $this->findSheetByNameInsensitive($spreadsheet, $wanted);

        if ($sheet instanceof Worksheet) {
            return $sheet;
        }

        throw new RuntimeException(
            "No se encontró la hoja requerida '{$wanted}'. Hojas disponibles: " .
            implode(', ', array_map(
                fn ($s) => (string) $s->getTitle(),
                $spreadsheet->getAllSheets()
            ))
        );
    }

    private function findSheetByNameInsensitive(Spreadsheet $spreadsheet, string $wanted): ?Worksheet
    {
        $wanted = $this->normalizeSheetName($wanted);

        foreach ($spreadsheet->getWorksheetIterator() as $ws) {
            $title = $this->normalizeSheetName((string) $ws->getTitle());
            if ($title === $wanted) {
                return $ws;
            }
        }

        return null;
    }

    private function normalizeSheetName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        return mb_strtolower($name);
    }

    private function forceZipTempDir(string $tmpDir): void
    {
        File::ensureDirectoryExists($tmpDir);
        @chmod($tmpDir, 0775);

        putenv("TMPDIR={$tmpDir}");
        putenv("TEMP={$tmpDir}");
        putenv("TMP={$tmpDir}");
        @ini_set('sys_temp_dir', $tmpDir);
    }

    private function loadSchemaIndexForTipo(string $tipo): SchemaIndex
    {
        $disk = Storage::disk('public');

        $xsdRel = "xsd/{$tipo}.xsd";

        if (!$disk->exists($xsdRel)) {
            throw new RuntimeException("No existe XSD para TipoeCF={$tipo} en storage/app/public/{$xsdRel}");
        }

        $content = $disk->get($xsdRel);

        $hash = sha1($content);
        $cacheKey = "xsd_tree_public:v2:{$tipo}:{$hash}";

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($content) {
            $root = $this->xsdParser->parseRootFromString($content);
            return new SchemaIndex($root);
        });
    }

    private function loadRfceSchemaIndex(): SchemaIndex
    {
        $disk = Storage::disk('public');

        $xsdRel = 'xsd/rfce.xsd';

        if (!$disk->exists($xsdRel)) {
            throw new RuntimeException("No existe XSD RFCE en storage/app/public/{$xsdRel}");
        }

        $content = $disk->get($xsdRel);

        $hash = sha1($content);
        $cacheKey = "xsd_tree_public:v2:rfce:{$hash}";

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($content) {
            $root = $this->xsdParser->parseRootFromString($content);
            return new SchemaIndex($root);
        });
    }

    private function readHeaderRow(Worksheet $sheet, int $row): array
    {
        $headers = [];

        $rowObj = $sheet->getRowIterator($row, $row)->current();
        if (!$rowObj) {
            return $headers;
        }

        $cellIterator = $rowObj->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(true);

        foreach ($cellIterator as $cell) {
            $colLetter = $cell->getColumn();
            $colIndex = Coordinate::columnIndexFromString($colLetter);
            $headers[$colIndex] = trim((string) $cell->getValue());
        }

        return $headers;
    }

    private function readRowValuesExistingCells(Worksheet $sheet, int $row): array
    {
        $values = [];

        $rowObj = $sheet->getRowIterator($row, $row)->current();
        if (!$rowObj) {
            return $values;
        }

        $cellIterator = $rowObj->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(true);

        foreach ($cellIterator as $cell) {
            $colIndex = Coordinate::columnIndexFromString($cell->getColumn());
            $values[$colIndex] = $cell->getFormattedValue();
        }

        return $values;
    }

    private function getValueByHeader(array $headersByColIndex, array $rowValuesByColIndex, string $targetHeader): ?string
    {
        $target = mb_strtolower(trim($targetHeader));

        foreach ($headersByColIndex as $colIndex => $header) {
            $headerNorm = mb_strtolower(trim((string) $header));

            if (
                $headerNorm === $target ||
                str_ends_with($headerNorm, '.' . $target)
            ) {
                if (!array_key_exists($colIndex, $rowValuesByColIndex)) {
                    continue;
                }

                $v = trim((string) $rowValuesByColIndex[$colIndex]);
                if ($v === '' || $v === '#e') {
                    return null;
                }

                return $v;
            }
        }

        return null;
    }

    private function normalizeAmount(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || $value === '#e') {
            return null;
        }

        $value = str_replace("\xC2\xA0", '', $value);
        $value = str_replace(' ', '', $value);

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $lastComma = strrpos($value, ',');
            $lastDot = strrpos($value, '.');

            if ($lastComma !== false && $lastDot !== false && $lastComma > $lastDot) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function sanitizeFilename(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'sin_nombre';
        }

        $name = preg_replace('/[^\w\-\.]+/u', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        $name = trim($name, '_');

        return $name ?: 'sin_nombre';
    }
}