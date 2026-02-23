<?php

namespace App\Services\Dgii;

use App\Models\DgiiCompanySetting;
use App\Models\DgiiEndpointCatalog;
use App\Models\DgiiTransmission;
use App\Services\Dgii\Endpoints\DgiiEndpointResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class HttpDgiiXmlSender
{
    public function __construct(
        private readonly DgiiEndpointResolver $builder,
        private readonly HttpDgiiAuthClient $authClient,
    ) {}

    /**
     * Envía XML a DGII y guarda todo el historial en dgii_transmissions (DB).
     *
     * NOTA: aquí NO guardamos request/response a archivos.
     *       Los únicos XML en disco son los del wrapper (y los *_signed.xml).
     */
    public function sendFromCatalog(
        int $companyId,
        string $environment,   // precert|cert|prod
        string $endpointKey,   // recepcion_ecf | aprobacion_comercial | recepcion_fc
        string $xml,           // normalmente el XML firmado
        string $filename = 'doc.xml',
        ?int $fiscalDocumentId = null,
        ?string $signedXmlPath = null,
        ?string $idempotencyKey = null,
        int $attempt = 1,
    ): array {

        // 1) Resolver endpoint desde catálogo
        $row = DgiiEndpointCatalog::query()
            ->where('environment', $environment)
            ->where('key', $endpointKey)
            ->where('is_active', 1)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();

        if (!$row) {
            throw new RuntimeException("DGII endpoint no encontrado: key={$endpointKey}, env={$environment}.");
        }

        $baseUrl = (string) $row->base_url;
        $path    = (string) $row->path;
        $method  = strtoupper((string) ($row->method ?: 'POST'));

        if (trim($baseUrl) === '' || trim($path) === '') {
            throw new RuntimeException("DGII endpoint inválido: key={$endpointKey}, env={$environment}.");
        }

        // 2) cfPrefix real según env
        $cfPrefix = match ($environment) {
            'precert' => 'testecf',
            'cert'    => 'certecf',
            'prod'    => 'ecf',
            default   => 'testecf',
        };

        // 3) URL final (reemplaza {cf}, etc.)
        $url = $this->builder->resolve($baseUrl, $path, $cfPrefix, []);

        // 4) Meta endpoint opcional (multipart_field, timeout, headers)
        $meta = $this->normalizeMeta($row->meta);
        $multipartField = (string) ($meta['multipart_field'] ?? 'xml');
        $extraHeaders   = is_array($meta['headers'] ?? null) ? $meta['headers'] : [];
        $timeout        = (int) ($meta['timeout'] ?? 30);

        // 5) Crear registro en dgii_transmissions ANTES de enviar
        $tx = new DgiiTransmission();
        $tx->company_id = $companyId;
        $tx->fiscal_document_id = $fiscalDocumentId;

        $tx->endpoint_key = $endpointKey;
        $tx->environment  = $environment;

        $tx->url = $url;
        $tx->http_method = $method;

        // puntero al archivo firmado (ya existe en tu private storage, pero aquí solo referenciamos)
        $tx->signed_xml_path = $signedXmlPath;
        $tx->signed_xml_sha256 = $xml !== '' ? hash('sha256', $xml) : null;
        $tx->signed_xml_size_bytes = $xml !== '' ? strlen($xml) : null;

        // request audit EN DB (como pediste)
        $tx->request_xml = $xml; // si esto lo quieres opcional, lo controlamos con config
        $tx->request_sha256 = $xml !== '' ? hash('sha256', $xml) : null;
        $tx->request_size_bytes = $xml !== '' ? strlen($xml) : null;
        $tx->request_content_type = 'multipart/form-data';
        $tx->request_headers = $extraHeaders ?: null;

        $tx->status = 'sending';
        $tx->attempt = max(1, $attempt);
        $tx->idempotency_key = $idempotencyKey;
        $tx->sent_at = now();
        $tx->save();

        // 6) Obtener token (tu authClient ya cachea)
        $setting = new DgiiCompanySetting();
        $setting->company_id = $companyId;
        $setting->environment = $environment;
        $setting->cf_prefix = $environment; // tu authClient mapea precert->testecf etc
        $setting->endpoints = [];

        $tok = $this->authClient->requestToken($setting);
        $token = (string) ($tok['token'] ?? '');

        if (trim($token) === '') {
            $tx->status = 'failed';
            $tx->error_message = 'DGII token vacío (requestToken no devolvió token).';
            $tx->received_at = now();
            $tx->save();
            throw new RuntimeException($tx->error_message);
        }

        $t0 = microtime(true);

        try {
            // 7) Enviar
            $res = Http::timeout($timeout)
                ->accept('*/*')
                ->withHeaders(array_merge([
                    'Accept-Encoding' => 'identity',
                    'User-Agent' => 'LaudaERP/1.0 (DGII Sender)',
                    'Authorization' => 'bearer ' . $token,
                ], $extraHeaders))
                ->attach($multipartField, $xml, $filename)
                ->send($method, $url);

            $durationMs = (int) round((microtime(true) - $t0) * 1000);

            $body = (string) $res->body();
            $ct   = (string) ($res->header('Content-Type') ?? '');

            // 8) Guardar response EN DB
            $tx->duration_ms = $durationMs;
            $tx->http_status = $res->status();
            $tx->response_content_type = $ct !== '' ? $ct : null;
            $tx->response_headers = $res->headers(); // array
            $tx->response_body = $body;
            $tx->response_sha256 = $body !== '' ? hash('sha256', $body) : null;
            $tx->response_size_bytes = $body !== '' ? strlen($body) : 0;
            $tx->received_at = now();

            // 9) Parse mínimo (trackId/estado/mensajes) para UI
            $parsed = $this->parseDgiiResponse($body, $ct);
            $tx->dgii_codigo   = $parsed['codigo'];
            $tx->dgii_estado   = $parsed['estado'];
            $tx->dgii_track_id = $parsed['track_id'];
            $tx->dgii_mensajes = $parsed['mensajes'];

            // 10) Status final por HTTP
            if ($res->ok()) {
                $tx->status = 'sent';
                $tx->save();

                return [
                    'ok' => true,
                    'transmission_id' => (int) $tx->id,
                    'transmission_public_id' => (string) $tx->public_id,
                    'http_status' => (int) $res->status(),
                    'dgii' => $parsed,
                ];
            }

            // HTTP != 2xx
            $tx->status = 'failed';
            $tx->error_message = "DGII envío falló ({$res->status()}).";
            $tx->save();

            Log::warning('DGII send failed', [
                'tx_public_id' => $tx->public_id,
                'company_id' => $companyId,
                'env' => $environment,
                'endpoint_key' => $endpointKey,
                'method' => $method,
                'url' => $url,
                'status' => $res->status(),
                'resp_snippet' => mb_substr(trim($body), 0, 1500),
            ]);

            throw new RuntimeException("DGII envío falló ({$res->status()}): " . mb_substr(trim($body), 0, 1500));

        } catch (Throwable $e) {
            // 11) Excepción => persistir en DB
            $tx->status = 'failed';
            $tx->error_message = mb_substr((string) $e->getMessage(), 0, 500);
            $tx->duration_ms = (int) round((microtime(true) - $t0) * 1000);
            $tx->received_at = now();
            $tx->save();

            throw $e;
        }
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

    /**
     * Parser tolerante (JSON o XML).
     * Solo extrae lo mínimo para UI: codigo/estado/trackId/mensajes.
     */
    private function parseDgiiResponse(string $body, ?string $contentType): array
    {
        $trim = ltrim($body);
        $ct   = strtolower((string) $contentType);

        $out = [
            'codigo' => null,
            'estado' => null,
            'track_id' => null,
            'mensajes' => null,
        ];

        // JSON?
        if (str_contains($ct, 'json') || ($trim !== '' && ($trim[0] === '{' || $trim[0] === '['))) {
            $json = json_decode($body, true);
            if (is_array($json)) {
                $out['codigo'] = $this->pick($json, ['codigo','Codigo','code','Code']);
                $out['estado'] = $this->pick($json, ['estado','Estado','status','Status']);
                $out['track_id'] = $this->pick($json, ['trackId','track_id','TrackId','TrackID','trackID']);

                $msgs = $this->pickAny($json, ['mensajes','Mensajes','messages','Messages','mensaje','Mensaje']);
                if (is_string($msgs)) $out['mensajes'] = [['message' => $msgs]];
                elseif (is_array($msgs)) $out['mensajes'] = $msgs;

                return $out;
            }
        }

        // XML (regex tolerante)
        if ($trim !== '' && str_contains($trim, '<')) {
            $out['track_id'] = $this->extractXmlTag($body, ['trackId','TrackId','TRACKID','trackID','TrackID']);
            $out['codigo']   = $this->extractXmlTag($body, ['codigo','Codigo','code','Code']);
            $out['estado']   = $this->extractXmlTag($body, ['estado','Estado','status','Status']);

            if (preg_match_all('/<\s*(mensaje|Mensaje)\s*>\s*([^<]+)\s*<\s*\/\s*\1\s*>/i', $body, $mm)) {
                $msgs = [];
                foreach ($mm[2] as $m) $msgs[] = ['message' => trim($m)];
                $out['mensajes'] = $msgs;
            }

            return $out;
        }

        // Texto plano
        if (trim($body) !== '') {
            $out['mensajes'] = [['message' => mb_substr(trim($body), 0, 1000)]];
        }

        return $out;
    }

    private function pick(array $data, array $keys): ?string
    {
        foreach ($keys as $k) {
            $v = data_get($data, $k);
            if (is_string($v) && trim($v) !== '') return trim($v);
        }
        return null;
    }

    private function pickAny(array $data, array $keys): mixed
    {
        foreach ($keys as $k) {
            $v = data_get($data, $k);
            if ($v !== null) return $v;
        }
        return null;
    }

    private function extractXmlTag(string $xml, array $tags): ?string
    {
        foreach ($tags as $t) {
            $m = []; // 👈 para Intelephense
            $pattern = '/<\s*' . preg_quote($t, '/') . '\s*>\s*([^<]+)\s*<\s*\/\s*' . preg_quote($t, '/') . '\s*>/i';

            if (preg_match($pattern, $xml, $m) === 1 && isset($m[1])) {
                return trim((string) $m[1]);
            }
        }

        return null;
    }
}