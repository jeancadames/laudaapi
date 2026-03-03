<?php

namespace App\Http\Controllers\DgiiWs;

use Illuminate\Http\Request;
use App\Services\DgiiWs\DgiiWsActivityLogger;
use App\Http\Controllers\DgiiWs\BaseDgiiWsController;

class SemillaController extends BaseDgiiWsController
{
    public function __invoke(Request $request)
    {
        $t0  = hrtime(true);
        $cid = DgiiWsActivityLogger::correlationId($request);

        try {
            $company = $this->company($request);

            // semilla simple (timestamp) + guardar
            $semilla = now()->format('YmdHis');

            $semillaPath = $this->wsAuthPath($company, 'semilla.txt');
            $this->putPrivate($semillaPath, $semilla);

            // XML respuesta
            $xml = '<?xml version="1.0" encoding="UTF-8"?>'
                . '<SemillaResponse>'
                . '<Semilla>' . e($semilla) . '</Semilla>'
                . '</SemillaResponse>';

            // auditoría (archivo)
            $auditName = now()->format('Ymd_His') . '_seed.xml';
            $auditPath = $this->wsLogPath($company, 'auth', $auditName);
            $this->putPrivate($auditPath, $xml);

            $durationMs = (int) ((hrtime(true) - $t0) / 1_000_000);

            // ✅ LOG inbound (DGII -> tu WS)
            DgiiWsActivityLogger::logInbound($request, [
                'channel' => 'ws.semilla',
                'event' => 'seed_requested',
                'status_code' => 200,
                'duration_ms' => $durationMs,
                'correlation_id' => $cid,
                'response_headers' => ['content-type' => ['application/xml']],
                'response_body' => $xml,
                'meta' => [
                    'tenant' => $request->route('tenant'),
                    'company_id' => $company->id,
                    'ws_subdomain' => $company->ws_subdomain ?? null,
                    'host' => $request->getHost(),
                    'saved_semilla_path' => $semillaPath,
                    'audit_xml_path' => $auditPath,
                ],
            ]);

            // Respuesta
            $resp = $this->respondXml(200, $xml);

            // opcional (útil para debug): devuelve correlación
            $resp->headers->set('X-Correlation-Id', $cid);

            return $resp;
        } catch (\Throwable $e) {
            $durationMs = (int) ((hrtime(true) - $t0) / 1_000_000);

            DgiiWsActivityLogger::logInbound($request, [
                'channel' => 'ws.semilla',
                'event' => 'seed_error',
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

            throw $e; // o si prefieres, devuelve XML error controlado
        }
    }
}
