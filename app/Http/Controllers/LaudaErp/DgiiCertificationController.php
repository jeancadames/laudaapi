<?php

namespace App\Http\Controllers\LaudaErp;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DgiiCertificate;
use App\Models\DgiiCompanySetting;
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
        'rfce' => 'cert-ecf', // ✅ ahora RFCE vive en el wrapper unificado
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

        $companySetting = DgiiCompanySetting::query()
        ->where('company_id', $company->id)
        ->first();

        $setting = [
            'environment' => $companySetting?->environment ?? 'precert',
            'cf_prefix' => $companySetting?->cf_prefix ?? 'testecf',
            'use_directory' => (bool) ($companySetting?->use_directory ?? true),
            'endpoints' => $companySetting?->endpoints ?? [],
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

        // ✅ Wrapper unificado: de aquí salen ecf + rfce
        $wrapperXmlFiles = $this->listUnifiedWrapperXmlFilesForCompany($company->id);

        $xmlFiles = [
            'wrapper' => $wrapperXmlFiles['wrapper'],
            'ecf' => $wrapperXmlFiles['ecf'],
            'rfce' => $wrapperXmlFiles['rfce'],
            'acecf' => $this->listXmlFilesForCompany('acecf', $company->id),
        ];

        logger()->info('WRAPPER COUNTS', [
            'wrapper_count' => $xmlFiles['wrapper']['count'] ?? null,
            'ecf_count' => $xmlFiles['ecf']['count'] ?? null,
            'rfce_count' => $xmlFiles['rfce']['count'] ?? null,
            'wrapper_names' => array_map(fn ($x) => [$x['kind'] ?? null, $x['name'] ?? null], $xmlFiles['wrapper']['items'] ?? []),
        ]);

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

    /**
     * ✅ Lee el wrapper unificado desde cert-ecf y lo separa en:
     * - wrapper (todo en orden combinado)
     * - ecf
     * - rfce
     */
    private function listUnifiedWrapperXmlFilesForCompany(int $companyId): array
    {
        $disk = Storage::disk('private');
        $baseDir = 'dgii/cert-ecf/company_' . $companyId;

        $buckets = [
            'wrapper' => $this->emptyBucket('wrapper', $baseDir),
            'ecf' => $this->emptyBucket('ecf', $baseDir),
            'rfce' => $this->emptyBucket('rfce', $baseDir),
        ];

        if (! $disk->exists($baseDir)) {
            return $buckets;
        }

        $manifest = $this->loadXmlManifestIndex($disk, $baseDir);
        $files = $disk->allFiles($baseDir);

        foreach ($files as $relPath) {
            if (! preg_match('/\.xml$/i', $relPath)) {
                continue;
            }

        $name = basename($relPath);
        $dirName = trim(str_replace($baseDir, '', dirname($relPath)), '/');
        $compositeKey = ($dirName !== '' ? "{$dirName}/" : '') . $name;

        $meta = $manifest[$compositeKey] ?? [];

            $kind = in_array(($meta['kind'] ?? null), ['ecf', 'rfce'], true)
                ? $meta['kind']
                : ($dirName === 'rfce' ? 'rfce' : 'ecf');

            $respSuffix = $kind === 'rfce' ? '_resp_fc.xml' : '_arecf.xml';
            $respName = preg_replace('/\.xml$/i', $respSuffix, $name) ?? ($name . $respSuffix);

            $subdir = trim((string) ($meta['subdir'] ?? $dirName), '/');
            $respRel = $subdir !== ''
                ? "{$baseDir}/{$subdir}/{$respName}"
                : "{$baseDir}/{$respName}";

            $tipo = $meta['tipo_ecf'] ?? null;
            $tipo = $tipo !== null && $tipo !== '' ? 'E' . $tipo : $this->extractTipoFromFilename($name);

            $item = [
                'name' => $name,
                'kind' => $kind,
                'type' => $tipo,
                'eNCF' => $meta['eNCF'] ?? null,
                'sheet' => $meta['sheet'] ?? null,
                'group_key' => $meta['group_key'] ?? null,
                'group_label' => $meta['group_label'] ?? null,
                'group_stage_order' => $meta['group_stage_order'] ?? null,
                'group_stage_label' => $meta['group_stage_label'] ?? null,
                'dgii_type_label' => $meta['dgii_type_label'] ?? null,
                'workflow' => $meta['workflow'] ?? 'send',
                'pair_eNCF' => $meta['pair_eNCF'] ?? null,
                'monto_total' => isset($meta['monto_total']) ? (float) $meta['monto_total'] : null,
                'has_security_code_placeholder' => (bool) ($meta['has_security_code_placeholder'] ?? false),

                'size_bytes' => (int) $disk->size($relPath),
                'last_modified' => (int) $disk->lastModified($relPath),
                'signed' => $this->isXmlSigned($disk, $relPath),
                'signed_name' => $this->isXmlSigned($disk, $relPath) ? $name : null,
                'sent' => $disk->exists($respRel),
                'response_name' => $disk->exists($respRel) ? $respName : null,
                'order_index' => (int) ($meta['order'] ?? PHP_INT_MAX),
            ];

            $buckets['wrapper']['items'][] = $item;
            $buckets[$kind]['items'][] = $item;
        }

        foreach (['wrapper', 'ecf', 'rfce'] as $bucketKey) {
            usort($buckets[$bucketKey]['items'], function ($a, $b) {
                $byOrder = ($a['order_index'] ?? PHP_INT_MAX) <=> ($b['order_index'] ?? PHP_INT_MAX);
                if ($byOrder !== 0) {
                    return $byOrder;
                }

                $byModified = ($b['last_modified'] ?? 0) <=> ($a['last_modified'] ?? 0);
                if ($byModified !== 0) {
                    return $byModified;
                }

                return strnatcasecmp((string) $a['name'], (string) $b['name']);
            });

            $buckets[$bucketKey]['items'] = array_map(function ($item) {
                unset($item['order_index']);
                return $item;
            }, $buckets[$bucketKey]['items']);

            $buckets[$bucketKey]['count'] = count($buckets[$bucketKey]['items']);
        }

        return $buckets;
    }

    private function emptyBucket(string $kind, string $baseDir): array
    {
        return [
            'kind' => $kind,
            'base_dir' => $baseDir,
            'count' => 0,
            'items' => [],
        ];
    }

    private function listXmlFilesForCompany(string $kind, int $companyId): array
    {
        $disk = Storage::disk('private');
        $dir = self::KIND_TO_DIR[$kind] ?? $kind;
        $baseDir = "dgii/{$dir}/company_{$companyId}";

        if (! $disk->exists($baseDir)) {
            return [
                'kind' => $kind,
                'base_dir' => $baseDir,
                'count' => 0,
                'items' => [],
            ];
        }

        $files = $disk->files($baseDir);
        $orderMap = $this->loadXmlOrderMapFromBaseDir($disk, $baseDir);

        $items = [];

        $respSuffixByKind = [
            'ecf' => '_arecf.xml',
            'rfce' => '_resp_fc.xml',
            'acecf' => '_resp_aprob.xml',
        ];

        $allKnownResponseSuffixes = [
            '_arecf.xml',
            '_resp_fc.xml',
            '_resp_aprob.xml',
        ];

        $respSuffix = $respSuffixByKind[$kind] ?? '_resp.xml';

        foreach ($files as $relPath) {
            if (! preg_match('/\.xml$/i', $relPath)) {
                continue;
            }

            $name = basename($relPath);
            $lowerName = strtolower($name);

            $isResponseArtifact = false;
            foreach ($allKnownResponseSuffixes as $suffix) {
                if (str_ends_with($lowerName, strtolower($suffix))) {
                    $isResponseArtifact = true;
                    break;
                }
            }

            if ($isResponseArtifact) {
                continue;
            }

            $respName = preg_replace('/\.xml$/i', $respSuffix, $name) ?? ($name . $respSuffix);
            $respRel = $baseDir . '/' . $respName;

            $isSigned = $this->isXmlSigned($disk, $relPath);
            $isSent = $disk->exists($respRel);

            $items[] = [
                'name' => $name,
                'kind' => $kind,
                'type' => $this->extractTipoFromFilename($name),
                'size_bytes' => (int) $disk->size($relPath),
                'last_modified' => (int) $disk->lastModified($relPath),
                'signed' => $isSigned,
                'signed_name' => $isSigned ? $name : null,
                'sent' => $isSent,
                'response_name' => $isSent ? $respName : null,
                'order_index' => $orderMap[$name] ?? PHP_INT_MAX,
            ];
        }

        usort($items, function ($a, $b) {
            $byOrder = ($a['order_index'] ?? PHP_INT_MAX) <=> ($b['order_index'] ?? PHP_INT_MAX);
            if ($byOrder !== 0) {
                return $byOrder;
            }

            return strnatcasecmp((string) $a['name'], (string) $b['name']);
        });

        $items = array_map(function ($item) {
            unset($item['order_index']);
            return $item;
        }, $items);

        return [
            'kind' => $kind,
            'base_dir' => $baseDir,
            'count' => count($items),
            'items' => $items,
        ];
    }

    private function loadXmlOrderMapFromBaseDir($disk, string $baseDir): array
    {
        $manifestRel = "{$baseDir}/_xml_order.json";

        if (! $disk->exists($manifestRel)) {
            return [];
        }

        $raw = (string) $disk->get($manifestRel);
        if (trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $items = $decoded['items'] ?? null;
        if (! is_array($items)) {
            return [];
        }

        $map = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            // Si el manifiesto trae "order", úsalo.
            // Si no, usa "row".
            // Si no, usa el índice del array.
            $order = null;

            if (isset($item['order']) && is_numeric($item['order'])) {
                $order = (int) $item['order'];
            } elseif (isset($item['row']) && is_numeric($item['row'])) {
                $order = (int) $item['row'];
            } else {
                $order = $index + 1;
            }

            $map[$name] = $order;
        }

        return $map;
    }

    private function isXmlSigned($disk, string $relPath): bool
    {
        if (! $disk->exists($relPath)) {
            return false;
        }

        $xml = (string) $disk->get($relPath);
        if (trim($xml) === '') {
            return false;
        }

        return str_contains($xml, '<Signature')
            || str_contains($xml, '<ds:Signature')
            || str_contains($xml, 'http://www.w3.org/2000/09/xmldsig#');
    }

    /**
     * ✅ Carga metadata completa del manifiesto del wrapper nuevo.
     */
    private function loadXmlManifestIndex($disk, string $baseDir): array
    {
        $manifestRel = "{$baseDir}/_xml_order.json";

        if (! $disk->exists($manifestRel)) {
            return [];
        }

        $raw = (string) $disk->get($manifestRel);
        if (trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $items = $decoded['items'] ?? null;
        if (! is_array($items)) {
            return [];
        }

        $map = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            $subdir = trim((string) ($item['subdir'] ?? ''), '/');

            if ($name === '') {
                continue;
            }

            $compositeKey = ($subdir !== '' ? "{$subdir}/" : '') . $name;

            $map[$compositeKey] = [
                'order' => (int) ($item['order'] ?? ($index + 1)),
                'subdir' => $subdir !== '' ? $subdir : null,
                'kind' => $item['kind'] ?? null,
                'sheet' => $item['sheet'] ?? null,
                'row' => isset($item['row']) ? (int) $item['row'] : null,
                'tipo_ecf' => $item['tipo_ecf'] ?? null,
                'eNCF' => $item['eNCF'] ?? null,
                'monto_total' => $item['monto_total'] ?? null,
                'group_key' => $item['group_key'] ?? null,
                'group_label' => $item['group_label'] ?? null,
                'group_stage_order' => isset($item['group_stage_order']) ? (int) $item['group_stage_order'] : null,
                'group_stage_label' => $item['group_stage_label'] ?? null,
                'dgii_type_label' => $item['dgii_type_label'] ?? null,
                'workflow' => $item['workflow'] ?? null,
                'pair_eNCF' => $item['pair_eNCF'] ?? null,
                'has_security_code_placeholder' => (bool) ($item['has_security_code_placeholder'] ?? false),
            ];
        }

        return $map;
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