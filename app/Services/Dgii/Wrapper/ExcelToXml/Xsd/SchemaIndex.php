<?php

namespace App\Services\Dgii\Wrapper\ExcelToXml\Xsd;

final class SchemaIndex
{
    /** @var array<string, string[]> leafName => [fullPath,...] */
    public array $leafPathsByName = [];

    /** @var array<string, string[]> leafNameLower => [fullPath,...] */
    public array $leafPathsByNameLower = [];

    public function __construct(public XsdNode $root)
    {
        $this->build();
    }

    /** @return string[] */
    public function findLeafPaths(string $name): array
    {
        $name = trim($name);
        if ($name === '') return [];

        if (isset($this->leafPathsByName[$name])) {
            return $this->leafPathsByName[$name];
        }

        $lower = mb_strtolower($name);
        return $this->leafPathsByNameLower[$lower] ?? [];
    }

    private function build(): void
    {
        $this->walk($this->root, []);
    }

    private function walk(XsdNode $node, array $path): void
    {
        if ($node->isAny) return;

        $path[] = $node->name;

        if (count($node->children) === 0) {
            $fullPath = implode('.', $path);

            $this->leafPathsByName[$node->name] ??= [];
            $this->leafPathsByName[$node->name][] = $fullPath;

            $lower = mb_strtolower($node->name);
            $this->leafPathsByNameLower[$lower] ??= [];
            $this->leafPathsByNameLower[$lower][] = $fullPath;
            return;
        }

        foreach ($node->children as $child) {
            $this->walk($child, $path);
        }
    }
}
