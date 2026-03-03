<?php

namespace App\Http\Controllers\DgiiWs;

use DOMDocument;
use Illuminate\Http\Request;
use App\Services\DgiiWs\DgiiWsActivityLogger;
use App\Http\Controllers\DgiiWs\BaseDgiiWsController;

class RecepcionEcfController extends BaseDgiiWsController
{
    public function __invoke(Request $request)
    {
        $t0  = hrtime(true);
        $cid = DgiiWsActivityLogger::correlationId($request);

        try {
            $company = $this->company($request);

            // ✅ Auth (Bearer token emitido por ValidacionCertificado)
            if (!$this->requireWsAuth($request, $company)) {
                $durationMs = (int) ((hrtime(true) - $t0) / 1_000_000);

                DgiiWsActivityLogger::logInbound($request, [
                    'channel' => 'ws.recepcion.ecf',
                    'event' => 'auth_failed',
                    'status_code' => 401,
                    'duration_ms' => $durationMs,
                    'correlation_id' => $cid,
                    'meta' => [
                        'tenant' => $request->route('tenant'),
                        'company_id' => $company->id,
                        'ws_subdomain' => $company->ws_subdomain ?? null,
                        'host' => $request->getHost(),
                    ],
                ]);

                return $this->errorXml(401, 'Not authenticated (Bearer token inválido o expirado).')
                    ->header('X-Correlation-Id', $cid);
            }

            $setting = $this->setting($company);

            $xmlPayload = $this->readIncomingXml($request);
            if ($xmlPayload === '' || !str_starts_with(ltrim($xmlPayload), '<')) {
                $durationMs = (int) ((hrtime(true) - $t0) / 1_000_000);

                DgiiWsActivityLogger::logInbound($request, [
                    'channel' => 'ws.recepcion.ecf',
                    'event' => 'invalid_payload',
                    'status_code' => 400,
                    'duration_ms' => $durationMs,
                    'correlation_id' => $cid,
                    'meta' => [
                        'tenant' => $request->route('tenant'),
                        'company_id' => $company->id,
                        'ws_subdomain' => $company->ws_subdomain ?? null,
                        'host' => $request->getHost(),
                        'reason' => 'No XML received',
                    ],
                ]);

                return $this->errorXml(400, 'No XML received')
                    ->header('X-Correlation-Id', $cid);
            }

            // ✅ auditoría entrada (archivo)
            $inName = 'ecf_in_' . now()->format('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.xml';
            $inPath = $this->wsLogPath($company, 'recepcion', $inName);
            $this->putPrivate($inPath, $xmlPayload);

            // ✅ parse eCF
            libxml_use_internal_errors(true);
            libxml_clear_errors();

            $ecf = simplexml_load_string($xmlPayload, 'SimpleXMLElement', LIBXML_NONET);
            if ($ecf === false) {
                libxml_clear_errors();

                $durationMs = (int) ((hrtime(true) - $t0) / 1_000_000);
                DgiiWsActivityLogger::logInbound($request, [
                    'channel' => 'ws.recepcion.ecf',
                    'event' => 'xml_parse_failed',
                    'status_code' => 400,
                    'duration_ms' => $durationMs,
                    'correlation_id' => $cid,
                    'meta' => [
                        'tenant' => $request->route('tenant'),
                        'company_id' => $company->id,
                        'ws_subdomain' => $company->ws_subdomain ?? null,
                        'host' => $request->getHost(),
                        'saved_in_path' => $inPath,
                    ],
                ]);

                return $this->errorXml(400, 'Invalid eCF XML')
                    ->header('X-Correlation-Id', $cid);
            }

            $rncEmisor    = (string)($ecf->Encabezado->Emisor->RNCEmisor ?? '');
            $rncComprador = (string)($ecf->Encabezado->Comprador->RNCComprador ?? '');
            $eNCF         = (string)($ecf->Encabezado->IdDoc->eNCF ?? '');

            // 0 OK, 1 error (muy básico). Si quieres más granularidad, lo afinamos.
            $estado = ($rncEmisor !== '' && $rncComprador !== '' && $eNCF !== '') ? '0' : '1';

            // ✅ construir ARECF
            $doc = new DOMDocument('1.0', 'UTF-8');
            $doc->preserveWhiteSpace = false;
            $doc->formatOutput = false;

            $arecf = $doc->createElement('ARECF');
            $doc->appendChild($arecf);

            $detalle = $doc->createElement('DetalleAcusedeRecibo');
            $arecf->appendChild($detalle);

            $detalle->appendChild($doc->createElement('Version', '1.0'));
            $detalle->appendChild($doc->createElement('RNCEmisor', $rncEmisor));
            $detalle->appendChild($doc->createElement('RNCComprador', $rncComprador));
            $detalle->appendChild($doc->createElement('eNCF', $eNCF));
            $detalle->appendChild($doc->createElement('Estado', $estado));
            $detalle->appendChild($doc->createElement('FechaHoraAcuseRecibo', gmdate('d-m-Y H:i:s')));

            // ✅ firmar con cert de la compañía
            [$pkey, $cert] = $this->loadCompanyPemPair($company, $setting);
            $this->signDom($doc, $pkey, $cert);

            $outXml = $doc->saveXML();

            // ✅ auditoría salida (archivo)
            $outName = 'arecf_out_' . now()->format('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.xml';
            $outPath = $this->wsLogPath($company, 'recepcion', $outName);
            $this->putPrivate($outPath, $outXml);

            $durationMs = (int) ((hrtime(true) - $t0) / 1_000_000);

            DgiiWsActivityLogger::logInbound($request, [
                'channel' => 'ws.recepcion.ecf',
                'event' => 'arecf_issued',
                'status_code' => 200,
                'duration_ms' => $durationMs,
                'correlation_id' => $cid,
                'meta' => [
                    'tenant' => $request->route('tenant'),
                    'company_id' => $company->id,
                    'ws_subdomain' => $company->ws_subdomain ?? null,
                    'host' => $request->getHost(),
                    'rnc_emisor' => $rncEmisor,
                    'rnc_comprador' => $rncComprador,
                    'encf' => $eNCF,
                    'estado' => $estado,
                    'saved_in_path' => $inPath,
                    'saved_out_path' => $outPath,
                ],
            ]);

            return $this->respondXml(200, $outXml)
                ->header('X-Correlation-Id', $cid);
        } catch (\Throwable $e) {
            $durationMs = (int) ((hrtime(true) - $t0) / 1_000_000);

            DgiiWsActivityLogger::logInbound($request, [
                'channel' => 'ws.recepcion.ecf',
                'event' => 'error',
                'status_code' => 500,
                'duration_ms' => $durationMs,
                'correlation_id' => $cid,
                'meta' => [
                    'tenant' => $request->route('tenant'),
                    'host' => $request->getHost(),
                    'error' => mb_substr($e->getMessage(), 0, 500),
                    'exception' => get_class($e),
                ],
            ]);

            throw $e;
        }
    }
}
