<?php

namespace Tests\Feature\BusinessCommercial;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BusinessCommercialTransportTestCase;

final class CommercialOutboxTest extends BusinessCommercialTransportTestCase
{
    private function receiver(string $service): PDO
    {
        return new PDO('sqlite:'.$this->runtime.'/'.$service.'/database.sqlite');
    }

    private function outbox(string $service, string $step, array $data = [], int $status = 200): array
    {
        return $this->client($service, 'outbox', ['step' => $step, ...$data], $status);
    }

    private function prepare(string $service = 'pos', string $source = 'native:1', array $data = []): string
    {
        return $this->outbox($service, 'prepare', ['source' => $source, ...$data])['id'];
    }

    public function test_all_receivers_persist_intent_then_share_one_document_across_process_restarts(): void
    {
        $this->startServer();
        $documentIds = [];
        foreach (['pos', 'ecf', 'bys', 'cumplimiento'] as $service) {
            $data = ['identity' => ['fiscal' => ['issuer' => '101010101', 'ncf' => 'E310000000051']],
                'kind' => $service === 'cumplimiento' ? 'reference' : 'reserve'];
            $id = $this->prepare($service, 'native:51', $data);
            $this->assertSame($id, $this->prepare($service, 'native:51', $data));
            $this->assertSame('prepared', $this->outbox($service, 'inspect', compact('id'))['state']);
            $receipt = $this->outbox($service, 'sync', compact('id'));
            $documentIds[] = $receipt['receipt']['document_id'];
            if ($service !== 'cumplimiento') {
                $evidence = $this->outbox($service, 'execute', compact('id'));
                $this->assertSame($evidence, $this->outbox($service, 'execute', compact('id')));
                $this->assertSame('local_committed', $this->outbox($service, 'inspect', compact('id'))['state']);
                $this->assertSame('committed', $this->outbox($service, 'sync', compact('id'))['state']);
                $this->assertSame(1, (int) $this->receiver($service)->query('SELECT count(*) FROM fixture_native_results')->fetchColumn());
            } else {
                $this->assertSame('referenced', $receipt['state']);
                $this->outbox($service, 'execute', compact('id'), 409);
                $this->outbox($service, 'prepare', ['source' => 'not-allowed'], 422);
            }
            $this->assertSame(1, (int) $this->receiver($service)->query('SELECT count(*) FROM business_commercial_operations')->fetchColumn());
        }
        $this->assertCount(1, array_unique($documentIds));
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
        $this->assertSame(4, DB::table('business_usage_reservations')->count());
        $this->assertSame(7, DB::table('business_usage_events')->count());
    }

    public function test_lost_reservation_and_commit_responses_recover_after_restarts_without_repeating_native_write(): void
    {
        $this->startServer();
        $id = $this->prepare();
        $this->outbox('pos', 'sync', compact('id') + ['mode' => 'lost'], 503);
        $this->assertSame('reserving', $this->outbox('pos', 'inspect', compact('id'))['state']);
        $this->assertSame(1, DB::table('business_usage_reservations')->count());
        $this->outbox('pos', 'sync', compact('id'), 409);
        $this->assertSame('reserved', $this->outbox('pos', 'sync', compact('id') + ['mode' => 'due'])['state']);
        $this->outbox('pos', 'execute', compact('id'));
        DB::table('payments')->update(['amount' => 0]);
        $this->outbox('pos', 'sync', compact('id') + ['mode' => 'lost'], 503);
        $this->assertSame('local_committed', $this->outbox('pos', 'inspect', compact('id'))['state']);
        $this->outbox('pos', 'execute', compact('id'));
        $this->assertSame('committed', $this->outbox('pos', 'sync', compact('id') + ['mode' => 'due'])['state']);
        $this->assertSame(1, (int) $this->receiver('pos')->query('SELECT count(*) FROM fixture_native_results')->fetchColumn());
        $this->assertSame(2, DB::table('business_usage_events')->count());
    }

