<?php

use App\Models\BusinessContract;
use App\Models\BusinessImplementationOrder;
use App\Models\BusinessRenewalAcceptance;
use App\Models\BusinessRenewalOffer;
use App\Models\BusinessRenewalPeriod;
use App\Models\BusinessTransition;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceCommercialQuote;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Services\Billing\BusinessContractActivationService;
use App\Services\Billing\BusinessQuoteAcceptanceService;
use App\Services\LaudaErp\ServiceAccessResolver;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BusinessContractFixtures;
use Tests\Support\BusinessIdentityApplication;
use Tests\Support\BusinessQuoteCheckoutFixtures;
use Tests\Support\BusinessQuoteFixtures;
use Tests\Support\BusinessTransitionFixtures;

require_once dirname(__DIR__, 2).'/Support/BusinessIdentityApplication.php';
$base = getenv('LAUDA_BUSINESS_RUNTIME');
if (! preg_match('#^/tmp/lauda-business-[a-f0-9]{16}$#D', $base ?: '') || ! is_file($base.'/synthetic-only')) {
    throw new RuntimeException('Synthetic Business runtime required.');
}
$action = PHP_SAPI === 'cli' ? ($argv[1] ?? '') : 'serve';
if ($action === 'serve') {
    $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if (str_starts_with($path, '/build/') && ! str_contains($path, '..')) {
        $file = $base.'/central/public'.$path;
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
    if (in_array($path, ['/favicon.svg', '/favicon-32x32.png', '/favicon-16x16.png', '/apple-touch-icon.png', '/site.webmanifest'], true)) {
        http_response_code(204);
        exit;
    }
    $_SERVER['HTTPS'] = 'on';
}
$app = BusinessIdentityApplication::create(dirname(__DIR__, 3), $base.'/central', [], 'https://127.0.0.1:9446');
config()->set('business_commercial.enabled', true);
$checkout = getenv('LAUDA_BUSINESS_CHECKOUT_TEST') === '1';
config()->set('business_commercial.checkout_enabled', $checkout);
$implementation = getenv('LAUDA_BUSINESS_IMPLEMENTATION_TEST') === '1';
config()->set('business_commercial.implementation_enabled', $implementation);
$activation = getenv('LAUDA_BUSINESS_ACTIVATION_TEST') === '1';
$renewal = getenv('LAUDA_BUSINESS_RENEWAL_TEST') === '1';
$transition = getenv('LAUDA_BUSINESS_TRANSITION_TEST') === '1';
config()->set('business_commercial.transition_enabled', $transition);
config()->set('business_commercial.renewal_enabled', $renewal);
if ($activation) {
    BusinessContractFixtures::configure();
    // Keep this synthetic session while moving the clock one contractual month.
    // Production session policy is unchanged; authentication is not under test here.
    config()->set('session.lifetime', 60 * 24 * 400);
    if (is_file($base.'/clock.json')) {
        $at = json_decode(file_get_contents($base.'/clock.json'), true, 16, JSON_THROW_ON_ERROR)['at'];
        CarbonImmutable::setTestNow($at);
        Carbon::setTestNow($at);
    }
}

if ($transition && in_array($action, ['transition-prepare', 'transition-cut', 'verify'], true)) {
    $quote = ServiceCommercialQuote::whereNotNull('snapshot->transition')->latest('id')->firstOrFail();
    if ($action === 'transition-prepare') {
        BusinessContractFixtures::prepare($quote);
        echo "Synthetic Business payment and completed implementation ready\n";
    } elseif ($action === 'transition-cut') {
        file_put_contents($base.'/clock.json', json_encode(['at' => $quote->snapshot['transition']['not_before']], JSON_THROW_ON_ERROR));
        echo "Synthetic clock at paid-period cutoff\n";
    } else {
        $contract = BusinessContract::sole();
        $execution = BusinessTransition::sole();
        $allowed = app(ServiceAccessResolver::class)->userCanAccess(User::findOrFail(1), Company::findOrFail(1), Service::where('service_key', 'pos')->sole());
        if (Subscription::count() !== 2 || Subscription::where('status', 'active')->count() !== 1 || SubscriptionItem::count() !== 5
            || SubscriptionItem::where('status', 'active')->count() !== 4 || Invoice::count() !== 3 || Payment::count() !== 3
            || DB::table('business_transition_items')->count() !== 2 || $execution->subscription_id !== $contract->subscription_id
            || $contract->subscription_id !== $quote->snapshot['transition']['target_subscription_id'] || ! $allowed
            || Invoice::whereNotNull('fiscal_number')->exists() || DB::table('audit_logs')->where('event', 'business.transition.executed')->count() !== 1) {
            throw new RuntimeException('Unexpected consolidation persistence.');
        }
        echo json_encode(['transitions' => 1, 'contracts' => 1, 'subscriptions_preserved' => 2, 'active_subscriptions' => 1,
            'items_preserved_or_created' => 5, 'active_items' => 4, 'invoices_preserved_or_created' => 3, 'payments' => 3,
            'source_item_mappings' => 2, 'business_access_allowed' => $allowed, 'fiscal_number_issued' => false,
            'source_hash' => $execution->source_hash], JSON_THROW_ON_ERROR).PHP_EOL;
    }
    exit;
}

if ($renewal && $action === 'verify') {
    $period = BusinessRenewalPeriod::sole();
    $offer = BusinessRenewalOffer::sole();
    $acceptance = BusinessRenewalAcceptance::sole();
    $invoice = Invoice::findOrFail($period->invoice_id);
    $allowed = app(ServiceAccessResolver::class)->userCanAccess(User::findOrFail(1), Company::findOrFail(1), Service::where('service_key', 'pos')->sole());
    if (Invoice::count() !== 2 || Payment::count() !== 2 || Subscription::count() !== 1 || SubscriptionItem::count() !== 4
        || $offer->terms_hash !== $acceptance->terms_hash || $period->terms_hash !== $offer->terms_hash
        || $invoice->total !== '3540.00' || $invoice->status !== 'paid' || $invoice->items()->count() !== 1
        || $invoice->fiscal_number !== null || $allowed
        || DB::table('audit_logs')->where('event', 'like', 'business.renewal.%')->count() !== 3) {
        throw new RuntimeException('Unexpected renewal persistence or expired-period access.');
    }
    echo json_encode(['offers' => 1, 'acceptances' => 1, 'renewal_periods' => 1, 'invoices' => 2, 'payments' => 2,
        'subscriptions' => 1, 'subscription_items' => 4, 'renewal_audits' => 3, 'total' => $invoice->total,
        'one_time_charges_repeated' => false, 'expired_access_denied' => true, 'fiscal_number_issued' => false], JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}

if ($action === 'seed') {
    if ($activation) {
        BusinessContractFixtures::schema();
    } else {
        $checkout ? BusinessQuoteCheckoutFixtures::schema() : BusinessQuoteFixtures::schema();
    }
    if ($implementation && ! $activation) {
        (require base_path('database/migrations/2026_11_06_000006_create_business_implementation_orders.php'))->up();
    }
    $quote = BusinessQuoteFixtures::seed();
    $expired = BusinessQuoteFixtures::issue();
    DB::table('service_commercial_quotes')->where('id', $expired->id)->update(['valid_until' => now()->subMinute()]);
    $foreign = BusinessQuoteFixtures::issue(companyId: 2);
    if ($renewal) {
        (require base_path('database/migrations/2026_11_06_000008_create_business_renewals.php'))->up();
        BusinessContractFixtures::prepare($quote);
        app(BusinessContractActivationService::class)->activate(User::findOrFail(4), $quote->public_id, [
            'snapshot_hash' => BusinessQuoteAcceptanceService::fingerprint($quote), 'idempotency_key' => (string) Str::uuid(),
            'implementation_version' => 4, 'technical_evidence' => array_fill_keys(array_keys($quote->snapshot['products']), 'Evidencia de activación preparada en el runtime sintético.'),
        ]);
    }
    if ($transition) {
        BusinessTransitionFixtures::schema();
        BusinessTransitionFixtures::sources();
    }
    if ($activation && ! $renewal && ! $transition) {
        BusinessContractFixtures::links($quote);
        DB::table('companies')->where('id', 1)->update(['owner_user_id' => 1]);
    }
    file_put_contents($base.'/quotes.json', json_encode(['quote' => $quote->public_id, 'expired' => $expired->public_id, 'foreign' => $foreign->public_id], JSON_THROW_ON_ERROR));

    $request = Request::create(config('app.url').'/app/business/quotes');
    $app->instance('request', $request);
    $session = $app['session.store'];
    $session->start();
    $request->setLaravelSession($session);
    Auth::loginUsingId(1);
    $session->save();
    $cookie = $app['encrypter']->encrypt(CookieValuePrefix::create($session->getName(), $app['encrypter']->getKey()).$session->getId(), false);
    file_put_contents($base.'/auth.json', json_encode(['cookies' => [[
        'name' => $session->getName(), 'value' => $cookie, 'domain' => '127.0.0.1', 'path' => '/', 'httpOnly' => true, 'secure' => true, 'sameSite' => 'Lax',
    ]]], JSON_THROW_ON_ERROR));
    chmod($base.'/auth.json', 0600);
    if ($implementation) {
        $session->setId(Str::random(40));
        $session->flush();
        Auth::loginUsingId(4);
        $session->save();
        $cookie = $app['encrypter']->encrypt(CookieValuePrefix::create($session->getName(), $app['encrypter']->getKey()).$session->getId(), false);
        file_put_contents($base.'/auth-admin.json', json_encode(['cookies' => [[
            'name' => $session->getName(), 'value' => $cookie, 'domain' => '127.0.0.1', 'path' => '/', 'httpOnly' => true, 'secure' => true, 'sameSite' => 'Lax',
        ]]], JSON_THROW_ON_ERROR));
        chmod($base.'/auth-admin.json', 0600);
    }
    echo "Synthetic quote data ready\n";
} elseif ($action === 'wayfinder') {
    $exit = Artisan::call('wayfinder:generate', ['--path' => $base.'/central/generated', '--with-form' => true]);
    if ($exit !== 0) {
        throw new RuntimeException('Isolated Wayfinder generation failed.');
    }
    echo "Real routes generated in the synthetic runtime\n";
} elseif ($action === 'verify') {
    $ids = json_decode(file_get_contents($base.'/quotes.json'), true, 32, JSON_THROW_ON_ERROR);
    $quote = ServiceCommercialQuote::where('public_id', $ids['quote'])->firstOrFail();
    $receipt = $quote->acceptance;
    if (! $receipt || DB::table('service_quote_acceptances')->count() !== 1
        || DB::table('audit_logs')->where('event', 'business.quote.accepted')->count() !== 1
        || $receipt->snapshot !== $quote->snapshot
        || $receipt->snapshot_hash !== BusinessQuoteAcceptanceService::fingerprint($quote)) {
        throw new RuntimeException('Unexpected acceptance evidence.');
    }
    if ($checkout) {
        $invoice = Invoice::sole();
        if (DB::table('service_quote_checkouts')->count() !== 1
            || DB::table('audit_logs')->where('event', 'business.quote.checkout_created')->count() !== 1
            || $invoice->billing_snapshot['price_snapshot'] !== $receipt->snapshot
            || $invoice->total !== '11210.00' || $invoice->tax_total !== '1710.00'
            || $invoice->amount_paid !== ($implementation ? '11210.00' : '10000.00') || $invoice->status !== ($implementation ? 'paid' : 'issued')
            || DB::table('invoice_items')->count() !== 2 || Payment::count() !== 2) {
            throw new RuntimeException('Unexpected checkout evidence.');
        }
    }
    if ($implementation) {
        $order = BusinessImplementationOrder::sole();
        if ($order->status !== 'completed' || $order->lock_version !== 9 || $order->scope_snapshot !== $receipt->snapshot
            || $order->events()->count() !== 9 || DB::table('audit_logs')->where('event', 'like', 'business.implementation.%')->count() !== 9) {
            throw new RuntimeException('Unexpected implementation evidence.');
        }
    }
    if ($activation) {
        $contract = BusinessContract::sole();
        $subscription = Subscription::sole();
        if ($contract->snapshot !== $receipt->snapshot || $subscription->total_amount !== '4130.00'
            || SubscriptionItem::count() !== 4 || DB::table('business_contract_items')->count() !== 4
            || DB::table('audit_logs')->where('event', 'business.contract.activated')->count() !== 1
            || app(ServiceAccessResolver::class)->userCanAccess(User::findOrFail(1), Company::findOrFail(1), Service::where('service_key', 'pos')->sole())) {
            throw new RuntimeException('Unexpected activation or expired-period access evidence.');
        }
    }
    foreach ([...($checkout ? [] : ['invoices', 'payments']), ...($activation ? [] : ['subscriptions', 'subscription_items', 'business_identity_links']), 'diagnosis_assessments', 'transformation_capability_activations'] as $table) {
        if (DB::table($table)->exists()) {
            throw new RuntimeException('Unexpected write to '.$table);
        }
    }
    echo json_encode(['acceptances' => 1, 'audit_events' => 1, 'exact_snapshot' => true, 'billing_created' => $checkout, 'implementation_completed' => $implementation, 'implementation_events' => $implementation ? 9 : 0, 'identity_and_entitlements_untouched' => ! $activation, 'business_contracts' => $activation ? 1 : 0, 'business_subscription_items' => $activation ? 4 : 0, 'expired_period_access_denied' => $activation], JSON_THROW_ON_ERROR).PHP_EOL;
} elseif ($renewal && in_array($action, ['renewal-pay', 'renewal-reverse', 'renewal-restore', 'renewal-start', 'renewal-end'], true)) {
    $period = BusinessRenewalPeriod::sole();
    if (in_array($action, ['renewal-start', 'renewal-end'], true)) {
        file_put_contents($base.'/clock.json', json_encode(['at' => ($action === 'renewal-start' ? $period->period_starts_at : $period->period_ends_at)->toIso8601String()], JSON_THROW_ON_ERROR));
    } elseif ($action === 'renewal-pay') {
        Payment::create(['invoice_id' => $period->invoice_id, 'company_id' => 1, 'currency' => 'DOP',
            'method' => 'bank_transfer', 'amount' => $period->total, 'paid_at' => now(), 'reference' => 'renewal-browser']);
    } else {
        Payment::where('reference', 'renewal-browser')->sole()->update(['paid_at' => $action === 'renewal-reverse' ? null : now()]);
    }
    echo "Synthetic renewal updated\n";
} elseif ($checkout && in_array($action, ['partial', 'paid', 'reverse', 'restore'], true)) {
    $invoice = Invoice::sole();
    if ($action === 'reverse') {
        Payment::where('reference', 'business-browser-partial')->sole()->update(['paid_at' => null]);
    } elseif ($action === 'restore') {
        Payment::where('reference', 'business-browser-partial')->sole()->update(['paid_at' => now()]);
    } else {
        Payment::create(['invoice_id' => $invoice->id, 'company_id' => $invoice->company_id,
            'currency' => $invoice->currency, 'method' => 'bank_transfer', 'paid_at' => now(),
            'amount' => $action === 'partial' ? '1210.00' : '10000.00', 'reference' => 'business-browser-'.$action]);
    }
    echo "Synthetic payment updated\n";
} elseif ($activation && $action === 'access') {
    echo json_encode(['allowed' => app(ServiceAccessResolver::class)->userCanAccess(
        User::findOrFail(1), Company::findOrFail(1), Service::where('service_key', 'pos')->sole())], JSON_THROW_ON_ERROR);
} elseif ($activation && $action === 'expire') {
    file_put_contents($base.'/clock.json', json_encode(['at' => BusinessContract::sole()->period_ends_at->toIso8601String()], JSON_THROW_ON_ERROR));
    echo "Synthetic clock advanced to the period boundary\n";
} elseif ($action === 'serve') {
    $app->handleRequest(Request::capture());
} else {
    throw new RuntimeException('Unsupported action.');
}
