<?php

namespace App\Http\Controllers\LaudaErp;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Dgii\HttpDgiiXmlSender;
use App\Services\Subscribers\SubscriberResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class DgiiXmlSendController extends Controller
{
    private const KIND_MAP = [
        'ecf' => [
            'root_dir' => 'dgii/cert-ecf',
            'subdir' => 'ecf',
            'endpoint_key' => 'recepcion.facturas_electronicas',
            'resp_suffix' => '_arecf.xml',
        ],
        'rfce' => [
            'root_dir' => 'dgii/cert-ecf',
            'subdir' => 'rfce',
            'endpoint_key' => 'recepcion_fc',
            'resp_suffix' => '_resp_fc.xml',
        ],
        'acecf' => [
            'root_dir' => 'dgii/cert-acecf',
            'subdir' => null,
            'endpoint_key' => 'aprobacion_comercial',
            'resp_suffix' => '_resp_aprob.xml',
        ],
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

    private function resolveEnvironmentForCompany(int $companyId): string
    {
        if (Schema::hasTable('dgii_company_settings')) {
            $row = \App\Models\DgiiCompanySetting::query()
                ->where('company_id', $companyId)
                ->first();

            $env = $row?->environment;
            if (is_string($env) && in_array($env, ['precert', 'cert', 'prod'], true)) {
                return $env;
            }
        }

        return 'precert';
    }

    public function send(Request $request, HttpDgiiXmlSender $sender)
    {
        try {
            $data = $request->validate([
                'kind' => ['required', 'in:ecf,rfce,acecf'],
                'name' => ['required', 'string', 'max:255'],
            ]);

            $company = $this->companyFromErp($request);
            $companyId = (int) $company->id;
            $kind = (string) $data['kind'];

            $name = basename($data['name']);
            abort_unless($name === $data['name'], 422, 'Nombre inválido.');
            abort_unless(preg_match('/\.xml$/i', $name) === 1, 422, 'Debe ser .xml');
            abort_unless(! str_contains($name, '..'), 422, 'Nombre inválido.');

            $disk = Storage::disk('private');

            $xmlRel = $this->xmlRelPathForKindWithFallback($disk, $kind, $companyId, $name);
            abort_unless($disk->exists($xmlRel), 422, "No existe XML: {$name}");

            // Regla de negocio:
            // ECF 32 < 250,000 no se envía; solo se firma y se descarga.
            if ($kind === 'ecf') {
                $meta = $this->findManifestItem($companyId, 'ecf', $name);

                if (($meta['workflow'] ?? null) === 'sign_download_only') {
                    throw new RuntimeException("Este ECF 32 menor a RD$250,000 no se envía a DGII. Solo debe firmarse y descargarse.");
                }
            }

            $signedXml = (string) $disk->get($xmlRel);
            $environment = $this->resolveEnvironmentForCompany($companyId);
            $endpointKey = (string) self::KIND_MAP[$kind]['endpoint_key'];

            $out = $sender->sendFromCatalog(
                companyId: $companyId,
                environment: $environment,
                endpointKey: $endpointKey,
                xml: $signedXml,
                filename: $name
            );

            $respRel = $this->responseRelPathForKind($kind, $companyId, $name);
            $respName = basename($respRel);

            $disk->put($respRel, (string) $out['body']);

            return response()->json([
                'ok' => true,
                'message' => 'Enviado correctamente.',
                'env' => $environment,
                'endpoint_key' => $endpointKey,
                'response_name' => $respName,
            ]);
        } catch (Throwable $e) {
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            logger()->error('XML SEND failed', [
                'status' => $status,
                'kind' => $request->input('kind'),
                'name' => $request->input('name'),
                'user_id' => optional($request->user())->id,
                'msg' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 3000),
            ]);

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage() ?: 'Error enviando XML.',
            ], $status);
        }
    }

    private function xmlBaseDirForKind(string $kind, int $companyId): string
    {
        $cfg = self::KIND_MAP[$kind] ?? null;
        if (! is_array($cfg)) {
            throw new RuntimeException("Kind inválido: {$kind}");
        }

        $base = rtrim((string) $cfg['root_dir'], '/') . "/company_{$companyId}";
        $subdir = $cfg['subdir'] ?? null;

        return $subdir ? "{$base}/{$subdir}" : $base;
    }

    private function xmlRelPathForKind(string $kind, int $companyId, string $name): string
    {
        return $this->xmlBaseDirForKind($kind, $companyId) . '/' . ltrim($name, '/');
    }

    private function xmlRelPathForKindWithFallback($disk, string $kind, int $companyId, string $name): string
    {
        $primary = $this->xmlRelPathForKind($kind, $companyId, $name);

        if ($disk->exists($primary)) {
            return $primary;
        }

        $legacy = match ($kind) {
            'ecf' => "dgii/cert-ecf/company_{$companyId}/{$name}",
            'rfce' => "dgii/cert-rfce/company_{$companyId}/{$name}",
            'acecf' => "dgii/cert-acecf/company_{$companyId}/{$name}",
            default => $primary,
        };

        return $disk->exists($legacy) ? $legacy : $primary;
    }

    private function responseRelPathForKind(string $kind, int $companyId, string $xmlName): string
    {
        $baseDir = $this->xmlBaseDirForKind($kind, $companyId);
        $suffix = (string) self::KIND_MAP[$kind]['resp_suffix'];

        $respName = preg_replace('/\.xml$/i', $suffix, $xmlName) ?? ($xmlName . $suffix);

        return "{$baseDir}/{$respName}";
    }

    private function manifestRelPath(int $companyId): string
    {
        return "dgii/cert-ecf/company_{$companyId}/_xml_order.json";
    }

    private function loadManifestItems(int $companyId): array
    {
        $disk = Storage::disk('private');
        $rel = $this->manifestRelPath($companyId);

        if (! $disk->exists($rel)) {
            return [];
        }

        $raw = (string) $disk->get($rel);
        if (trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $items = $decoded['items'] ?? [];
        return is_array($items) ? $items : [];
    }

    private function findManifestItem(int $companyId, string $kind, string $name): ?array
    {
        foreach ($this->loadManifestItems($companyId) as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['kind'] ?? null) !== $kind) {
                continue;
            }

            if (trim((string) ($item['name'] ?? '')) !== $name) {
                continue;
            }

            return $item;
        }

        return null;
    }
}