<?php

use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\BusinessIdentityApplication;

$app = require __DIR__.'/bootstrap.php';
set_exception_handler(function (Throwable $e): never {
    fwrite(STDERR, $e->getMessage().PHP_EOL);
    exit(1);
});
if (Schema::hasTable('users')) {
    throw new RuntimeException('Seed requires an empty task-owned database.');
}
$project = getenv('LAUDA_BUSINESS_PROJECT');
if ($project === 'central') {
    BusinessIdentityApplication::centralSchema();
} elseif ($project === 'bys') {
    Artisan::call('migrate', ['--force' => true]);
} else {
    $migrations = ['0001_01_01_000000_create_users_table', '2025_08_14_170933_add_two_factor_columns_to_users_table'];
    if ($project === 'pos') {
        $migrations = ['0001_01_01_000000_create_users_table', '2026_05_23_220900_create_pos_core_tables'];
    }
    if ($project === 'cumplimiento') {
        $migrations[] = '2026_04_13_235907_create_error_logs_table';
        $migrations[] = '2026_10_29_030000_create_fiscal_company_access';
    }
    $migrations[] = '2026_11_06_000002_create_business_identity_registry';
    $migrations[] = '2026_11_06_000003_add_business_identity_handoffs';
    foreach ($migrations as $migration) {
        (require base_path('database/migrations/'.$migration.'.php'))->up();
    }
}
$user = ['id' => 1, 'name' => 'Ana Rivera', 'email' => 'ana@identity.invalid', 'email_verified_at' => now(),
    'password' => Hash::make('business-synthetic-only')];
if ($project === 'central') {
    $user['role'] = 'subscriber';
} elseif ($project === 'pos') {
    $user += ['role' => 'owner', 'current_company_id' => 1, 'current_branch_id' => 1];
} else {
    $user += ['slug' => 'ana-business', 'razon_social' => 'Comercial Rivera'];
}
$user = array_intersect_key($user, array_flip(Schema::getColumnListing('users')));
DB::table('users')->insert($user);
if ($project === 'central') {
    DB::table('subscribers')->insert(['id' => 1, 'name' => 'Comercial Rivera', 'slug' => 'rivera']);
    DB::table('subscriber_user')->insert(['subscriber_id' => 1, 'user_id' => 1, 'role' => 'owner', 'active' => true]);
    DB::table('companies')->insert(['id' => 1, 'subscriber_id' => 1, 'name' => 'Comercial Rivera', 'slug' => 'rivera']);
    DB::table('subscriptions')->insert(['id' => 1, 'subscriber_id' => 1, 'status' => 'active']);
    foreach (['ecf', 'pos', 'bys', 'cumplimiento'] as $key) {
        $id = DB::table('services')->insertGetId(['title' => strtoupper($key), 'slug' => $key, 'service_key' => $key]);
        DB::table('subscription_items')->insert(['subscription_id' => 1, 'service_id' => $id, 'status' => 'active']);
    }
} elseif ($project === 'pos') {
    DB::table('companies')->insert(['id' => 1, 'owner_id' => 1, 'name' => 'Comercial Rivera · POS']);
    DB::table('branches')->insert(['id' => 1, 'company_id' => 1, 'name' => 'Sucursal principal']);
} elseif ($project === 'bys') {
    DB::table('bys_companies')->insert(['id' => 1, 'owner_user_id' => 1, 'name' => 'Comercial Rivera · BYS', 'connection_reference' => (string) Str::uuid()]);
    DB::table('bys_companies')->insert(['id' => 2, 'owner_user_id' => 1, 'name' => 'Otra empresa de Ana', 'connection_reference' => (string) Str::uuid()]);
}
// Existing authenticated session fixture; no new production login mechanism is registered.
$request = Request::create(config('app.url').'/business/identity');
$app->instance('request', $request);
$session = $app['session.store'];
$session->start();
$request->setLaravelSession($session);
Auth::loginUsingId(1);
$session->save();
$cookie = $app['encrypter']->encrypt(CookieValuePrefix::create($session->getName(), $app['encrypter']->getKey()).$session->getId(), false);
file_put_contents(getenv('LAUDA_BUSINESS_RUNTIME').'/'.$project.'/auth.json', json_encode(['cookies' => [[
    'name' => $session->getName(), 'value' => $cookie, 'domain' => '127.0.0.1', 'path' => '/', 'httpOnly' => true, 'secure' => true, 'sameSite' => 'Lax',
]]], JSON_THROW_ON_ERROR));
chmod(getenv('LAUDA_BUSINESS_RUNTIME').'/'.$project.'/auth.json', 0600);
echo $project." synthetic schema and session ready\n";
