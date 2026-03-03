<?php

namespace App\Http\Controllers\DgiiWs;

use Illuminate\Http\Request;
use App\Services\DgiiWs\DgiiWsActivityLogger;
use App\Http\Controllers\DgiiWs\BaseDgiiWsController;

class ValidacionCertificadoController extends BaseDgiiWsController
{
    public function __invoke(Request $request)
    {
        $t0  = hrtime(true);
        $cid = DgiiWsActivityLogger::correlationId($request);

        try {
            $company = $this->company($request);

            $semilla = $this->getPrivate($this->wsAuthPath($company, 'semilla.txt'));
            if (!$semilla) {
                $durationMs = (int) ((hrtime(true) - $t0) / 1_000_000);

                DgiiWsActivityLogger::logInbound($request, [
                    'channel' => 'ws.validacioncertificado',
                    'event' => 'seed_missing',
                    'status_code' => 400,
                    'duration_ms' => $durationMs,
                    'correlation_id' => $cid,
                    'meta' => [
                        'tenant' => $request->route('tenant'),
                        'company_id' => $company->id,
                        'ws_subdomain' => $company->ws_subdomain ?? null,
                        'host' => $request->getHost(),
                    ],
                ]);

                return $this->errorXml(400, 'Semilla no encontrada (llama primero /fe/autenticacion/api/semilla).');
            }

            // body entrante (DGII manda XML firmado)
            $rawIn = (string) $request->getContent();

            $inPath = $this->wsLogPath($company, 'auth', now()->format('Ymd_His') . '_validacion_in.xml');
            $this->putPrivate($inPath, $rawIn !== '' ? $rawIn : '<empty/>');

            // emitir token WS (1 hora)
            $issued = $this->issueWsToken($company, 3600);
            $tokenFull = (string) ($issued['token'] ?? '');

            $xmlOut = '<?xml version="1.0" encoding="UTF-8"?>'
                . '<RespuestaAutenticacion>'
                . '<token>' . e($tokenFull) . '</token>'
                . '<expira>' . e($issued['expires_at']->toIso8601String()) . '</expira>'
                . '<expedido>' . e($issued['issued_at']->toIso8601String()) . '</expedido>'
                . '</RespuestaAutenticacion>';

            // ✅ auditoría (archivo OUT) — si quieres full token guardado aquí, déjalo tal cual
            // Si prefieres NO guardar el token completo, cambia a $xmlOutMasked.
            $outPath = $this->wsLogPath($company, 'auth', now()->format('Ymd_His') . '_validacion_out.xml');
            $this->putPrivate($outPath, $xmlOut);

            // Para el activity log, guardamos token masked (seguridad)
            $tokenMasked = $tokenFull !== ''
                ? (substr($tokenFull, 0, 6) . '…' . substr($tokenFull, -6))
                : '';

            $xmlOutMasked = '<?xml version="1.0" encoding="UTF-8"?>'
                . '<RespuestaAutenticacion>'
                . '<token>' . e($tokenMasked) . '</token>'
                . '<expira>' . e($issued['expires_at']->toIso8601String()) . '</expira>'
                . '<expedido>' . e($issued['issued_at']->toIso8601String()) . '</expedido>'
                . '</RespuestaAutenticacion>';

            $durationMs = (int) ((hrtime(true) - $t0) / 1_000_000);

            DgiiWsActivityLogger::logInbound($request, [
                'channel' => 'ws.validacioncertificado',
                'event' => 'token_issued',
                'status_code' => 200,
                'duration_ms' => $durationMs,
                'correlation_id' => $cid,
                'request_headers' => [
                    'content-type' => [$request->header('content-type')],
                ],
                'request_body' => $rawIn,
                'response_headers' => ['content-type' => ['application/xml']],
                'response_body' => $xmlOutMasked,
                'meta' => [
                    'tenant' => $request->route('tenant'),
                    'company_id' => $company->id,
                    'ws_subdomain' => $company->ws_subdomain ?? null,
                    'host' => $request->getHost(),
                    'seed_present' => true,
                    'saved_in_path' => $inPath,
                    'saved_out_path' => $outPath,
                    'expires_at' => $issued['expires_at']->toIso8601String(),
                ],
            ]);

            $resp = $this->respondXml(200, $xmlOut);
            $resp->headers->set('X-Correlation-Id', $cid);
            return $resp;
        } catch (\Throwable $e) {
            $durationMs = (int) ((hrtime(true) - $t0) / 1_000_000);

            DgiiWsActivityLogger::logInbound($request, [
                'channel' => 'ws.validacioncertificado',
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
