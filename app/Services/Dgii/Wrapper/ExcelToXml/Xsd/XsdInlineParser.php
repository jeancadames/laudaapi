<?php

namespace App\Services\Dgii\Wrapper\ExcelToXml\Xsd;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

final class XsdInlineParser
{
    public function parseRootFromString(string $xsdContent): XsdNode
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xsdContent);

        $xp = new DOMXPath($dom);
        $xp->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');

        /** @var DOMElement|null $rootEl */
        $rootEl = $xp->query('/xs:schema/xs:element')->item(0);
        if (!$rootEl) {
            throw new RuntimeException('No root xs:element found in XSD.');
        }

        return $this->parseElement($xp, $rootEl);
    }

    private function parseElement(DOMXPath $xp, DOMElement $el): XsdNode
    {
        $name = $el->getAttribute('name');
        if ($name === '') {
            // puede ser xs:element ref=... (no soportado aquí)
            // si lo necesitas luego, se implementa con resolución de refs/includes
            $name = $el->getAttribute('ref');
        }

        $min = $el->getAttribute('minOccurs') !== '' ? (int) $el->getAttribute('minOccurs') : 1;
        $maxRaw = $el->getAttribute('maxOccurs');
        $max = $maxRaw === '' ? 1 : ($maxRaw === 'unbounded' ? null : (int) $maxRaw);

        $node = new XsdNode($name, $min, $max);

        $seq = $xp->query('./xs:complexType/xs:sequence', $el)->item(0);
        if ($seq instanceof DOMElement) {
            foreach ($xp->query('./xs:element|./xs:any', $seq) as $child) {
                if (!($child instanceof DOMElement)) continue;

                if ($child->localName === 'any') {
                    $node->children[] = new XsdNode('__ANY__', 0, 1, [], true);
                    continue;
                }

                $node->children[] = $this->parseElement($xp, $child);
            }
        }

        return $node;
    }
}
