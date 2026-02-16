<?php

namespace App\Services\Dgii;

use DOMDocument;
use DOMElement;
use RuntimeException;
use Selective\XmlDSig\Algorithm;
use Selective\XmlDSig\CryptoSigner;
use Selective\XmlDSig\PrivateKeyStore;
use Selective\XmlDSig\XmlSigner;

class DgiiSeedSigner
{
    private const DSIG_NS = 'http://www.w3.org/2000/09/xmldsig#';

    public function signSemillaXml(string $xml, string $p12Binary, string $p12Password): string
    {
        $xml = $this->stripBom($xml);
        if (trim($xml) === '') {
            throw new RuntimeException('XML de semilla vacío.');
        }

        // ✅ Parse sin "normalizar"
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = true;
        $doc->formatOutput = false;

        if (!$doc->loadXML($xml, LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new RuntimeException('No se pudo parsear el XML de semilla.');
        }

        $root = $doc->documentElement;
        if (!$root || $root->nodeName !== 'SemillaModel') {
            throw new RuntimeException('XML inválido: raíz esperada <SemillaModel>.');
        }

        // ✅ Leer PKCS12 (leaf + store) y validar que el cert corresponde a la llave privada
        [$leafPem, $privateKeyStore] = $this->loadPkcs12LeafAndValidate(
            $p12Binary,
            (string) $p12Password
        );

        // ✅ Firmar (Reference URI="") igual que DGII/VB6-Chilkat
        $algorithm = new Algorithm(Algorithm::METHOD_SHA256);
        $cryptoSigner = new CryptoSigner($privateKeyStore, $algorithm);

        $xmlSigner = new XmlSigner($cryptoSigner);
        $xmlSigner->setReferenceUri(''); // Reference URI=""

        $signedXml = $xmlSigner->signDocument($doc);

        if (!is_string($signedXml) || trim($signedXml) === '') {
            throw new RuntimeException('No se pudo firmar la semilla (resultado vacío).');
        }

        // ✅ Re-cargar solo para editar KeyInfo -> X509Data (sin "beautify")
        $signedDoc = new DOMDocument();
        $signedDoc->preserveWhiteSpace = true;
        $signedDoc->formatOutput = false;

        $signedXml = $this->stripBom($signedXml);

        if (!$signedDoc->loadXML($signedXml, LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new RuntimeException('No se pudo parsear el XML firmado para insertar X509Data.');
        }

        // ✅ KeyInfo = X509Data con SOLO el leaf (igual a lo aceptado por DGII)
        $this->replaceKeyInfoWithX509LeafOnly($signedDoc, $leafPem);

        $out = $signedDoc->saveXML();
        if (!is_string($out) || trim($out) === '') {
            throw new RuntimeException('XML firmado final vacío.');
        }

        return $this->stripBom($out);
    }

    /**
     * Lee PKCS#12 robusto:
     * - soporta entrada binaria o base64 "disfrazado"
     * - extrae leaf cert + pkey
     * - valida que el cert corresponde a la pkey (modulus RSA)
     * - crea PrivateKeyStore para Selective\XmlDSig usando el P12 completo
     */
    private function loadPkcs12LeafAndValidate(string $p12Binary, string $password): array
    {
        if (!function_exists('openssl_pkcs12_read')) {
            throw new RuntimeException('OpenSSL no está disponible en PHP (openssl_pkcs12_read).');
        }

        $p12Binary = $this->normalizePkcs12($p12Binary);

        $certs = [];
        if (!openssl_pkcs12_read($p12Binary, $certs, $password)) {
            $err = $this->collectOpenSslErrors();
            throw new RuntimeException('PKCS12 inválido o password incorrecto. ' . $err);
        }

        $leafPem = $certs['cert'] ?? null;
        $pkeyPem = $certs['pkey'] ?? null;

        if (!is_string($leafPem) || trim($leafPem) === '') {
            throw new RuntimeException('El PKCS12 no contiene certificado leaf (cert).');
        }
        if (!is_string($pkeyPem) || trim($pkeyPem) === '') {
            throw new RuntimeException('El PKCS12 no contiene llave privada (pkey).');
        }

        // ✅ Validar cert/public key
        $leafX509 = openssl_x509_read($leafPem);
        if ($leafX509 === false) {
            throw new RuntimeException('No se pudo leer el X509 leaf del PKCS12. ' . $this->collectOpenSslErrors());
        }

        $pubKey = openssl_pkey_get_public($leafX509);
        if ($pubKey === false) {
            throw new RuntimeException('No se pudo extraer public key del X509 leaf. ' . $this->collectOpenSslErrors());
        }

        // ✅ En OpenSSL 3 / PHP 8.4 esto es más estable:
        // primero sin password, luego con password
        $privKey = openssl_pkey_get_private($pkeyPem);
        if ($privKey === false) {
            $privKey = openssl_pkey_get_private($pkeyPem, $password);
        }
        if ($privKey === false) {
            throw new RuntimeException('No se pudo leer private key del PKCS12. ' . $this->collectOpenSslErrors());
        }

        // ✅ modulus mismatch check (normalizado)
        $pubDetails = openssl_pkey_get_details($pubKey) ?: null;
        $privDetails = openssl_pkey_get_details($privKey) ?: null;

        $pubN = $pubDetails['rsa']['n'] ?? null;
        $privN = $privDetails['rsa']['n'] ?? null;

        $norm = static fn($s) => ltrim((string) $s, "\x00"); // quita leading zeros binarios

        if ($pubDetails === null || $privDetails === null || !is_string($pubN) || !is_string($privN) || $norm($pubN) === '' || $norm($privN) === '' || $norm($pubN) !== $norm($privN)) {
            throw new RuntimeException(
                'El certificado X509 del PKCS12 no corresponde a la llave privada (modulus mismatch). ' .
                    'Si el P12 trae múltiples entradas, hay que seleccionar la correcta.'
            );
        }

        // ✅ PrivateKeyStore para Selective: usa el P12 completo (ya normalizado)
        $store = new PrivateKeyStore();
        $store->loadFromPkcs12($p12Binary, $password);

        return [$leafPem, $store];
    }

    private function replaceKeyInfoWithX509LeafOnly(DOMDocument $doc, string $leafPem): void
    {
        $sig = $doc->getElementsByTagNameNS(self::DSIG_NS, 'Signature')->item(0);
        if (!$sig instanceof DOMElement) {
            throw new RuntimeException('No se encontró <Signature> en el XML firmado.');
        }

        // Buscar/crear KeyInfo
        $keyInfo = null;
        foreach ($sig->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'KeyInfo') {
                $keyInfo = $child;
                break;
            }
        }

        if (!$keyInfo) {
            $keyInfo = $doc->createElementNS(self::DSIG_NS, 'KeyInfo');
            $sig->appendChild($keyInfo);
        }

        // Limpiar KeyInfo existente (RSAKeyValue u otros)
        while ($keyInfo->firstChild) {
            $keyInfo->removeChild($keyInfo->firstChild);
        }

        // X509Data
        $x509Data = $doc->createElementNS(self::DSIG_NS, 'X509Data');

        // ✅ SOLO leaf (esto suele ser lo que DGII tolera mejor)
        $leafDerB64 = $this->pemToDerBase64($leafPem);
        if ($leafDerB64 === '') {
            throw new RuntimeException('No se pudo convertir el certificado PEM a Base64 DER (leaf vacío).');
        }

        $x509Data->appendChild(
            $doc->createElementNS(self::DSIG_NS, 'X509Certificate', $leafDerB64)
        );

        $keyInfo->appendChild($x509Data);
    }

    private function pemToDerBase64(string $pem): string
    {
        $pem = trim($pem);
        $pem = preg_replace('/-----BEGIN CERTIFICATE-----/', '', $pem);
        $pem = preg_replace('/-----END CERTIFICATE-----/', '', $pem);
        $pem = preg_replace('/\s+/', '', $pem);
        return is_string($pem) ? $pem : '';
    }

    /**
     * Soporta P12 binario o base64 (muchos .bin terminan siendo base64 "plano").
     */
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

    private function collectOpenSslErrors(): string
    {
        if (!function_exists('openssl_error_string')) {
            return '';
        }
        $errs = [];
        while ($e = openssl_error_string()) {
            $errs[] = $e;
        }
        return $errs ? ('OpenSSL: ' . implode(' | ', $errs)) : '';
    }
}
