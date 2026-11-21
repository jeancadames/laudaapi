<?php

use App\Models\BusinessContract;
use App\Models\BusinessImplementationOrder;
use App\Models\Invoice;
use App\Models\ServiceCommercialQuote;
use App\Models\User;
use App\Services\Billing\BusinessContractActivationService;
use App\Services\Billing\BusinessQuoteAcceptanceService;
use App\Services\Billing\BusinessQuoteCheckoutService;
use App\Services\Billing\BusinessQuoteInvoiceAmounts;
use App\Services\Billing\BusinessRenewalService;
use App\Services\Billing\BusinessRenewalTerms;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Support\BusinessContractFixtures;
use Tests\Support\BusinessIdentityApplication;
use Tests\Support\BusinessIdentityMySql;
use Tests\Support\BusinessQuoteCheckoutFixtures;
use Tests\Support\BusinessQuoteFixtures;

require_once dirname(__DIR__, 2).'/Support/BusinessIdentityApplication.php';
require_once dirname(__DIR__, 2).'/Support/BusinessIdentityMySql.php';
umask(0077);
$failure = static function (Throwable $error): never {
    fwrite(STDERR, $error::class.': '.$error->getMessage().PHP_EOL);
    exit(2);
};
set_exception_handler($failure);
$base = getenv('LAUDA_BUSINESS_RUNTIME');
if (! is_string($base) || ! preg_match('#^/tmp/lauda-business-[a-f0-9]{16}$#D', $base)) {
    throw new RuntimeException('Synthetic finance runtime required.');
}
BusinessIdentityMySql::privateDirectory($base);
$marker = BusinessIdentityMySql::read($base.'/finance-qa.json');
if (($marker['purpose'] ?? '') !== 'business-finance-qa' || ($marker['base'] ?? '') !== $base
    || ! in_array($marker['mode'] ?? '', ['mysql', 'sqlite_self_test'], true)
    || (getenv('LAUDA_BUSINESS_MYSQL') === '1') !== ($marker['mode'] === 'mysql')) {
    throw new RuntimeException('Finance QA mode does not match its private marker.');
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (($input['action'] ?? '') === 'locks') {
    if ($marker['mode'] !== 'mysql') {
        throw new RuntimeException('Lock evidence requires MySQL.');
    }
    $run = BusinessIdentityMySql::marker($base);
    $pdo = BusinessIdentityMySql::server($run['server'])['pdo'];
    $database = BusinessIdentityMySql::configuration($base.'/central')['database'];
    $statement = $pdo->prepare('SELECT COUNT(*) FROM performance_schema.data_lock_waits w
        JOIN performance_schema.threads r ON r.THREAD_ID = w.REQUESTING_THREAD_ID
        JOIN performance_schema.threads b ON b.THREAD_ID = w.BLOCKING_THREAD_ID
        WHERE r.PROCESSLIST_ID = ? AND b.PROCESSLIST_ID = ? AND r.PROCESSLIST_DB = ? AND b.PROCESSLIST_DB = ?');
    $deadline = microtime(true) + 5;
    do {
        $statement->execute([$input['waiting'], $input['holding'], $database, $database]);
        $waits = (int) $statement->fetchColumn();
        if ($waits > 0) {
            break;
        }
        usleep(10000);
    } while (microtime(true) < $deadline);
    echo json_encode(['lock_waits' => $waits], JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}

$app = BusinessIdentityApplication::create(dirname(__DIR__, 3), $base.'/central');
set_exception_handler($failure);
BusinessQuoteFixtures::assertSyntheticDatabase();
BusinessContractFixtures::configure();
config()->set(['business_commercial.enabled' => true, 'business_commercial.renewal_enabled' => true]);
Carbon::setTestNow('2026-11-18 12:00:00 UTC');
CarbonImmutable::setTestNow('2026-11-18 12:00:00 UTC');
Mail::fake();
Notification::fake();
$connection = DB::connection();
$info = ['driver' => $connection->getDriverName(), 'database' => $connection->getDatabaseName(), 'pid' => getmypid()];
if ($marker['mode'] === 'mysql') {
    DB::statement('SET SESSION innodb_lock_wait_timeout = 20');
    $info += (array) DB::selectOne('SELECT CONNECTION_ID() AS connection_id, @@transaction_isolation AS isolation_level, @@sql_mode AS sql_mode');
}
$activation = static fn (ServiceCommercialQuote $quote): array => [
    'confirmed' => true, 'snapshot_hash' => BusinessQuoteAcceptanceService::fingerprint($quote),
    'idempotency_key' => (string) Str::uuid(), 'implementation_version' => BusinessImplementationOrder::sole()->lock_version,
    'technical_evidence' => array_fill_keys(array_keys($quote->snapshot['products']), 'Preparación sintética de concurrencia financiera.'),
];

if ($input['action'] === 'setup') {
    if (! in_array($input['stage'] ?? '', ['quote', 'checkout', 'activation', 'renewal'], true)) {
        throw new RuntimeException('Unknown finance setup stage.');
    }
    // The guard above verifies the exact disposable schema and its private manifest.
    // Recreate it between scenarios, only after the runner has joined all workers.
    Schema::dropAllTables();
    BusinessContractFixtures::schema();
    (require base_path('database/migrations/2026_11_06_000008_create_business_renewals.php'))->up();
    $quote = BusinessQuoteCheckoutFixtures::seed();
    $quotes = [$quote];
    if ($input['second_quote'] ?? false) {
        $quotes[] = BusinessQuoteFixtures::issue();
    }
    if ($input['stage'] === 'checkout') {
        foreach ($quotes as $item) {
            app(BusinessQuoteAcceptanceService::class)->accept(User::findOrFail(1), $item->public_id,
                BusinessQuoteAcceptanceService::fingerprint($item), (string) Str::uuid());
        }
    }
    $extra = [];
    if (in_array($input['stage'], ['activation', 'renewal'], true)) {
        BusinessContractFixtures::prepare($quote);
        $extra['activation'] = $activation($quote);
    }
    if ($input['stage'] === 'renewal') {
        $contract = app(BusinessContractActivationService::class)->activate(User::findOrFail(4), $quote->public_id, $extra['activation']);
        $extra['proposal'] = ['confirmed' => true, 'idempotency_key' => (string) Str::uuid(), 'period_number' => 2,
            'expected_revision' => 0, 'valid_until' => $contract->period_ends_at->subDay()->toIso8601String(),
            'base_cents' => 350000, 'discount_cents' => 50000, 'tax_bps' => 1800,
            'commercial_note' => 'Condiciones sintéticas de QA para un único período de renovación.', ...BusinessRenewalTerms::POLICIES];
    }
    echo json_encode(['quotes' => array_map(static fn ($item) => ['public_id' => $item->public_id,
        'hash' => BusinessQuoteAcceptanceService::fingerprint($item)], $quotes), 'connection' => $info, ...$extra], JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}
if ($input['action'] === 'inspect') {
    $counts = [];
    foreach (['service_quote_acceptances', 'service_quote_checkouts', 'invoices', 'invoice_items', 'payments',
        'business_contracts', 'business_contract_items', 'subscriptions', 'subscription_items',
        'business_renewal_offers', 'business_renewal_acceptances', 'business_renewal_periods'] as $table) {
        $counts[$table] = DB::table($table)->count();
    }
    $amounts = app(BusinessQuoteInvoiceAmounts::class);
    $invoices = Invoice::orderBy('id')->get()->map(static fn ($invoice) => [
        'id' => $invoice->id, 'number' => $invoice->number, 'source' => data_get($invoice->billing_snapshot, 'source'),
        'subtotal_cents' => $amounts->cents($invoice->subtotal), 'tax_cents' => $amounts->cents($invoice->tax_total),
        'total_cents' => $amounts->cents($invoice->total), 'paid_cents' => $amounts->cents($invoice->amount_paid),
    ])->all();
    $audits = DB::table('audit_logs')->selectRaw('event, COUNT(*) AS total')->groupBy('event')->pluck('total', 'event')->all();
    echo json_encode(['counts' => $counts, 'invoices' => $invoices, 'audits' => $audits,
        'renewal_offers' => DB::table('business_renewal_offers')->orderBy('id')->get(['id', 'revision', 'terms_hash'])->all(),
        'renewal_acceptances' => DB::table('business_renewal_acceptances')->get(['business_renewal_offer_id', 'terms_hash'])->all(),
        'renewal_periods' => DB::table('business_renewal_periods')->get(['period_number', 'terms_hash', 'invoice_id'])->all(),
        'contract_hashes' => BusinessContract::pluck('snapshot_hash')->all(), 'connection' => $info], JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}
if ($input['action'] !== 'request') {
    throw new RuntimeException('Unknown finance action.');
}
$reached = false;
if (isset($input['barrier'])) {
    if ($marker['mode'] !== 'mysql' || ! preg_match('/^[a-z0-9_-]+$/D', $input['barrier']['name'] ?? '')
        || ! in_array($input['barrier']['role'] ?? '', ['hold', 'wait'], true)
        || ! in_array($input['barrier']['table'] ?? '', ['companies', 'service_catalogs'], true)) {
        throw new RuntimeException('Invalid finance MySQL barrier.');
    }
    $prefix = $base.'/'.$input['barrier']['name'];
    $table = $input['barrier']['table'];
    $publish = static function (string $role) use ($prefix, $info): void {
        $path = $prefix.'.'.$role.'.ready';
        $temporary = $path.'.tmp-'.getmypid();
        if (file_put_contents($temporary, json_encode($info, JSON_THROW_ON_ERROR)) === false || ! rename($temporary, $path)) {
            throw new RuntimeException('Could not publish finance barrier.');
        }
    };
    $isMutex = static fn (string $query): bool => str_starts_with($query, 'select')
        && str_contains($query, '`'.$table.'`') && str_contains($query, 'for update');
    if ($input['barrier']['role'] === 'wait') {
        $connection->beforeExecuting(static function ($query) use ($publish, $isMutex, &$reached): void {
            if (! $reached && $isMutex($query)) {
                $reached = true;
                $publish('wait');
            }
        });
    } else {
        DB::listen(static function ($query) use ($prefix, $publish, $isMutex, &$reached): void {
            if (! $reached && $isMutex($query->sql)) {
                $reached = true;
                $publish('hold');
                $deadline = microtime(true) + 15;
                while (! is_file($prefix.'.go')) {
                    if (microtime(true) >= $deadline) {
                        throw new RuntimeException('Timed out waiting for finance mutex release.');
                    }
                    usleep(10000);
                }
            }
        });
    }
}
try {
    $data = $input['payload'];
    $owner = User::findOrFail(1);
    $admin = User::findOrFail(4);
    $result = match ($input['operation']) {
        'accept' => app(BusinessQuoteAcceptanceService::class)->accept($owner, $input['quote'], $data['hash'], $data['idempotency_key']),
        'checkout' => app(BusinessQuoteCheckoutService::class)->checkout($owner, $input['quote'], $data['hash'], $data['idempotency_key']),
        'activate' => app(BusinessContractActivationService::class)->activate($admin, $input['quote'], $data),
        'propose' => app(BusinessRenewalService::class)->propose($admin, $input['quote'], $data),
        'renewal_accept' => app(BusinessRenewalService::class)->accept($owner, $input['quote'], $data),
        'renewal_invoice' => app(BusinessRenewalService::class)->issue($admin, $input['quote'], $data),
        default => throw new RuntimeException('Unknown financial operation.'),
    };
    $result = ['status' => 200, 'body' => $result->only(['id', 'invoice_id', 'terms_hash', 'snapshot_hash', 'revision'])];
} catch (HttpExceptionInterface $error) {
    $result = ['status' => $error->getStatusCode(), 'body' => ['message' => $error->getMessage()]];
}
echo json_encode([...$result, 'connection' => $info, 'barrier_reached' => $reached], JSON_THROW_ON_ERROR).PHP_EOL;
