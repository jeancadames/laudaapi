<?php

namespace App\Services\DgiiWs;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DgiiWsActivityLogger
{
    public static function correlationId(Request $request): string
    {
        $cid = trim((string) $request->header('X-Correlation-Id'));
        if ($cid === '') $cid = (string) Str::uuid();

        $request->attributes->set('correlation_id', $cid);
        return $cid;
    }

    private static function companyFromRequest(Request $request): ?Company
    {
        $c = $request->attributes->get('company');
        return ($c instanceof Company) ? $c : null;
    }

    private static function baseCtx(?Company $company, array $ctx): array
    {
        return array_merge([
            'company_id'    => $company?->id,
            'company_slug'  => $company?->slug,
            'ws_subdomain'  => $company?->ws_subdomain,
        ], $ctx);
    }

    /**
     * ✅ Helper: log inicial del request entrante.
     * Uso:
     *   $cid = DgiiWsActivityLogger::logInbound($request, ['event' => 'ws.aprobacion.incoming']);
     */
    public static function logInbound(Request $request, array $ctx = [], string $event = 'ws.inbound'): string
    {
        $cid = self::correlationId($request);
        $company = self::companyFromRequest($request);

        $base = [
            'cid'          => $cid,
            'host'         => $request->getHost(),
            'path'         => $request->getPathInfo(),
            'method'       => $request->getMethod(),
            'ip'           => $request->ip(),
            'content_type' => $request->header('Content-Type'),
            'accept'       => $request->header('Accept'),
            'user_agent'   => Str::limit((string) $request->userAgent(), 160),
            'content_len'  => $request->header('Content-Length'),
        ];

        self::info($company, $event, array_merge($base, $ctx));

        return $cid;
    }

    /**
     * ✅ Helper: log de salida (status/duración).
     * Uso:
     *   DgiiWsActivityLogger::logOutbound($request, 200, ['duration_ms' => 12.3]);
     */
    public static function logOutbound(Request $request, int $status, array $ctx = [], string $event = 'ws.outbound'): void
    {
        $cid = (string) ($request->attributes->get('correlation_id') ?? self::correlationId($request));
        $company = self::companyFromRequest($request);

        self::info($company, $event, array_merge([
            'cid' => $cid,
            'status' => $status,
        ], $ctx));
    }

    public static function logException(Request $request, \Throwable $e, array $ctx = [], string $event = 'ws.exception'): void
    {
        $cid = (string) ($request->attributes->get('correlation_id') ?? self::correlationId($request));
        $company = self::companyFromRequest($request);

        self::error($company, $event, array_merge([
            'cid'   => $cid,
            'error' => Str::limit($e->getMessage(), 500),
            'ex'    => get_class($e),
        ], $ctx));
    }

    public static function info(?Company $company, string $event, array $ctx = []): void
    {
        Log::channel('dgii_ws')->info($event, self::baseCtx($company, $ctx));
    }

    public static function warning(?Company $company, string $event, array $ctx = []): void
    {
        Log::channel('dgii_ws')->warning($event, self::baseCtx($company, $ctx));
    }

    public static function error(?Company $company, string $event, array $ctx = []): void
    {
        Log::channel('dgii_ws')->error($event, self::baseCtx($company, $ctx));
    }
}
