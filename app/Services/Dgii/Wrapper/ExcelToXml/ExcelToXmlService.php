<?php

namespace App\Services\Dgii\Wrapper\ExcelToXml;

use App\Services\Dgii\Wrapper\ExcelToXml\Xsd\SchemaIndex;
use App\Services\Dgii\Wrapper\ExcelToXml\Xsd\XsdInlineParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\File;
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
    ) {}

    /**
     * @return string relative storage path like "output/xml_YYYYMMDD_HHMMSS_xxx.zip"
     */
    public function convertToZip(string $excelFullPath, string $mode = 'compact', int $companyId = 0): string 
    {
        $disk = Storage::disk('private');

        $baseDir = "dgii/cert-ecf/company_{$companyId}";

        // ✅ Reset: borrar todo lo anterior de ese cliente (solo ECF)
        if ($disk->exists($baseDir)) {
            $disk->deleteDirectory($baseDir);
        }
        
        $disk->makeDirectory($baseDir);

        $reader = IOFactory::createReaderForFile($excelFullPath);
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($excelFullPath);
        $sheet = $this->getSheetByNameInsensitive($spreadsheet, 'ecf');
        // Header row: fila 1
        $headersByColIndex = $this->readHeaderRow($sheet, 1);

        // Preparar zip (MISMO disk local => storage/app/private)
        $ts = now()->format('Ymd_His');
        $zipRel = "output/xml_{$ts}_" . bin2hex(random_bytes(4)) . ".zip";

        // Asegurar carpetas en el disk correcto
        Storage::disk('local')->makeDirectory('output');
        Storage::disk('local')->makeDirectory('tmp');

        // IMPORTANTE: path absoluto desde el disk local (private)
        $zipFull = Storage::disk('local')->path($zipRel);

        // Forzar temp de ZipArchive dentro de storage/app/private/tmp
        $this->forceZipTempDir(Storage::disk('local')->path('tmp'));

        $zip = new ZipArchive();
        if ($zip->open($zipFull, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("No se pudo crear ZIP: {$zipFull}");
        }

        $highestRow = $sheet->getHighestDataRow();

        // Cachear árboles XSD por tipo
        $schemaCache = [];

        for ($row = 2; $row <= $highestRow; $row++) {
            // Leer solo celdas existentes para NO iterar miles vacías
            $rowValuesByColIndex = $this->readRowValuesExistingCells($sheet, $row);

            // CasoPrueba para filename
            $casoPrueba = $this->getValueByHeader($headersByColIndex, $rowValuesByColIndex, 'CasoPrueba');
            $fileBase = $this->sanitizeFilename($casoPrueba ?: ("row_" . $row));

            // Determinar TipoeCF para escoger XSD
            $tipo = $this->getValueByHeader($headersByColIndex, $rowValuesByColIndex, 'TipoeCF');

            if (!$tipo) {
                $tipo = '31';
            }

            // Cargar schema por tipo y cachearlo
            if (!isset($schemaCache[$tipo])) {
                $schemaCache[$tipo] = $this->loadSchemaIndexForTipo($tipo);
            }

            /** @var SchemaIndex $schemaIndex */
            $schemaIndex = $schemaCache[$tipo];

            $warnings = [];
            $bag = $this->bagBuilder->build($headersByColIndex, $rowValuesByColIndex, $schemaIndex, $warnings);

            // Construir XML jerárquico con XSD
            $xml = $this->xmlBuilder->build($schemaIndex->root, $bag, $mode);

            if ($companyId > 0) {
                $this->storeXmlToPrivate('/dgii/cert-ecf', $companyId, $fileBase, $xml, $row);
            }

            // Guardar dentro del zip
            $zip->addFromString($fileBase . '.xml', $xml);

            if (!empty($warnings)) {
                logger()->warning("ExcelToXml warnings (row {$row}, CasoPrueba={$fileBase}): " . implode(' || ', $warnings));
            }
        }

        $zip->close();

        // DEBUG: confirma que quedó físicamente en private/output
        logger()->info('ZIP terminado', [
            'zipRel' => $zipRel,
            'zipFull' => $zipFull,
            'exists' => file_exists($zipFull),
            'size' => file_exists($zipFull) ? filesize($zipFull) : null,
        ]);

        // liberar memoria de spreadsheet
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


    private function forceZipTempDir(string $tmpDir): void
    {
        File::ensureDirectoryExists($tmpDir);
        @chmod($tmpDir, 0775);

        putenv("TMPDIR={$tmpDir}");
        putenv("TEMP={$tmpDir}");
        putenv("TMP={$tmpDir}");
        @ini_set('sys_temp_dir', $tmpDir);
    }

    private function loadSchemaIndexForTipo(string $tipo): \App\Services\Dgii\Wrapper\ExcelToXml\Xsd\SchemaIndex
    {
        $disk = Storage::disk('public');

        $xsdRel = "xsd/{$tipo}.xsd"; // storage/app/public/xsd/31.xsd

        if (!$disk->exists($xsdRel)) {
            throw new \RuntimeException("No existe XSD para TipoeCF={$tipo} en storage/app/public/{$xsdRel}");
        }

        $content = $disk->get($xsdRel);

        $hash = sha1($content);
        $cacheKey = "xsd_tree_public:{$tipo}:{$hash}";

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addDays(30), function () use ($content) {
            $root = $this->xsdParser->parseRootFromString($content);
            return new \App\Services\Dgii\Wrapper\ExcelToXml\Xsd\SchemaIndex($root);
        });
    }

    private function readHeaderRow($sheet, int $row): array
    {
        $headers = [];

        $rowObj = $sheet->getRowIterator($row, $row)->current();
        $cellIterator = $rowObj->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(true);

        foreach ($cellIterator as $cell) {
            $colLetter = $cell->getColumn();
            $colIndex = Coordinate::columnIndexFromString($colLetter);
            $headers[$colIndex] = trim((string)$cell->getValue());
        }

        return $headers;
    }

    private function readRowValuesExistingCells($sheet, int $row): array
    {
        $values = [];

        $rowObj = $sheet->getRowIterator($row, $row)->current();
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
        // match exacto
        foreach ($headersByColIndex as $colIndex => $header) {
            if ($header === $targetHeader || str_ends_with($header, '.' . $targetHeader)) {
                if (!array_key_exists($colIndex, $rowValuesByColIndex)) continue;
                $v = trim((string)$rowValuesByColIndex[$colIndex]);
                if ($v === '' || $v === '#e') return null;
                return $v;
            }
        }
        return null;
    }

    private function sanitizeFilename(string $name): string
    {
        $name = trim($name);
        if ($name === '') return 'sin_nombre';

        // reemplazar caracteres raros
        $name = preg_replace('/[^\w\-\.]+/u', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        $name = trim($name, '_');

        return $name ?: 'sin_nombre';
    }
}