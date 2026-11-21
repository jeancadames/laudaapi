<?php

use App\Enums\Bys\BysPermission;
use App\Models\Bys\BysCompany;
use App\Models\Bys\BysFiscalContractSnapshot;
use App\Models\Bys\BysPurchaseDocument;
use App\Models\Bys\BysSupplier;
use App\Models\Plan;
use App\Models\User;
use App\Services\BusinessCommercial\BysPurchaseContext;
use App\Services\BusinessCommercial\CommercialToken;
use App\Services\Bys\BysAccessService;
use App\Services\Bys\BysConnectionService;
use App\Services\Bys\BysFiscalContractService;
use App\Services\Bys\BysPurchaseUsageService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Support\BusinessIdentityApplication;
use Tests\Support\BysFiscalContractFixture;

require_once dirname(__DIR__, 2).'/Support/BusinessIdentityApplication.php';
$runtime = $argv[1] ?? '';
if (! preg_match('#^/tmp/lauda-business-[a-f0-9]{16}$#D', $runtime) || ! is_file($runtime.'/synthetic-only')) {
    throw new RuntimeException('Synthetic runtime required.');
}
$fixture = json_decode(file_get_contents($runtime.'/commercial-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
$identity = ['enabled' => true, 'service' => 'bys', 'issuer' => $fixture['identity']['issuer'], 'secret' => $fixture['identity']['peers']['bys']['secret']];
$app = BusinessIdentityApplication::create(dirname(__DIR__, 4).'/byslaudaapi', $runtime.'/bys', $identity, $identity['issuer']);
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
config()->set(['bys_connections.ecf.url' => $identity['issuer'],
    'business_commercial_purchases.fiscal_handoff_enabled' => $input['fiscal_enabled'] ?? true,
    'business_commercial_references.enabled' => $input['references_enabled'] ?? false, 'business_commercial_bridge.enabled' => true, 'business_commercial_outbox.enabled' => true,
    'business_commercial_purchases.assistant_enabled' => $input['assistant_enabled'] ?? false,
    'business_commercial_purchases.shipments_enabled' => $input['shipments_enabled'] ?? false,
    'business_commercial_purchases.imports_enabled' => $input['imports_enabled'] ?? false,
    'business_commercial_purchases.inbox_enabled' => $input['inbox_enabled'] ?? false,
    'business_commercial_purchases.manual_enabled' => $input['enabled'] ?? true]);
Http::allowStrayRequests([$identity['issuer'].'/*']);
Http::globalOptions(['verify' => $runtime.'/tls.pem', 'proxy' => '']);
if ($input['action'] === 'setup') {
    foreach (glob(base_path('database/migrations/*.php')) as $migration) {
        (require $migration)->up();
    }
    $user = User::factory()->withBysAccess()->create(['id' => 501, 'name' => 'Comprador sintético', 'email' => 'bys@example.test']);
    $company = BysCompany::where('owner_user_id', 501)->sole();
    if (isset($input['company_rnc'])) {
        $company->update(['rnc' => $input['company_rnc']]);
    }
    BysSupplier::forceCreate(['id' => 1, 'user_id' => 501, 'company_id' => $company->id, 'name' => 'Proveedor sintético',
        'rnc' => '101010101', 'code' => 'SYNTHETIC', 'default_currency' => 'DOP', 'status' => 'active']);
    $plan = Plan::where('slug', 'business')->firstOrFail();
    $plan->update(['features' => array_replace($plan->features, ['purchase_limit_monthly' => $input['local_limit'] ?? 0])]);
    DB::table('business_identity_links')->insert($fixture['links']['bys']);
    echo json_encode(['status' => 200, 'company' => $company->id]);
    exit;
}
if ($input['action'] === 'fiscal_setup') {
    $owner = User::findOrFail(501);
    $owner->update(['rnc' => '101010101']);
    $company = BysCompany::where('owner_user_id', 501)->sole();
    $company->update(['rnc' => '101010101']);
    if (($input['type'] ?? 'e43') === 'e47') {
        BysSupplier::findOrFail(1)->update(['origin' => 'international', 'tax_id' => 'SYNTHETIC-US', 'rnc' => null, 'country_code' => 'US']);
    }
    $connection = app(BysConnectionService::class)->save($company, $owner, [
        'system' => 'ecf', 'environment' => 'testecf', 'expected_generation' => 0,
        'key' => 'synthetic-business-key', 'secret' => 'synthetic-business-secret']);
    echo json_encode(['status' => 200, 'connection' => $connection->id, 'capabilities' => $connection->capabilities]);
    exit;
}
if ($input['action'] === 'references_reconcile') {
    $args = ['--company' => 1, '--dry-run' => $input['dry_run'] ?? false];
    if (isset($input['discover'])) {
        $args['--discover'] = $input['discover'];
    }
    $exit = Artisan::call('business:references-reconcile', $args);
    echo json_encode(['status' => 200, 'exit' => $exit, 'output' => Artisan::output()]);
    exit;
}
if ($input['action'] === 'prepare_snapshot') {
    require_once base_path('tests/Support/BysFiscalContractFixture.php');
    $document = BysPurchaseDocument::findOrFail($input['document']);
    $owner = User::findOrFail(501);
    if (! BysFiscalContractSnapshot::exists()) {
        BysFiscalContractFixture::review($document, $owner);
    }
    $snapshot = app(BysFiscalContractService::class)->prepare($document->fresh(), 501, $input['environment'] ?? 'ecf');
    echo json_encode(['status' => 200, 'snapshot' => $snapshot->id]);
    exit;
}
require __DIR__.'/bys-purchase-sources.php';
if (str_contains($input['path'] ?? '', '/emission/')) {
    config()->set('bys_connections.ecf.url', $identity['issuer']);
}
$action = null;
$mode = $input['mode'] ?? '';
Http::globalRequestMiddleware(function ($request) use (&$action, $identity) {
    $action = null;
    if (! str_contains($request->getUri()->getPath(), '/api/business/commercial/')) {
        return $request;
    }
    $action = (new CommercialToken($identity['secret']))->decode(substr($request->getHeaderLine('Authorization'), 7), 'bys', $identity['issuer'], 'request', now()->timestamp)['action'];

    return $request;
});
Http::globalResponseMiddleware(function ($response) use (&$action, $mode, $runtime, $input) {
    if ($action === 'fiscal_publish' && $mode === 'lost_publish') {
        throw new ConnectionException('Synthetic lost publish.');
    }
    if ($action === 'fiscal_publish' && $mode === 'revoke_after_publish') {
        DB::table('users')->where('id', 501)->update(['is_active' => false]);
    }
    if ($action === 'snapshot' && $mode === 'revoke_user_after_snapshot') {
        DB::table('users')->where('id', 501)->update(['is_active' => false]);
    }
    if ($action === 'reserve') {
        bysSourceFault($mode, $input, $runtime);
        if ($mode === 'pause_after_reserve') {
            touch($runtime.'/bys-paused');
            $deadline = microtime(true) + 45;
            while (! is_file($runtime.'/bys-resume')) {
                if (microtime(true) > $deadline) {
                    throw new RuntimeException('Synthetic worker pause timed out.');
                }
                usleep(10000);
            }
        }
        if ($mode === 'revoke_user') {
            DB::table('users')->where('id', 501)->update(['is_active' => false]);
        } elseif ($mode === 'revoke_link') {
            DB::table('business_identity_links')->update(['active' => false]);
        } elseif ($mode === 'change_supplier') {
            DB::table('bys_suppliers')->where('id', 1)->update(['rnc' => '131880681']);
        }
    }
    if ($mode === 'lost_'.$action) {
        throw new ConnectionException('Synthetic lost commercial reply.');
    }

    return $response;
});
if ($input['action'] === 'reconcile') {
    if ($input['due'] ?? false) {
        DB::table('business_commercial_operations')->update(['next_attempt_at' => null]);
    }
    $options = array_replace(['--company' => 1, '--limit' => $input['limit'] ?? 100, '--dry-run' => $input['dry_run'] ?? false], $input['options'] ?? []);
    $exit = Artisan::call('bys:business-purchases-reconcile', $options);
    echo json_encode(['status' => 200, 'exit' => $exit, 'output' => Artisan::output()]);
    exit;
}
if ($input['action'] === 'unguarded') {
    try {
        app(BysAccessService::class)->forOwner(501, 501, BysPermission::CreatePurchases,
            fn () => DB::transaction(fn () => app(BysPurchaseUsageService::class)->preflight(501, [])));
        echo json_encode(['status' => 200]);
    } catch (HttpExceptionInterface $error) {
        echo json_encode(['status' => $error->getStatusCode()]);
    }
    exit;
}
if ($mode === 'rollback') {
    DB::statement("CREATE TRIGGER synthetic_rollback BEFORE INSERT ON bys_purchase_events WHEN NEW.event_type = 'purchase_document_created' BEGIN SELECT RAISE(ABORT, 'synthetic rollback'); END");
}
$session = app('session')->driver();
$session->start();
if (! ($input['guest'] ?? false)) {
    Auth::login(User::findOrFail($input['actor'] ?? 501));
}
$csrf = $session->token();
$session->save();
$cookie = app('encrypter')->encrypt(CookieValuePrefix::create(config('session.cookie'), app('encrypter')->getKey()).$session->getId(), false);
Auth::forgetGuards();
$data = $input['payload'] ?? ['supplier_id' => 1, 'document_type' => 'local_fiscal_invoice', 'purchase_kind' => 'service', 'status' => 'draft',
    'ncf' => 'E310000000001', 'supplier_invoice_number' => 'SYNTHETIC-1', 'is_credit' => false, 'requires_accounts_payable' => false,
    'currency' => 'DOP', 'exchange_rate' => 1, 'lines' => [['description' => 'Servicio sintético', 'quantity' => 1, 'unit_price_original' => 100, 'tax_rate' => 18]]];
$data['_token'] = ($input['bad_csrf'] ?? false) ? 'invalid' : $csrf;
$headers = ['HTTP_ACCEPT' => 'application/json', 'HTTPS' => 'on', 'HTTP_X_BYS_COMPANY' => (string) ($input['company'] ?? 1)];
if (($input['key'] ?? null) !== null) {
    $headers['HTTP_IDEMPOTENCY_KEY'] = $input['key'];
}
$request = Request::create($identity['issuer'].($input['path'] ?? '/bys/purchase-documents'), $input['method'] ?? 'POST', $data,
    [config('session.cookie') => $cookie], bysSourceFiles($input), $headers);
$kernel = app(Kernel::class);
$response = $kernel->handle($request);
$kernel->terminate($request, $response);
echo json_encode(['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true),
    'context_clear' => ! app()->bound(BysPurchaseContext::class)], JSON_THROW_ON_ERROR);
