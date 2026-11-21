<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageRecord;
use App\Models\User;
use App\Services\BusinessCommercial\CommercialOutbox;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__.'/ecf-bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (($input['action'] ?? '') === 'browser_prepare') {
    $exit = Artisan::call('wayfinder:generate', ['--path' => $runtime.'/ecf/generated', '--with-form' => true]);
    $session = app('session')->driver();
    $session->start();
    Auth::login(User::findOrFail(501));
    $session->save();
    $cookie = app('encrypter')->encrypt(CookieValuePrefix::create(config('session.cookie'), app('encrypter')->getKey()).$session->getId(), false);
    file_put_contents($runtime.'/facil-browser.json', json_encode(['url' => $identity['issuer'], 'draft' => $input['draft'],
        'cookie' => ['name' => config('session.cookie'), 'value' => $cookie, 'url' => $identity['issuer'], 'httpOnly' => true, 'secure' => true, 'sameSite' => 'Lax']]));
    chmod($runtime.'/facil-browser.json', 0600);
    echo json_encode(['exit' => $exit]);
    exit;
}
if (($input['action'] ?? '') === 'prepare_next_generation') {
    $row = DB::table('business_ecf_emissions')->sole();
    $outbox = app(CommercialOutbox::class);
    $operation = $outbox->prepare(501, 'ecf:request:'.$row->id.':'.($row->generation + 1), $row->native_fingerprint);
    $outbox->synchronize(501, $operation);
    echo json_encode(['exit' => 0, 'operation' => $operation]);
    exit;
}
if (in_array($input['action'] ?? '', ['exhausted_plan', 'local_plan'], true)) {
    $count = $input['action'] === 'exhausted_plan' ? 1 : 0;
    $limit = $count ? 1 : 100;
    $plan = Plan::create(['slug' => 'synthetic-only', 'name' => 'Synthetic only', 'ecf_limit' => $limit, 'access_testecf' => true]);
    $subscription = Subscription::forceCreate(['user_id' => 501, 'plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addMonth()]);
    UsageRecord::create(['user_id' => 501, 'subscription_id' => $subscription->id, 'year' => now()->year, 'month' => now()->month, 'ecf_count' => $count, 'ecf_limit' => $limit]);
    echo json_encode(['exit' => 0]);
    exit;
}
if ($input['due'] ?? false) {
    DB::table('business_commercial_operations')->update(['next_attempt_at' => null]);
}
$exit = Artisan::call(match ($input['action'] ?? '') {
    'direct_reconcile' => 'business:direct-emissions-reconcile', 'bys_reconcile' => 'business:bys-emissions-reconcile', default => 'business:pos-emissions-reconcile'
},
    ['--company' => 501, '--dry-run' => $input['dry_run'] ?? false]);
echo json_encode(['exit' => $exit, 'output' => Artisan::output()], JSON_THROW_ON_ERROR);
