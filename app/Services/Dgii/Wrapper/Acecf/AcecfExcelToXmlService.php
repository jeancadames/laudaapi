<?php

namespace App\Services\Dgii\Wrapper\Acecf;

use App\Services\Dgii\Wrapper\ExcelToXml\RowBagBuilder;
use App\Services\Dgii\Wrapper\ExcelToXml\XmlFromXsdBuilder;
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

final class AcecfExcelToXmlService
{
    public function __construct(
        private readonly RowBagBuilder $bagBuilder,
        private readonly XmlFromXsdBuilder $xmlBuilder,
        private readonly XsdInlineParser $xsdInlineParser,
    ) {}

    public function convertToZip(string $excelFullPath, string $mode = 'compact', int $companyId = 0): string
    {
        $disk = Storage::disk('private');

        $baseDir = "dgii/cert-acecf/company_{$companyId}";

        if ($disk->exists($baseDir)) {
            $disk->deleteDirectory($baseDir);
        }

        $disk->makeDirectory($baseDir);

        $schemaIndex = $this->loadAcecfSchemaIndex();

        $reader = IOFactory::createReaderForFile($excelFullPath);
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($excelFullPath);
        $sheet = $this->getSheetByNameInsensitive($spreadsheet, 'acecf');

        $headersByColIndex = $this->readHeaderRow($sheet, 1);

        $ts = now()->format('Ymd_His');
        $zipRel = "output/acecf_{$ts}_" . bin2hex(random_bytes(4)) . ".zip";
        $zipFull = $disk->path($zipRel);
        $disk->makeDirectory('output');

        $this->forceZipTempDir();

        $zip = new ZipArchive();
        if ($zip->open($zipFull, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("No se pudo crear ZIP: {$zipFull}");
        }

        $highestRow = $sheet->getHighestDataRow();
        $xmlOrderManifest = [];

        for ($row = 2; $row <= $highestRow; $row++) {
            $rowValuesByColIndex = $this->readRowValuesExistingCells($sheet, $row);
            if (empty($rowValuesByColIndex)) {
                continue;
            }

            $rncEmisor = $this->getValueByHeader($headersByColIndex, $rowValuesByColIndex, 'RNCEmisor');
            $encf = $this->getValueByHeader($headersByColIndex, $rowValuesByColIndex, 'eNCF');
            $caso = $this->getValueByHeader($headersByColIndex, $rowValuesByColIndex, 'CasoPrueba');

            $fileBase = $this->buildAcecfFileBase(
                rncEmisor: $rncEmisor,
                encf: $encf,
                casoPrueba: $caso,
                row: $row,
            );

            $warnings = [];
            $bag = $this->bagBuilder->build($headersByColIndex, $rowValuesByColIndex, $schemaIndex, $warnings);

            $xml = $this->xmlBuilder->build($schemaIndex->root, $bag, $mode);

            if ($companyId > 0) {
                $storedPath = $this->storeXmlToPrivate('dgii/cert-acecf', $companyId, $fileBase, $xml, $row);

                $xmlOrderManifest[] = [
                    'row' => $row,
                    'name' => basename($storedPath),
                    'rnc_emisor' => $rncEmisor,
                    'eNCF' => $encf,
                    'caso_prueba' => $caso,
                ];
            }

            $zip->addFromString($fileBase . '.xml', $xml);

            if (!empty($warnings)) {
                logger()->warning("ACECF warnings (row {$row}, file={$fileBase}): " . implode(' || ', $warnings));
            }
        }

        if ($companyId > 0) {
            $this->storeXmlOrderManifest($baseDir, $xmlOrderManifest);
        }

        $zip->close();

        $zipAbs = $disk->path($zipRel);

        logger()->info('ACECF ZIP generado', [
            'zipRel' => $zipRel,
            'zipAbs' => $zipAbs,
            'exists' => file_exists($zipAbs),
            'size' => file_exists($zipAbs) ? filesize($zipAbs) : null,
        ]);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $zipRel;
    }

    private function buildAcecfFileBase(?string $rncEmisor, ?string $encf, ?string $casoPrueba, int $row): string
    {
        $rncEmisor = trim((string) $rncEmisor);
        $encf = trim((string) $encf);
        $casoPrueba = trim((string) $casoPrueba);

        // Regla real para ACECF:
        // nombre = RNCEmisor + eNCF
        if ($rncEmisor !== '' && $encf !== '') {
            return $this->sanitizeFilename($rncEmisor . $encf);
        }

        // Fallbacks por si una fila viene incompleta
        if ($casoPrueba !== '') {
            return $this->sanitizeFilename($casoPrueba);
        }

        if ($encf !== '') {
            return $this->sanitizeFilename($encf);
        }

        return "row_{$row}";
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

    private function storeXmlToPrivate(string $bucket, int $companyId, string $baseName, string $xml, int $row): string
    {
        $disk = Storage::disk('private');

        $dir = "{$bucket}/company_{$companyId}";
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

    private function getSheetByNameInsensitive(Spreadsheet $spreadsheet, string $wanted): Worksheet
    {
        $wanted = $this->normalizeSheetName($wanted);

        foreach ($spreadsheet->getWorksheetIterator() as $ws) {
            $title = $this->normalizeSheetName((string) $ws->getTitle());
            if ($title === $wanted) {
                return $ws;
            }
        }

        logger()->warning('No se encontró hoja requerida; usando hoja 0 (fallback).', [
            'wanted' => $wanted,
            'available' => array_map(
                fn($s) => (string) $s->getTitle(),
                $spreadsheet->getAllSheets()
            ),
        ]);

        return $spreadsheet->getSheet(0);
    }

    private function normalizeSheetName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        return mb_strtolower($name);
    }

    private function loadAcecfSchemaIndex(): SchemaIndex
    {
        $disk = Storage::disk('public');
        $xsdRel = 'xsd/acecf.xsd';

        $xsdAbs = $disk->path($xsdRel);

        logger()->info('ACECF XSD resolve', [
            'disk'   => 'public',
            'xsdRel' => $xsdRel,
            'xsdAbs' => $xsdAbs,
            'exists' => $disk->exists($xsdRel),
            'size'   => $disk->exists($xsdRel) ? $disk->size($xsdRel) : null,
            'mtime'  => $disk->exists($xsdRel) ? date('c', $disk->lastModified($xsdRel)) : null,
        ]);

        if (!$disk->exists($xsdRel)) {
            throw new RuntimeException("No existe XSD ACECF en storage/app/public/{$xsdRel}");
        }

        $content = $disk->get($xsdRel);

        logger()->info('ACECF XSD loaded', [
            'sha1' => sha1($content),
            'bytes' => strlen($content),
            'head' => substr($content, 0, 120),
        ]);

        $hash = sha1($content);
        $cacheKey = "xsd_tree_public:acecf:{$hash}";

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($content) {
            $root = $this->xsdInlineParser->parseRootFromString($content);

            logger()->info('ACECF XSD parsed', [
                'rootName' => $root->name,
            ]);

            return new SchemaIndex($root);
        });
    }

    private function forceZipTempDir(): void
    {
        $tmpDir = storage_path('app/tmp');
        File::ensureDirectoryExists($tmpDir);
        @chmod($tmpDir, 0775);

        putenv("TMPDIR={$tmpDir}");
        putenv("TEMP={$tmpDir}");
        putenv("TMP={$tmpDir}");
        @ini_set('sys_temp_dir', $tmpDir);
    }

    private function readHeaderRow($sheet, int $row): array
    {
        $headers = [];

        $rowObj = $sheet->getRowIterator($row, $row)->current();
        if (!$rowObj) {
            return $headers;
        }

        $cellIterator = $rowObj->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);

        $colIndex = 1;
        foreach ($cellIterator as $cell) {
            $headers[$colIndex] = trim((string) $cell->getValue());
            $colIndex++;
        }

        return $headers;
    }

    private function readRowValuesExistingCells($sheet, int $row): array
    {
        $values = [];

        $rowObj = $sheet->getRowIterator($row, $row)->current();
        if (!$rowObj) {
            return $values;
        }

        $cellIterator = $rowObj->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(true);

        foreach ($cellIterator as $cell) {
            $colLetter = $cell->getColumn();
            $colIndex  = Coordinate::columnIndexFromString($colLetter);
            $values[$colIndex] = $cell->getValue();
        }

        return $values;
    }

    private function getValueByHeader(array $headersByColIndex, array $rowValuesByColIndex, string $wantedHeader): ?string
    {
        $wantedLower = mb_strtolower($wantedHeader);

        foreach ($headersByColIndex as $col => $h) {
            if ($h === null || $h === '') {
                continue;
            }

            if ($h === $wantedHeader || mb_strtolower($h) === $wantedLower) {
                $v = $rowValuesByColIndex[$col] ?? null;
                return $v === null ? null : trim((string) $v);
            }
        }

        return null;
    }

    private function sanitizeFilename(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^\pL\pN\-_\.]+/u', '_', $name) ?? $name;
        return $name !== '' ? $name : 'file';
    }
}