<?php

namespace App\Http\Controllers\DgiiWs;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DgiiCompanySetting;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

abstract class BaseDgiiWsController extends Controller
{
    protected function company(Request $request): Company
    {
        // ✅ FIX: precedencia (no uses ?? con ternario en una línea)
        $company = $request->attributes->get('company');

        if (!$company && app()->bound('currentCompany')) {
            $company = app('currentCompany');
        }

        if (!$company instanceof Company) {
            abort(404);
        }

        return $company;
    }

    protected function setting(Company $company): DgiiCompanySetting
    {
        return DgiiCompanySetting::query()
            ->where('company_id', $company->id)
            ->firstOrFail();
    }

    protected function respondXml(int $status, string $xml)
    {
        return response($xml, $status)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    protected function errorXml(int $status, string $message)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><error>' . e($message) . '</error>';
        return $this->respondXml($status, $xml);
    }

    /**
     * DGII manda multipart con "xml". También soporta raw body.
     */
    protected function readIncomingXml(Request $request): string
    {
        if ($request->hasFile('xml') && $request->file('xml')->isValid()) {
            return ltrim((string) file_get_contents($request->file('xml')->getRealPath()));
        }

        foreach (($request->allFiles() ?? []) as $f) {
            if (is_array($f)) continue;
            if ($f && method_exists($f, 'isValid') && $f->isValid()) {
                $candidate = ltrim((string) file_get_contents($f->getRealPath()));
                if ($candidate !== '' && str_starts_with($candidate, '<')) {
                    return $candidate;
                }
            }
        }

        return ltrim((string) $request->getContent());
    }

    protected function wsAuthPath(Company $company, string $file): string
    {
        return "private/dgii/ws_auth/company_{$company->id}/{$file}";
    }

    protected function wsLogPath(Company $company, string $channel, string $file): string
    {
        return "private/dgii/ws_logs/{$channel}/company_{$company->id}/{$file}";
    }

    protected function putPrivate(string $path, string $contents): void
    {
        Storage::disk('local')->put($path, $contents);
    }

    protected function getPrivate(string $path): ?string
    {
        if (!Storage::disk('local')->exists($path)) return null;
        return Storage::disk('local')->get($path);
    }

    /**
     * Correlation ID para encadenar logs (entrada/salida/error)
     */
    protected function correlationId(Request $request): string
    {
        $h = trim((string) $request->header('X-Correlation-Id'));
        return $h !== '' ? $h : (string) Str::uuid();
    }

    /**
     * Token “WS” para que DGII llame tus endpoints de recepcion/aprobacion
     * (NO es el token DGII guardado en dgii_company_settings).
     */
    protected function issueWsToken(Company $company, int $ttlSeconds = 3600): array
    {
        $issuedAt  = CarbonImmutable::now('UTC');
        $expiresAt = $issuedAt->addSeconds($ttlSeconds);

        $token = hash('sha256', $company->id . '|' . $issuedAt->timestamp . '|' . Str::random(32));

        $payloadArr = [
            'token' => $token,
            'token_hash' => hash('sha256', $token), // ✅ útil para logs/auditoría sin exponer token
            'issued_at' => $issuedAt->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
        ];

        $this->putPrivate(
            $this->wsAuthPath($company, 'ws_token.json'),
            json_encode($payloadArr, JSON_UNESCAPED_SLASHES)
        );

        return [
            'token' => $token,
            'token_hash' => $payloadArr['token_hash'],
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
        ];
    }

