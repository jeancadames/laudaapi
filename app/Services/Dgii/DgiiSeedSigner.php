<?php

namespace App\Services\Dgii;

use DOMDocument;
use RuntimeException;
use Selective\XmlDSig\Algorithm;
use Selective\XmlDSig\CryptoSigner;
use Selective\XmlDSig\PrivateKeyStore;
use Selective\XmlDSig\XmlSigner;

class DgiiSeedSigner
{
    public function signSemillaXml(string $xml, string $p12Binary, string $p12Password): string
    {
        $xml = $this->stripBom($xml);
        if (trim($xml) === '') {
            throw new RuntimeException('XML de semilla vacío.');
        }

        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;

        if (!$doc->loadXML($xml, LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new RuntimeException('No se pudo parsear el XML de semilla.');
        }

        $root = $doc->documentElement;
        if (!$root || $root->nodeName !== 'SemillaModel') {
            throw new RuntimeException('XML inválido: raíz esperada <SemillaModel>.');
        }

        $privateKeyStore = $this->loadPkcs12Robust($p12Binary, (string) $p12Password);

        $algorithm = new Algorithm(Algorithm::METHOD_SHA256);
        $cryptoSigner = new CryptoSigner($privateKeyStore, $algorithm);

        $xmlSigner = new XmlSigner($cryptoSigner);
        $xmlSigner->setReferenceUri(''); // Reference URI=""

        // 🔥 IMPORTANTE: devuelve EXACTAMENTE lo que firma la librería
        $signedXml = $xmlSigner->signDocument($doc);

        if (!is_string($signedXml) || trim($signedXml) === '') {
            throw new RuntimeException('No se pudo firmar la semilla (resultado vacío).');
        }

        // ✅ Solo quita BOM, no reserialices con C14N
        $signedXml = $this->stripBom($signedXml);

        // ✅ Si quieres asegurar declaración XML:
        // (sin cambiar el contenido) — opcional
        if (!str_starts_with(ltrim($signedXml), '<?xml')) {
            $signedXml = '<?xml version="1.0" encoding="utf-8"?>' . "\n" . $signedXml;
        }

        return $signedXml;
    }

    private function loadPkcs12Robust(string $p12Binary, string $password): PrivateKeyStore
    {
        $pks = new PrivateKeyStore();
        $pks->loadFromPkcs12($p12Binary, $password);
        return $pks;
    }

    private function stripBom(string $s): string
    {
        return str_starts_with($s, "\xEF\xBB\xBF") ? substr($s, 3) : $s;
    }
}
