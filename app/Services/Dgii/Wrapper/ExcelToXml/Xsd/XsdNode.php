<?php

namespace App\Services\Dgii\Wrapper\ExcelToXml\Xsd;

final class XsdNode
{
    /**
     * @param XsdNode[] $children
     */
    public function __construct(
        public string $name,
        public int $minOccurs = 0,
        public ?int $maxOccurs = 1, // null => unbounded
        public array $children = [],
        public bool $isAny = false, // xs:any
    ) {}
}
