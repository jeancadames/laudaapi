<?php

namespace App\Http\Controllers\DgiiWs;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DgiiCertificate;
use App\Models\DgiiCompanySetting;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use RuntimeException;

abstract class BaseDgiiWsController extends Controller
{
    protected function company(Request $request): Company
    {
        $company = $request->attributes->get('company');

        if (! $company && app()->bound('currentCompany')) {
            $company = app('currentCompany');
        }

        if (! $company instanceof Company) {
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
            if (is_array($f)) {
                continue;
            }

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
        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

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
            'token_hash' => hash('sha256', $token),
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
        if (! $raw) {
            return null;
        }

        $data = json_decode($raw, true);
        if (! is_array($data) || empty($data['token']) || empty($data['expires_at'])) {
            return null;
        }

        if (empty($data['token_hash']) && ! empty($data['token'])) {
            $data['token_hash'] = hash('sha256', (string) $data['token']);
        }

        return $data;
    }

    /**
     * Valida auth y devuelve reason + hashes para logging.
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
        if (! $data) {
            return [
                'ok' => false,
                'reason' => 'missing_ws_token',
                'bearer_hash' => hash('sha256', $bearer),
                'ws_token_hash' => null,
                'expires_at' => null,
            ];
        }

        try {
            $expiresAt = CarbonImmutable::parse((string) $data['expires_at'])
                ->subSeconds(max(0, $skewSeconds));
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
                'expires_at' => (string) ($data['expires_at'] ?? null),
            ];
        }

        if (! hash_equals((string) $data['token'], $bearer)) {
            return [
                'ok' => false,
                'reason' => 'mismatch',
                'bearer_hash' => hash('sha256', $bearer),
                'ws_token_hash' => $data['token_hash'] ?? null,
                'expires_at' => (string) ($data['expires_at'] ?? null),
            ];
        }

        return [
            'ok' => true,
            'reason' => null,
            'bearer_hash' => hash('sha256', $bearer),
            'ws_token_hash' => $data['token_hash'] ?? null,
            'expires_at' => (string) ($data['expires_at'] ?? null),
            'token' => $bearer,
        ];
    }

    /**
     * Backwards compatible: antes devolvía token|string|null
     */
    protected function requireWsAuth(Request $request, Company $company): ?string
    {
        $res = $this->wsAuthCheck($request, $company);

        return $res['ok'] ? (string) $res['token'] : null;
    }

    /**
     * Carga el certificado activo real de la compañía desde dgii_certificates.
     *
     * Mantenemos la firma del método para no tocar RecepcionEcfController
     * ni AprobacionComercialEcfController, aunque $setting ya no se use aquí.
     *
     * Retorna: [$privateKeyPem, $publicCertPem, $path]
     */
    protected function loadCompanyPemPair(Company $company, DgiiCompanySetting $setting): array
    {
        [$bytes, $password, $path] = $this->loadCompanyP12BinaryAndPassword($company);

        $certs = [];
        if (! openssl_pkcs12_read($bytes, $certs, $password)) {
            throw new RuntimeException(
                "openssl_pkcs12_read falló para cert_path={$path}. Verifica password/meta o legacy OpenSSL."
            );
        }

        $privateKeyPem = (string) ($certs['pkey'] ?? '');
        $publicCertPem = (string) ($certs['cert'] ?? '');

        if ($privateKeyPem === '' || $publicCertPem === '') {
            throw new RuntimeException("El P12 no trajo pkey/cert válidos para {$path}.");
        }

        return [$privateKeyPem, $publicCertPem, $path];
    }

    private function normalizeCertMeta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if (is_string($meta) && trim($meta) !== '') {
            $decoded = json_decode($meta, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function extractP12PasswordFromCertMeta(array $meta, int $certId): string
    {
        try {
            if (! empty($meta['p12_password_enc']) && is_string($meta['p12_password_enc'])) {
                return trim((string) Crypt::decryptString($meta['p12_password_enc']));
            }

            if (! empty($meta['password_enc']) && is_string($meta['password_enc'])) {
                return trim((string) Crypt::decryptString($meta['password_enc']));
            }
        } catch (DecryptException $e) {
            throw new RuntimeException(
                "No se pudo desencriptar password del certificado (cert_id={$certId}). Probable APP_KEY diferente."
            );
        }

        if (isset($meta['p12_password']) && is_string($meta['p12_password'])) {
            return trim($meta['p12_password']);
        }

        if (isset($meta['password']) && is_string($meta['password'])) {
            return trim($meta['password']);
        }

        throw new RuntimeException(
            "Certificado sin password en meta (cert_id={$certId}). Debes guardar p12_password_enc/password_enc o p12_password/password."
        );
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

        // Mantén URI vacío, como ya hacías
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        $ref = $xpath->query('//*[local-name()="Reference" and namespace-uri()="http://www.w3.org/2000/09/xmldsig#"]')->item(0);
        if ($ref instanceof DOMElement) {
            $ref->setAttribute('URI', '');
        }
    }

    protected function loadCompanyP12BinaryAndPassword(Company $company): array
    {
        /** @var DgiiCertificate|null $cert */
        $cert = DgiiCertificate::query()
            ->where('company_id', $company->id)
            ->whereIn('type', ['p12', 'pfx'])
            ->when(
                Schema::hasColumn('dgii_certificates', 'status'),
                fn($q) => $q->where('status', 'active')
            )
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();

        if (! $cert) {
            throw new RuntimeException("No existe certificado P12/PFX activo para company_id={$company->id}.");
        }

        $disk = (string) ($cert->file_disk ?: 'private');
        $path = (string) ($cert->file_path ?: '');

        if ($path === '') {
            throw new RuntimeException("Certificado sin file_path (cert_id={$cert->id}).");
        }

        if (! Storage::disk($disk)->exists($path)) {
            throw new RuntimeException(
                "No se encontró el archivo del certificado en storage (cert_id={$cert->id}): disk={$disk}, path={$path}."
            );
        }

        $bytes = (string) Storage::disk($disk)->get($path);
        if ($bytes === '') {
            throw new RuntimeException(
                "Archivo del certificado vacío (cert_id={$cert->id}): disk={$disk}, path={$path}."
            );
        }

        $sha = hash('sha256', $bytes);
        if (! empty($cert->file_sha256) && is_string($cert->file_sha256) && $sha !== $cert->file_sha256) {
            throw new RuntimeException(
                "Storage mismatch (cert_id={$cert->id}): sha256 leído NO coincide con DB. leído={$sha}, db={$cert->file_sha256}."
            );
        }

        $meta = $this->normalizeCertMeta($cert->meta);
        $password = $this->extractP12PasswordFromCertMeta($meta, (int) $cert->id);

        return [$bytes, $password, $path];
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
        if ($v === '') {
            return $v;
        }

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