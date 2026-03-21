<?php

namespace App\Services\Dgii\Wrapper\ExcelToXml;

use App\Services\Dgii\Wrapper\ExcelToXml\Xsd\XsdNode;
use XMLWriter;

final class XmlFromXsdBuilder
{
    public const FORCE_EMPTY_ELEMENT = '__FORCE_EMPTY_ELEMENT__';

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

    private function emitNode(XMLWriter $w, XsdNode $node, array $bag, array $path, array $ctxIndices, string $mode): bool
    {
        if ($node->isAny) {
            return false;
        }

        $currentPath = array_merge($path, [$node->name]);
        $fullPath = implode('.', $currentPath);

        $isRepeatable = ($node->maxOccurs === null || $node->maxOccurs > 1);

        // LEAF
        if (count($node->children) === 0) {
            if ($isRepeatable) {
                $repeatCount = $this->inferRepeatCountForNode($bag, $fullPath . '.', $ctxIndices);
                $repeatCount = max($repeatCount, $this->inferRepeatCountForLeaf($bag, $fullPath, $ctxIndices));

                if ($repeatCount < 1) {
                    return false;
                }

                $emitted = false;

                for ($i = 1; $i <= $repeatCount; $i++) {
                    $idxKey = $this->idxKeyFromCtx(array_merge($ctxIndices, [$i]));
                    $value = $bag[$fullPath][$idxKey] ?? null;

                    if ($this->isSkippableValue($value)) {
                        continue;
                    }

                    $w->startElement($node->name);

                    if ($value !== self::FORCE_EMPTY_ELEMENT) {
                        $w->text((string) $value);
                    }

                    $w->endElement();
                    $emitted = true;
                }

                return $emitted;
            }

            // ✅ Importante:
            // usar SOLO el índice del contexto actual.
            // No hacer fallback a ['1'], porque eso hace que
            // Item[5] herede valores de Item[1] si el campo viene vacío.
            $idxKey = $this->idxKeyFromCtx($ctxIndices);
            $value = $bag[$fullPath][$idxKey] ?? null;

            if ($this->isSkippableValue($value)) {
                return false;
            }

            $w->startElement($node->name);

            if ($value !== self::FORCE_EMPTY_ELEMENT) {
                $w->text((string) $value);
            }

            $w->endElement();
            return true;
        }

        // COMPLEX NODE
        $repeatCount = 1;
        if ($isRepeatable) {
            $repeatCount = $this->inferRepeatCountForNode($bag, $fullPath . '.', $ctxIndices);
            if ($repeatCount < 1) {
                $repeatCount = 1;
            }
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

    private function inferRepeatCountForNode(array $bag, string $prefixPath, array $ctxIndices): int
    {
        $pos = count($ctxIndices);
        $max = 0;

        foreach ($bag as $leafPath => $indexedValues) {
            if (!str_starts_with($leafPath . '.', $prefixPath)) {
                continue;
            }

            foreach ($indexedValues as $idxKey => $val) {
                if ($this->isSkippableValue($val)) {
                    continue;
                }

                $vec = $this->idxKeyToVector($idxKey);
                if (!$this->matchesPrefix($vec, $ctxIndices)) {
                    continue;
                }

                if (isset($vec[$pos])) {
                    $max = max($max, (int) $vec[$pos]);
                }
            }
        }

        return $max;
    }

    private function inferRepeatCountForLeaf(array $bag, string $leafPath, array $ctxIndices): int
    {
        $pos = count($ctxIndices);
        $max = 0;

        foreach (($bag[$leafPath] ?? []) as $idxKey => $val) {
            if ($this->isSkippableValue($val)) {
                continue;
            }

            $vec = $this->idxKeyToVector($idxKey);
            if (!$this->matchesPrefix($vec, $ctxIndices)) {
                continue;
            }

            if (isset($vec[$pos])) {
                $max = max($max, (int) $vec[$pos]);
            } else {
                $max = max($max, 1);
            }
        }

        return $max;
    }

    private function idxKeyFromCtx(array $ctxIndices): string
    {
        if (empty($ctxIndices)) {
            return '1';
        }

        return implode('.', array_map('intval', $ctxIndices));
    }

    private function idxKeyToVector(string $idxKey): array
    {
        $idxKey = trim($idxKey);
        if ($idxKey === '' || $idxKey === '1') {
            return [1];
        }

        $parts = explode('.', $idxKey);
        $out = [];

        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }

            $out[] = (int) $p;
        }

        return $out ?: [1];
    }

    private function matchesPrefix(array $vec, array $prefix): bool
    {
        $n = count($prefix);

        for ($i = 0; $i < $n; $i++) {
            if (!isset($vec[$i]) || (int) $vec[$i] !== (int) $prefix[$i]) {
                return false;
            }
        }

        return true;
    }

    private function isSkippableValue(mixed $value): bool
    {
        return $value === null || $value === '' || $value === '#e';
    }
}