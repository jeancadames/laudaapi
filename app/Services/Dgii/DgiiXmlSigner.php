<?php

namespace App\Services\Dgii;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DgiiXmlSigner
{
    public function signXmlDgii(string $xml, string $certDisk, string $certPath, ?string $password = null): string
    {
        if (!class_exists(\RobRichards\XMLSecLibs\XMLSecurityDSig::class)) {
            throw new RuntimeException('Falta xmlseclibs: instala robrichards/xmlseclibs:^3.');
        }

        $p12 = Storage::disk($certDisk)->get($certPath);

        $certs = [];
        $ok = @openssl_pkcs12_read($p12, $certs, (string)($password ?? ''));

        if (!$ok || empty($certs['pkey']) || empty($certs['cert'])) {
            throw new RuntimeException('No se pudo abrir P12/PFX o falta llave privada/cert. Verifica password.');
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;

        // ✅ No usar LIBXML_NOBLANKS (no queremos tocar el XML original)
        if (!$dom->loadXML($xml)) {
            throw new RuntimeException('XML inválido.');
        }

        $dsig = new \RobRichards\XMLSecLibs\XMLSecurityDSig();

        // SignedInfo canonicalization (como tu ejemplo)
        $dsig->setCanonicalMethod(\RobRichards\XMLSecLibs\XMLSecurityDSig::C14N);

        // ✅ DGII: Reference URI="" y SOLO enveloped-signature
        $dsig->addReference(
            $dom->documentElement,
            \RobRichards\XMLSecLibs\XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['uri' => '']
        );

        $key = new \RobRichards\XMLSecLibs\XMLSecurityKey(
            \RobRichards\XMLSecLibs\XMLSecurityKey::RSA_SHA256,
            ['type' => 'private']
        );

        $key->loadKey($certs['pkey'], false);

        $dsig->sign($key);

        // ✅ Inserta la firma en el root
        if (method_exists($dsig, 'appendSignature')) {
            $dsig->appendSignature($dom->documentElement);
        }

        // ✅ KeyInfo/X509Data/X509Certificate (sin subjectName, como tu ejemplo)
        $dsig->add509Cert($certs['cert'], true, false, ['subjectName' => false]);

        // ✅ Normaliza para que quede SIN "ds:" y con xmlns default
        $this->stripDsPrefixKeepDefaultNs($dom);

        return $dom->saveXML();
    }

    private function stripDsPrefixKeepDefaultNs(DOMDocument $dom): void
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        $sig = $xpath->query('//ds:Signature')->item(0);
        if (!$sig) return;

        // Renombrar Signature primero
        $dom->renameNode($sig, 'http://www.w3.org/2000/09/xmldsig#', 'Signature');

        // Asegurar xmlns default como tu ejemplo
        if ($sig instanceof \DOMElement) {
            $sig->setAttribute('xmlns', 'http://www.w3.org/2000/09/xmldsig#');
            if ($sig->hasAttribute('xmlns:ds')) $sig->removeAttribute('xmlns:ds');
        }

        // Renombrar todo el subárbol: ds:SignedInfo, ds:Reference, etc -> sin prefijo
        $nodes = $xpath->query('//*[namespace-uri()="http://www.w3.org/2000/09/xmldsig#"]');
        foreach ($nodes as $n) {
            /** @var \DOMElement $n */
            $dom->renameNode($n, 'http://www.w3.org/2000/09/xmldsig#', $n->localName);
        }
    }
}
