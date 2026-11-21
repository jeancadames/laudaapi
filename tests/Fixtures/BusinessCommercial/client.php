<?php

use App\Services\BusinessCommercial\CommercialClient;
use App\Services\BusinessCommercial\CommercialToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Support\BusinessIdentityApplication;

require_once dirname(__DIR__, 2).'/Support/BusinessIdentityApplication.php';
[$script, $runtime, $project] = $argv;
$roots = ['ecf' => 'ecflaudaapi', 'pos' => 'poslaudaapi', 'bys' => 'byslaudaapi', 'cumplimiento' => 'cumplimientolaudaapi'];
if (! isset($roots[$project]) || ! preg_match('#^/tmp/lauda-business-[a-f0-9]{16}$#D', $runtime) || ! is_file($runtime.'/synthetic-only')) {
    throw new RuntimeException('Synthetic runtime required.');
}
$fixture = json_decode(file_get_contents($runtime.'/commercial-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
$identity = ['enabled' => true, 'service' => $project, 'issuer' => $fixture['identity']['issuer'], 'secret' => $fixture['identity']['peers'][$project]['secret']];
$app = BusinessIdentityApplication::create(dirname(__DIR__, 4).'/'.$roots[$project], $runtime.'/'.$project, $identity);
config()->set('business_commercial_bridge.enabled', true);
if (! Schema::hasTable('business_identity_links')) {
    (require base_path('database/migrations/2026_11_06_000002_create_business_identity_registry.php'))->up();
    DB::table('business_identity_links')->insert($fixture['links'][$project]);
}
Http::allowStrayRequests([$identity['issuer'].'/*']);
$transportError = null;
Http::globalOptions(['verify' => $runtime.'/tls.pem', 'proxy' => '', 'on_stats' => function ($stats) use (&$transportError): void {
    $transportError = $stats->getHandlerErrorData();
}]);
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$mode = $input['mode'] ?? 'real';
if ($mode === 'corrupted') {
    Http::fake(['*' => Http::response(['token' => 'corrupted'])]);
} elseif ($mode === 'wrong_request') {
    $token = (new CommercialToken($identity['secret']))->encode($identity['issuer'], $project, 'response', ['request_hash' => str_repeat('0', 64), 'status' => 200, 'result' => ['managed' => false]], now()->timestamp);
    Http::fake(['*' => Http::response(['token' => $token])]);
} elseif ($mode === 'unavailable') {
    Http::fake(['*' => Http::failedConnection()]);
} elseif ($mode === 'disabled') {
    config()->set('business_commercial_bridge.enabled', false);
} elseif ($mode === 'revoked') {
    DB::table('business_identity_links')->update(['active' => false]);
}
try {
    if ($input['action'] === 'outbox') {
        require __DIR__.'/outbox.php';
        exit;
    }
    $client = app(CommercialClient::class);
    $args = [(int) ($input['company_id'] ?? 501)];
    if ($input['action'] !== 'snapshot') {
        $args[] = $input['operation_key'];
    }
    if (in_array($input['action'], ['reserve', 'reference'], true)) {
        $args[] = $input['document'];
    }
    if (! in_array($input['action'], ['snapshot', 'reserve', 'reference', 'commit', 'release', 'reconcile', 'cancel'], true)) {
        throw new RuntimeException('Invalid fixture action');
    }
    $result = $client->{$input['action']}(...$args);
    echo json_encode(['status' => 200, 'result' => $result], JSON_THROW_ON_ERROR);
} catch (HttpExceptionInterface $error) {
    echo json_encode(['status' => $error->getStatusCode(), 'message' => $error->getMessage().' transport='.json_encode($transportError)], JSON_THROW_ON_ERROR);
}
