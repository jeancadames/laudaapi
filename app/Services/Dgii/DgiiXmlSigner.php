<?php

namespace App\Services\Dgii;

use App\Services\Dgii\Wrapper\ExcelToXml\Xsd\XsdInlineParser;
use App\Services\Dgii\Wrapper\ExcelToXml\Xsd\XsdNode;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

final class DgiiXmlSigner
{
    public function __construct(
        private readonly XsdInlineParser $xsdParser = new XsdInlineParser(),
    ) {}

    public function signAnyXml(string $xml, string $p12Binary, string $p12Password, ?string $kind = null): string    
    {
        $xml = $this->stripBom($xml);
        if (trim($xml) === '') {
            throw new RuntimeException('XML vacío.');
        }

        if ($kind === 'ecf') {
            $xml = $this->prepareXmlForSigning($xml);
        }

        $tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'dgii_xml_' . Str::random(12);

        if (!@mkdir($tmpDir, 0700, true) && !is_dir($tmpDir)) {
            throw new RuntimeException('No se pudo crear el directorio temporal para firmar.');
        }

        $passFile   = $tmpDir . DIRECTORY_SEPARATOR . 'pass.txt';
        $p12File    = $tmpDir . DIRECTORY_SEPARATOR . 'cert.p12';
        $xmlFile    = $tmpDir . DIRECTORY_SEPARATOR . 'input.xml';
        $signedFile = $tmpDir . DIRECTORY_SEPARATOR . 'output_signed.xml';

        try {
            file_put_contents($passFile, (string) $p12Password);
            file_put_contents($p12File, $this->normalizePkcs12($p12Binary));
            file_put_contents($xmlFile, $xml);

            $nodeBin = config('dgii.node_bin', 'node');
            $script  = base_path('node_scripts/signCert.js');

            if (!is_file($script)) {
                throw new RuntimeException("No se encontró el script de firma Node: {$script}");
            }

            $cmd = [$nodeBin, $script, $passFile, $p12File, $xmlFile, $signedFile];

            $process = new Process($cmd, base_path());
            $process->setTimeout((float) config('dgii.node_sign_timeout', 25));
            $process->run();

            if (!$process->isSuccessful()) {
                $err = trim($process->getErrorOutput() ?: $process->getOutput());
                $err = $err !== '' ? $err : 'Node signing failed (sin salida).';
                throw new RuntimeException($err);
            }

            if (!is_file($signedFile)) {
                throw new RuntimeException('Node no generó el archivo signed XML.');
            }

            $signedXml = (string) file_get_contents($signedFile);
            $signedXml = $this->stripBom($signedXml);

            if (trim($signedXml) === '') {
                throw new RuntimeException('XML firmado final vacío.');
            }

            return $signedXml;

        } finally {
            $this->safeCleanup($tmpDir);
        }
    }

    private function prepareXmlForSigning(string $xml): string
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;

        $prev = libxml_use_internal_errors(true);

        try {
            if (!$dom->loadXML($xml, LIBXML_NONET)) {
                $errors = array_map(
                    fn($e) => trim($e->message),
                    libxml_get_errors()
                );
                libxml_clear_errors();

                throw new RuntimeException(
                    'No se pudo parsear el XML antes de firmar: ' . implode(' | ', $errors)
                );
            }
        } finally {
            libxml_use_internal_errors($prev);
        }

        $tipoeCF = $this->extractTipoeCF($dom);

        // Si no es un XML ECF con TipoeCF, no tocamos nada.
        if ($tipoeCF === null) {
            return $xml;
        }

        $xsdRoot = $this->loadRootFromTipoeCF($tipoeCF);

        $fechaHoraFirmaPath = $this->findPathToLeaf($xsdRoot, 'FechaHoraFirma');
        if ($fechaHoraFirmaPath === null || count($fechaHoraFirmaPath) < 2) {
            return $xml;
        }

        $parentPath = array_slice($fechaHoraFirmaPath, 0, -1);
        $leafName   = end($fechaHoraFirmaPath);

        $parentSchemaNode = $this->findNodeByPath($xsdRoot, $parentPath);
        if (!$parentSchemaNode) {
            throw new RuntimeException('No se pudo resolver el nodo padre de FechaHoraFirma en el XSD.');
        }

        $parentDom = $this->findDomElementByPath($dom, $parentPath);
        if (!$parentDom) {
            throw new RuntimeException(
                'No se encontró en el XML el nodo padre donde debe insertarse FechaHoraFirma: ' . implode('.', $parentPath)
            );
        }

        $timestamp = now('America/Santo_Domingo')->format('d-m-Y H:i:s');

        $existing = $this->findDirectChildElement($parentDom, $leafName);

        if ($existing) {
            // ✅ Si ya existe, la actualizamos al instante real de firma
            $existing->nodeValue = $timestamp;
        } else {
            // ✅ Si no existe, la insertamos en la posición correcta según el XSD
            $newElement = $this->createElementLikeParentNamespace($dom, $parentDom, $leafName);
            $newElement->nodeValue = $timestamp;

            $this->insertChildRespectingXsdOrder($parentDom, $newElement, $parentSchemaNode, $leafName);
        }

