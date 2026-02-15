<?php

namespace App\Http\Controllers\LaudaErp;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DgiiCertificate;
use App\Models\DgiiCompanySetting;
use App\Services\Dgii\DgiiSeedSigner;
use App\Services\Subscribers\SubscriberResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class DgiiTokenController extends Controller
{
    public function generate(Request $request)
    {
        $company = $this->resolveCompany($request);

        $setting = DgiiCompanySetting::firstOrCreate(
            ['company_id' => $company->id],
            [
                'environment'   => 'precert',
                'cf_prefix'     => $this->defaultCfPrefix('precert'),
                'use_directory' => true,
                'endpoints'     => null,
                'meta'          => null,
            ]
        );

        // 1) resolver URLs (GetSeed / ValidarSemilla)
        $urls = $this->resolveTokenUrls($setting);

        // 2) GET semilla
        $seedResp = Http::timeout(25)->get($urls['get_seed']);

        if (!$seedResp->ok()) {
            return back()->with('error', "DGII GetSeed falló (HTTP {$seedResp->status()}).");
        }

        $seedXml = (string) $seedResp->body();
        if (trim($seedXml) === '') {
            return back()->with('error', 'DGII GetSeed devolvió respuesta vacía.');
        }

        // 3) Cert default P12/PFX + password (guardado en meta.p12_password_enc)
        try {
            [$p12Binary, $p12Password] = $this->getDefaultCertificateP12($company->id);
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo obtener el certificado default: ' . $e->getMessage());
        }

        // 4) Firmar SemillaModel
        try {
            /** @var DgiiSeedSigner $signer */
            $signer = app(DgiiSeedSigner::class);

            $signedSeedXml = $signer->signSemillaXml($seedXml, $p12Binary, $p12Password);
        } catch (\Throwable $e) {
            return back()->with('dgii_token_debug', [
                'get_seed_url' => $urls['get_seed'],
                'validate_url' => $urls['validate_seed'],
                'seed_xml'     => $seedXml,
            ])->with('error', 'Error firmando SemillaModel: ' . $e->getMessage());
        }

        // 5) POST validarsemilla (XML)
        $validateResp = Http::timeout(25)
            ->withHeaders([
                'Content-Type' => 'application/xml; charset=utf-8',
                'Accept'       => 'application/xml, text/xml, */*',
            ])
            ->send('POST', $urls['validate_seed'], [
                'body' => $signedSeedXml,
            ]);

        if (!$validateResp->ok()) {
            return back()->with('dgii_token_debug', [
                'get_seed_url'      => $urls['get_seed'],
                'validate_url'      => $urls['validate_seed'],
                'seed_xml'          => $seedXml,
                'signed_seed_xml'   => $signedSeedXml,
                'http_status'       => $validateResp->status(),
                'response_headers'  => $validateResp->headers(),
                'response_body'     => (string) $validateResp->body(),
            ])->with('error', "DGII ValidarSemilla falló (HTTP {$validateResp->status()}).");
        }

        $tokenXml = (string) $validateResp->body();
        if (trim($tokenXml) === '') {
            return back()->with('error', 'DGII ValidarSemilla devolvió respuesta vacía.');
        }

        // 6) Extraer token (si aplica) + guardar en meta.token
        $extractedToken = $this->extractTokenFromXml($tokenXml);

        $meta = (array) ($setting->meta ?? []);
        $meta['token'] = [
            'created_at' => now()->toISOString(),
            'raw'        => $tokenXml,
            'token'      => $extractedToken, // puede ser null si DGII cambia la forma
        ];

        $setting->meta = $meta;
        $setting->save();

        return back()->with('success', 'Token generado y guardado en setting.meta.token')
            ->with('dgii_token_debug', [
                'get_seed_url'    => $urls['get_seed'],
                'validate_url'    => $urls['validate_seed'],
                'signed_seed_xml' => $signedSeedXml,
                'token_raw'       => $tokenXml,
                'token'           => $extractedToken,
            ]);
    }

    /* -------------------- helpers -------------------- */

    private function resolveCompany(Request $request): Company
    {
        $user = $request->user();
        abort_unless($user, 403);

        $subscriberId = (int) $request->attributes->get('resolved_subscriber_id', 0);
        if ($subscriberId <= 0) {
            $subscriberId = (int) app(SubscriberResolver::class)->resolve($user);
        }
        abort_unless($subscriberId > 0, 403);

        return Company::where('subscriber_id', $subscriberId)->firstOrFail();
    }

    private function defaultCfPrefix(string $env): string
    {
        return match ($env) {
            'precert' => 'testecf',
            'cert'    => 'certecf',
            'prod'    => 'ecf',
            default   => 'testecf',
        };
    }

    /**
     * Resuelve URLs finales:
     * - base URL: meta.base_urls.ecf (si existe) o endpoints.UrlDGII o default https://ecf.dgii.gov.do
     * - paths: endpoints overrides o fallback catálogo
     */
    private function resolveTokenUrls(DgiiCompanySetting $setting): array
    {
        $env = $setting->environment;
        $cf  = $setting->cf_prefix ?: $this->defaultCfPrefix($env);

        $ep = (array) ($setting->endpoints ?? []);

        if (count($ep) === 0) {
            $ep = $this->endpointsFromCatalog($env);
        }

        // Base host preferido
        $baseEcf = $this->resolveBaseEcf($setting, $ep);

        $getSeedPath  = (string) ($ep['UrlGetSeed']  ?? '/{cf}/autenticacion/api/autenticacion/semilla');
        $validPath    = (string) ($ep['UrlTestSeed'] ?? '/{cf}/autenticacion/api/autenticacion/validarsemilla');

        $getSeedPath = str_replace('{cf}', $cf, $getSeedPath);
        $validPath   = str_replace('{cf}', $cf, $validPath);

        return [
            'get_seed'      => $this->joinUrl($baseEcf, $getSeedPath),
            'validate_seed' => $this->joinUrl($baseEcf, $validPath),
        ];
    }

    private function resolveBaseEcf(DgiiCompanySetting $setting, array $ep): string
    {
        // 1) meta.base_urls.ecf (si ya lo estás guardando desde UI)
        $meta = (array) ($setting->meta ?? []);
        $baseUrls = (array) ($meta['base_urls'] ?? []);
        if (!empty($baseUrls['ecf'])) {
            return rtrim((string) $baseUrls['ecf'], '/');
        }

        // 2) endpoints.UrlDGII
        if (!empty($ep['UrlDGII'])) {
            return rtrim((string) $ep['UrlDGII'], '/');
        }

        // 3) default
        return 'https://ecf.dgii.gov.do';
    }

    private function joinUrl(string $base, string $path): string
    {
        $base = rtrim($base, '/');
        $path = trim($path);

        if (preg_match('/^https?:\/\//i', $path)) return $path;
        if (!str_starts_with($path, '/')) $path = '/' . $path;

        return $base . $path;
    }

    private function endpointsFromCatalog(string $env): array
    {
        if (!Schema::hasTable('dgii_endpoint_catalog')) return [];

        $rows = DB::table('dgii_endpoint_catalog')
            ->where('environment', $env)
            ->where('is_active', 1)
            ->get();

        $out = [];

        foreach ($rows as $r) {
            $k = (string) ($r->key ?? '');

            // Ajusta según tus keys reales
            if ($k === 'auth.seed' || $k === 'auth.get_seed') {
                $out['UrlGetSeed'] = $r->path;
            }

            if ($k === 'auth.validate_seed' || $k === 'auth.validate') {
                $out['UrlTestSeed'] = $r->path;
            }

            // si tu catálogo trae base_url, podrías setear UrlDGII aquí también
            // if (!empty($r->base_url)) $out['UrlDGII'] = $r->base_url;
        }

        return $out;
    }

    private function extractTokenFromXml(string $xml): ?string
    {
        // DGII suele devolver algo como <token>...</token> o variantes.
        // Mantenlo flexible.
        foreach (
            [
                '/<token>\s*([^<]+)\s*<\/token>/i',
                '/<Token>\s*([^<]+)\s*<\/Token>/i',
                '/<semillaToken>\s*([^<]+)\s*<\/semillaToken>/i',
            ] as $re
        ) {
            if (preg_match($re, $xml, $m)) {
                $t = trim((string) ($m[1] ?? ''));
                if ($t !== '') return $t;
            }
        }

        return null;
    }

    /**
     * Devuelve: [p12Binary, password]
     * - password puede ser '' (vacío) y eso es válido para algunos P12/PFX
     */
    private function getDefaultCertificateP12(int $companyId): array
    {
        $cert = DgiiCertificate::query()
            ->where('company_id', $companyId)
            ->whereIn('type', ['p12', 'pfx'])
            ->where('is_default', true)
            ->first();

        if (!$cert) {
            throw new \RuntimeException('No hay certificado default (P12/PFX) para esta compañía.');
        }

        if (!$cert->has_private_key) {
            throw new \RuntimeException('El certificado default no tiene private key.');
        }

        if (!$cert->password_ok) {
            throw new \RuntimeException('El certificado default tiene password inválido o no configurado (password_ok=false).');
        }

        $disk = $cert->file_disk ?: 'private';
        $path = $cert->file_path;

        if (!Storage::disk($disk)->exists($path)) {
            throw new \RuntimeException("No se encontró el archivo del certificado en storage: disk={$disk}, path={$path}");
        }

        $p12Binary = Storage::disk($disk)->get($path);

        $meta = (array) ($cert->meta ?? []);
        $enc = $meta['p12_password_enc'] ?? null;

        if (!$enc) {
            throw new \RuntimeException('El certificado default no tiene password guardado en meta.p12_password_enc. Re-súbelo.');
        }

        try {
            $password = Crypt::decryptString((string) $enc);
        } catch (\Throwable $e) {
            throw new \RuntimeException('No se pudo desencriptar meta.p12_password_enc.');
        }

        // ✅ IMPORTANTE: password vacío '' es permitido (no lo bloquees aquí)
        return [$p12Binary, (string) $password];
    }
}
