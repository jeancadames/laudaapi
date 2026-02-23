<?php

namespace App\Http\Controllers\LaudaErp;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DgiiCertificate;
use App\Models\DgiiEndpointCatalog;
use App\Services\Subscribers\SubscriberResolver;
use App\Services\Dgii\DgiiCertificateRequirements;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use Inertia\Inertia;

class DgiiCertificationController extends Controller
{
    private const KIND_TO_DIR = [
        'ecf'   => 'cert-ecf',
        'rfce'  => 'cert-rfce',
        'acecf' => 'cert-acecf',
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 403);

        $subscriberId = (int) $request->attributes->get('resolved_subscriber_id', 0);

        if ($subscriberId <= 0) {
            $subscriberId = (int) app(SubscriberResolver::class)->resolve($user);
        }

        abort_unless($subscriberId > 0, 403);

        $company = Company::where('subscriber_id', $subscriberId)->firstOrFail();

        // ✅ Defaults temporales (sin DgiiCompanySetting aún)
        $setting = [
            'environment'   => 'precert',
            'use_directory' => true,
            'endpoints'     => [], // (ya no dependemos de esto para mostrar el catálogo)
        ];

        // ✅ traer certificados para el tab del Index
        $certs = DgiiCertificate::query()
            ->where('company_id', $company->id)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'label' => $c->label,
                'type' => $c->type,
                'is_default' => (bool) $c->is_default,

                'subject_cn' => $c->subject_cn,
                'subject_rnc' => $c->subject_rnc,
                'issuer_cn' => $c->issuer_cn,
                'serial_number' => $c->serial_number,
                'valid_from' => optional($c->valid_from)->toISOString(),
                'valid_to' => optional($c->valid_to)->toISOString(),

                'has_private_key' => (bool) $c->has_private_key,
                'password_ok' => (bool) $c->password_ok,
                'status' => $c->status,

                'original_name' => $c->original_name,
                'file_size' => $c->file_size,

                // ⚠️ ojo: si tu meta a veces tiene bytes no-utf8, mejor NO mandarla aquí
                'meta' => $c->meta ?? null,
            ]);

        $default = $certs->firstWhere('is_default', true);

        // ✅ NUEVO: leer endpoints del seeder (dgii_endpoint_catalog) según ambiente
        $endpointCatalog = DgiiEndpointCatalog::query()
            ->where('environment', $setting['environment'])
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('key')
            ->get(['key', 'name', 'base_url', 'path', 'method', 'is_templated', 'is_default', 'meta'])
            ->map(function ($row) {
                // meta en tu seeder se guarda como string JSON
                $meta = $row->meta;

                if (is_string($meta)) {
                    $decoded = json_decode($meta, true);
                    $row->meta = is_array($decoded) ? $decoded : null;
                }

                return [
                    'key' => $row->key,
                    'name' => $row->name,
                    'base_url' => $row->base_url,
                    'path' => $row->path,
                    'method' => $row->method,
                    'is_templated' => (bool) $row->is_templated,
                    'is_default' => (bool) $row->is_default,
                    'meta' => $row->meta,
                ];
            });

        $certCheck = app(DgiiCertificateRequirements::class)->checkForCompany($company->id);

        $xmlFiles = [
            'ecf'   => $this->listXmlFilesForCompany('cert-ecf',   $company->id),
            'rfce'  => $this->listXmlFilesForCompany('cert-rfce',  $company->id),
            'acecf' => $this->listXmlFilesForCompany('cert-acecf', $company->id),
        ];

        return Inertia::render('LaudaERP/CertificacionEmisor/Index', [
            'company' => [
                'id'   => $company->id,
                'name' => $company->name ?? $company->business_name ?? null,
                'rnc'  => $company->rnc ?? null,
            ],
            'setting' => $setting,

            'certs' => $certs,
            'certs_summary' => [
                'count' => $certs->count(),
                'has_default' => (bool) $default,
                'default_cert_id' => $default['id'] ?? null,
            ],

            // ✅ NUEVO PROP: el tab Endpoints lo debe leer de aquí
            'endpoint_catalog' => $endpointCatalog,
            'cert_requirements' => $certCheck,
            // Para mostrar listados de xml
            'xml_files' => $xmlFiles,
        ]);
    }

    private function listXmlFilesForCompany(string $kind, int $companyId): array
    {
        // kind: cert-ecf | cert-rfce | cert-acecf
        $disk = Storage::disk('private');
        $baseDir = "dgii/{$kind}/company_{$companyId}";

        if (!$disk->exists($baseDir)) {
            return ['count' => 0, 'items' => []];
        }

        $files = $disk->files($baseDir);

        $items = [];
        foreach ($files as $relPath) {
            if (!preg_match('/\.xml$/i', $relPath)) continue;

            $name = basename($relPath);

            // ignorar los signed como “base” (los marcamos via flag)
            if (str_ends_with(strtolower($name), '_signed.xml')) continue;

            $signedName = preg_replace('/\.xml$/i', '_signed.xml', $name) ?? ($name . '_signed.xml');
            $signedRel  = $baseDir . '/' . $signedName;

            $mtime = $disk->lastModified($relPath);

            $respSuffixByKind = [
            'cert-ecf'   => '_arecf.xml',
            'cert-rfce'  => '_resp_fc.xml',
            'cert-acecf' => '_resp_aprob.xml',
            ];

            $respSuffix = $respSuffixByKind[$kind] ?? '_resp.xml';

            $respName = preg_replace('/\.xml$/i', $respSuffix, $name) ?? ($name . $respSuffix);
            $respRel  = $baseDir . '/' . $respName;

            $items[] = [
                'name' => $name,
                'type' => $this->extractTipoFromFilename($name),
                'size_bytes' => (int) $disk->size($relPath),
                // 'last_modified' => Carbon::createFromTimestamp($mtime)->format('Y-m-d H:i:s'),
                'last_modified' => (int) $disk->lastModified($relPath),
                'signed' => $disk->exists($signedRel),
                'signed_name' => $disk->exists($signedRel) ? $signedName : null,
                'sent' => $disk->exists($respRel),
                'response_name' => $disk->exists($respRel) ? $respName : null,
            ];
        }

        // ordenar por fecha desc
        usort($items, fn($a, $b) => strcmp($b['last_modified'], $a['last_modified']));

        return ['count' => count($items), 'items' => $items];
    }

    private function extractTipoFromFilename(string $filename): ?string
    {
        // quitar .xml o .XML
        $base = preg_replace('/\.xml$/i', '', $filename) ?? $filename;

        // Caso ideal: el tipo va justo antes de los últimos 10 dígitos
        // ...E46 + 0000000008
        if (preg_match('/(E\d{2})(?=\d{10}$)/', $base, $m)) {
            return $m[1];
        }

        // Fallback: último E## en el string
        if (preg_match_all('/E\d{2}/', $base, $mm) && !empty($mm[0])) {
            return end($mm[0]) ?: null;
        }

        return null;
    }
}
