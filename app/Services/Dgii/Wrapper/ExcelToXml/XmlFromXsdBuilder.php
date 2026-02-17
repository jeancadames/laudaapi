<?php

namespace App\Services\Dgii\Wrapper\ExcelToXml;

use App\Services\Dgii\Wrapper\ExcelToXml\Xsd\XsdNode;
use XMLWriter;

final class XmlFromXsdBuilder
{
    public function build(XsdNode $root, array $bag, string $mode = 'compact'): string
    {
        $w = new XMLWriter();
        $w->openMemory();
        $w->startDocument('1.0', 'utf-8');

        $ok = $this->emitNode($w, $root, $bag, [], [], $mode);

        if (!$ok && $mode === 'compact') {
            $w->startElement($root->name);
            $w->endElement();
        }

        $w->endDocument();
        return $w->outputMemory();
    }

    /**
     * @param array<int,int> $ctxIndices  índices de repeatables ya elegidos (ej: [1,1])
     */
    private function emitNode(XMLWriter $w, XsdNode $node, array $bag, array $path, array $ctxIndices, string $mode): bool
    {
        if ($node->isAny) return false;

        $currentPath = array_merge($path, [$node->name]);
        $fullPath = implode('.', $currentPath);

        $isRepeatable = ($node->maxOccurs === null || $node->maxOccurs > 1);

        // LEAF
        if (count($node->children) === 0) {
            // Leaf repeatable => emitir múltiples <TelefonoEmisor>...</TelefonoEmisor>
            if ($isRepeatable) {
                $repeatCount = $this->inferRepeatCountForNode($bag, $fullPath . '.', $ctxIndices); // subtree pref
                // para leaf, también puede venir directo en $bag[$fullPath]
                $repeatCount = max($repeatCount, $this->inferRepeatCountForLeaf($bag, $fullPath, $ctxIndices));

                if ($repeatCount < 1) return false;

                $emitted = false;
                for ($i = 1; $i <= $repeatCount; $i++) {
                    $idxKey = $this->idxKeyFromCtx(array_merge($ctxIndices, [$i]));
                    $value = $bag[$fullPath][$idxKey] ?? null;

                    if ($value === null || $value === '' || $value === '#e') continue;

                    $w->startElement($node->name);
                    $w->text((string) $value);
                    $w->endElement();
                    $emitted = true;
                }

                return $emitted;
            }

            // Leaf no-repeatable: usa ctx actual
            $idxKey = $this->idxKeyFromCtx($ctxIndices);
            $value = $bag[$fullPath][$idxKey] ?? $bag[$fullPath]['1'] ?? null;

            if ($value === null || $value === '' || $value === '#e') {
                return false;
            }

            $w->startElement($node->name);
            $w->text((string) $value);
            $w->endElement();
            return true;
        }

        // COMPLEX NODE
        $repeatCount = 1;
        if ($isRepeatable) {
            // buscamos cuántas instancias hay para este repeatable dentro del contexto actual
            $repeatCount = $this->inferRepeatCountForNode($bag, $fullPath . '.', $ctxIndices);
            if ($repeatCount < 1) $repeatCount = 1;
        }

        $emittedAnyInstance = false;

        for ($i = 1; $i <= $repeatCount; $i++) {
            $tmp = new XMLWriter();
            $tmp->openMemory();

            $tmp->startElement($node->name);

            $childEmitted = false;

            $nextCtx = $ctxIndices;
            if ($isRepeatable) {
                $nextCtx[] = $i;
            }

            foreach ($node->children as $child) {
                $childEmitted = $this->emitNode($tmp, $child, $bag, $currentPath, $nextCtx, $mode) || $childEmitted;
            }

            $tmp->endElement();

            if ($childEmitted) {
                $w->writeRaw($tmp->outputMemory());
                $emittedAnyInstance = true;
            }
        }

        return $emittedAnyInstance;
    }

    /**
     * Determina cuántas repeticiones hay para un repeatable node, mirando valores bajo su subtree.
     * - $prefixPath debe ser "A.B.C." (con punto al final)
     * - $ctxIndices define el contexto externo (ej: dentro de Item[2] => ctxIndices=[2])
     */
    private function inferRepeatCountForNode(array $bag, string $prefixPath, array $ctxIndices): int
    {
        $pos = count($ctxIndices); // el índice de "este node" está en esta posición
        $max = 0;

        foreach ($bag as $leafPath => $indexedValues) {
            if (!str_starts_with($leafPath . '.', $prefixPath)) continue;

            foreach ($indexedValues as $idxKey => $val) {
                if ($val === null || $val === '' || $val === '#e') continue;

                $vec = $this->idxKeyToVector($idxKey);
                if (!$this->matchesPrefix($vec, $ctxIndices)) continue;

                if (isset($vec[$pos])) {
                    $max = max($max, (int) $vec[$pos]);
                }
            }
        }

        return $max;
    }

    /**
     * Caso leaf repeatable directo (ej: TelefonoEmisor) que vive en $bag[$leafPath]
     */
    private function inferRepeatCountForLeaf(array $bag, string $leafPath, array $ctxIndices): int
    {
        $pos = count($ctxIndices);
        $max = 0;

        foreach (($bag[$leafPath] ?? []) as $idxKey => $val) {
            if ($val === null || $val === '' || $val === '#e') continue;

            $vec = $this->idxKeyToVector($idxKey);
            if (!$this->matchesPrefix($vec, $ctxIndices)) continue;

            if (isset($vec[$pos])) {
                $max = max($max, (int) $vec[$pos]);
            } else {
                // si el vector es exactamente ctx, cuenta como 1
                $max = max($max, 1);
            }
        }

        return $max;
    }

    private function idxKeyFromCtx(array $ctxIndices): string
    {
        if (empty($ctxIndices)) return '1';
        return implode('.', array_map('intval', $ctxIndices));
    }

    private function idxKeyToVector(string $idxKey): array
    {
        $idxKey = trim($idxKey);
        if ($idxKey === '' || $idxKey === '1') return [1];

        $parts = explode('.', $idxKey);
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') continue;
            $out[] = (int) $p;
        }
        return $out ?: [1];
    }

    private function matchesPrefix(array $vec, array $prefix): bool
    {
        $n = count($prefix);
        for ($i = 0; $i < $n; $i++) {
            if (!isset($vec[$i]) || (int) $vec[$i] !== (int) $prefix[$i]) return false;
        }
        return true;
    }
}
