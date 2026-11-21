<?php

use App\Services\BusinessCommercial\CommercialToken;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

require __DIR__.'/ecf-bootstrap.php';
$mode = is_file($runtime.'/fiscal-mode') ? trim(file_get_contents($runtime.'/fiscal-mode')) : '';
if ($mode === 'disabled') {
    config()->set('business_commercial_emission.pos_handoff_enabled', false);
}
if ($mode === 'direct_disabled') {
    config()->set(['business_commercial_emission.direct_enabled' => false, 'business_commercial_emission.pos_handoff_enabled' => false]);
}
if ($mode === 'replace_execution_token') {
    DB::listen(function ($query): void {
        if (str_starts_with($query->sql, 'insert into "business_pos_emissions"')) {
            DB::afterCommit(fn () => DB::table('business_pos_emissions')->update(['execution_token' => '11111111-1111-4111-8111-111111111111']));
        }
    });
}
$action = null;
Http::globalRequestMiddleware(function ($request) use (&$action, $identity) {
    $action = null;
    if (str_contains($request->getUri()->getPath(), '/api/business/commercial/')) {
        $action = (new CommercialToken($identity['secret']))->decode(substr($request->getHeaderLine('Authorization'), 7), 'ecf', $identity['issuer'], 'request', now()->timestamp)['action'];
    }

    return $request;
});
Http::globalResponseMiddleware(function ($response) use (&$action, $mode, $runtime) {
    if ($action === 'snapshot' && in_array($mode, ['facil_snapshot_after_receipt', 'facil_pause_before_map'], true)
        && DB::table('ecf_api_requests')->exists()) {
        if ($mode === 'facil_snapshot_after_receipt') {
            throw new ConnectionException('Synthetic lost snapshot after receipt.');
        }
        touch($runtime.'/facil-paused');
        $deadline = microtime(true) + 45;
        while (! is_file($runtime.'/facil-resume')) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Synthetic worker pause timed out.');
            }
            usleep(10000);
        }
    }
    if ($action === 'snapshot' && $mode === 'resume_old_after_claim' && ! is_file($runtime.'/old-emission-finished')) {
        touch($runtime.'/continue-emission');
        $deadline = microtime(true) + 15;
        while (! is_file($runtime.'/old-emission-finished')) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Old worker did not finish after the new receipt claim.');
            }
            usleep(10000);
        }
    }
    if ($action === 'reserve' && $mode === 'facil_revoke_user') {
        DB::table('users')->where('id', 501)->update(['is_active' => false]);
    }
    if ($action === 'reserve' && $mode === 'direct_revoke_credential') {
        DB::table('api_credentials')->update(['active' => false]);
    }
    if ($action === 'reserve' && $mode === 'direct_revoke_link') {
        DB::table('business_identity_links')->where('service', 'ecf')->update(['active' => false]);
    }
    if ($action === 'reserve' && $mode === 'direct_change_environment') {
        DB::table('users')->where('id', 501)->update(['dgii_environment' => 'certecf']);
    }
    if ($action === 'reserve' && $mode === 'external_changed_lost_cancel') {
        DB::table('external_fiscal_originals')->update(['source_hash' => str_repeat('f', 64)]);
    }
    if ($action === 'fiscal_receive' && $mode === 'revoke_after_receive') {
        DB::table('business_identity_links')->where('service', 'ecf')->update(['active' => false]);
    }
    if ($mode === 'lost_'.$action || ($action === 'cancel' && $mode === 'external_changed_lost_cancel')) {
        throw new ConnectionException('Synthetic lost central reply.');
    }

    return $response;
});
Http::fake(function ($request) use ($runtime, $mode) {
    $host = parse_url($request->url(), PHP_URL_HOST);
    if (! is_string($host) || ! str_ends_with($host, '.dgii.gov.do')) {
        return null;
    }
    $path = strtolower((string) parse_url($request->url(), PHP_URL_PATH));
    file_put_contents($runtime.'/fiscal-boundary.ndjson', json_encode(['method' => $request->method(), 'path' => $path])."\n", FILE_APPEND | LOCK_EX);
    if (str_ends_with($path, '/semilla')) {
        return Http::response('<SemillaModel><valor>SYNTHETIC</valor><fecha>'.now()->toIso8601String().'</fecha></SemillaModel>', 200, ['Content-Type' => 'application/xml']);
    }
    if (str_contains($path, 'validarsemilla')) {
        return Http::response(['token' => 'SYNTHETIC-NOT-DGII', 'expira' => now()->addHour()->toIso8601String(), 'expedido' => now()->toIso8601String()]);
    }
    if ($request->method() === 'GET') {
        return Http::response(['estado' => 'Aceptado', 'codigo' => 1, 'codigoSeguridad' => 'TEST00', 'mensaje' => 'SIMULACION LOCAL']);
    }
    if ($mode === 'lost_send') {
        throw new ConnectionException('Synthetic loss after fiscal send.');
    }

    if ($host === 'fc.dgii.gov.do') {
        return Http::response(['estado' => 'Aceptado', 'codigo' => 1, 'encf' => 'E320000000001', 'secuenciaUtilizada' => true, 'mensajes' => [['valor' => 'SIMULACION LOCAL SIN VALIDEZ FISCAL']]]);
    }

    return Http::response(['trackId' => 'SYNTHETIC-NOT-DGII']);
});
if (is_file($runtime.'/facil-browser-enabled') && PHP_SAPI !== 'cli') {
    $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if (str_starts_with($path, '/build/') && ! str_contains($path, '..')) {
        $file = $runtime.'/ecf/public'.$path;
        if (! is_file($file)) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: '.match (pathinfo($file, PATHINFO_EXTENSION)) {
            'js' => 'application/javascript', 'css' => 'text/css', 'woff2' => 'font/woff2', default => 'application/octet-stream',
        });
        readfile($file);
        exit;
    }
    if (in_array($path, ['/favicon.ico', '/favicon.svg', '/favicon-32x32.png', '/favicon-16x16.png', '/apple-touch-icon.png', '/site.webmanifest'], true)) {
        http_response_code(204);
        exit;
    }
}
$_SERVER['HTTPS'] = 'on';
if (! defined('BUSINESS_FISCAL_FIXTURE_CLI')) {
    $app->handleRequest(Request::capture());
}
