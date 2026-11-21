<?php

use App\Http\Controllers\BusinessCommercial\CapacityController;
use App\Http\Controllers\BusinessCommercial\CommercialController;
use App\Models\BusinessImplementationOrder;
use App\Models\User;
use App\Services\Billing\BusinessContractActivationService;
use App\Services\Billing\BusinessQuoteAcceptanceService;
use App\Services\BusinessCommercial\CapacityService;
use App\Services\BusinessCommercial\CommercialService;
use App\Services\BusinessCommercial\CommercialToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\BusinessContractFixtures;
use Tests\Support\BusinessIdentityApplication;
use Tests\Support\BusinessIdentityMySql;
use Tests\Support\BusinessQuoteFixtures;

require_once dirname(__DIR__, 2).'/Support/BusinessIdentityApplication.php';
require_once dirname(__DIR__, 2).'/Support/BusinessIdentityMySql.php';
umask(0077);
set_exception_handler(static function (Throwable $error): never {
    fwrite(STDERR, $error::class.': '.$error->getMessage().PHP_EOL);
    exit(2);
});
$base = getenv('LAUDA_BUSINESS_RUNTIME');
if (! is_string($base) || ! preg_match('#^/tmp/lauda-business-[a-f0-9]{16}$#D', $base)) {
    throw new RuntimeException('Synthetic runtime required.');
}
BusinessIdentityMySql::privateDirectory($base);
$marker = BusinessIdentityMySql::read($base.'/commercial-qa.json');
if (($marker['purpose'] ?? '') !== 'business-commercial-qa' || ($marker['base'] ?? '') !== $base
    || ! in_array($marker['mode'] ?? '', ['mysql', 'sqlite_self_test'], true)
    || (getenv('LAUDA_BUSINESS_MYSQL') === '1') !== ($marker['mode'] === 'mysql')) {
    throw new RuntimeException('Commercial QA mode does not match its private marker.');
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (($input['action'] ?? '') === 'locks') {
    if ($marker['mode'] !== 'mysql') {
        throw new RuntimeException('Lock evidence requires MySQL.');
    }
    $run = BusinessIdentityMySql::marker($base);
    $pdo = BusinessIdentityMySql::server($run['server'])['pdo'];
    $database = BusinessIdentityMySql::configuration($base.'/central')['database'];
    $sql = 'SELECT COUNT(*) FROM performance_schema.data_lock_waits w
        JOIN performance_schema.threads r ON r.THREAD_ID = w.REQUESTING_THREAD_ID
        JOIN performance_schema.threads b ON b.THREAD_ID = w.BLOCKING_THREAD_ID
        WHERE r.PROCESSLIST_ID = ? AND b.PROCESSLIST_ID = ? AND r.PROCESSLIST_DB = ? AND b.PROCESSLIST_DB = ?';
    $statement = $pdo->prepare($sql);
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
set_exception_handler(static function (Throwable $error): never {
    fwrite(STDERR, $error::class.': '.$error->getMessage().PHP_EOL);
    exit(2);
});
BusinessQuoteFixtures::assertSyntheticDatabase();
BusinessContractFixtures::configure();
config()->set(['business_commercial_bridge.enabled' => true, 'business_commercial_bridge.capacities_enabled' => true]);
Mail::fake();
Notification::fake();
$connection = DB::connection();
$info = ['driver' => $connection->getDriverName(), 'database' => $connection->getDatabaseName(), 'pid' => getmypid()];
if ($marker['mode'] === 'mysql') {
    DB::statement('SET SESSION innodb_lock_wait_timeout = 20');
    $info += (array) DB::selectOne('SELECT CONNECTION_ID() AS connection_id, @@transaction_isolation AS isolation_level, @@sql_mode AS sql_mode');
}

if ($input['action'] === 'setup') {
    BusinessContractFixtures::schema();
    foreach (['2026_11_07_000010_create_business_document_usage.php', '2026_11_07_000012_create_business_usage_cancellations.php',
        '2026_11_07_000014_create_business_fiscal_handoffs.php', '2026_11_17_000022_create_business_capacity_users.php'] as $migration) {
        (require base_path('database/migrations/'.$migration))->up();
    }
    $quote = BusinessContractFixtures::seed();
    $contract = app(BusinessContractActivationService::class)->activate(User::findOrFail(4), $quote->public_id, [
        'confirmed' => true, 'snapshot_hash' => BusinessQuoteAcceptanceService::fingerprint($quote), 'idempotency_key' => (string) Str::uuid(),
        'implementation_version' => BusinessImplementationOrder::sole()->lock_version,
        'technical_evidence' => array_fill_keys(array_keys($quote->snapshot['products']), 'Preparación sintética de concurrencia.'),
    ]);
    $snapshot = $contract->snapshot;
    $snapshot['capacity']['documents'] = 1;
    $snapshot['capacity']['users'] = 1;
    $snapshot['capacity_policy']['owner_counts'] = false;
    DB::table('business_contracts')->where('id', $contract->id)->update(['snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
    echo json_encode(['setup' => true, 'connection' => $info], JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}
if ($input['action'] === 'reset') {
    DB::transaction(static function (): void {
        foreach (['business_capacity_events', 'business_capacity_users', 'business_capacity_sources', 'business_usage_cancellations', 'business_usage_events', 'business_usage_reservations', 'business_usage_aliases', 'business_usage_documents'] as $table) {
            DB::table($table)->delete();
        }
    });
    echo json_encode(['reset' => true, 'connection' => $info], JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}
$exchange = static function (string $service, string $action, array $payload = []): array {
    $link = DB::table('business_identity_links')->where('service', $service)->sole();
    $capacity = str_starts_with($action, 'capacity:');
    $action = $capacity ? substr($action, 9) : $action;
    $data = ['action' => $action, 'link_id' => $link->id, 'local_company_id' => 501,
        'central_company_id' => 1, 'central_subscriber_id' => 1, ...$payload];
    $codec = new CommercialToken(config('business_identity.peers.'.$service.'.secret'));
    $issuer = config('business_identity.issuer');
    $token = $codec->encode($service, $issuer, 'request', $data, now()->timestamp);
    $request = Request::create('/api/business/commercial/v1/'.$service, 'POST');
    $request->headers->set('Authorization', 'Bearer '.$token);
    $response = $capacity
        ? app(CapacityController::class)($request, $service, app(CapacityService::class))
        : app(CommercialController::class)($request, $service, app(CommercialService::class));
    $reply = $codec->decode($response->getData(true)['token'], $issuer, $service, 'response', now()->timestamp);
    if (($reply['request_hash'] ?? '') !== hash('sha256', $token) || ($reply['status'] ?? 0) !== $response->status()) {
        throw new RuntimeException('Invalid signed response.');
    }

    return ['status' => $response->status(), 'body' => $reply['result'] ?? $reply];
};
if ($input['action'] === 'inspect') {
    $counts = [];
    foreach (['documents', 'reservations', 'aliases', 'events', 'cancellations'] as $table) {
        $counts[$table] = DB::table('business_usage_'.$table)->count();
    }
    echo json_encode(['counts' => $counts, 'usage' => $exchange('pos', 'snapshot')['body']['documents'],
        'reservations' => DB::table('business_usage_reservations')->orderBy('created_at')->get(['id', 'operation_key', 'state', 'document_id'])->all(),
        'connection' => $info], JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}
if ($input['action'] !== 'request') {
    throw new RuntimeException('Unknown QA action.');
}
$reached = false;
if (isset($input['barrier'])) {
    if ($marker['mode'] !== 'mysql' || ! preg_match('/^[a-z0-9_-]+$/D', $input['barrier']['name'] ?? '')
        || ! in_array($input['barrier']['role'] ?? '', ['hold', 'wait'], true)) {
        throw new RuntimeException('Invalid MySQL barrier.');
    }
    $prefix = $base.'/'.$input['barrier']['name'];
    $role = $input['barrier']['role'];
    $publish = static function (string $role) use ($prefix, $info): void {
        $path = $prefix.'.'.$role.'.ready';
        $temporary = $path.'.tmp-'.getmypid();
        if (file_put_contents($temporary, json_encode($info, JSON_THROW_ON_ERROR)) === false || ! rename($temporary, $path)) {
            throw new RuntimeException('Could not publish the MySQL barrier.');
        }
    };
    $isMutex = static fn (string $query): bool => str_starts_with($query, 'select')
        && str_contains($query, '`companies`') && str_contains($query, 'for update');
    if ($role === 'wait') {
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
                        throw new RuntimeException('Timed out waiting for the second MySQL connection.');
                    }
                    usleep(10000);
                }
            }
        });
    }
}
$result = $exchange($input['service'], $input['operation'], $input['payload'] ?? []);
echo json_encode([...$result, 'connection' => $info, 'barrier_reached' => $reached], JSON_THROW_ON_ERROR).PHP_EOL;
