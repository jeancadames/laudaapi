<?php

use App\Models\Branch;
use App\Models\Bys\BysCompany;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\FiscalCompanyAccess;
use App\Models\FiscalCompanyInvitation;
use App\Models\Sale;
use App\Models\User;
use App\Services\BusinessCommercial\CommercialToken;
use App\Services\BusinessCommercial\NativeCapacityInventory;
use App\Services\BusinessCommercial\PosLocalCapacity;
use App\Services\BusinessCommercial\UserCapacity;
use App\Services\Bys\BysCompanyService;
use App\Services\Cashier\CashSessionService;
use App\Services\FiscalCompanyService;
use App\Services\Tenants\TenantUserService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Support\BusinessIdentityApplication;

require_once dirname(__DIR__, 2).'/Support/BusinessIdentityApplication.php';
[$script, $runtime, $service] = $argv;
if (! preg_match('#^/tmp/lauda-business-[a-f0-9]{16}$#D', $runtime) || ! is_file($runtime.'/synthetic-only') || ! in_array($service, ['pos', 'bys', 'ecf', 'cumplimiento'], true)) {
    throw new RuntimeException('Synthetic capacity runtime required.');
}
$fixture = json_decode(file_get_contents($runtime.'/commercial-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
$identity = ['enabled' => true, 'service' => $service, 'issuer' => $fixture['identity']['issuer'], 'secret' => $fixture['identity']['peers'][$service]['secret']];
$app = BusinessIdentityApplication::create(dirname(__DIR__, 4).'/'.$service.'laudaapi', $runtime.'/'.$service, $identity, $identity['issuer']);
config()->set(['business_commercial_bridge.enabled' => true, 'business_commercial_bridge.capacities_enabled' => true]);
Http::allowStrayRequests([$identity['issuer'].'/*']);
Http::globalOptions(['verify' => $runtime.'/tls.pem', 'proxy' => '']);
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$company = (int) $fixture['links'][$service]['local_company_id'];
if (! Schema::hasTable('users')) {
    DB::statement('PRAGMA synchronous = OFF');
    DB::connection()->getPdo()->sqliteCreateFunction('GREATEST', static fn (...$values) => max($values));
    foreach (glob(base_path('database/migrations/*.php')) as $migration) {
        if ($service === 'pos' && basename($migration) === '2026_08_30_171500_make_payment_notice_case_nullable_for_integrations.php') {
            Schema::table('accounts_receivable_payment_notices', fn ($table) => $table->unsignedBigInteger('accounts_receivable_case_id')->nullable()->change());
        } else {
            (require $migration)->up();
        }
    }
    $attributes = ['id' => 501, 'name' => 'Titular sintético', 'email' => 'capacity@example.test', 'password' => 'synthetic-only', 'email_verified_at' => now(), 'is_active' => true, 'is_admin' => false];
    if ($service === 'bys') {
        $owner = User::factory()->withBysAccess()->create($attributes);
    } else {
        $owner = User::withoutEvents(fn () => User::forceCreate([...$attributes, ...($service === 'pos' ? ['role' => 'owner'] : ['rnc' => '101010101'])]));
    }
    if ($service === 'pos') {
        Company::forceCreate(['id' => $company, 'owner_id' => 501, 'name' => 'Empresa sintética', 'is_active' => true]);
        Branch::forceCreate(['id' => 501, 'company_id' => $company, 'code' => 'MAIN', 'name' => 'Principal', 'is_active' => true]);
        $owner->forceFill(['current_company_id' => $company, 'current_branch_id' => 501])->save();
        $owner->branches()->sync([501 => ['is_active' => true, 'is_primary' => true, 'role' => 'owner']]);
    }
    DB::table('business_identity_links')->insert($fixture['links'][$service]);
    DB::statement('PRAGMA synchronous = FULL');
}
if (($input['mode'] ?? '') === 'unavailable') {
    Http::fake(['*' => Http::failedConnection()]);
}
if (($input['mode'] ?? '') === 'disabled') {
    config()->set('business_commercial_bridge.capacities_enabled', false);
}
if (($input['mode'] ?? '') === 'after_commit') {
    $failed = false;
    app('events')->listen(TransactionCommitted::class, function () use (&$failed, $input) {
        if (! $failed && DB::transactionLevel() === 0 && User::whereKey($input['user'])->exists()) {
            $failed = true;
            abort(503, 'Synthetic post-commit failure.');
        }
    });
}
$action = null;
Http::globalRequestMiddleware(function ($request) use (&$action, $identity, $service) {
    $action = (new CommercialToken($identity['secret']))->decode(substr($request->getHeaderLine('Authorization'), 7), $service, $identity['issuer'], 'request', now()->timestamp)['action'];

    return $request;
});
Http::globalResponseMiddleware(function ($response) use (&$action, $input) {
    if (($input['mode'] ?? '') === 'lost_'.$action) {
        throw new ConnectionException('Synthetic lost capacity response.');
    }

    return $response;
});
try {
    $result = match ($input['action'] ?? 'sync') {
        'sync' => app(UserCapacity::class)->synchronize($company),
        'sync_command' => (function () use ($company, $input) {
            $exit = Artisan::call('business:users-sync', ['--company' => $input['company'] ?? (string) $company]);

            return ['exit' => $exit, 'output' => Artisan::output()];
        })(),
        'users' => app(NativeCapacityInventory::class)->users($company),
        'member' => (function () use ($service, $company, $input) {
            $id = (int) ($input['user'] ?? 502);
            $owner = User::findOrFail($input['actor'] ?? 501);
            $active = $input['active'] ?? true;
            if ($service === 'pos') {
                $data = ['name' => 'Usuario sintético '.$id, 'email' => 'capacity-'.$id.'@example.test', 'password' => 'synthetic-only-password',
                    'role' => 'cashier', 'is_active' => $active, 'branch_ids' => [501], 'primary_branch_id' => 501];
                $users = app(TenantUserService::class);
                $user = User::find($id) ? $users->update($owner, $id, $data) : $users->create($owner, $data);

                return ['id' => $user->id, 'active' => (bool) $user->is_active];
            }
            $member = User::find($id) ?? User::withoutEvents(fn () => User::forceCreate(['id' => $id, 'name' => 'Usuario sintético '.$id,
                'email' => 'capacity-'.$id.'@example.test', 'password' => 'synthetic-only', 'email_verified_at' => now(), 'is_active' => true, 'is_admin' => false]));
            if ($service === 'bys') {
                $membership = app(BysCompanyService::class)->saveMember($owner, BysCompany::findOrFail($company), $member, 'viewer', $active);

                return ['id' => $id, 'active' => (bool) $membership->is_active];
            }
            if ($service === 'cumplimiento') {
                $companies = app(FiscalCompanyService::class);
                if ($active) {
                    $companies->invite($owner, $company, ['email' => $member->email, 'role' => 'viewer']);
                    $invitation = FiscalCompanyInvitation::where('company_id', $company)->where('account_id', $id)->where('status', 'pending')->sole();
                    $companies->respond($member, $invitation->id, true);
                } else {
                    $access = FiscalCompanyAccess::where('company_id', $company)->where('account_id', $id)->sole();
                    $companies->revoke($owner, $company, $access->id);
                }

                return ['id' => $id, 'active' => $active];
            }
            abort(422);
        })(),
        'cash_open' => (function () use ($input) {
            $session = app(CashSessionService::class)->open(User::findOrFail($input['user'] ?? 501), 0, usageScope: $input['scope'] ?? 'business');

            return ['id' => $session->id, 'scope' => $session->business_usage_scope, 'status' => $session->status];
        })(),
        'cash_close' => (function () use ($input) {
            $session = app(CashSessionService::class)->close(CashSession::findOrFail($input['session']), User::findOrFail($input['user'] ?? 501), 0);

            return ['id' => $session->id, 'status' => $session->status];
        })(),
        'onboarding' => (function () use ($company, $input, $app) {
            $app->instance('env', 'testing');
            config()->set(['app.env' => 'testing', 'app.debug' => true]);
            auth()->guard()->setUser(User::findOrFail(501));
            $request = Request::create('/onboarding', 'POST', [
                'commercial_name' => null, 'tax_id' => null, 'fiscal_name' => null,
                'company_phone' => null, 'company_email' => null, 'company_address' => null,
                'branch_phone' => null, 'branch_email' => null, 'branch_address' => null,
                'company_name' => 'Empresa sintética', 'branch_name' => 'Sucursal sintética', 'branch_code' => $input['code'] ?? 'EXTRA',
                'business_activity_type' => 'goods', 'business_activity_category' => 'grocery_store', 'operation_mode' => 'counter',
                'catalog_size_range' => 'small', 'product_listing_style' => 'grid', 'service_listing_style' => 'list',
                'default_catalog_view' => 'products', 'menu_style' => 'standard', 'inventory_enabled' => true,
            ], server: ['HTTP_ACCEPT' => 'application/json']);
            $response = $app->make(Kernel::class)->handle($request);
            if ($response->getStatusCode() !== 302) {
                abort($response->getStatusCode(), json_decode($response->getContent(), true)['message'] ?? $response->getContent());
            }

            return ['branches' => Branch::where('company_id', $company)->where('is_active', true)->count()];
        })(),
        'changed_context' => (function () use ($company) {
            $capacity = app(PosLocalCapacity::class);
            $context = $capacity->context($company);
            DB::table('business_identity_links')->where('local_company_id', $company)->update(['active' => false]);
            DB::transaction(fn () => $capacity->verify($company, $context));
        })(),
        'branch' => (function () use ($company, $input) {
            app(PosLocalCapacity::class)->onboard($company, 501, function () use ($company, $input) {
                Branch::updateOrCreate(['company_id' => $company, 'code' => $input['code'] ?? 'EXTRA'], ['name' => 'Sucursal sintética', 'is_active' => true]);
            });

            return ['branches' => Branch::where('company_id', $company)->where('is_active', true)->count()];
        })(),
        'payment_scope' => (function () use ($company, $input) {
            $sale = new Sale(['company_id' => $company, 'branch_id' => 501]);
            app(PosLocalCapacity::class)->assertPaymentScope($sale, (int) $input['session']);

            return ['allowed' => true];
        })(),
        default => throw new RuntimeException('Unsupported synthetic capacity action.'),
    };
    echo json_encode(['status' => 200, 'result' => $result], JSON_THROW_ON_ERROR);
} catch (ValidationException $error) {
    echo json_encode(['status' => 422, 'message' => $error->getMessage()], JSON_THROW_ON_ERROR);
} catch (HttpExceptionInterface $error) {
    echo json_encode(['status' => $error->getStatusCode(), 'message' => $error->getMessage()], JSON_THROW_ON_ERROR);
}
