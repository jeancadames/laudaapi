<?php

namespace App\Services\Dgii;

use App\Models\DgiiCompanySetting;
use App\Models\DgiiEndpointCatalog;
use App\Models\DgiiTransmission;
use App\Services\Dgii\Endpoints\DgiiEndpointResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class HttpDgiiXmlSender
{
    public function __construct(
        private readonly DgiiEndpointResolver $builder,
        private readonly HttpDgiiAuthClient $authClient,
    ) {}

    public function sendFromCatalog(
        int $companyId,
        string $environment,   // precert|cert|prod
        string $endpointKey,   // recepcion.facturas_electronicas | etc
        string $xml,           // fallback si no se puede leer desde disco
        ?string $filename = null,
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

        if (trim($baseUrl) === '' || trim($path) === '') {
            throw new RuntimeException("DGII endpoint inválido: key={$endpointKey}, env={$environment}.");
        }

        // 2) Mapear env real DGII
        $cfPrefix = match ($environment) {
            'precert' => 'testecf',
            'cert'    => 'certecf',
            'prod'    => 'ecf',
            default   => 'testecf',
        };

        // 3) URL final
        $url = $this->builder->resolve($baseUrl, $path, $cfPrefix, []);

        // 4) Nombre final del archivo
        $finalFilename = $this->resolveUploadFilename($filename, $signedXmlPath);

        // 5) Cargar EXACTAMENTE el XML firmado final desde disco si existe
        $payloadXml = $xml;
        $resolvedSignedXmlAbsolutePath = $this->resolveReadableSignedXmlAbsolutePath($signedXmlPath);

        if ($resolvedSignedXmlAbsolutePath !== null) {
            $diskXml = @file_get_contents($resolvedSignedXmlAbsolutePath);

            if ($diskXml === false || trim($diskXml) === '') {
                throw new RuntimeException("No se pudo leer el XML firmado desde {$resolvedSignedXmlAbsolutePath}.");
            }

            $payloadXml = $diskXml;
        }

        if (trim($payloadXml) === '') {
            throw new RuntimeException('XML firmado vacío para envío a DGII.');
        }

        // 6) Validación local mínima
        $this->validateXmlBeforeSend($payloadXml, $finalFilename);

        // 7) Crear transmisión antes de enviar
        $tx = new DgiiTransmission();
        $tx->company_id = $companyId;
        $tx->fiscal_document_id = $fiscalDocumentId;
        $tx->endpoint_key = $endpointKey;
        $tx->environment = $environment;
        $tx->url = $url;
        $tx->http_method = 'POST';

        $tx->signed_xml_path = $signedXmlPath;
        $tx->signed_xml_sha256 = hash('sha256', $payloadXml);
        $tx->signed_xml_size_bytes = strlen($payloadXml);

        $tx->request_xml = $payloadXml;
        $tx->request_sha256 = hash('sha256', $payloadXml);
        $tx->request_size_bytes = strlen($payloadXml);
        $tx->request_content_type = 'multipart/form-data';
        $tx->request_headers = $this->toJsonString([
            'Accept' => 'application/json',
        ]);

        $tx->status = 'sending';
        $tx->attempt = max(1, $attempt);
        $tx->idempotency_key = $idempotencyKey;
        $tx->sent_at = now();
        $tx->save();

        // 8) Obtener token
        $setting = new DgiiCompanySetting();
        $setting->company_id = $companyId;
        $setting->environment = $environment;
        $setting->cf_prefix = $environment;
        $setting->endpoints = [];

        $tok = $this->authClient->requestToken($setting);

        logger()->info('DGII requestToken raw', [
            'company_id' => $companyId,
            'environment' => $environment,
            'tok_type' => gettype($tok),
            'tok_keys' => is_array($tok) ? array_keys($tok) : null,
            'tok_preview' => is_array($tok)
                ? array_map(function ($v) {
                    return is_string($v) ? mb_substr($v, 0, 80) : $v;
                }, $tok)
                : $tok,
        ]);

        $token = trim((string) ($tok['token'] ?? ''));

        logger()->info('DGII token extracted', [
            'company_id' => $companyId,
            'environment' => $environment,
            'token_len' => strlen($token),
            'token_prefix' => mb_substr($token, 0, 30),
        ]);

        if ($token === '') {
            $tx->status = 'failed';
            $tx->error_message = 'DGII token vacío (requestToken no devolvió token).';
            $tx->received_at = now();
            $tx->save();

            throw new RuntimeException($tx->error_message);
        }

        // 9) Logs de depuración fuertes
        logger()->info('DGII exact payload compare', [
            'company_id' => $companyId,
            'environment' => $environment,
            'endpoint_key' => $endpointKey,
            'url' => $url,
            'filename' => $finalFilename,
            'signed_xml_path_input' => $signedXmlPath,
            'signed_xml_path_resolved' => $resolvedSignedXmlAbsolutePath,
            'payload_sha256' => hash('sha256', $payloadXml),
            'payload_size' => strlen($payloadXml),
            'payload_signature_present' => str_contains($payloadXml, '<Signature'),
            'payload_fecha_hora_firma' => $this->extractXmlTag($payloadXml, ['FechaHoraFirma']),
            'payload_tipo_ecf' => $this->extractXmlTag($payloadXml, ['TipoeCF']),
            'payload_encf' => $this->extractXmlTag($payloadXml, ['eNCF']),
            'payload_rnc_emisor' => $this->extractXmlTag($payloadXml, ['RNCEmisor']),
        ]);

        if ($resolvedSignedXmlAbsolutePath !== null) {
            $diskXml = @file_get_contents($resolvedSignedXmlAbsolutePath);

            logger()->info('DGII disk vs payload', [
                'disk_sha256' => is_string($diskXml) ? hash('sha256', $diskXml) : null,
                'disk_size' => is_string($diskXml) ? strlen($diskXml) : null,
                'same_as_payload' => is_string($diskXml)
                    ? hash('sha256', $diskXml) === hash('sha256', $payloadXml)
                    : false,
            ]);
        }

        $t0 = microtime(true);

        try {
            // 10) Request lo más parecido posible al servicio viejo
            $response = Http::withToken($token)
                ->timeout(100)
                ->accept('application/json')
                ->attach(
                    'xml',
                    $payloadXml,
                    $finalFilename,
                    ['Content-Type' => 'text/xml']
                )
                ->post($url);

            $durationMs = (int) round((microtime(true) - $t0) * 1000);

            $body = (string) $response->body();
            $ct   = (string) ($response->header('Content-Type') ?? '');

            $tx->duration_ms = $durationMs;
            $tx->http_status = $response->status();
            $tx->response_content_type = $ct !== '' ? $ct : null;
            $tx->response_headers = $this->toJsonString($response->headers());
            $tx->response_body = $body;
            $tx->response_sha256 = $body !== '' ? hash('sha256', $body) : null;
            $tx->response_size_bytes = $body !== '' ? strlen($body) : 0;
            $tx->received_at = now();

            $parsed = $this->parseDgiiResponse($body, $ct);

            $tx->dgii_codigo   = $parsed['codigo'];
            $tx->dgii_estado   = $parsed['estado'];
            $tx->dgii_track_id = $parsed['track_id'];
            $tx->dgii_mensajes = $this->toJsonString($parsed['mensajes']);

            if ($response->ok()) {
                $tx->status = 'sent';
                $tx->save();

                return [
                    'ok' => true,
                    'status' => (int) $response->status(),
                    'http_status' => (int) $response->status(),
                    'body' => $body,
                    'dgii' => $parsed,
                    'transmission_id' => (int) $tx->id,
                    'transmission_public_id' => (string) $tx->public_id,
                ];
            }

            $tx->status = 'failed';
            $tx->error_message = "DGII envío falló ({$response->status()}).";
            $tx->save();

            Log::warning('DGII send failed', [
                'tx_public_id' => $tx->public_id,
                'company_id' => $companyId,
                'env' => $environment,
                'endpoint_key' => $endpointKey,
                'method' => 'POST',
                'url' => $url,
                'status' => $response->status(),
                'filename' => $finalFilename,
                'resp_snippet' => mb_substr(trim($body), 0, 1500),
            ]);

            throw new RuntimeException(
                "DGII envío falló ({$response->status()}): " . mb_substr(trim($body), 0, 1500)
            );
        } catch (Throwable $e) {
            $tx->status = 'failed';
            $tx->error_message = mb_substr((string) $e->getMessage(), 0, 500);
            $tx->duration_ms = (int) round((microtime(true) - $t0) * 1000);
            $tx->received_at = now();
            $tx->save();

            throw $e;
        }
    }

    private function resolveUploadFilename(?string $filename, ?string $signedXmlPath): string
    {
        $candidate = null;

        if (is_string($filename) && trim($filename) !== '') {
            $candidate = $filename;
        } elseif (is_string($signedXmlPath) && trim($signedXmlPath) !== '') {
            $candidate = basename($signedXmlPath);
        }

        if (!is_string($candidate) || trim($candidate) === '') {
            throw new RuntimeException(
                'No se pudo resolver el nombre original del XML para enviar a DGII.'
            );
        }

        $candidate = basename(trim($candidate));
        $candidate = str_replace(["\r", "\n"], '', $candidate);

        return $candidate;
    }

    private function resolveReadableSignedXmlAbsolutePath(?string $signedXmlPath): ?string
    {
        if (!is_string($signedXmlPath) || trim($signedXmlPath) === '') {
            return null;
        }

        $signedXmlPath = trim($signedXmlPath);

        if (is_file($signedXmlPath)) {
            return $signedXmlPath;
        }

        $storageAppPath = storage_path('app/' . ltrim($signedXmlPath, '/'));
        if (is_file($storageAppPath)) {
            return $storageAppPath;
        }

        try {
            $localPath = Storage::disk('local')->path($signedXmlPath);
            if (is_file($localPath)) {
                return $localPath;
            }
        } catch (Throwable) {
            // no-op
        }

        return null;
    }

    private function validateXmlBeforeSend(string $xml, string $filename): void
    {
        $errors = [];

        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($xml, LIBXML_NONET);

        if (!$loaded) {
            $msgs = array_map(
                fn($e) => trim($e->message) . " (line {$e->line})",
                libxml_get_errors()
            );
            libxml_clear_errors();

            throw new RuntimeException(
                'XML no parseable localmente: ' . implode(' | ', $msgs)
            );
        }

        $xp = new \DOMXPath($dom);

        $rootName = trim((string) $xp->evaluate('local-name(/*[1])'));
        $tipoeCF = trim((string) $xp->evaluate('string(//*[local-name()="TipoeCF"][1])'));
        $fechaHoraFirma = trim((string) $xp->evaluate('string(//*[local-name()="FechaHoraFirma"][1])'));
        $hasSignature = (int) $xp->evaluate('count(//*[local-name()="Signature"])') > 0;

        // FechaHoraFirma SOLO para ECF
        if (strcasecmp($rootName, 'ECF') === 0) {
            if ($fechaHoraFirma === '') {
                $errors[] = 'Falta FechaHoraFirma.';
            } elseif (!preg_match('/^\d{2}-\d{2}-\d{4} \d{2}:\d{2}:\d{2}$/', $fechaHoraFirma)) {
                $errors[] = 'FechaHoraFirma no cumple formato dd-MM-AAAA HH:mm:ss.';
            }
        }

        if (!$hasSignature) {
            $errors[] = 'Falta Signature.';
        }

        $encf = trim((string) $xp->evaluate('string(//*[local-name()="eNCF"][1])'));
        $rnc  = trim((string) $xp->evaluate('string(//*[local-name()="RNCEmisor"][1])'));

        if ($encf !== '' && $rnc !== '') {
            $expected = "{$rnc}{$encf}.xml";
            if ($filename !== $expected) {
                $errors[] = "Nombre de archivo no coincide. Esperado: {$expected}, recibido: {$filename}.";
            }
        }

        if (in_array($tipoeCF, ['33', '34'], true)) {
            $hasInfoRef = (int) $xp->evaluate('count(//*[local-name()="InformacionReferencia"])') > 0;
            if (!$hasInfoRef) {
                $errors[] = "TipoeCF {$tipoeCF} requiere InformacionReferencia.";
            }
        }

        if ($errors) {
            throw new RuntimeException('Validación local DGII falló: ' . implode(' | ', $errors));
        }
    }

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

        if (str_contains($ct, 'json') || ($trim !== '' && ($trim[0] === '{' || $trim[0] === '['))) {
            $json = json_decode($body, true);

            if (is_array($json)) {
                $out['codigo'] = $this->pick($json, ['codigo', 'Codigo', 'code', 'Code', 'error']);
                $out['estado'] = $this->pick($json, ['estado', 'Estado', 'status', 'Status']);
                $out['track_id'] = $this->pick($json, ['trackId', 'track_id', 'TrackId', 'TrackID', 'trackID']);

                $msgs = $this->pickAny($json, ['mensajes', 'Mensajes', 'messages', 'Messages', 'mensaje', 'Mensaje', 'error']);
                if (is_string($msgs)) {
                    $out['mensajes'] = [['message' => $msgs]];
                } elseif (is_array($msgs)) {
                    $out['mensajes'] = $msgs;
                }

                return $out;
            }
        }

        if ($trim !== '' && str_contains($trim, '<')) {
            $out['track_id'] = $this->extractXmlTag($body, ['trackId', 'TrackId', 'TRACKID', 'trackID', 'TrackID']);
            $out['codigo']   = $this->extractXmlTag($body, ['codigo', 'Codigo', 'code', 'Code', 'error', 'Error']);
            $out['estado']   = $this->extractXmlTag($body, ['estado', 'Estado', 'status', 'Status']);

            if (preg_match_all('/<\s*(mensaje|Mensaje|error|Error)\s*>\s*([^<]+)\s*<\s*\/\s*\1\s*>/i', $body, $mm)) {
                $msgs = [];
                foreach ($mm[2] as $m) {
                    $msgs[] = ['message' => trim($m)];
                }
                $out['mensajes'] = $msgs;
            }

            return $out;
        }

        if (trim($body) !== '') {
            $out['mensajes'] = [['message' => mb_substr(trim($body), 0, 1000)]];
        }

        return $out;
    }

    private function pick(array $data, array $keys): ?string
    {
        foreach ($keys as $k) {
            $v = data_get($data, $k);
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }

        return null;
    }

    private function pickAny(array $data, array $keys): mixed
    {
        foreach ($keys as $k) {
            $v = data_get($data, $k);
            if ($v !== null) {
                return $v;
            }
        }

        return null;
    }

    private function extractXmlTag(string $xml, array $tags): ?string
    {
        foreach ($tags as $t) {
            $m = [];
            $pattern = '/<\s*' . preg_quote($t, '/') . '\s*>\s*([^<]+)\s*<\s*\/\s*' . preg_quote($t, '/') . '\s*>/i';

            if (preg_match($pattern, $xml, $m) === 1 && isset($m[1])) {
                return trim((string) $m[1]);
            }
        }

        return null;
    }

    private function toJsonString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? null : $json;
    }
}