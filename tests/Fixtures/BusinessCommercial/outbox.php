<?php

use App\Services\BusinessCommercial\CommercialOutbox;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

// Included only by the guarded, synthetic receiver fixture. No application routes call this file.
config()->set('business_commercial_outbox.enabled', $mode !== 'outbox_disabled');
if (! Schema::hasTable('business_commercial_operations')) {
    (require base_path('database/migrations/2026_11_07_000011_create_business_commercial_outbox.php'))->up();
    Schema::create('fixture_native_results', function (Blueprint $table): void {
        $table->id();
        $table->uuid('operation_id')->unique();
        $table->uuid('document_id');
    });
}
$outbox = app(CommercialOutbox::class);
$company = (int) ($input['company_id'] ?? 501);
$id = $input['id'] ?? null;
$step = $input['step'];
if ($mode === 'due' && $id) {
    DB::table('business_commercial_operations')->where('id', $id)->update(['next_attempt_at' => null]);
}
if ($mode === 'lost') {
    Http::globalResponseMiddleware(function ($response) {
        throw new ConnectionException('Synthetic lost response after remote commit.');
    });
}
if ($mode === 'expired') {
    $row = DB::table('business_commercial_operations')->where('id', $id)->first();
    CarbonImmutable::setTestNow(CarbonImmutable::parse(json_decode($row->receipt, true)['valid_until']));
}
if ($mode === 'leased' || $mode === 'stale_lease') {
    DB::table('business_commercial_operations')->where('id', $id)->update([
        'lease_token' => (string) Str::uuid(), 'leased_until' => now()->addSeconds($mode === 'leased' ? 30 : -30),
    ]);
}
if (in_array($mode, ['fenced', 'fenced_error'], true)) {
    Http::globalResponseMiddleware(function ($response) use ($id, $mode) {
        DB::table('business_commercial_operations')->where('id', $id)->update([
            'lease_token' => '11111111-1111-4111-8111-111111111111', 'leased_until' => now()->addSeconds(30),
        ]);
        if ($mode === 'fenced_error') {
            throw new ConnectionException('Synthetic stale transport failure.');
        }

        return $response;
    });
}
if ($mode === 'changed_link') {
    DB::table('business_identity_links')->update(['central_company_id' => 999]);
}
if ($mode === 'missing_schema') {
    Schema::drop('business_commercial_operation_events');
}
if ($mode === 'event_failure') {
    DB::statement("CREATE TRIGGER fixture_fail_event BEFORE INSERT ON business_commercial_operation_events WHEN NEW.event = 'local_committed' BEGIN SELECT RAISE(ABORT, 'synthetic event failure'); END");
}
$writer = function (array $receipt) use ($id, $mode): array {
    DB::table('fixture_native_results')->insert(['operation_id' => $id, 'document_id' => $receipt['document_id']]);
    abort_if($mode === 'write_failure', 422, 'Synthetic local rollback.');

    return ['reference' => 'fixture:'.$id, 'fingerprint' => hash('sha256', $receipt['document_id'])];
};
$verifier = function (array $receipt) use ($id): array {
    abort_unless(DB::table('fixture_native_results')->where('operation_id', $id)->where('document_id', $receipt['document_id'])->exists(), 409, 'Native result not proven.');

    return ['reference' => 'fixture:'.$id, 'fingerprint' => hash('sha256', $receipt['document_id'])];
};
if ($mode === 'outer_transaction') {
    DB::beginTransaction();
}
try {
    $result = match ($step) {
        'prepare' => ['id' => $outbox->prepare($company, $input['source'] ?? 'native:1', $input['fingerprint'] ?? hash('sha256', 'native-intent'), $input['identity'] ?? [], $input['kind'] ?? 'reserve')],
        'sync' => $outbox->synchronize($company, $id),
        'inspect' => $outbox->inspect($company, $id),
        'recovery' => $outbox->recoveryContext($company, $id),
        'execute' => $outbox->executeLocal($company, $id, $input['fingerprint'] ?? hash('sha256', 'native-intent'), $writer),
        'begin' => $outbox->beginExternal($company, $id, $input['fingerprint'] ?? hash('sha256', 'native-intent')),
        'complete' => $outbox->completeExternal($company, $id, $input['execution_token'], $verifier),
        'abandon' => $outbox->abandonExternal($company, $id, $input['execution_token'], function (array $receipt) use ($id, $input): array {
            abort_unless(($input['definitive_rejection'] ?? false) && ! DB::table('fixture_native_results')->where('operation_id', $id)->exists(), 409, 'No definitive native rejection.');

            return ['reference' => 'rejection:'.$id, 'fingerprint' => hash('sha256', 'fixture definitive rejection')];
        }),
        'cancel' => $outbox->cancel($company, $id),
        'native_result' => $writer($outbox->inspect($company, $id)['receipt']),
        'down' => (function (): array {
            (require base_path('database/migrations/2026_11_07_000011_create_business_commercial_outbox.php'))->down();

            return [];
        })(),
        'command' => (function () use ($input): array {
            $code = Artisan::call('business:commercial-reconcile', $input['options'] ?? []);

            return ['code' => $code, 'output' => Artisan::output()];
        })(),
        default => throw new RuntimeException('Unknown outbox fixture action'),
    };
    echo json_encode(['status' => 200, 'result' => $result], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    $status = $error instanceof HttpExceptionInterface ? $error->getStatusCode() : ($error instanceof ValidationException ? 422 : 500);
    echo json_encode(['status' => $status, 'message' => $error->getMessage()], JSON_THROW_ON_ERROR);
} finally {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    CarbonImmutable::setTestNow();
}
