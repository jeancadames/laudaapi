<?php

namespace App\Http\Controllers\LaudaErp;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DgiiCertificate;
use App\Models\DgiiEndpointCatalog;
use App\Services\Dgii\DgiiCertificateRequirements;
use App\Services\Subscribers\SubscriberResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DgiiCertificationController extends Controller
{
    private const KIND_TO_DIR = [
        'ecf' => 'cert-ecf',
        'rfce' => 'cert-rfce',
        'acecf' => 'cert-acecf',
    ];

    private function companyFromErp(Request $request): Company
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

    public function index(Request $request): Response
    {
        $company = $this->companyFromErp($request);

        // Defaults temporales
        $setting = [
            'environment' => 'precert',
            'use_directory' => true,
            'endpoints' => [],
        ];

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

                'meta' => $c->meta ?? null,
            ]);

        $default = $certs->firstWhere('is_default', true);

        $endpointCatalog = DgiiEndpointCatalog::query()
            ->where('environment', $setting['environment'])
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('key')
            ->get(['key', 'name', 'base_url', 'path', 'method', 'is_templated', 'is_default', 'meta'])
            ->map(function ($row) {
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
            'ecf' => $this->listXmlFilesForCompany('cert-ecf', $company->id),
            'rfce' => $this->listXmlFilesForCompany('cert-rfce', $company->id),
            'acecf' => $this->listXmlFilesForCompany('cert-acecf', $company->id),
        ];

        return Inertia::render('LaudaERP/CertificacionEmisor/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name ?? $company->business_name ?? null,
                'rnc' => $company->rnc ?? null,
                'slug' => $company->slug ?? null,
                'ws_subdomain' => $company->ws_subdomain ?? null,
            ],
            'setting' => $setting,

            'certs' => $certs,
            'certs_summary' => [
                'count' => $certs->count(),
                'has_default' => (bool) $default,
                'default_cert_id' => $default['id'] ?? null,
            ],

            'endpoint_catalog' => $endpointCatalog,
            'cert_requirements' => $certCheck,
            'xml_files' => $xmlFiles,

            // Opcional: si luego quieres precargar logs por Inertia
            'ws_activity' => [],
        ]);
    }

    public function wsActivity(Request $request): JsonResponse
    {
        $company = $this->companyFromErp($request);

        $level = strtolower((string) $request->string('level', 'all'));
        $search = trim((string) $request->string('search', ''));
        $limit = (int) $request->integer('limit', 200);

        $limit = max(1, min($limit, 500));

        $logPath = storage_path('app/private/dgii/ws-activity/company_' . $company->id . '.jsonl');

        if (! File::exists($logPath)) {
            return response()->json([
                'items' => [],
                'stats' => [
                    'total' => 0,
                    'warnings' => 0,
                    'errors' => 0,
                    'last' => null,
                ],
            ]);
        }

        $lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (! is_array($lines) || empty($lines)) {
            return response()->json([
                'items' => [],
                'stats' => [
                    'total' => 0,
                    'warnings' => 0,
                    'errors' => 0,
                    'last' => null,
                ],
            ]);
        }

        $items = [];

        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $raw = trim($lines[$i]);

            if ($raw === '') {
                continue;
            }

            $decoded = json_decode($raw, true);

            if (! is_array($decoded)) {
                continue;
            }

            $item = [
                'ts' => $decoded['ts'] ?? $decoded['timestamp'] ?? null,
                'level' => strtolower((string) ($decoded['level'] ?? 'info')),
                'event' => $decoded['event'] ?? null,
                'method' => $decoded['method'] ?? null,
                'path' => $decoded['path'] ?? null,
                'status' => isset($decoded['status']) ? (int) $decoded['status'] : null,
                'duration_ms' => isset($decoded['duration_ms']) ? (int) $decoded['duration_ms'] : null,
                'cid' => $decoded['cid'] ?? $decoded['correlation_id'] ?? null,
                'in_path' => $decoded['in_path'] ?? null,
                'out_path' => $decoded['out_path'] ?? null,
                'dgii_resp_path' => $decoded['dgii_resp_path'] ?? null,
                'encf' => $decoded['encf'] ?? null,
                'rnc' => $decoded['rnc'] ?? null,
            ];

            if ($level !== 'all' && $item['level'] !== $level) {
                continue;
            }

            if ($search !== '') {
                $haystack = strtolower(implode(' ', array_filter([
                    $item['ts'],
                    $item['level'],
                    $item['event'],
                    $item['method'],
                    $item['path'],
                    (string) $item['status'],
                    (string) $item['duration_ms'],
                    $item['cid'],
                    $item['in_path'],
                    $item['out_path'],
                    $item['dgii_resp_path'],
                    $item['encf'],
                    $item['rnc'],
                ], fn($v) => $v !== null && $v !== '')));

                if (! str_contains($haystack, strtolower($search))) {
                    continue;
                }
            }

            $items[] = $item;

            if (count($items) >= $limit) {
                break;
            }
        }

        $warnings = collect($items)->where('level', 'warning')->count();
        $errors = collect($items)->where('level', 'error')->count();

        return response()->json([
            'items' => $items,
            'stats' => [
                'total' => count($items),
                'warnings' => $warnings,
                'errors' => $errors,
                'last' => $items[0]['ts'] ?? null,
            ],
        ]);
    }

    private function listXmlFilesForCompany(string $kind, int $companyId): array
    {
        $disk = Storage::disk('private');
        $baseDir = "dgii/{$kind}/company_{$companyId}";

        if (! $disk->exists($baseDir)) {
            return ['count' => 0, 'items' => []];
        }

        $files = $disk->files($baseDir);

        $items = [];

        foreach ($files as $relPath) {
            if (! preg_match('/\.xml$/i', $relPath)) {
                continue;
            }

            $name = basename($relPath);

            if (str_ends_with(strtolower($name), '_signed.xml')) {
                continue;
            }

            $signedName = preg_replace('/\.xml$/i', '_signed.xml', $name) ?? ($name . '_signed.xml');
            $signedRel = $baseDir . '/' . $signedName;

            $respSuffixByKind = [
                'cert-ecf' => '_arecf.xml',
                'cert-rfce' => '_resp_fc.xml',
                'cert-acecf' => '_resp_aprob.xml',
            ];

            $respSuffix = $respSuffixByKind[$kind] ?? '_resp.xml';

            $respName = preg_replace('/\.xml$/i', $respSuffix, $name) ?? ($name . $respSuffix);
            $respRel = $baseDir . '/' . $respName;

            $items[] = [
                'name' => $name,
                'type' => $this->extractTipoFromFilename($name),
                'size_bytes' => (int) $disk->size($relPath),
                'last_modified' => (int) $disk->lastModified($relPath),
                'signed' => $disk->exists($signedRel),
                'signed_name' => $disk->exists($signedRel) ? $signedName : null,
                'sent' => $disk->exists($respRel),
                'response_name' => $disk->exists($respRel) ? $respName : null,
            ];
        }

        usort($items, fn($a, $b) => $b['last_modified'] <=> $a['last_modified']);

        return ['count' => count($items), 'items' => $items];
    }

    private function extractTipoFromFilename(string $filename): ?string
    {
        $base = preg_replace('/\.xml$/i', '', $filename) ?? $filename;

        if (preg_match('/(E\d{2})(?=\d{10}$)/', $base, $m)) {
            return $m[1];
        }

        if (preg_match_all('/E\d{2}/', $base, $mm) && ! empty($mm[0])) {
            return end($mm[0]) ?: null;
        }

        return null;
    }
}
