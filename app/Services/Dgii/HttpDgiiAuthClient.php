<?php

namespace App\Services\Dgii;

use App\Models\DgiiCertificate;
use App\Models\DgiiCompanySetting;
use App\Models\DgiiEndpointCatalog;
use App\Services\Dgii\Endpoints\DgiiEndpointResolver;
use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

use RuntimeException;

class HttpDgiiAuthClient implements DgiiAuthClient
{
    public function __construct(
        private readonly DgiiEndpointResolver $builder,
        private readonly DgiiXmlSigner $signer,
        private readonly DgiiCertificateReader $certReader,
    ) {}

    public function requestToken(DgiiCompanySetting $setting): array
    {
        $skipCache = (bool) config('dgii.auth_skip_cache', (bool) env('DGII_AUTH_SKIP_CACHE', false));

        // ✅ usa el CF real para cache (DGII usa testecf/certecf/ecf)
        $cf = $this->resolveCfPrefix($setting);

        $cacheKey = "dgii:token:company:{$setting->company_id}:cf:{$cf}";

        if (!$skipCache) {
            $cached = cache()->get($cacheKey);

            if (is_array($cached)) {
                $tok = (string) ($cached['token'] ?? '');
                if (trim($tok) !== '') {
                    return $cached;
                }
            }
        }

        // ✅ keys correctos
        $seedUrl     = $this->endpointUrl($setting, 'auth.seed');
        $validateUrl = $this->endpointUrl($setting, 'auth.validate_seed');

        // Semilla fresca
        $seedXml = $this->sanitizeXml($this->getSeedXml($seedUrl));

        [$p12Bytes, $p12Password] = $this->loadActiveP12ForCompany($setting->company_id);

        $signedSeedXml = $this->sanitizeXml(
            $this->signer->signAnyXml($seedXml, $p12Bytes, $p12Password)
        );

        $this->maybeDumpAuthArtifacts($setting, $seedXml, $signedSeedXml, $seedUrl, $validateUrl);

        $out = $this->validateSignedSeed($validateUrl, $signedSeedXml);

        if (!$skipCache) {
            $expiresIn  = (int) ($out['expires_in'] ?? 3600);
            $ttlSeconds = max(60, $expiresIn - 120);
            cache()->put($cacheKey, $out, now()->addSeconds($ttlSeconds));
        }

        return $out;
    }

    private function endpointUrl(DgiiCompanySetting $setting, string $key, array $params = []): string
    {
        // ✅ siempre resuelve el cfPrefix correcto (testecf/certecf/ecf)
        $cfPrefix = $this->resolveCfPrefix($setting);

        $overrides = (array) ($setting->endpoints ?? []);
        if (isset($overrides[$key])) {
            $ov = $overrides[$key];

            // override como URL completa
            if (is_string($ov) && trim($ov) !== '') {
                return $ov;
            }

            // override como { base_url, path }
            if (is_array($ov)) {
                $base = (string) ($ov['base_url'] ?? '');
                $path = (string) ($ov['path'] ?? '');
                if ($base !== '' && $path !== '') {
                    return $this->builder->resolve($base, $path, $cfPrefix, $params);
                }
            }
        }

        /** @var \App\Models\DgiiEndpointCatalog|null $row */
        $row = DgiiEndpointCatalog::query()
            ->where('environment', (string) $setting->environment)
            ->where('key', $key)
            ->where('is_active', 1)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();

        if (!$row) {
            throw new RuntimeException("DGII endpoint no encontrado: key={$key}, env={$setting->environment}.");
        }

        $baseUrl = (string) $row->base_url;
        $path    = (string) $row->path;

        if (trim($baseUrl) === '' || trim($path) === '') {
            throw new RuntimeException("DGII endpoint inválido: key={$key}, env={$setting->environment}.");
        }

        // ✅ usar cfPrefix mapeado, NO setting->cf_prefix crudo
        return $this->builder->resolve($baseUrl, $path, $cfPrefix, $params);
    }

    private function getSeedXml(string $url): string
    {
        $res = Http::timeout(25)
            ->accept('*/*')
            // ✅ evita problemas raros de compresión / proxies
            ->withHeaders([
                'Accept-Encoding' => 'identity',
                'User-Agent' => 'LaudaAPI/1.0 (DGII Auth Client)',
            ])
            ->get($url);

        if (!$res->ok()) {
            throw new RuntimeException("DGII get_seed failed ({$res->status()}): " . $this->safeBody($res->body()));
        }

        $body = (string) $res->body();
        if (trim($body) === '') {
            throw new RuntimeException('DGII get_seed devolvió body vacío.');
        }

        return $body;
    }

