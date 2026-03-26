<?php

namespace App\Services\Dgii\Wrapper\ExcelToXml;

use App\Services\Dgii\Wrapper\ExcelToXml\Xsd\SchemaIndex;

final class XsdBagValidator
{
    /**
     * Valida en esta etapa del wrapper solo una cosa:
     * si una celda del Excel viene poblada (no vacía y no #e), ese dato no puede perderse
     * al resolver el header hacia el bag.
     *
     * NO valida campos requeridos del XSD que se completan después (ej. FechaHoraFirma / firma).
     *
     * @return string[]
     */
    public function validateResolvedSourceData(
        array $headersByColIndex,
        array $rowValuesByColIndex,
        SchemaIndex $schemaIndex,
        RowBagBuilder $bagBuilder,
        array $bag,
    ): array {
        $errors = [];

        foreach ($rowValuesByColIndex as $colIndex => $valueRaw) {
            $headerRaw = $headersByColIndex[$colIndex] ?? null;
            if ($headerRaw === null) {
                continue;
            }

            $headerRaw = trim((string) $headerRaw);
            if ($headerRaw === '') {
                continue;
            }

            if ($bagBuilder->isIgnoredHeader($headerRaw)) {
                continue;
            }

            if ($bagBuilder->isSkippableCellValue($valueRaw)) {
                continue;
            }

            $warnings = [];
            [$path, $idxKey] = $bagBuilder->resolveHeaderToPathAndIdxKey($headerRaw, $schemaIndex, $warnings);

            if ($path === null) {
                $errors[] = !empty($warnings)
                    ? implode(' || ', $warnings)
                    : "No se pudo resolver el header '{$headerRaw}' hacia una ruta del XSD.";
                continue;
            }

            $value = trim((string) $valueRaw);
            $bagValue = $bag[$path][$idxKey] ?? null;

            if ($bagValue === null || trim((string) $bagValue) === '') {
                $errors[] = sprintf(
                    "Dato poblado en Excel perdido en wrapper: header '%s' => %s[%s] con valor '%s'.",
                    $headerRaw,
                    $path,
                    $idxKey,
                    $value
                );
            }
        }

        return array_values(array_unique($errors));
    }
}
