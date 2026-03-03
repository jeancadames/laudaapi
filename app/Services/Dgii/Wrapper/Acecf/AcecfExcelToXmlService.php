<?php

namespace App\Services\Dgii\Wrapper\Acecf;

use App\Services\Dgii\Wrapper\ExcelToXml\RowBagBuilder;
use App\Services\Dgii\Wrapper\ExcelToXml\XmlFromXsdBuilder;
use App\Services\Dgii\Wrapper\ExcelToXml\Xsd\SchemaIndex;
use App\Services\Dgii\Wrapper\ExcelToXml\Xsd\XsdInlineParser; // <-- ESTE es el que ya usas con parseRootFromString()
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
        private readonly XsdInlineParser $xsdInlineParser, // ✅ ahora sí existe
    ) {}

    /**
     * Genera un ZIP con 1 XML por fila.
     * - Mapea headers a leaves usando el XSD ACECF actual (storage/app/public/xsd/acecf.xsd).
     * - Arma jerarquía padre/hijo con XmlFromXsdBuilder (compact => omite secciones vacías).
     */
    public function convertToZip(string $excelFullPath, string $mode = 'compact', int $companyId = 0): string
    {
        $disk = Storage::disk('private');

        $baseDir = "dgii/cert-acecf/company_{$companyId}";

        // ✅ Reset: borrar todo lo anterior de ese cliente (solo ECF)
        if ($disk->exists($baseDir)) {
            $disk->deleteDirectory($baseDir);
        }

        $disk->makeDirectory($baseDir);

        $schemaIndex = $this->loadAcecfSchemaIndex();

        // Leer Excel
        $reader = IOFactory::createReaderForFile($excelFullPath);
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($excelFullPath);
        $sheet = $this->getSheetByNameInsensitive($spreadsheet, 'acecf');

        $headersByColIndex = $this->readHeaderRow($sheet, 1);

        // Preparar zip
        $ts = now()->format('Ymd_His');
        $zipRel = "output/acecf_{$ts}_" . bin2hex(random_bytes(4)) . ".zip";
        $zipFull = Storage::disk('local')->path($zipRel);
        Storage::disk('local')->makeDirectory('output');

        $this->forceZipTempDir();

        $zip = new ZipArchive();
        if ($zip->open($zipFull, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("No se pudo crear ZIP: {$zipFull}");
        }

        $highestRow = $sheet->getHighestDataRow();

        for ($row = 2; $row <= $highestRow; $row++) {
            $rowValuesByColIndex = $this->readRowValuesExistingCells($sheet, $row);
            if (empty($rowValuesByColIndex)) continue;

            // filename: CasoPrueba -> eNCF -> row_N
            $caso = $this->getValueByHeader($headersByColIndex, $rowValuesByColIndex, 'CasoPrueba');
            $encf = $this->getValueByHeader($headersByColIndex, $rowValuesByColIndex, 'eNCF');

            $fileBase = $this->sanitizeFilename($caso ?: ($encf ?: ("row_" . $row)));

            $warnings = [];
            $bag = $this->bagBuilder->build($headersByColIndex, $rowValuesByColIndex, $schemaIndex, $warnings);

            $xml = $this->xmlBuilder->build($schemaIndex->root, $bag, $mode);

            if ($companyId > 0) {
                $this->storeXmlToPrivate('dgii/cert-acecf', $companyId, $fileBase, $xml, $row);
            }

            $zip->addFromString($fileBase . '.xml', $xml);

            if (!empty($warnings)) {
                logger()->warning("ACECF warnings (row {$row}, file={$fileBase}): " . implode(' || ', $warnings));
            }
        }

        $zip->close();

        $zipAbs = Storage::disk('local')->path($zipRel);

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

    private function storeXmlToPrivate(string $bucket, int $companyId, string $baseName, string $xml, int $row): string
    {
        $disk = Storage::disk('private');

        $dir = "{$bucket}/company_{$companyId}";
        $disk->makeDirectory($dir);

        // filename base + fallback por row para evitar colisiones
        $baseName = $this->sanitizeFilename($baseName);
        $filename = $baseName !== '' ? "{$baseName}.xml" : "row_{$row}.xml";

        // si existe, agrega sufijo incremental
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

        return $path; // por si luego quieres log/DB
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

        // fallback: primera hoja (comportamiento actual)
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
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name; // colapsa espacios
        return mb_strtolower($name);
    }

    private function loadAcecfSchemaIndex(): SchemaIndex
    {
        $disk = Storage::disk('public');
        $xsdRel = 'xsd/acecf.xsd';

        $xsdAbs = $disk->path($xsdRel); // <-- ruta real en el filesystem

        logger()->info('ACECF XSD resolve', [
            'disk'   => 'public',
            'xsdRel' => $xsdRel,
            'xsdAbs' => $xsdAbs,
            'exists' => $disk->exists($xsdRel),
            'size'   => $disk->exists($xsdRel) ? $disk->size($xsdRel) : null,
            'mtime'  => $disk->exists($xsdRel) ? date('c', $disk->lastModified($xsdRel)) : null,
        ]);

        if (!$disk->exists($xsdRel)) {
            throw new \RuntimeException("No existe XSD ACECF en storage/app/public/{$xsdRel}");
        }

        $content = $disk->get($xsdRel);

        logger()->info('ACECF XSD loaded', [
            'sha1' => sha1($content),
            'bytes' => strlen($content),
            'head' => substr($content, 0, 120), // solo para ver que es el archivo correcto
        ]);

        $hash = sha1($content);
        $cacheKey = "xsd_tree_public:acecf:{$hash}";

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($content) {
            $root = $this->xsdInlineParser->parseRootFromString($content);

            // Opcional: muestra root name para confirmar parse
            logger()->info('ACECF XSD parsed', [
                'rootName' => $root->name,
            ]);

            return new SchemaIndex($root);
        });
    }

    // ====================
    // Helpers
    // ====================

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
        if (!$rowObj) return $headers;

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
        if (!$rowObj) return $values;

        $cellIterator = $rowObj->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(true); // OK, pero sin colIndex manual

        foreach ($cellIterator as $cell) {
            $colLetter = $cell->getColumn(); // ej: "A", "B", "I"
            $colIndex  = Coordinate::columnIndexFromString($colLetter); // A=1, B=2...
            $values[$colIndex] = $cell->getValue();
        }

        return $values;
    }

    private function getValueByHeader(array $headersByColIndex, array $rowValuesByColIndex, string $wantedHeader): ?string
    {
        $wantedLower = mb_strtolower($wantedHeader);

        foreach ($headersByColIndex as $col => $h) {
            if ($h === null || $h === '') continue;

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