        $result = $dom->saveXML();
        if (!is_string($result) || trim($result) === '') {
            throw new RuntimeException('No se pudo reconstruir el XML preparado para firma.');
        }

        return $this->stripBom($result);
    }

    private function extractTipoeCF(DOMDocument $dom): ?string
    {
        $xp = new DOMXPath($dom);
        $node = $xp->query('//*[local-name()="TipoeCF"][1]')->item(0);

        if (!$node) {
            return null;
        }

        $value = trim((string) $node->textContent);
        return $value !== '' ? $value : null;
    }

    private function loadRootFromTipoeCF(string $tipoeCF): XsdNode
    {
        $disk = Storage::disk('public');
        $xsdRel = "xsd/{$tipoeCF}.xsd";

        if (!$disk->exists($xsdRel)) {
            throw new RuntimeException("No existe XSD para TipoeCF={$tipoeCF} en storage/app/public/{$xsdRel}");
        }

        $content = (string) $disk->get($xsdRel);
        if (trim($content) === '') {
            throw new RuntimeException("El XSD {$xsdRel} está vacío.");
        }

        return $this->xsdParser->parseRootFromString($content);
    }

    /**
     * Devuelve la ruta completa al leaf, ej:
     * ['ECF', 'Encabezado', 'IdDoc', 'FechaHoraFirma']
     */
    private function findPathToLeaf(XsdNode $node, string $leafName, array $path = []): ?array
    {
        if ($node->isAny) {
            return null;
        }

        $currentPath = array_merge($path, [$node->name]);

        if ($node->name === $leafName && count($node->children) === 0) {
            return $currentPath;
        }

        foreach ($node->children as $child) {
            $found = $this->findPathToLeaf($child, $leafName, $currentPath);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private function findNodeByPath(XsdNode $root, array $path): ?XsdNode
    {
        if (empty($path)) {
            return null;
        }

        if ($root->name !== $path[0]) {
            return null;
        }

        $current = $root;

        for ($i = 1; $i < count($path); $i++) {
            $wanted = $path[$i];
            $next = null;

            foreach ($current->children as $child) {
                if ($child->isAny) {
                    continue;
                }

                if ($child->name === $wanted) {
                    $next = $child;
                    break;
                }
            }

            if (!$next) {
                return null;
            }

            $current = $next;
        }

        return $current;
    }

    private function findDomElementByPath(DOMDocument $dom, array $path): ?DOMElement
    {
        $root = $dom->documentElement;
        if (!$root) {
            return null;
        }

        if ($root->localName !== $path[0]) {
            return null;
        }

        $current = $root;

        for ($i = 1; $i < count($path); $i++) {
            $next = $this->findDirectChildElement($current, $path[$i]);
            if (!$next) {
                return null;
            }
            $current = $next;
        }

        return $current;
    }

    private function findDirectChildElement(DOMElement $parent, string $localName): ?DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if (!($child instanceof DOMElement)) {
                continue;
            }

            if ($child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private function createElementLikeParentNamespace(DOMDocument $dom, DOMElement $parent, string $name): DOMElement
    {
        $ns = $parent->namespaceURI;
        $prefix = $parent->prefix;

        if ($ns) {
            $qualifiedName = $prefix ? "{$prefix}:{$name}" : $name;
            return $dom->createElementNS($ns, $qualifiedName);
        }

        return $dom->createElement($name);
    }

    private function insertChildRespectingXsdOrder(
        DOMElement $parentDom,
        DOMElement $newChild,
        XsdNode $parentSchemaNode,
        string $targetChildName
    ): void {
        $orderedChildNames = [];

        foreach ($parentSchemaNode->children as $child) {
            if ($child->isAny) {
                continue;
            }

            $orderedChildNames[] = $child->name;
        }

        $targetIndex = array_search($targetChildName, $orderedChildNames, true);

        if ($targetIndex === false) {
            $parentDom->appendChild($newChild);
            return;
        }

        for ($i = $targetIndex + 1; $i < count($orderedChildNames); $i++) {
            $nextExistingSibling = $this->findDirectChildElement($parentDom, $orderedChildNames[$i]);

            if ($nextExistingSibling) {
                $parentDom->insertBefore($newChild, $nextExistingSibling);
                return;
            }
        }

        $parentDom->appendChild($newChild);
    }

    private function normalizePkcs12(string $raw): string
    {
        $trim = trim($raw);

        $looksBase64 =
            $trim !== '' &&
            preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $trim) &&
            (strlen($trim) % 4 === 0);

        if ($looksBase64) {
            $decoded = base64_decode($trim, true);
            if ($decoded !== false && $decoded !== '') {
                return $decoded;
            }
        }

        return $raw;
    }

    private function stripBom(string $s): string
    {
        return str_starts_with($s, "\xEF\xBB\xBF") ? substr($s, 3) : $s;
    }

    private function safeCleanup(string $dir): void
    {
        if (!is_dir($dir)) return;

        $files = @scandir($dir);
        if (is_array($files)) {
            foreach ($files as $f) {
                if ($f === '.' || $f === '..') continue;
                @unlink($dir . DIRECTORY_SEPARATOR . $f);
            }
        }
        @rmdir($dir);
    }
}