    protected function readWsToken(Company $company): ?array
    {
        $raw = $this->getPrivate($this->wsAuthPath($company, 'ws_token.json'));
        if (!$raw) return null;

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['token']) || empty($data['expires_at'])) return null;

        // normaliza token_hash
        if (empty($data['token_hash']) && !empty($data['token'])) {
            $data['token_hash'] = hash('sha256', (string)$data['token']);
        }

        return $data;
    }

    /**
     * ✅ NUEVO: valida auth y devuelve reason + hashes para logging
     */
    protected function wsAuthCheck(Request $request, Company $company, int $skewSeconds = 0): array
    {
        $bearer = trim((string) $request->bearerToken());
        if ($bearer === '') {
            return [
                'ok' => false,
                'reason' => 'missing_bearer',
                'bearer_hash' => null,
                'ws_token_hash' => null,
                'expires_at' => null,
            ];
        }

        $data = $this->readWsToken($company);
        if (!$data) {
            return [
                'ok' => false,
                'reason' => 'missing_ws_token',
                'bearer_hash' => hash('sha256', $bearer),
                'ws_token_hash' => null,
                'expires_at' => null,
            ];
        }

        try {
            $expiresAt = CarbonImmutable::parse((string)$data['expires_at'])->subSeconds(max(0, $skewSeconds));
        } catch (\Throwable) {
            return [
                'ok' => false,
                'reason' => 'invalid_ws_token_payload',
                'bearer_hash' => hash('sha256', $bearer),
                'ws_token_hash' => $data['token_hash'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
            ];
        }

        if (CarbonImmutable::now('UTC')->gte($expiresAt)) {
            return [
                'ok' => false,
                'reason' => 'expired',
                'bearer_hash' => hash('sha256', $bearer),
                'ws_token_hash' => $data['token_hash'] ?? null,
                'expires_at' => (string)($data['expires_at'] ?? null),
            ];
        }

        if (!hash_equals((string) $data['token'], $bearer)) {
            return [
                'ok' => false,
                'reason' => 'mismatch',
                'bearer_hash' => hash('sha256', $bearer),
                'ws_token_hash' => $data['token_hash'] ?? null,
                'expires_at' => (string)($data['expires_at'] ?? null),
            ];
        }

        return [
            'ok' => true,
            'reason' => null,
            'bearer_hash' => hash('sha256', $bearer),
            'ws_token_hash' => $data['token_hash'] ?? null,
            'expires_at' => (string)($data['expires_at'] ?? null),
            'token' => $bearer,
        ];
    }

    /**
     * Backwards compatible: antes devolvía token|string|null
     */
    protected function requireWsAuth(Request $request, Company $company): ?string
    {
        $res = $this->wsAuthCheck($request, $company);
        return $res['ok'] ? (string)$res['token'] : null;
    }

    /**
     * Encuentra un P12/PFX/BIN en storage/app/private/dgii/certs/company_{id}
     */
    protected function loadCompanyPemPair(Company $company, DgiiCompanySetting $setting): array
    {
        $dir = storage_path("app/private/dgii/certs/company_{$company->id}");

        $candidates = [];
        foreach (['*.p12', '*.pfx', '*.bin'] as $glob) {
            $candidates = array_merge($candidates, glob($dir . DIRECTORY_SEPARATOR . $glob) ?: []);
        }

        if (empty($candidates)) {
            throw new \RuntimeException("No se encontró certificado P12/PFX en: {$dir}");
        }

        usort($candidates, fn($a, $b) => filemtime($b) <=> filemtime($a));
        $p12Path = $candidates[0];

        $password =
            (string)($setting->meta['p12_password'] ?? $setting->meta['cert_password'] ?? env('DGII_P12_PASSWORD', ''));

        $p12 = @file_get_contents($p12Path);
        if ($p12 === false || trim($p12) === '') {
            throw new \RuntimeException("No se pudo leer el P12: {$p12Path}");
        }

        $certs = [];
        if (!openssl_pkcs12_read($p12, $certs, $password)) {
            throw new \RuntimeException("openssl_pkcs12_read falló. Verifica password del P12 en meta/env.");
        }

        $privateKeyPem = (string)($certs['pkey'] ?? '');
        $publicCertPem = (string)($certs['cert'] ?? '');

        if ($privateKeyPem === '' || $publicCertPem === '') {
            throw new \RuntimeException("El P12 no trajo pkey/cert válidos.");
        }

        return [$privateKeyPem, $publicCertPem, $p12Path];
    }

    /**
     * Firma enveloped (sin ds: prefix)
     */
    protected function signDom(DOMDocument $doc, string $privateKeyPem, string $publicCertPem): void
    {
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;

        $dsig = new XMLSecurityDSig();
        $dsig->setCanonicalMethod(XMLSecurityDSig::C14N);

        $dsig->addReference(
            $doc->documentElement,
            XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['uri' => '']
        );

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $key->loadKey($privateKeyPem, false);
        $dsig->sign($key);

        $dsig->add509Cert($publicCertPem, true, false);
        $dsig->appendSignature($doc->documentElement);

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        $ref = $xpath->query('//ds:Reference')->item(0);
        if ($ref instanceof DOMElement) {
            $ref->setAttribute('URI', '');
        }

        $nodes = $xpath->query('//*[namespace-uri()="http://www.w3.org/2000/09/xmldsig#"]');
        if ($nodes) {
            foreach ($nodes as $n) {
                if ($n instanceof DOMElement && $n->prefix) {
                    $doc->renameNode($n, 'http://www.w3.org/2000/09/xmldsig#', $n->localName);
                }
            }
        }

        $sig = $xpath->query('//*[local-name()="Signature" and namespace-uri()="http://www.w3.org/2000/09/xmldsig#"]')->item(0);
        if ($sig instanceof DOMElement) {
            $sig->setAttribute('xmlns', 'http://www.w3.org/2000/09/xmldsig#');
            if ($sig->hasAttribute('xmlns:ds')) {
                $sig->removeAttribute('xmlns:ds');
            }
        }
    }

    protected function normalizeDdMmYyyy(string $v): string
    {
        $v = trim($v);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        return $v;
    }

    protected function normalizeAmount(string $v): string
    {
        $v = trim($v);
        if ($v === '') return $v;

        $v = str_replace([' '], [''], $v);

        if (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $v)) {
            $v = str_replace(',', '', $v);
        }

        if (preg_match('/^\d+,\d+$/', $v)) {
            $v = str_replace(',', '.', $v);
        }

        return $v;
    }
}