    private function validateSignedSeed(string $url, string $signedXml): array
    {
        $signedXml = $this->sanitizeXml($signedXml);

        $res = Http::timeout(25)
            ->accept('application/json, application/xml, text/xml, */*')
            ->withHeaders([
                'Accept-Encoding' => 'identity',
                'User-Agent' => 'LaudaAPI/1.0 (DGII Auth Client)',
            ])
            // ✅ DGII doc: multipart/form-data con campo "xml"
            ->attach('xml', $signedXml, 'semilla.xml')
            ->post($url);

        if (!$res->ok()) {
            Log::warning('DGII validate_seed failed', [
                'status' => $res->status(),
                'url' => $url,
                'resp_snippet' => $this->safeBody($res->body()),
                'req_sha256' => hash('sha256', $signedXml),
                'req_len' => strlen($signedXml),
            ]);

            throw new RuntimeException("DGII validate_seed failed ({$res->status()}): " . $this->safeBody($res->body()));
        }

        $body = (string) $res->body();
        $trim = ltrim($body);

        // DGII puede devolver JSON o XML
        if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
            $json = $res->json() ?? [];
            $token = data_get($json, 'token') ?? data_get($json, 'access_token') ?? data_get($json, 'Token');

            if (is_string($token) && trim($token) !== '') {
                $expiresIn = (int) (data_get($json, 'expires_in') ?? 3600);
                return ['token' => trim($token), 'expires_in' => $expiresIn > 0 ? $expiresIn : 3600];
            }
        }

        $token = $this->extractTokenFromXml($body);
        if (is_string($token) && trim($token) !== '') {
            return ['token' => trim($token), 'expires_in' => 3600];
        }

        // fallback JSON parse
        $json = $res->json();
        if (is_array($json)) {
            $token2 = data_get($json, 'token') ?? data_get($json, 'access_token') ?? data_get($json, 'Token');
            if (is_string($token2) && trim($token2) !== '') {
                return ['token' => trim($token2), 'expires_in' => (int) (data_get($json, 'expires_in') ?? 3600)];
            }
        }

