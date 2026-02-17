<?php

namespace App\Services\Dgii\Wrapper\ExcelToXml;

use App\Services\Dgii\Wrapper\ExcelToXml\Xsd\SchemaIndex;

final class RowBagBuilder
{
    /**
     * Headers del Excel que NO van al XML/XSD (meta para naming/logística)
     */
    private array $ignoreHeadersLower = [
        'casoprueba' => true,
    ];

    /**
     * Alias Excel -> XSD (case-insensitive)
     */
    private array $aliases = [
        'encf' => 'eNCF',
        'montosubrecargo' => 'MontoSubRecargo',
        // NumeroLineaDoR en el XSD sigue llamándose NumeroLinea
        'numerolineador' => 'NumeroLinea',
    ];

    /**
     * Preferencia por header ORIGINAL (antes de alias):
     * - NumeroLinea     -> Item.NumeroLinea
     * - NumeroLineaDoR  -> DoR.NumeroLinea
     */
    private array $preferredByOriginalHeader = [
        'numerolinea' => 'ECF.DetallesItems.Item.NumeroLinea',
        'numerolineador' => 'ECF.DescuentosORecargos.DescuentoORecargo.NumeroLinea',
    ];

    /**
     * Preferencia general por leaf (fallback si queda ambiguo)
     */
    private array $preferredLeafPath = [
        'numerolinea' => 'ECF.DetallesItems.Item.NumeroLinea',
    ];

    /**
     * Bag format (idxKey con puntos):
     * [
     *   '...TelefonoEmisor' => ['1' => '809...', '2' => '829...'],
     *   '...TipoCodigo' => ['1.1' => 'Interno', '2.1' => 'Interno'],
     * ]
     */
    public function build(array $headersByColIndex, array $rowValuesByColIndex, SchemaIndex $index, array &$warnings = []): array
    {
        $bag = [];

        foreach ($rowValuesByColIndex as $colIndex => $valueRaw) {
            $headerRaw = $headersByColIndex[$colIndex] ?? null;
            if (!$headerRaw) continue;

            $headerRaw = $this->normalizeHeader((string)$headerRaw);
            if ($headerRaw === '') continue;

            $baseLower = mb_strtolower($this->stripAllIndicesAndPath($headerRaw));

            // Ignorar meta headers sin warning (CasoPrueba)
            $originalBaseLower = mb_strtolower($this->stripAllIndicesAndPath($headerRaw));
            if (isset($this->ignoreHeadersLower[$originalBaseLower])) {
                continue;
            }

            $value = $this->normalizeCellValue($valueRaw);

            // Regla máxima: #e => no se incluye
            if ($value === '#e') continue;
            if ($value === '' || $value === null) continue;

            [$path, $idxKey] = $this->parseHeaderToPathAndIdxKey($headerRaw, $index, $warnings);

            if ($path === null) continue;

            $bag[$path][$idxKey] = $value;
        }

        return $bag;
    }

    private function normalizeHeader(string $h): string
    {
        $h = str_replace("\xC2\xA0", ' ', $h);
        return trim($h);
    }

    private function normalizeCellValue($value): ?string
    {
        if ($value === null) return null;
        return trim((string)$value);
    }

    /**
     * @return array{0:?string,1:string} [path, idxKey]
     */
    private function parseHeaderToPathAndIdxKey(string $header, SchemaIndex $index, array &$warnings): array
    {
        // RUTA explícita: A.B.C[2].D[1]  (si algún día la usas)
        if (str_contains($header, '.')) {
            $segments = explode('.', $header);

            $names = [];
            $indices = [];

            foreach ($segments as $seg) {
                $seg = trim($seg);
                if ($seg === '') continue;

                [$name, $segIdxs] = $this->splitNameAndIndices($seg);
                $name = $this->applyAlias($name);

                $names[] = $name;
                foreach ($segIdxs as $i) $indices[] = $i;
            }

            if (empty($indices)) $indices = [1];
            return [implode('.', $names), $this->indicesToIdxKey($indices)];
        }

        // Header plano: ENCF, NumeroLinea[2], TipoCodigo[4][1], NumeroLineaDoR[3], etc.
        [$originalName, $indices] = $this->splitNameAndIndices($header);
        if (empty($indices)) $indices = [1];

        $originalLower = mb_strtolower(trim($originalName));

        // Canonical por alias
        $leafName = $this->applyAlias($originalName);

        $paths = $index->findLeafPaths($leafName);

        if (count($paths) === 1) {
            return [$paths[0], $this->indicesToIdxKey($indices)];
        }

        if (count($paths) > 1) {
            // 1) Preferencia por header original (NumeroLinea vs NumeroLineaDoR)
            $pref = $this->preferredByOriginalHeader[$originalLower] ?? null;
            if ($pref && in_array($pref, $paths, true)) {
                return [$pref, $this->indicesToIdxKey($indices)];
            }

            // 2) Preferencia general por leaf
            $prefLeaf = $this->preferredLeafPath[mb_strtolower($leafName)] ?? null;
            if ($prefLeaf && in_array($prefLeaf, $paths, true)) {
                return [$prefLeaf, $this->indicesToIdxKey($indices)];
            }

            $warnings[] = "Header ambiguo '{$header}' aparece en múltiples rutas: " . implode(' | ', $paths);
            return [null, '1'];
        }

        $warnings[] = "Header '{$header}' no encontrado como leaf en XSD.";
        return [null, '1'];
    }

    /**
     * "TipoCodigo[2][1]" => ["TipoCodigo", [2,1]]
     * "MontoPago[2]" => ["MontoPago", [2]]
     * "ENCF" => ["ENCF", []]
     *
     * @return array{0:string,1:int[]}
     */
    private function splitNameAndIndices(string $seg): array
    {
        $seg = trim($seg);

        if (preg_match('/^([^\[]+)((?:\[\d+\])+)$/', $seg, $m)) {
            $name = trim($m[1]);
            preg_match_all('/\[(\d+)\]/', $m[2], $mm);
            $indices = array_map('intval', $mm[1] ?? []);
            return [$name, $indices];
        }

        return [$seg, []];
    }

    private function applyAlias(string $name): string
    {
        $key = mb_strtolower(trim($name));
        return $this->aliases[$key] ?? trim($name);
    }

    private function indicesToIdxKey(array $indices): string
    {
        if (empty($indices)) return '1';
        return implode('.', array_map('intval', $indices));
    }

    /**
     * Quita ruta y todos los índices: "ECF.IdDoc.eNCF[1]" => "eNCF"
     * "TipoCodigo[1][1]" => "TipoCodigo"
     */
    private function stripAllIndicesAndPath(string $header): string
    {
        $h = $header;
        if (str_contains($h, '.')) {
            $parts = explode('.', $h);
            $h = end($parts) ?: $h;
        }
        $h = preg_replace('/\[\d+\]/', '', $h) ?? $h;
        return trim($h);
    }
}
