<?php

namespace App\Http\Controllers\DgiiWs;

use App\Services\Dgii\DgiiTokenManager;
use DOMDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Services\DgiiWs\DgiiWsActivityLogger;
use App\Http\Controllers\DgiiWs\BaseDgiiWsController;

class AprobacionComercialEcfController extends BaseDgiiWsController
{
    public function __construct(
        private readonly DgiiTokenManager $tokenManager,
    ) {}

    public function __invoke(Request $request)
    {
        $t0  = hrtime(true);
        $cid = DgiiWsActivityLogger::correlationId($request);

        $company = $this->company($request);

        // ✅ Log básico de request
        DgiiWsActivityLogger::info($company, 'ws.aprobacion.incoming', [
            'cid' => $cid,
            'host' => $request->getHost(),
            'path' => $request->getPathInfo(),
            'method' => $request->getMethod(),
            'ip' => $request->ip(),
            'content_type' => $request->header('Content-Type'),
            'user_agent' => Str::limit((string)$request->userAgent(), 120),
        ]);

        /**
         * ✅ Auth con reason/hashes
         * Requiere que tu BaseDgiiWsController tenga wsAuthCheck()
         * (el que te pasé en el mensaje anterior).
         */
        $auth = method_exists($this, 'wsAuthCheck')
            ? $this->wsAuthCheck($request, $company)
            : ['ok' => (bool)$this->requireWsAuth($request, $company), 'reason' => 'unknown'];

        if (empty($auth['ok'])) {
            DgiiWsActivityLogger::warning($company, 'ws.aprobacion.auth_failed', [
                'cid' => $cid,
                'auth_reason' => $auth['reason'] ?? 'unknown',
                'bearer_hash' => $auth['bearer_hash'] ?? null,
                'ws_token_hash' => $auth['ws_token_hash'] ?? null,
                'ws_expires_at' => $auth['expires_at'] ?? null,
            ]);

            return $this->errorXml(401, 'Not authenticated (Bearer token inválido o expirado).')
                ->header('X-Correlation-Id', $cid);
        }

        $setting = $this->setting($company);

        $xmlPayload = $this->readIncomingXml($request);
        $xmlPayload = preg_replace('/^\xEF\xBB\xBF/', '', (string)$xmlPayload); // quita BOM

        if ($xmlPayload === '' || !str_starts_with(ltrim($xmlPayload), '<')) {
            DgiiWsActivityLogger::warning($company, 'ws.aprobacion.invalid_payload', [
                'cid' => $cid,
                'reason' => 'empty_or_not_xml',
                'bytes' => strlen($xmlPayload),
            ]);

            return $this->errorXml(400, 'No XML received')
                ->header('X-Correlation-Id', $cid);
        }

        // ✅ auditoría entrada (archivo)
        $inName = 'ecf_in_' . now()->format('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.xml';
        $inPath = $this->wsLogPath($company, 'aprobacion', $inName);
        $this->putPrivate($inPath, $xmlPayload);

        // parse eCF
        libxml_use_internal_errors(true);
        libxml_clear_errors();

        $ecf = simplexml_load_string($xmlPayload, 'SimpleXMLElement', LIBXML_NONET);
        if ($ecf === false) {
            libxml_clear_errors();

            DgiiWsActivityLogger::warning($company, 'ws.aprobacion.invalid_xml', [
                'cid' => $cid,
                'in_path' => $inPath,
            ]);

            return $this->errorXml(400, 'Invalid eCF XML')
                ->header('X-Correlation-Id', $cid);
        }

        $rncEmisor    = (string)($ecf->Encabezado->Emisor->RNCEmisor ?? '');
        $rncComprador = (string)($ecf->Encabezado->Comprador->RNCComprador ?? '');
        $eNCF         = (string)($ecf->Encabezado->IdDoc->eNCF ?? '');

        $fechaEmision = (string)($ecf->Encabezado->IdDoc->FechaEmision ?? '');
        $fechaEmision = $this->normalizeDdMmYyyy($fechaEmision);

        $montoTotal = (string)($ecf->Totales->MontoTotal ?? '');
        $montoTotal = $this->normalizeAmount($montoTotal);

        $estado = ($rncEmisor !== '' && $rncComprador !== '' && $eNCF !== '' && $fechaEmision !== '' && $montoTotal !== '')
            ? '0'
            : '1';

        // construir ACECF
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;

        $acecf = $doc->createElement('ACECF');
        $doc->appendChild($acecf);

        $detalle = $doc->createElement('DetalleAprobacionComercial');
        $acecf->appendChild($detalle);

        $detalle->appendChild($doc->createElement('Version', '1.0'));
        $detalle->appendChild($doc->createElement('RNCEmisor', $rncEmisor));
        $detalle->appendChild($doc->createElement('eNCF', $eNCF));
        $detalle->appendChild($doc->createElement('FechaEmision', $fechaEmision));
        $detalle->appendChild($doc->createElement('MontoTotal', $montoTotal));
        $detalle->appendChild($doc->createElement('RNCComprador', $rncComprador));
        $detalle->appendChild($doc->createElement('Estado', $estado));

        // ✅ Consistente con ARECF: usa UTC (o cambia a local si DGII lo exige)
        $detalle->appendChild($doc->createElement('FechaHoraAprobacionComercial', now('UTC')->format('d-m-Y H:i:s')));

        // firmar ACECF
        // (tu loadCompanyPemPair devuelve 3 valores, aquí ignoramos el 3ro)
        [$pkey, $cert] = $this->loadCompanyPemPair($company, $setting);
        $this->signDom($doc, $pkey, $cert);

        $acecfXml = $doc->saveXML();

        // auditoría salida firmada
        $outName = 'acecf_out_' . now()->format('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.xml';
        $outPath = $this->wsLogPath($company, 'aprobacion', $outName);
        $this->putPrivate($outPath, $acecfXml);

        // ✅ Log negocio
        DgiiWsActivityLogger::info($company, 'ws.aprobacion.built', [
            'cid' => $cid,
            'estado' => $estado,
            'rnc_emisor' => $rncEmisor ?: null,
            'rnc_comprador' => $rncComprador ?: null,
            'encf' => $eNCF ?: null,
            'in_path' => $inPath,
            'out_path' => $outPath,
        ]);

        /**
         * ✅ IMPORTANTE:
         * Este WS normalmente SOLO responde a DGII con el ACECF.
         * Si quieres reenviar a DGII, hazlo opcional (y idealmente async).
         */
        $forwardToDgii = (bool) config('dgii_ws.forward_acecf', false);

        if ($forwardToDgii) {
            try {
                $dgiiToken = $this->tokenManager->ensureValidToken($setting);

                $prefix = $setting->cf_prefix ?: 'testecf';
                $dgiiUrl = "https://{$prefix}.dgii.gov.do/fe/aprobacioncomercial/api/ecf";

                $resp = Http::withHeaders([
                    'Accept' => 'application/xml',
                    'Expect' => '',
                    'Authorization' => "Bearer {$dgiiToken}",
                    'X-Correlation-Id' => $cid,
                ])
                    ->timeout(60)
                    ->attach('xml', $acecfXml, 'acecf.xml')
                    ->post($dgiiUrl);

                $respName = 'dgii_resp_' . now()->format('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.xml';
                $respPath = $this->wsLogPath($company, 'aprobacion', $respName);
                $this->putPrivate($respPath, (string) $resp->body());

                DgiiWsActivityLogger::info($company, 'ws.aprobacion.forwarded', [
                    'cid' => $cid,
                    'dgii_url' => $dgiiUrl,
                    'dgii_status' => $resp->status(),
                    'dgii_resp_path' => $respPath,
                ]);
            } catch (\Throwable $e) {
                DgiiWsActivityLogger::error($company, 'ws.aprobacion.forward_failed', [
                    'cid' => $cid,
                    'error' => Str::limit($e->getMessage(), 250),
                ]);
                // NO rompas el response principal a DGII por fallas del forward
            }
        }

        $ms = (hrtime(true) - $t0) / 1e6;
        DgiiWsActivityLogger::info($company, 'ws.aprobacion.completed', [
            'cid' => $cid,
            'duration_ms' => round($ms, 2),
        ]);

        // ✅ Responder siempre el ACECF a quien llamó este WS
        return $this->respondXml(200, $acecfXml)
            ->header('X-Correlation-Id', $cid);
    }
}
