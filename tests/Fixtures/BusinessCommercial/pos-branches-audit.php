<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Services\BusinessCommercial\BranchCapacityAudit;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Support\BusinessIdentityApplication;

require_once dirname(__DIR__, 2).'/Support/BusinessIdentityApplication.php';
[$script, $runtime] = $argv;
if (! preg_match('#^/tmp/lauda-business-[a-f0-9]{16}$#D', $runtime) || ! is_file($runtime.'/synthetic-only')) {
    throw new RuntimeException('Synthetic runtime required.');
}
$fixture = json_decode(file_get_contents($runtime.'/commercial-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
$identity = ['enabled' => true, 'service' => 'pos', 'issuer' => $fixture['identity']['issuer'], 'secret' => $fixture['identity']['peers']['pos']['secret']];
$app = BusinessIdentityApplication::create(dirname(__DIR__, 4).'/poslaudaapi', $runtime.'/pos', $identity, 'https://pos.example');
config()->set('business_commercial_bridge.enabled', true);
Http::allowStrayRequests([$identity['issuer'].'/*']);
Http::globalOptions(['verify' => $runtime.'/tls.pem', 'proxy' => '']);
DB::connection()->getPdo()->sqliteCreateFunction('GREATEST', static fn (...$values) => max($values));
if (! Schema::hasTable('pos_business_companies')) {
    DB::statement('PRAGMA synchronous = OFF');
    foreach (glob(base_path('database/migrations/*.php')) as $migration) {
        if (basename($migration) === '2026_08_30_171500_make_payment_notice_case_nullable_for_integrations.php') {
            Schema::table('accounts_receivable_payment_notices', fn ($table) => $table->unsignedBigInteger('accounts_receivable_case_id')->nullable()->change());
        } else {
            (require $migration)->up();
        }
    }
    foreach ([501, 502] as $id) {
        User::forceCreate(['id' => $id, 'name' => 'Responsable sintético '.$id, 'email' => 'audit'.$id.'@example.test',
            'password' => 'synthetic-only', 'email_verified_at' => now(), 'role' => 'owner', 'is_active' => true, 'is_admin' => false]);
        Company::forceCreate(['id' => $id, 'owner_id' => $id, 'name' => 'Empresa sintética '.$id, 'is_active' => true]);
    }
    Branch::forceCreate(['id' => 501, 'company_id' => 501, 'code' => 'MAIN', 'name' => 'Sucursal activa', 'is_active' => true]);
    Branch::forceCreate(['id' => 502, 'company_id' => 501, 'code' => 'OLD', 'name' => 'Sucursal inactiva', 'is_active' => false]);
    Branch::forceCreate(['id' => 503, 'company_id' => 502, 'code' => 'OTHER', 'name' => 'Otra empresa', 'is_active' => true]);
    DB::table('business_identity_links')->insert($fixture['links']['pos']);
    DB::statement('PRAGMA synchronous = FULL');
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$mode = $input['mode'] ?? 'real';
if ($mode === 'disabled') {
    config()->set('business_commercial_bridge.enabled', false);
} elseif ($mode === 'wrong_service') {
    config()->set('business_identity.service', 'ecf');
} elseif ($mode === 'unavailable') {
    Http::fake(['*' => Http::failedConnection()]);
} elseif ($mode === 'corrupted') {
    Http::fake(['*' => Http::response(['token' => 'corrupted'])]);
} elseif ($mode === 'rebind') {
    Http::globalResponseMiddleware(function ($response) {
        DB::table('business_identity_links')->where('service', 'pos')->update(['central_company_id' => 999]);

        return $response;
    });
}
$calls = 0;
Http::globalRequestMiddleware(function ($request) use (&$calls) {
    $calls++;

    return $request;
});
$fingerprint = static function (): string {
    $state = [];
    foreach (['companies', 'branches', 'business_identity_links', 'pos_business_companies', 'business_commercial_operations'] as $table) {
        $state[$table] = DB::table($table)->get()->toArray();
    }

    return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
};
$before = $fingerprint();
try {
    if (($input['action'] ?? 'inspect') === 'command') {
        $code = Artisan::call('business:branches-audit', $input['options'] ?? ['--company' => 501]);
        $result = ['status' => 200, 'code' => $code, 'output' => Artisan::output()];
    } else {
        $inspect = fn () => app(BranchCapacityAudit::class)->inspect((int) ($input['company_id'] ?? 501));
        $report = $mode === 'transaction' ? DB::transaction($inspect) : $inspect();
        $result = ['status' => 200, 'report' => $report];
    }
} catch (HttpExceptionInterface $error) {
    $result = ['status' => $error->getStatusCode(), 'message' => $error->getMessage()];
}
echo json_encode([...$result, 'http_calls' => $calls, 'unchanged' => $before === $fingerprint()], JSON_THROW_ON_ERROR);
