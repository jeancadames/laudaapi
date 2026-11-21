<?php

namespace Tests\Feature\BusinessCommercial;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\PosFiscalHandoffTestCase;

final class RecurringFiscalEmissionTest extends PosFiscalHandoffTestCase
{
    private function startRecurring(): void
    {
        $this->startServer(true);
        touch($this->runtime.'/direct-fiscal-enabled');
    }

    private function fixture(string $name, array $input): array
    {
        $process = new Process(['php', base_path('tests/Fixtures/BusinessCommercial/'.$name.'.php'), $this->runtime]);
        $process->setInput(json_encode($input, JSON_THROW_ON_ERROR));
        $process->setTimeout(60)->mustRun();

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function recurring(array $input, int $status = 302): array
    {
        $result = $this->fixture('recurring-request', $input);
        if (isset($result['status'])) {
            $this->assertSame($status, $result['status'], json_encode($result).' '.$this->logs());
        } else {
            $this->assertSame(0, $result['exit'], $this->logs());
            $this->assertTrue($result['context_clear']);
            $this->assertTrue($result['unattended']);
        }

        return $result;
    }

    private function manual(string $draft, string $action, array $payload = []): array
    {
        $result = $this->fixture('facil-request', compact('draft', 'action', 'payload'));
        $this->assertSame(200, $result['status'], json_encode($result).' '.$this->logs());

        return $result['body'];
    }

    private function documents(int $count): void
    {
        $this->assertSame($count, (int) $this->receiver('ecf')->query('SELECT count(*) FROM ecf_documents')->fetchColumn());
        $this->assertSame($count, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public static function invoiceTypes(): array
    {
        return [['e31'], ['e32']];
    }

    #[DataProvider('invoiceTypes')]
    public function test_last_period_emits_once_unattended_without_local_debit(string $type): void
    {
        $this->startRecurring();
        $this->fixture('ecf-command', ['action' => 'exhausted_plan']);
        $series = $this->recurring(['action' => 'store', 'last_period' => true])['series'];
        if ($type === 'e32') {
            $db = $this->receiver('ecf');
            $payload = json_decode($db->query('SELECT payload FROM recurring_invoices')->fetchColumn(), true);
            $payload['tipo_ecf'] = 'e32';
            $db->prepare('UPDATE recurring_invoices SET payload = ?')->execute([json_encode($payload)]);
        }
        $prepared = $this->recurring(['action' => 'prepare', 'series' => $series]);
        $this->assertSame('ended', $prepared['series'][0]['status']);
        $this->assertNull($prepared['series'][0]['next_date']);
        $this->documents(0);
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
        $result = $this->recurring(['action' => 'command', 'after_reserve' => 'reorder_json']);
        $this->assertSame('submitted', $result['runs'][0]['status'], $this->logs());
        $this->assertSame('ended', $result['series'][0]['status']);
        $boundary = file_get_contents($this->runtime.'/fiscal-boundary.ndjson');
        $again = $this->recurring(['action' => 'command']);
        $this->assertCount(1, $again['runs']);
        $this->assertSame($boundary, file_get_contents($this->runtime.'/fiscal-boundary.ndjson'));
        $this->documents(1);
        $this->assertSame($type.'0000000001', $this->receiver('ecf')->query('SELECT encf FROM ecf_documents')->fetchColumn());
        $this->assertSame(1, (int) $this->receiver('ecf')->query('SELECT ecf_count FROM usage_records')->fetchColumn());
        $this->assertSame(1, DB::table('business_usage_aliases')->where('kind', 'fiscal')->count());
    }

    public function test_review_mode_keeps_manual_confirmation_and_future_template_edits_keep_prepared_period(): void
    {
        $this->startRecurring();
        $first = $this->recurring(['action' => 'store', 'payload' => ['mode' => 'review']])['series'];
        $state = $this->recurring(['action' => 'command']);
        $this->assertSame('prepared', $state['runs'][0]['status']);
        $this->documents(0);
        $draft = $state['runs'][0]['draft_id'];
        $token = $this->manual($draft, 'review', ['version' => 1])['token'];
        $this->assertSame('completed', $this->manual($draft, 'confirm', compact('token'))['receipt']['request_status']);
        $second = $this->recurring(['action' => 'store'])['series'];
        $state = $this->recurring(['action' => 'prepare', 'series' => $second]);
        $original = collect($state['series'])->firstWhere('id', $second);
        $payload = $original['payload'];
        $payload['items'][0]['precio_unitario'] = '999';
        $this->recurring(['action' => 'update', 'series' => $second, 'payload' => ['payload' => $payload]]);
        $state = $this->recurring(['action' => 'command']);
        $this->assertSame('submitted', collect($state['runs'])->firstWhere('recurring_invoice_id', $second)['status']);
        $this->assertSame($original['next_date'], collect($state['series'])->firstWhere('id', $second)['next_date']);
        $this->documents(2);
        $this->assertEquals([11800, 11800], $this->receiver('ecf')->query('SELECT monto_total FROM ecf_documents ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN));
    }

    public static function stops(): array
    {
        return [['pause'], ['end']];
    }

    #[DataProvider('stops')]
    public function test_pause_or_end_prevents_prepared_period_from_emitting(string $action): void
    {
        $this->startRecurring();
        $series = $this->recurring(['action' => 'store'])['series'];
        $this->recurring(['action' => 'prepare', 'series' => $series]);
        $this->recurring(['action' => 'action', 'series' => $series, 'payload' => ['action' => $action]]);
        $state = $this->recurring(['action' => 'command']);
        $this->assertSame('prepared', $state['runs'][0]['status']);
        $this->assertSame('review', $state['runs'][0]['mode']);
        $this->documents(0);
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
    }

    public function test_pause_after_claim_keeps_native_processing_period_semantics(): void
    {
        $this->startRecurring();
        $this->recurring(['action' => 'store']);
        $state = $this->recurring(['action' => 'command', 'after_reserve' => 'pause_series']);
        $this->assertSame('submitted', $state['runs'][0]['status']);
        $this->assertSame('paused', $state['series'][0]['status']);
        $this->documents(1);
    }

    public function test_native_consent_and_disabled_business_connection_stop_automatic_emission(): void
    {
        $this->startRecurring();
        $this->recurring(['action' => 'store', 'payload' => ['automatic_consent' => false]], 422);
        $this->assertSame(0, (int) $this->receiver('ecf')->query('SELECT count(*) FROM recurring_invoices')->fetchColumn());
        $this->recurring(['action' => 'store']);
        $state = $this->recurring(['action' => 'command', 'enabled' => false]);
        $this->assertSame('failed', $state['runs'][0]['status']);
        $this->assertSame('paused', $state['series'][0]['status']);
        $this->assertSame(0, (int) $this->receiver('ecf')->query('SELECT count(*) FROM ecf_api_requests')->fetchColumn());
        $this->documents(0);
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
    }

    public static function drifts(): array
    {
        return [['draft', ''], ['run_owner', ''], ['run_date', ''], ['run_status', ''],
            ['', 'direct_revoke_link'], ['', 'direct_change_environment'], ['', 'facil_revoke_user']];
    }

    #[DataProvider('drifts')]
    public function test_changed_authority_or_payload_after_reservation_blocks_creation(string $change, string $mode): void
    {
        $this->startRecurring();
        $this->recurring(['action' => 'store']);
        file_put_contents($this->runtime.'/fiscal-mode', $mode);
        $state = $this->recurring(['action' => 'command', 'after_reserve' => $change]);
        $this->assertSame('failed', $state['runs'][0]['status']);
        $this->assertSame('paused', $state['series'][0]['status']);
        $this->documents(0);
        $this->assertSame(1, (int) $this->receiver('ecf')->query("SELECT siguiente FROM ncf_sequences WHERE tipo_ncf = 'e31'")->fetchColumn());
        $this->assertSame($mode === 'direct_revoke_link' ? 1 : 0, DB::table('business_usage_reservations')->where('state', 'reserved')->count());
    }

    public static function losses(): array
    {
        return [['lost_reserve', 0, 'failed'], ['lost_commit', 1, 'completed'],
            ['lost_reference', 1, 'completed'], ['lost_send', 1, 'uncertain'], ['facil_snapshot_after_receipt', 0, 'failed']];
    }

    #[DataProvider('losses')]
    public function test_response_losses_preserve_native_pause_and_never_resend(string $mode, int $count, string $status): void
    {
        $this->startRecurring();
        $this->recurring(['action' => 'store']);
        file_put_contents($this->runtime.'/fiscal-mode', $mode);
        $state = $this->recurring(['action' => 'command']);
        $this->assertSame($status === 'completed' ? 'submitted' : 'failed', $state['runs'][0]['status']);
        $this->assertSame($status, $this->receiver('ecf')->query('SELECT status FROM ecf_api_requests')->fetchColumn());
        $draft = $state['runs'][0]['draft_id'];
        $boundary = @file_get_contents($this->runtime.'/fiscal-boundary.ndjson');
        file_put_contents($this->runtime.'/fiscal-mode', '');
        $this->fixture('ecf-command', ['action' => 'direct_reconcile', 'due' => true]);
        $this->recurring(['action' => 'command']);
        $this->assertSame($boundary, @file_get_contents($this->runtime.'/fiscal-boundary.ndjson'));
        $this->documents($count);
        if ($count === 0) {
            $receipt = $this->manual($draft, 'status')['receipt'];
            if ($receipt['recover_allowed']) {
                $this->manual($draft, 'recover');
            }
            $this->assertTrue($this->manual($draft, 'status')['receipt']['retry_allowed']);
            $token = $this->manual($draft, 'review', ['version' => 1])['token'];
            $this->assertSame('completed', $this->manual($draft, 'confirm', compact('token'))['receipt']['request_status']);
            $this->documents(1);
        } else {
            $receipt = $this->manual($draft, 'status')['receipt'];
            $this->assertFalse($receipt['retry_allowed']);
            $this->assertTrue($receipt['recover_allowed']);
            $recovery = $this->fixture('facil-request', ['draft' => $draft, 'action' => 'recover']);
            // A retained document can be polled or sent for manual review, never issued again.
            $this->assertContains($recovery['status'], [202, 409], json_encode($recovery));
            $retained = $this->manual($draft, 'status')['receipt'];
            $this->assertSame($receipt['ecf_document_id'], $retained['ecf_document_id']);
            $this->assertSame($receipt['encf'], $retained['encf']);
            $this->assertSame(substr_count((string) $boundary, '/recepcion'),
                substr_count((string) file_get_contents($this->runtime.'/fiscal-boundary.ndjson'), '/recepcion'));
            $this->documents(1);
        }
    }

    public function test_existing_retryable_receipt_requires_manual_confirmation_and_is_not_adopted_by_worker(): void
    {
        $this->startRecurring();
        $series = $this->recurring(['action' => 'store'])['series'];
        $state = $this->recurring(['action' => 'prepare', 'series' => $series]);
        $draft = $state['runs'][0]['draft_id'];
        $token = $this->manual($draft, 'review', ['version' => 1])['token'];
        file_put_contents($this->runtime.'/fiscal-mode', 'lost_reserve');
        $this->assertSame('failed', $this->manual($draft, 'confirm', compact('token'))['receipt']['request_status']);
        file_put_contents($this->runtime.'/fiscal-mode', '');
        $this->manual($draft, 'recover');
        $this->assertTrue($this->manual($draft, 'status')['receipt']['retry_allowed']);
        $state = $this->recurring(['action' => 'command']);
        $this->assertSame('failed', $state['runs'][0]['status']);
        $this->assertSame('paused', $state['series'][0]['status']);
        $this->documents(0);
        $this->assertSame(1, (int) $this->receiver('ecf')->query('SELECT generation FROM business_ecf_emissions')->fetchColumn());
        $token = $this->manual($draft, 'review', ['version' => 1])['token'];
        $this->assertSame('completed', $this->manual($draft, 'confirm', compact('token'))['receipt']['request_status']);
        $this->documents(1);
    }

    public function test_exhausted_business_quota_preserves_draft_and_does_not_retry_when_capacity_returns(): void
    {
        $this->startRecurring();
        $this->recurring(['action' => 'store']);
        $snapshot = $this->contract->snapshot;
        $snapshot['capacity']['documents'] = 0;
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $state = $this->recurring(['action' => 'command']);
        $this->assertSame('failed', $state['runs'][0]['status']);
        $this->assertSame('paused', $state['series'][0]['status']);
        $this->assertSame(429, (int) $this->receiver('ecf')->query('SELECT response_status FROM ecf_api_requests')->fetchColumn());
        $this->documents(0);
        $snapshot['capacity']['documents'] = 5;
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $again = $this->recurring(['action' => 'command']);
        $this->assertSame($state['runs'], $again['runs']);
        $this->documents(0);
        $draft = $state['runs'][0]['draft_id'];
        $this->manual($draft, 'recover');
        $token = $this->manual($draft, 'review', ['version' => 1])['token'];
        $this->assertSame('completed', $this->manual($draft, 'confirm', compact('token'))['receipt']['request_status']);
        $this->documents(1);
    }

    public function test_standalone_recurring_keeps_local_plan_with_business_flag_disabled(): void
    {
        DB::table('business_contract_items')->where('identity_link_id', DB::table('business_identity_links')->where('service', 'ecf')->value('id'))->delete();
        DB::table('business_identity_links')->where('service', 'ecf')->update(['central_company_id' => 2, 'central_subscriber_id' => 2]);
        $this->startRecurring();
        $this->fixture('ecf-command', ['action' => 'local_plan']);
        $this->recurring(['action' => 'store']);
        $state = $this->recurring(['action' => 'command', 'enabled' => false]);
        $this->assertSame('submitted', $state['runs'][0]['status'], $this->logs());
        $this->assertSame(1, (int) $this->receiver('ecf')->query('SELECT ecf_count FROM usage_records')->fetchColumn());
        $this->assertSame(0, DB::table('business_usage_documents')->count());
        $this->assertSame(0, (int) $this->receiver('ecf')->query('SELECT count(*) FROM business_ecf_emissions')->fetchColumn());
    }

    public function test_failed_period_does_not_leak_context_to_next_period_in_same_command(): void
    {
        $this->startRecurring();
        foreach (range(1, 2) as $unused) {
            $series = $this->recurring(['action' => 'store'])['series'];
            $this->recurring(['action' => 'prepare', 'series' => $series]);
        }
        $db = $this->receiver('ecf');
        $draft = $db->query('SELECT draft_id FROM recurring_invoice_runs ORDER BY id LIMIT 1')->fetchColumn();
        $db->prepare('UPDATE ecf_drafts SET payload_hash = ? WHERE id = ?')->execute([str_repeat('f', 64), $draft]);
        $state = $this->recurring(['action' => 'command']);
        $this->assertSame(['failed', 'submitted'], array_column($state['runs'], 'status'));
        $this->documents(1);
    }

    public function test_late_worker_cannot_replace_manual_recovery_or_emit_a_second_document(): void
    {
        $this->startRecurring();
        $series = $this->recurring(['action' => 'store'])['series'];
        $state = $this->recurring(['action' => 'prepare', 'series' => $series]);
        $draft = $state['runs'][0]['draft_id'];
        file_put_contents($this->runtime.'/fiscal-mode', 'facil_pause_before_map');
        $worker = new Process(['php', base_path('tests/Fixtures/BusinessCommercial/recurring-request.php'), $this->runtime]);
        $worker->setInput(json_encode(['action' => 'command']));
        $worker->setTimeout(60)->start();
        try {
            $deadline = microtime(true) + 30;
            while (! is_file($this->runtime.'/facil-paused')) {
                if (! $worker->isRunning() || microtime(true) > $deadline) {
                    $this->fail('Recurring worker did not reach interruption: '.$worker->getOutput().$worker->getErrorOutput().$this->logs());
                }
                usleep(10000);
            }
            $this->receiver('ecf')->exec("UPDATE ecf_api_requests SET updated_at = datetime('now', '-20 minutes')");
            file_put_contents($this->runtime.'/fiscal-mode', '');
            $this->manual($draft, 'recover');
            $token = $this->manual($draft, 'review', ['version' => 1])['token'];
            $this->assertSame('completed', $this->manual($draft, 'confirm', compact('token'))['receipt']['request_status']);
            $boundary = file_get_contents($this->runtime.'/fiscal-boundary.ndjson');
            touch($this->runtime.'/facil-resume');
            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $state = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertTrue($state['context_clear']);
            $this->assertSame('submitted', $state['runs'][0]['status']);
            $this->assertSame('completed', $this->manual($draft, 'status')['receipt']['request_status']);
            $this->assertSame($boundary, file_get_contents($this->runtime.'/fiscal-boundary.ndjson'));
            $this->documents(1);
            $this->assertSame(1, (int) $this->receiver('ecf')->query('SELECT count(*) FROM business_ecf_emissions')->fetchColumn());
        } finally {
            touch($this->runtime.'/facil-resume');
            $worker->stop(1);
        }
    }
}
