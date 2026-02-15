<?php

namespace App\Http\Controllers\LaudaErp;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DgiiCertificate;
use App\Models\DgiiEndpointCatalog; // ✅ NUEVO
use App\Services\Subscribers\SubscriberResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DgiiCertificationController extends Controller
{
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
        ]);
    }
}
