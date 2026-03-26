<?php

namespace App\Services\Dgii\Wrapper\ExcelToXml\Xsd;

final class SchemaIndex
{
    /** @var array<string, string[]> leafName => [fullPath,...] */
    public array $leafPathsByName = [];

    /** @var array<string, string[]> leafNameLower => [fullPath,...] */
    public array $leafPathsByNameLower = [];

    /**
     * @var array<string, array<int, array{
     *     path:string,
     *     leaf:string,
     *     repeatable_depth:int,
     *     repeatable_ancestors:string[],
     *     nearest_repeatable_ancestor:?string
     * }>>
     */
    private array $leafCandidatesByName = [];

    /**
     * @var array<string, array<int, array{
     *     path:string,
     *     leaf:string,
     *     repeatable_depth:int,
     *     repeatable_ancestors:string[],
     *     nearest_repeatable_ancestor:?string
     * }>>
     */
    private array $leafCandidatesByNameLower = [];

    public function __construct(public XsdNode $root)
    {
        $this->build();
    }

    public function __wakeup(): void
    {
        $this->ensureBuilt();
    }

    /** @return string[] */
    public function findLeafPaths(string $name): array
    {
        $this->ensureBuilt();

        $name = trim($name);
        if ($name === '') return [];

        if (isset($this->leafPathsByName[$name])) {
            return $this->leafPathsByName[$name];
        }

        $lower = mb_strtolower($name);
        return $this->leafPathsByNameLower[$lower] ?? [];
    }

    /**
     * @return array<int, array{
     *     path:string,
     *     leaf:string,
     *     repeatable_depth:int,
     *     repeatable_ancestors:string[],
     *     nearest_repeatable_ancestor:?string
     * }>
     */
    public function findLeafCandidates(string $name): array
    {
        $this->ensureBuilt();

        $name = trim($name);
        if ($name === '') {
            return [];
        }

        if (isset($this->leafCandidatesByName[$name])) {
            return $this->leafCandidatesByName[$name];
        }

        $lower = mb_strtolower($name);
        return $this->leafCandidatesByNameLower[$lower] ?? [];
    }


    private function ensureBuilt(): void
    {
        if (!empty($this->leafPathsByName) && !empty($this->leafCandidatesByName)) {
            return;
        }

        $this->leafPathsByName = [];
        $this->leafPathsByNameLower = [];
        $this->leafCandidatesByName = [];
        $this->leafCandidatesByNameLower = [];

        $this->build();
    }

    private function build(): void
    {
        $this->walk($this->root, [], []);
    }

    /**
     * @param string[] $path
     * @param string[] $repeatableAncestors
     */
    private function walk(XsdNode $node, array $path, array $repeatableAncestors): void
    {
        if ($node->isAny) return;

        $path[] = $node->name;

        $isRepeatable = ($node->maxOccurs === null || $node->maxOccurs > 1);
        if ($isRepeatable) {
            $repeatableAncestors[] = $node->name;
        }

        if (count($node->children) === 0) {
            $fullPath = implode('.', $path);

            $this->leafPathsByName[$node->name] ??= [];
            $this->leafPathsByName[$node->name][] = $fullPath;

            $lower = mb_strtolower($node->name);
            $this->leafPathsByNameLower[$lower] ??= [];
            $this->leafPathsByNameLower[$lower][] = $fullPath;

            $candidate = [
                'path' => $fullPath,
                'leaf' => $node->name,
                'repeatable_depth' => count($repeatableAncestors),
                'repeatable_ancestors' => array_values($repeatableAncestors),
                'nearest_repeatable_ancestor' => !empty($repeatableAncestors)
                    ? $repeatableAncestors[array_key_last($repeatableAncestors)]
                    : null,
            ];

            $this->leafCandidatesByName[$node->name] ??= [];
            $this->leafCandidatesByName[$node->name][] = $candidate;

            $this->leafCandidatesByNameLower[$lower] ??= [];
            $this->leafCandidatesByNameLower[$lower][] = $candidate;
            return;
        }

        foreach ($node->children as $child) {
            $this->walk($child, $path, $repeatableAncestors);
        }
    }
}
