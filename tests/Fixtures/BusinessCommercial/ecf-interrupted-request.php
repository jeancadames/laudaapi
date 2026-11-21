<?php

use App\Services\BusinessCommercial\CommercialToken;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

require __DIR__.'/ecf-bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if ($input['stop_after_release'] ?? false) {
    DB::listen(function ($query): void {
        if (str_starts_with($query->sql, 'update "business_ecf_emissions"') && str_contains($query->sql, '"released_at"')) {
            DB::afterCommit(fn () => exit(75));
        }
    });
}
$action = null;
Http::globalRequestMiddleware(function ($request) use (&$action, $identity) {
    $action = (new CommercialToken($identity['secret']))->decode(substr($request->getHeaderLine('Authorization'), 7),
        'ecf', $identity['issuer'], 'request', now()->timestamp)['action'];

    return $request;
});
Http::globalResponseMiddleware(function ($response) use (&$action, $input, $runtime) {
    if ($action === 'reserve') {
        if ($input['stop_after_release'] ?? false) {
            throw new ConnectionException('Synthetic lost reply before successful cancellation.');
        }
        if (! ($input['pause'] ?? false)) {
            exit(73);
        }
        touch($runtime.'/emission-paused');
        $deadline = microtime(true) + 60;
        while (! is_file($runtime.'/continue-emission')) {
            if (microtime(true) > $deadline) {
                exit(74);
            }
            usleep(10000);
        }
    }

    return $response;
});
$request = Request::create($identity['issuer'].'/v1/ecf', 'POST', $input['payload'], [], [], [
    'HTTPS' => 'on', 'HTTP_ACCEPT' => 'application/json', 'HTTP_IDEMPOTENCY_KEY' => $input['reference'],
    'HTTP_X_API_KEY' => 'synthetic-business-key', 'HTTP_X_API_SECRET' => 'synthetic-business-secret',
]);
$kernel = app(Kernel::class);
$response = $kernel->handle($request);
$kernel->terminate($request, $response);
echo json_encode(['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true)], JSON_THROW_ON_ERROR);
touch($runtime.'/old-emission-finished');
