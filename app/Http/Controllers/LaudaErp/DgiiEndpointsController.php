<?php

namespace App\Http\Controllers\LaudaErp;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DgiiCompanySetting;
use App\Services\Subscribers\SubscriberResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class DgiiEndpointsController extends Controller
{
    public function show(Request $request)
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

        $baseUrls = $this->getBaseUrls($setting);
        $endpoints = (array)($setting->endpoints ?? []);

        // catálogo (seed) para UI
        $catalog = $this->buildCatalogForUi($setting->environment, $setting->cf_prefix);

        return Inertia::render('LaudaERP/CertificacionEmisor/Endpoints', [
            'company' => [
                'id'   => $company->id,
                'name' => $company->name ?? $company->business_name ?? null,
                'rnc'  => $company->rnc ?? null,
            ],
            'setting' => [
                'environment'   => $setting->environment,
                'cf_prefix'     => $setting->cf_prefix,
                'use_directory' => (bool)$setting->use_directory,
                'endpoints'     => $endpoints,
                'base_urls'     => $baseUrls,
            ],
            'catalog' => $catalog,
        ]);
    }

    public function update(Request $request)
    {
        $company = $this->resolveCompany($request);

        $data = $request->validate([
            'environment'   => ['required', 'in:precert,cert,prod'],
            'use_directory' => ['required', 'boolean'],
            'cf_prefix'     => ['nullable', 'string', 'max:20'],

            'endpoints'     => ['nullable', 'array'],
            'base_urls'     => ['nullable', 'array'],

            // hardening
            'base_urls.*'   => ['nullable', 'string', 'max:255'],
        ]);

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

        $env = $data['environment'];

        // cf_prefix default por ambiente si viene vacío
        $cfPrefix = trim((string)($data['cf_prefix'] ?? ''));
        if ($cfPrefix === '') {
            $cfPrefix = $this->defaultCfPrefix($env);
        }

        // endpoints: solo strings/números, sin vacíos
        $endpoints = (array)($data['endpoints'] ?? []);
        $endpoints = collect($endpoints)
            ->filter(fn($v, $k) => is_string($k) && (is_string($v) || is_numeric($v)))
            ->map(fn($v) => trim((string)$v))
            ->filter(fn($v) => $v !== '')
            ->toArray();

        // base_urls: normaliza y asegura 3 keys (ecf/fc/status)
        $baseUrls = (array)($data['base_urls'] ?? []);
        $baseUrls = collect($baseUrls)
            ->filter(fn($v, $k) => in_array($k, ['ecf', 'fc', 'status'], true))
            ->map(fn($v) => rtrim(trim((string)$v), '/'))
            ->filter(fn($v) => $v !== '')
            ->toArray();

        // defaults si no te mandan nada
        if (empty($baseUrls)) {
            $baseUrls = $this->defaultBaseUrls();
        } else {
            $baseUrls = array_merge($this->defaultBaseUrls(), $baseUrls);
        }

        $setting->environment   = $env;
        $setting->cf_prefix     = $cfPrefix;
        $setting->use_directory = (bool)$data['use_directory'];

        $setting->endpoints = !empty($endpoints) ? $endpoints : null;

        // guarda base_urls en columna si existe; si no, en meta
        $this->setBaseUrls($setting, $baseUrls);

        $setting->save();

        return redirect()
            ->to('/erp/services/certificacion-emisor/endpoints')
            ->with('success', 'Endpoints actualizados');
    }

    /* ---------------- Helpers ---------------- */

    private function resolveCompany(Request $request): Company
    {
        $user = $request->user();
        abort_unless($user, 403);

        $subscriberId = (int)$request->attributes->get('resolved_subscriber_id', 0);
        if ($subscriberId <= 0) {
            $subscriberId = (int)app(SubscriberResolver::class)->resolve($user);
        }

        abort_unless($subscriberId > 0, 403);

        return Company::where('subscriber_id', $subscriberId)->firstOrFail();
    }

    private function defaultCfPrefix(string $env): string
    {
        // Nota: DGII usa: testecf / certecf / ecf
        return match ($env) {
            'precert' => 'testecf',
            'cert'    => 'certecf',
            'prod'    => 'ecf',
            default   => 'certecf',
        };
    }

    private function defaultBaseUrls(): array
    {
        return [
            'ecf'    => 'https://ecf.dgii.gov.do',
            'fc'     => 'https://fc.dgii.gov.do',
            'status' => 'https://statusecf.dgii.gov.do',
        ];
    }

    private function hasBaseUrlsColumn(): bool
    {
        return Schema::hasColumn('dgii_company_settings', 'base_urls');
    }

    private function getBaseUrls(DgiiCompanySetting $setting): array
    {
        if ($this->hasBaseUrlsColumn()) {
            $val = (array)($setting->base_urls ?? []);
            return array_merge($this->defaultBaseUrls(), $val);
        }

        $meta = (array)($setting->meta ?? []);
        $val = (array)($meta['base_urls'] ?? []);
        return array_merge($this->defaultBaseUrls(), $val);
    }

    private function setBaseUrls(DgiiCompanySetting $setting, array $baseUrls): void
    {
        if ($this->hasBaseUrlsColumn()) {
            $setting->base_urls = $baseUrls;
            return;
        }

        $meta = (array)($setting->meta ?? []);
        $meta['base_urls'] = $baseUrls;
        $setting->meta = $meta;
    }

    private function buildCatalogForUi(string $env, string $cfPrefix): array
    {
        if (!Schema::hasTable('dgii_endpoint_catalog')) return [];

        $rows = DB::table('dgii_endpoint_catalog')
            ->where('environment', $env)
            ->where('is_active', 1)
            ->orderBy('is_default', 'desc')
            ->orderBy('key')
            ->get();

        $hostKey = function (?string $baseUrl): string {
            $u = strtolower(trim((string)$baseUrl));
            if (str_contains($u, 'statusecf.dgii.gov.do')) return 'status';
            if (str_contains($u, 'fc.dgii.gov.do')) return 'fc';
            return 'ecf';
        };

        $extractPlaceholders = function (string $path): array {
            preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $path, $m);
            return array_values(array_unique(array_filter($m[1] ?? [], fn($x) => $x !== 'cf')));
        };

        $previewUrl = function (?string $baseUrl, ?string $path) use ($cfPrefix): string {
            $base = rtrim((string)$baseUrl, '/');
            $p = (string)$path;
            if ($base === '' || $p === '') return '—';

            $p = str_replace('{cf}', $cfPrefix, $p);
            $p = str_replace('{trackid}', 'TRACKID_DEMO', $p);

            return $base . (str_starts_with($p, '/') ? $p : ('/' . $p));
        };

        return $rows->map(function ($r) use ($hostKey, $extractPlaceholders, $previewUrl) {
            $path = (string)($r->path ?? '');
            return [
                'key'          => $r->key,
                'label'        => $r->name ?? $r->key,
                'method'       => $r->method ?? 'GET',
                'host_key'     => $hostKey($r->base_url ?? null),
                'path'         => $path,
                'placeholders' => $extractPlaceholders($path),
                'preview'      => $previewUrl($r->base_url ?? null, $path),
            ];
        })->values()->all();
    }
}
