<?php

use App\Http\Controllers\Concerns\HandlesBusinessIdentity;
use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use App\Services\BusinessIdentity\CentralIdentityService;
use App\Services\BusinessIdentity\ReceiverIdentityService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require __DIR__.'/bootstrap.php';
set_exception_handler(static function (Throwable $error): never {
    fwrite(STDERR, $error::class.': '.$error->getMessage().PHP_EOL);
    exit(2);
});
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$connection = DB::connection();
$mysql = $connection->getDriverName() === 'mysql';
$info = ['driver' => $connection->getDriverName(), 'database' => $connection->getDatabaseName(), 'pid' => getmypid()];
if ($mysql) {
    DB::statement('SET SESSION innodb_lock_wait_timeout = 15');
    $info += (array) DB::selectOne('SELECT CONNECTION_ID() AS connection_id, @@transaction_isolation AS isolation_level, @@sql_mode AS sql_mode');
}
$waited = false;
$wait = static function () use (&$waited, $input, $info): void {
    if ($waited || ! isset($input['barrier'])) {
        return;
    }
    $waited = true; // Deadlock retries must not re-enter the barrier.
    $base = getenv('LAUDA_BUSINESS_RUNTIME');
    $barrier = $input['barrier'];
    if (! preg_match('/^[a-z0-9_-]+$/D', $barrier['name']) || ! in_array($barrier['worker'], [0, 1], true)) {
        throw new RuntimeException('Barrera inválida.');
    }
    $prefix = $base.'/'.$barrier['name'];
    file_put_contents($prefix.'.'.$barrier['worker'].'.ready', json_encode($info, JSON_THROW_ON_ERROR));
    $deadline = microtime(true) + 20;
    while (! is_file($prefix.'.go')) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('La segunda conexión no llegó a la barrera.');
        }
        usleep(10000);
    }
};
if ($mysql && isset($input['barrier'])) {
    if (($input['barrier']['point'] ?? '') === 'availability') {
        DB::listen(static function ($query) use ($wait): void {
            if (str_starts_with($query->sql, 'select') && str_contains($query->sql, 'business_identity_accounts') && ! str_contains($query->sql, 'for update')) {
                $wait();
            }
        });
    } else {
        $connection->beforeExecuting(static function ($query) use ($wait): void {
            if (str_contains($query, 'for update')) {
                $wait();
            }
        });
    }
}

// Exercise the same uniqueness-to-409 adapter as the actual HTTP controllers.
$adapter = new class
{
    use HandlesBusinessIdentity;

    public function run(Closure $action): Response
    {
        return $this->identityResponse(fn () => response()->json($action()));
    }
};
try {
    $response = $adapter->run(function () use ($input) {
        $action = $input['action'];
        if ($action === 'extra-company') {
            $user = (array) DB::table('users')->where('id', 1)->first();
            $user['id'] = 2;
            $user['email'] = 'other@identity.invalid';
            DB::table('users')->insert($user);
            DB::table('subscribers')->insert(['id' => 2, 'name' => 'Other synthetic company', 'slug' => 'other']);
            DB::table('subscriber_user')->insert(['subscriber_id' => 2, 'user_id' => 2, 'role' => 'owner', 'active' => true]);
            DB::table('companies')->insert(['id' => 2, 'subscriber_id' => 2, 'name' => 'Other synthetic company', 'slug' => 'other']);

            return ['created' => true];
        }
        if ($action === 'state') {
            return ['links' => DB::table('business_identity_links')->orderBy('central_company_id')->get()->all(),
                'members' => DB::table('business_identity_members')->get()->all(),
                'intents' => DB::table('business_identity_intents')->count(), 'accounts' => DB::table('business_identity_accounts')->count(),
                'nonces' => DB::table('business_identity_nonces')->count(),
                'consented' => DB::table('business_identity_events')->where('event', 'consented')->count(),
                'launched' => DB::table('business_identity_events')->where('event', 'launched')->count()];
        }
        if ($action === 'invoices-schema') {
            (require base_path('database/migrations/2026_01_24_123104_create_invoices_table.php'))->up();

            return ['created' => true];
        }
        $user = User::findOrFail($input['user'] ?? 1);
        $company = $input['company'] ?? 1;
        $service = $input['service'] ?? 'bys';
        if (getenv('LAUDA_BUSINESS_PROJECT') === 'central') {
            $identity = app(CentralIdentityService::class);

            return match ($action) {
                'start' => $identity->start($user, Company::findOrFail($company), $service, true, $input['restart'] ?? false),
                'confirm' => $identity->complete($user, $service, $input['token']),
                'ready' => $identity->ready($user, $service, $input['token']),
                'launch' => ['url' => $identity->launch($user, Company::findOrFail($company), Service::where('service_key', $service)->firstOrFail())],
                default => throw new RuntimeException('Acción central desconocida.'),
            };
        }
        $identity = app(ReceiverIdentityService::class);
        if ($action === 'revoke') {
            $identity->revoke($user, $company, true);

            return ['revoked' => true];
        }

        return match ($action) {
            'prove' => $identity->prove($user, $company, $input['token']),
            'activate' => $identity->activate($user, $input['token']),
            'launch' => ['destination' => $identity->launch($user, $input['token'])],
            default => throw new RuntimeException('Acción receptora desconocida.'),
        };
    });
    $result = ['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR)];
} catch (HttpExceptionInterface $error) {
    $result = ['status' => $error->getStatusCode(), 'body' => ['message' => $error->getMessage()]];
}
echo json_encode([...$result, 'connection' => $info, 'barrier_reached' => $waited], JSON_THROW_ON_ERROR).PHP_EOL;