    public function test_cancel_before_remote_admission_blocks_late_requests_even_when_cancellation_reply_is_lost(): void
    {
        $this->startServer();
        $id = $this->prepare();
        $this->outbox('pos', 'sync', compact('id') + ['mode' => 'unavailable'], 503);
        $this->assertSame('release_pending', $this->outbox('pos', 'cancel', compact('id'))['state']);
        $this->outbox('pos', 'sync', compact('id') + ['mode' => 'lost'], 503);
        $this->assertSame(1, DB::table('business_usage_cancellations')->count());
        $this->client('pos', 'reserve', ['operation_key' => $id, 'document' => ['source_reference' => 'native:1']], 409);
        $this->assertSame('cancelled', $this->outbox('pos', 'sync', compact('id') + ['mode' => 'due'])['state']);
        $this->outbox('pos', 'execute', compact('id'), 409);
        $this->assertSame(0, DB::table('business_usage_documents')->count());
    }

    public function test_cancellation_after_admission_releases_once_and_preserves_history(): void
    {
        $this->startServer();
        $id = $this->prepare();
        $this->outbox('pos', 'sync', compact('id'));
        $this->outbox('pos', 'cancel', compact('id'));
        $this->outbox('pos', 'sync', compact('id') + ['mode' => 'lost'], 503);
        $this->assertSame('released', $this->outbox('pos', 'sync', compact('id') + ['mode' => 'due'])['state']);
        $this->assertSame('released', $this->outbox('pos', 'cancel', compact('id'))['state']);
        $this->client('pos', 'reserve', ['operation_key' => $id, 'document' => ['source_reference' => 'native:1']], 409);
        $this->assertSame(2, DB::table('business_usage_events')->count());
        $this->assertSame(0, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
        $this->outbox('pos', 'down', [], 500);
        $this->expectException(\RuntimeException::class);
        (require base_path('database/migrations/2026_11_07_000012_create_business_usage_cancellations.php'))->down();
    }

    public function test_expired_permit_never_executes_or_automatically_releases_capacity(): void
    {
        $this->startServer();
        $id = $this->prepare();
        $receipt = $this->outbox('pos', 'sync', compact('id'))['receipt'];
        $this->outbox('pos', 'execute', compact('id') + ['mode' => 'expired'], 409);
        $this->outbox('pos', 'begin', compact('id') + ['mode' => 'expired'], 409);
        $this->assertSame('reserved', DB::table('business_usage_reservations')->sole()->state);
        $this->assertSame($receipt, $this->outbox('pos', 'sync', compact('id'))['receipt']);
        $this->assertSame(0, (int) $this->receiver('pos')->query('SELECT count(*) FROM fixture_native_results')->fetchColumn());
    }

    public function test_external_result_can_be_recovered_after_lost_start_context_and_expiry_without_reexecution(): void
    {
        $this->startServer();
        $id = $this->prepare();
        $this->outbox('pos', 'sync', compact('id'));
        $start = $this->outbox('pos', 'begin', compact('id'));
        $this->outbox('pos', 'begin', compact('id'), 409);
        $this->outbox('pos', 'cancel', compact('id'), 409);
        $this->assertSame('executing', $this->outbox('pos', 'sync', compact('id'))['state']);
        $recovered = $this->outbox('pos', 'recovery', compact('id'));
        $this->assertFalse($recovered['execution_allowed']);
        $this->assertSame($start['execution_token'], $recovered['execution_token']);
        $args = compact('id') + ['execution_token' => $recovered['execution_token']];
        $this->outbox('pos', 'complete', $args, 409);
        $this->outbox('pos', 'native_result', compact('id'));
        DB::table('payments')->update(['amount' => 0]);
        $this->outbox('pos', 'complete', $args + ['mode' => 'expired']);
        $this->outbox('pos', 'complete', $args);
        $this->outbox('pos', 'abandon', $args + ['definitive_rejection' => true], 409);
        $this->assertSame('committed', $this->outbox('pos', 'sync', compact('id'))['state']);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public function test_external_absence_needs_verified_native_rejection_not_a_timeout(): void
    {
        $this->startServer();
        $id = $this->prepare();
        $this->outbox('pos', 'sync', compact('id'));
        $start = $this->outbox('pos', 'begin', compact('id'));
        $args = compact('id') + ['execution_token' => $start['execution_token']];
        $this->outbox('pos', 'abandon', $args, 409);
        $this->outbox('pos', 'complete', compact('id') + ['execution_token' => (string) Str::uuid()], 409);
        $this->assertSame('release_pending', $this->outbox('pos', 'abandon', $args + ['definitive_rejection' => true])['state']);
        $this->assertSame('released', $this->outbox('pos', 'sync', compact('id'))['state']);
        $this->outbox('pos', 'complete', $args, 409);
    }

    #[DataProvider('rollbackFailures')]
    public function test_native_write_and_local_evidence_rollback_together(string $mode, int $status): void
    {
        $this->startServer();
        $id = $this->prepare();
        $this->outbox('pos', 'sync', compact('id'));
        $before = (int) $this->receiver('pos')->query('SELECT count(*) FROM business_commercial_operation_events')->fetchColumn();
        $this->outbox('pos', 'execute', compact('id', 'mode'), $status);
        $this->assertSame('reserved', $this->outbox('pos', 'inspect', compact('id'))['state']);
        $this->assertSame(0, (int) $this->receiver('pos')->query('SELECT count(*) FROM fixture_native_results')->fetchColumn());
        $this->assertSame($before, (int) $this->receiver('pos')->query('SELECT count(*) FROM business_commercial_operation_events')->fetchColumn());
        $this->assertSame('reserved', DB::table('business_usage_reservations')->sole()->state);
    }

    public static function rollbackFailures(): array
    {
        return [['write_failure', 422], ['event_failure', 500]];
    }

    #[DataProvider('staleWorkers')]
    public function test_late_worker_cannot_clear_new_lease_or_overwrite_state(string $mode, int $status): void
    {
        $this->startServer();
        $id = $this->prepare();
        $this->outbox('pos', 'sync', compact('id', 'mode'), $status);
        $row = $this->receiver('pos')->query('SELECT * FROM business_commercial_operations')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('reserving', $row['state']);
        $this->assertSame('11111111-1111-4111-8111-111111111111', $row['lease_token']);
        $this->assertNull($row['last_error_status']);
        $this->outbox('pos', 'sync', compact('id'), 409);
        $this->outbox('pos', 'cancel', compact('id'), 409);
        $this->assertSame('reserved', $this->outbox('pos', 'sync', compact('id') + ['mode' => 'stale_lease'])['state']);
        $this->assertSame(1, DB::table('business_usage_reservations')->count());
    }

    public static function staleWorkers(): array
    {
        return [['fenced', 409], ['fenced_error', 503]];
    }

    public function test_source_fingerprint_tenant_and_link_cannot_be_changed(): void
    {
        $this->startServer();
        $id = $this->prepare();
        $this->outbox('pos', 'prepare', ['fingerprint' => hash('sha256', 'changed')], 409);
        $this->outbox('pos', 'prepare', ['identity' => ['document_id' => (string) Str::uuid()]], 409);
        $this->outbox('pos', 'inspect', compact('id') + ['company_id' => 999], 404);
        $this->outbox('pos', 'sync', compact('id') + ['company_id' => 999], 404);
        $this->outbox('pos', 'sync', compact('id'));
        $this->outbox('pos', 'execute', compact('id') + ['fingerprint' => hash('sha256', 'changed')], 409);
        $this->outbox('pos', 'begin', compact('id') + ['fingerprint' => hash('sha256', 'changed')], 409);
        $this->outbox('pos', 'sync', compact('id') + ['mode' => 'changed_link'], 403);
        $this->assertSame(0, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public function test_outer_transaction_and_disabled_or_missing_dependencies_never_admit(): void
    {
        $this->startServer();
        foreach (['outer_transaction' => 409, 'outbox_disabled' => 503, 'disabled' => 503] as $mode => $status) {
            $this->outbox('pos', 'prepare', compact('mode'), $status);
        }
        $id = $this->prepare();
        $this->outbox('pos', 'sync', compact('id') + ['mode' => 'outer_transaction'], 409);
        $this->assertSame('cancelled', $this->outbox('pos', 'cancel', compact('id'))['state']);
        $this->assertSame('cancelled', $this->outbox('pos', 'sync', compact('id'))['state']);
        $this->outbox('pos', 'prepare', ['source' => 'native:2', 'mode' => 'revoked'], 403);
        $this->outbox('pos', 'prepare', ['source' => 'native:3', 'mode' => 'missing_schema'], 503);
        $this->assertSame(0, DB::table('business_usage_documents')->count());
    }

    public function test_command_is_read_only_in_dry_run_and_never_starts_native_or_prepared_work(): void
    {
        $this->startServer();
        $prepared = $this->prepare('pos', 'prepared:1');
        $id = $this->prepare('pos', 'native:1');
        $this->outbox('pos', 'sync', compact('id'));
        $this->outbox('pos', 'execute', compact('id'));
        $db = $this->receiver('pos');
        $before = $db->query('SELECT * FROM business_commercial_operations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $dry = $this->outbox('pos', 'command', ['options' => ['--dry-run' => true], 'mode' => 'unavailable']);
        $this->assertSame(0, $dry['code']);
        $this->assertSame($before, $db->query('SELECT * FROM business_commercial_operations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC));
        $this->assertSame(0, $this->outbox('pos', 'command')['code']);
        $this->assertSame('prepared', $this->outbox('pos', 'inspect', ['id' => $prepared])['state']);
        $this->assertSame('committed', $this->outbox('pos', 'inspect', compact('id'))['state']);
        $other = $this->prepare('pos', 'native:2');
        $this->outbox('pos', 'sync', ['id' => $other]);
        $this->outbox('pos', 'begin', ['id' => $other]);
        $this->assertSame(1, $this->outbox('pos', 'command')['code']);
        $this->assertSame('executing', $this->outbox('pos', 'inspect', ['id' => $other])['state']);
        $this->assertSame(2, $this->outbox('pos', 'command', ['options' => ['--operation' => 'invalid']])['code']);
        $this->assertSame(1, $this->outbox('pos', 'command', ['mode' => 'outbox_disabled'])['code']);
    }

    public function test_missing_cancellation_schema_never_treats_absence_as_success(): void
    {
        $this->startServer();
        $id = $this->prepare();
        $this->outbox('pos', 'sync', compact('id') + ['mode' => 'unavailable'], 503);
        $this->outbox('pos', 'cancel', compact('id'));
        Schema::drop('business_usage_cancellations');
        $this->outbox('pos', 'sync', compact('id'), 503);
        $this->assertSame('release_pending', $this->outbox('pos', 'inspect', compact('id'))['state']);
        $this->assertFalse(Schema::hasTable('business_usage_cancellations'));
    }

    #[DataProvider('changedReceipts')]
    public function test_changed_remote_receipt_blocks_execution_and_preserves_original_evidence(string $field): void
    {
        $this->startServer();
        $id = $this->prepare();
        $receipt = $this->outbox('pos', 'sync', compact('id'))['receipt'];
        if ($field === 'document_id') {
            DB::table('business_usage_documents')->update(['public_id' => (string) Str::uuid()]);
        } else {
            DB::table('business_usage_reservations')->update([$field => now()->addYear()]);
        }
        $this->outbox('pos', 'sync', compact('id'), 409);
        $this->assertSame($receipt, $this->outbox('pos', 'inspect', compact('id'))['receipt']);
        $this->outbox('pos', 'execute', compact('id'), 409);
        $this->outbox('pos', 'begin', compact('id'), 409);
        $this->assertSame(0, (int) $this->receiver('pos')->query('SELECT count(*) FROM fixture_native_results')->fetchColumn());
    }

    public static function changedReceipts(): array
    {
        return [['document_id'], ['period_ends_at'], ['valid_until']];
    }

    public static function receivers(): array
    {
        return [['pos'], ['ecf'], ['bys'], ['cumplimiento']];
    }

    #[DataProvider('receivers')]
    public function test_first_signed_receipt_cannot_expire_before_its_period_begins(string $service): void
    {
        $this->startServer();
        $identity = [];
        $kind = $service === 'cumplimiento' ? 'reference' : 'reserve';
        if ($kind === 'reference') {
            $origin = $this->operation('origin:confirmed');
            $receipt = $this->exchange('pos', 'reserve', $origin);
            $this->exchange('pos', 'commit', ['operation_key' => $origin['operation_key']]);
            $identity = ['document_id' => $receipt['document_id']];
        }
        $id = $this->prepare($service, 'native:future', compact('identity', 'kind'));
        $this->exchange($service, $kind, ['operation_key' => $id, 'document' => ['source_reference' => 'native:future', ...$identity]]);
        DB::table('business_usage_reservations')->where('operation_key', $id)->update(['period_starts_at' => now()->addDay()]);

        $this->outbox($service, 'sync', compact('id'), 503);
        $local = $this->outbox($service, 'inspect', compact('id'));
        $this->assertSame($kind === 'reference' ? 'referencing' : 'reserving', $local['state']);
        $this->assertSame([], $local['receipt']);
        $this->assertSame(503, $local['last_error_status']);
        $this->outbox($service, 'execute', compact('id'), 409);
        $this->outbox($service, 'begin', compact('id'), 409);
        $this->assertSame(0, (int) $this->receiver($service)->query('SELECT count(*) FROM fixture_native_results')->fetchColumn());
    }

    public static function originators(): array
    {
        return [['pos'], ['ecf'], ['bys']];
    }

    #[DataProvider('originators')]
    public function test_future_period_receipt_cannot_start_native_work_before_its_start(string $service): void
    {
        $this->startServer();
        $id = $this->prepare($service, 'native:future-period');
        $this->exchange($service, 'reserve', ['operation_key' => $id, 'document' => ['source_reference' => 'native:future-period']]);
        DB::table('business_usage_reservations')->where('operation_key', $id)->update([
            'period_starts_at' => now()->addDay(), 'valid_until' => now()->addDay()->addSeconds(120),
        ]);
        $receipt = $this->outbox($service, 'sync', compact('id'))['receipt'];
        $this->outbox($service, 'execute', compact('id'), 409);
        $this->outbox($service, 'begin', compact('id'), 409);
        $local = $this->outbox($service, 'inspect', compact('id'));
        $this->assertSame('reserved', $local['state']);
        $this->assertSame($receipt, $local['receipt']);
        $this->assertSame(0, (int) $this->receiver($service)->query('SELECT count(*) FROM fixture_native_results')->fetchColumn());
        $this->assertSame('reserved', DB::table('business_usage_reservations')->where('operation_key', $id)->sole()->state);
    }

    public function test_revocation_or_unverified_reservation_never_grants_native_execution(): void
    {
        $this->startServer();
        foreach (['corrupted', 'wrong_request'] as $mode) {
            $id = $this->prepare('pos', $mode);
            $this->outbox('pos', 'sync', compact('id', 'mode'), 503);
            $this->outbox('pos', 'execute', compact('id'), 409);
        }
        $id = $this->prepare('pos', 'valid');
        $this->outbox('pos', 'sync', compact('id'));
        $this->outbox('pos', 'execute', compact('id') + ['mode' => 'revoked'], 403);
        $this->assertSame('reserved', $this->outbox('pos', 'inspect', compact('id'))['state']);
        $this->assertSame(0, (int) $this->receiver('pos')->query('SELECT count(*) FROM fixture_native_results')->fetchColumn());
    }

    public function test_shared_outbox_and_migrations_are_identical_in_four_repositories(): void
    {
        foreach (['app/Services/BusinessCommercial/CommercialOutbox.php', 'app/Console/Commands/ReconcileBusinessCommercial.php',
            'config/business_commercial_outbox.php', 'database/migrations/2026_11_07_000011_create_business_commercial_outbox.php'] as $file) {
            $hash = null;
            foreach (['ecflaudaapi', 'poslaudaapi', 'byslaudaapi', 'cumplimientolaudaapi'] as $repo) {
                $current = hash_file('sha256', dirname(base_path()).'/'.$repo.'/'.$file);
                $hash ??= $current;
                $this->assertSame($hash, $current);
            }
        }
    }
}
