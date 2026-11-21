<?php

use App\Jobs\ProcessEcfNotification;
use App\Models\ApiCredential;
use App\Models\BysFiscalSource;
use App\Models\EcfConnection;
use App\Models\EcfFiscalDocument;
use App\Models\EcfNotification;
use App\Models\Evento;
use App\Models\User;
use App\Services\BusinessCommercial\CommercialToken;
use App\Services\Bys\BysFiscalDelivery;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\BusinessIdentityApplication;
use Tests\Support\BysFiscalContractFixtures as B;
use Tests\Support\EcfFiscalFixtures as F;
use Tests\Support\EcfWebhookFixtures as W;

require_once dirname(__DIR__, 2).'/Support/BusinessIdentityApplication.php';
$runtime = $argv[1] ?? '';
if (! preg_match('#^/tmp/lauda-business-[a-f0-9]{16}$#D', $runtime) || ! is_file($runtime.'/synthetic-only')) {
    throw new RuntimeException('Synthetic runtime required.');
}
$fixture = json_decode(file_get_contents($runtime.'/commercial-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
$identity = ['enabled' => true, 'service' => 'cumplimiento', 'issuer' => $fixture['identity']['issuer'], 'secret' => $fixture['identity']['peers']['cumplimiento']['secret']];
$app = BusinessIdentityApplication::create(dirname(__DIR__, 4).'/cumplimientolaudaapi', $runtime.'/cumplimiento', $identity, $identity['issuer']);
foreach (['BysFiscalContractFixtures', 'EcfFiscalFixtures', 'EcfWebhookFixtures'] as $helper) {
    require_once base_path('tests/Support/'.$helper.'.php');
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
config()->set(['business_commercial_bridge.enabled' => true, 'business_commercial_outbox.enabled' => true,
    'business_commercial_references.enabled' => $input['enabled'] ?? true]);
Http::allowStrayRequests([$identity['issuer'].'/*']);
Http::globalOptions(['verify' => $runtime.'/tls.pem', 'proxy' => '']);
Bus::fake([ProcessEcfNotification::class]);
if ($input['action'] === 'setup') {
    foreach (glob(base_path('database/migrations/*.php')) as $migration) {
        (require $migration)->up();
    }
    $owner = User::factory()->create(['id' => 501, 'rnc' => '131234567', 'razon_social' => 'Empresa sintética', 'is_active' => true, 'onboarding_completed' => true,
        'declara_itbis' => true, 'declara_606' => true, 'declara_609' => true, 'declara_retenciones' => true]);
    $connection = W::connection(['environment' => 'ecf'], $owner);
    $body = B::body($owner);
    $generated = ApiCredential::generate($owner, 'Synthetic BYS', ['bys:identity', 'bys:fiscal:1']);
    $generated['credential']->forceFill(['bys_installation' => $body['scope']['installation'],
        'bys_company_reference' => $body['scope']['company_reference'], 'bys_environment' => 'ecf'])->save();
    $headers = ['Authorization' => 'Bearer '.$generated['key'], 'X-Api-Secret' => $generated['secret'],
        'X-Bys-Installation' => $body['scope']['installation'], 'X-Bys-Company-Reference' => $body['scope']['company_reference'],
        'X-Bys-Environment' => 'ecf', 'X-Bys-RNC' => $owner->rnc];
    file_put_contents($runtime.'/compliance-source.json', json_encode(compact('body', 'headers')));
    DB::table('business_identity_links')->insert($fixture['links']['cumplimiento']);
    echo json_encode(['status' => 200, 'fiscal' => array_intersect_key($body['identity'], ['issuer' => true, 'ncf' => true])]);
    exit;
}
if (($input['mode'] ?? '') === 'reference_storage_fault') {
    DB::statement("CREATE TRIGGER synthetic_reference_fault BEFORE UPDATE ON business_document_references BEGIN SELECT RAISE(ABORT, 'synthetic reference storage fault'); END");
}
if ($input['clear_storage_fault'] ?? false) {
    DB::statement('DROP TRIGGER IF EXISTS synthetic_reference_fault');
}
if (in_array($input['mode'] ?? '', ['crash_after_stage', 'crash_after_prepare'], true)) {
    $table = $input['mode'] === 'crash_after_stage' ? 'business_document_references' : 'business_commercial_operations';
    DB::listen(function ($query) use ($table): void {
        if (str_starts_with($query->sql, 'insert into "'.$table.'"')) {
            DB::afterCommit(function (): void {
                echo json_encode(['status' => 200, 'sources' => BysFiscalSource::count(),
                    'references' => DB::table('business_document_references')->get(),
                    'operations' => DB::table('business_commercial_operations')->get(['action', 'state'])]);
                exit;
            });
        }
    });
}
$action = null;
Http::globalRequestMiddleware(function ($request) use (&$action, $identity) {
    $action = (new CommercialToken($identity['secret']))->decode(substr($request->getHeaderLine('Authorization'), 7), 'cumplimiento', $identity['issuer'], 'request', now()->timestamp)['action'];

    return $request;
});
Http::globalResponseMiddleware(function ($response) use (&$action, $input) {
    if (($input['mode'] ?? '') === 'lost_reference' && $action === 'reference') {
        throw new ConnectionException('Synthetic lost reference reply.');
    }

    return $response;
});
if ($input['due'] ?? false) {
    DB::table('business_document_references')->update(['next_attempt_at' => null]);
    DB::table('business_commercial_operations')->update(['next_attempt_at' => null]);
}
if ($input['action'] === 'reconcile') {
    $args = ['--company' => 501, '--dry-run' => $input['dry_run'] ?? false];
    if (isset($input['discover'])) {
        $args['--discover'] = $input['discover'];
    }
    $exit = Artisan::call('business:references-reconcile', $args);
    echo json_encode(['status' => 200, 'exit' => $exit, 'output' => Artisan::output(), 'references' => DB::table('business_document_references')->get()]);
    exit;
}
$source = json_decode(file_get_contents($runtime.'/compliance-source.json'), true, flags: JSON_THROW_ON_ERROR);
if ($input['action'] === 'webhook') {
    $connection = EcfConnection::sole();
    $body = F::body($connection, 'e31', 'received');
    $body['data']['encf'] = $source['body']['identity']['ncf'];
    $body['data']['rnc_emisor'] = $source['body']['identity']['issuer'];
    $body['data']['environment'] = $input['environment'] ?? 'ecf';
    $body['environment'] = $body['data']['environment'];
    $path = '/api/webhooks/ecf/'.$connection->id;
    $headers = W::headers(json_encode($body, JSON_THROW_ON_ERROR), $body);
} else {
    $body = $source['body'];
    $body['version'] = $input['version'] ?? $body['version'];
    $body = B::rehash($body);
    $path = '/api/v1/bys/fiscal/sources';
    $headers = $source['headers'] + ['X-Bys-Operation' => BysFiscalDelivery::reference($body)];
    if ($input['bad_auth'] ?? false) {
        $headers['X-Api-Secret'] = 'invalid';
    }
}
$request = Request::create($identity['issuer'].$path, 'POST', server: ['HTTPS' => 'on', 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: json_encode($body, JSON_THROW_ON_ERROR));
$request->headers->add($headers);
$kernel = app(Kernel::class);
$response = $kernel->handle($request);
$kernel->terminate($request, $response);
if ($input['action'] === 'webhook' && $response->getStatusCode() === 202) {
    $notification = EcfNotification::where('event_id', $body['event_id'])->sole();
    app()->call([new ProcessEcfNotification($notification->id), 'handle']);
}
echo json_encode(['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true),
    'sources' => BysFiscalSource::count(), 'events' => Evento::count(), 'documents' => EcfFiscalDocument::count(),
    'references' => DB::table('business_document_references')->get(), 'operations' => DB::table('business_commercial_operations')->get(['action', 'state'])], JSON_THROW_ON_ERROR);