        throw new RuntimeException('DGII validate_seed no devolvió token (JSON/XML/unknown).');
    }

    /**
     * ✅ Guarda seed/signed si DGII_AUTH_DEBUG=true
     * Esto permite comparar EXACTAMENTE qué se firmó y qué se envió.
     */
    private function maybeDumpAuthArtifacts(
        DgiiCompanySetting $setting,
        string $seedXml,
        string $signedXml,
        string $seedUrl,
        string $validateUrl
    ): void {
        $debug = (bool) config('dgii.auth_debug', (bool) env('DGII_AUTH_DEBUG', false));
        if (!$debug) {
            return;
        }

        $disk = 'private';
        $dir = "dgii/auth_debug/company_{$setting->company_id}";
        $ts = now()->format('Ymd_His');
        $seedPath = "{$dir}/{$ts}_seed.xml";
        $signedPath = "{$dir}/{$ts}_signed.xml";

        Storage::disk($disk)->put($seedPath, $seedXml);
        Storage::disk($disk)->put($signedPath, $signedXml);

        Log::info('DGII auth debug artifacts saved', [
            'company_id' => $setting->company_id,
            'env' => $setting->environment,
            'seed_url' => $seedUrl,
            'validate_url' => $validateUrl,
            'seed_sha256' => hash('sha256', $seedXml),
            'signed_sha256' => hash('sha256', $signedXml),
            'seed_path' => $seedPath,
            'signed_path' => $signedPath,
        ]);
    }

    private function sanitizeXml(string $xml): string
    {
        // quita BOM si viene
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml) ?? $xml;

        // normaliza saltos de línea (no debe romper C14N, pero evita basura)
        $xml = str_replace(["\r\n", "\r"], "\n", $xml);

        // NO hacemos trim agresivo (para no alterar)
        return $xml;
    }

    private function loadActiveP12ForCompany(int $companyId): array
    {
        /** @var DgiiCertificate|null $cert */
        $cert = DgiiCertificate::query()
            ->where('company_id', $companyId)
            ->whereIn('type', ['p12', 'pfx'])
            ->when(
                Schema::hasColumn('dgii_certificates', 'status'),
                fn($q) => $q->where('status', 'active')
            )
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();

        if (!$cert) {
            throw new RuntimeException("No existe certificado P12/PFX activo para company_id={$companyId}.");
        }

        $disk = (string) ($cert->file_disk ?: 'private');
        $path = (string) ($cert->file_path ?: '');

        if ($path === '') {
            throw new RuntimeException("Certificado sin file_path (cert_id={$cert->id}).");
        }

        if (!Storage::disk($disk)->exists($path)) {
            throw new RuntimeException(
                "No se encontró el archivo del certificado en storage (cert_id={$cert->id}): disk={$disk}, path={$path}. " .
                    "Esto suele pasar si el certificado se subió desde otra app/servidor con storage distinto."
            );
        }

        $bytes = Storage::disk($disk)->get($path);
        if ($bytes === '' || $bytes === null) {
            throw new RuntimeException("Archivo del certificado vacío (cert_id={$cert->id}): disk={$disk}, path={$path}.");
        }

        $sha = hash('sha256', $bytes);
        if (!empty($cert->file_sha256) && is_string($cert->file_sha256) && $sha !== $cert->file_sha256) {
            throw new RuntimeException(
                "Storage mismatch (cert_id={$cert->id}): sha256 leído NO coincide con DB. " .
                    "leído={$sha}, db={$cert->file_sha256}, disk={$disk}, path={$path}. " .
                    "=> Estás leyendo OTRO archivo (storage root diferente entre apps)."
            );
        }

        $meta = $this->normalizeMeta($cert->meta);
        $password = $this->extractP12PasswordFromMeta($meta, $cert->id);

        $info = $this->certReader->readFromUpload((string) $cert->type, $bytes, $password);

        if (($info['status'] ?? null) !== 'active') {
            $hint = data_get($info, 'meta.hint') ?: 'Certificado no válido.';
            $src  = data_get($info, 'meta.parse_source') ?: 'unknown';
            throw new RuntimeException(
                "No se pudo leer el P12/PFX (cert_id={$cert->id}): {$hint} [parse_source={$src}, disk={$disk}, path={$path}]"
            );
        }

        if (($info['has_private_key'] ?? false) !== true) {
            throw new RuntimeException("Certificado no contiene private key (cert_id={$cert->id}).");
        }

        $validTo = data_get($info, 'valid_to');
        if (is_string($validTo) && trim($validTo) !== '') {
            try {
                if (now()->greaterThan(Carbon::parse($validTo))) {
                    throw new RuntimeException("Certificado expirado (cert_id={$cert->id}, valid_to={$validTo}).");
                }
            } catch (\Throwable $e) {
                // no rompas si parse falla
            }
        }

        return [$bytes, $password];
    }

    private function normalizeMeta(mixed $meta): array
    {
        if (is_array($meta)) return $meta;

        if (is_string($meta) && trim($meta) !== '') {
            $decoded = json_decode($meta, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function extractP12PasswordFromMeta(array $meta, int $certId): string
    {
        try {
            if (!empty($meta['p12_password_enc']) && is_string($meta['p12_password_enc'])) {
                return trim((string) Crypt::decryptString($meta['p12_password_enc']));
            }
            if (!empty($meta['password_enc']) && is_string($meta['password_enc'])) {
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
            "Certificado sin password en meta (cert_id={$certId}). " .
                "Debes guardar meta.p12_password_enc o meta.password_enc (o p12_password/password)."
        );
    }

    private function extractTokenFromXml(string $xml): ?string
    {
        $xml = trim($xml);
        if ($xml === '') return null;

        if (preg_match('/<\s*(access_token|token|Token)\s*>\s*([^<]+)\s*<\s*\/\s*\1\s*>/i', $xml, $m)) {
            return trim($m[2]);
        }

        return null;
    }

    private function safeBody(string $body): string
    {
        $b = trim($body);
        return $b === '' ? '(empty body)' : mb_substr($b, 0, 1200);
    }

    private function resolveCfPrefix(DgiiCompanySetting $setting): string
    {
        // prioridad: cf_prefix explícito si ya viene DGII-native
        $raw = trim((string) ($setting->cf_prefix ?? ''));

        $map = function (string $v): string {
            return match ($v) {
                'precert' => 'testecf',
                'cert'    => 'certecf',
                'prod'    => 'ecf',

                // ya en formato DGII
                'testecf', 'certecf', 'ecf' => $v,

                default => $v,
            };
        };

        if ($raw !== '') {
            $cf = $map($raw);
            if (in_array($cf, ['testecf', 'certecf', 'ecf'], true)) {
                return $cf;
            }
        }

        // fallback: usar environment
        $env = trim((string) ($setting->environment ?? 'precert'));
        $cf  = $map($env);

        return in_array($cf, ['testecf', 'certecf', 'ecf'], true) ? $cf : 'testecf';
    }
}
