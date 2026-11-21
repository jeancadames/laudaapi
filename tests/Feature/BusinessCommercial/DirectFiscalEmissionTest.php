<?php

namespace Tests\Feature\BusinessCommercial;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\PosFiscalHandoffTestCase;

final class DirectFiscalEmissionTest extends PosFiscalHandoffTestCase
{
    private function startDirect(): void
    {
        $this->startServer(true);
        touch($this->runtime.'/direct-fiscal-enabled');
    }

    private function payload(string $type = 'e31'): array
    {
        return ['tipo_ecf' => $type, 'fecha_emision' => now()->format('Y-m-d'), 'tipo_pago' => 1, 'tipo_ingresos' => '01',
            'receptor' => ['rnc' => '131880681', 'razon_social' => 'Comprador sintético'],
            'items' => [['descripcion' => 'Producto sintético', 'cantidad' => '1', 'precio_unitario' => '100', 'indicador_itbis' => 1, 'indicador_bien_servicio' => 1]]];
    }

    private function command(array $input): array
    {
        $process = new Process(['php', base_path('tests/Fixtures/BusinessCommercial/ecf-command.php'), $this->runtime]);
        $process->setInput(json_encode($input, JSON_THROW_ON_ERROR));
        $process->setTimeout(45)->mustRun();

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function assertDocuments(int $count): void
    {
        $this->assertSame($count, (int) $this->receiver('ecf')->query('SELECT count(*) FROM ecf_documents')->fetchColumn());
        $this->assertSame($count, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public static function types(): array
    {
        return [['e31'], ['e32']];
    }

    #[DataProvider('types')]
    public function test_direct_api_validates_without_consuming_and_emits_once_without_debiting_local_plan(string $type): void
    {
        $this->startDirect();
        $this->command(['action' => 'exhausted_plan']);
        $payload = $this->payload($type);
        $this->fiscal('ecf/validar', $payload, status: 200);
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
        $first = $this->fiscal('ecf', $payload, 'direct:one');
        $this->assertSame($type.'0000000001', $first['encf']);
        $again = $this->fiscal('ecf', $payload, 'direct:one');
        $this->assertSame($first['encf'], $again['encf']);
        $this->assertDocuments(1);
        $this->assertSame(1, (int) $this->receiver('ecf')->query('SELECT ecf_count FROM usage_records')->fetchColumn());
        $this->assertSame(1, (int) $this->receiver('ecf')->query('SELECT count(*) FROM business_ecf_emissions WHERE confirmed_at IS NOT NULL')->fetchColumn());
        $this->assertSame(1, DB::table('business_usage_aliases')->where('kind', 'fiscal')->count());
        $this->assertSame(0, DB::table('business_fiscal_handoffs')->count());
        $payload['items'][0]['cantidad'] = '2';
        $this->fiscal('ecf', $payload, 'direct:one', status: 409);
        $this->assertDocuments(1);
    }

    public static function adjustments(): array
    {
        return [['nota-credito', 'e34'], ['nota-debito', 'e33']];
    }

    #[DataProvider('adjustments')]
    public function test_native_adjustment_is_a_separate_consumption_and_retains_original(string $operation, string $type): void
    {
        $this->startDirect();
        $first = $this->fiscal('ecf', $this->payload(), 'direct:original');
        $this->receiver('ecf')->exec("UPDATE ecf_documents SET status = 'aceptado'");
        $line = $this->payload()['items'][0];
        $line['precio_unitario'] = '10';
        $payload = ['codigo_mod' => 3, 'motivo' => 'Ajuste sintético', 'items' => [$line]];
        $note = $this->fiscal('ecf/'.$first['encf'].'/'.$operation, $payload, 'direct:note');
        $this->assertSame($type.'0000000001', $note['ajuste']['encf']);
        $again = $this->fiscal('ecf/'.$first['encf'].'/'.$operation, $payload, 'direct:note');
        $this->assertSame($note['ajuste']['encf'], $again['ajuste']['encf']);
        $this->assertDocuments(2);
        $this->assertSame(2, DB::table('business_usage_aliases')->where('kind', 'fiscal')->count());
        $this->assertSame(2, (int) $this->receiver('ecf')->query('SELECT count(DISTINCT central_document_id) FROM business_ecf_emissions')->fetchColumn());
    }

    public function test_quota_rejection_keeps_sequence_and_allows_same_receipt_after_recovery(): void
    {
        $snapshot = $this->contract->snapshot;
        $snapshot['capacity']['documents'] = 0;
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->startDirect();
        $this->fiscal('ecf', $this->payload(), 'direct:quota', status: 429);
        $this->assertDocuments(0);
        $this->assertSame(1, (int) $this->receiver('ecf')->query("SELECT siguiente FROM ncf_sequences WHERE tipo_ncf = 'e31'")->fetchColumn());
        $snapshot['capacity']['documents'] = 5;
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->fiscal('ecf/solicitudes/direct%3Aquota/reconciliar', status: 200);
        $this->fiscal('ecf', $this->payload(), 'direct:quota');
        $this->assertDocuments(1);
        $this->assertSame(2, (int) $this->receiver('ecf')->query('SELECT generation FROM business_ecf_emissions')->fetchColumn());
    }

    public static function lostResponses(): array
    {
        return [['lost_reserve', 503], ['lost_commit', 202], ['lost_reference', 202], ['lost_send', 500]];
    }

    #[DataProvider('lostResponses')]
    public function test_lost_responses_recover_without_second_fiscal_document(string $mode, int $status): void
    {
        $this->startDirect();
        file_put_contents($this->runtime.'/fiscal-mode', $mode);
        $this->fiscal('ecf', $this->payload(), 'direct:recover', status: $status);
        file_put_contents($this->runtime.'/fiscal-mode', '');
        if ($mode === 'lost_reserve') {
            $this->assertDocuments(0);
            $this->fiscal('ecf/solicitudes/direct%3Arecover/reconciliar', status: 200);
            $this->fiscal('ecf', $this->payload(), 'direct:recover');
        } elseif ($mode === 'lost_send') {
            $recovery = $this->fiscal('ecf/solicitudes/direct%3Arecover/reconciliar', status: 409);
            $this->assertTrue($recovery['document_created']);
            $this->assertFalse($recovery['retry_allowed']);
            $this->fiscal('ecf', $this->payload(), 'direct:recover', status: 500);
        }
        $boundary = is_file($this->runtime.'/fiscal-boundary.ndjson') ? file_get_contents($this->runtime.'/fiscal-boundary.ndjson') : '';
        $this->assertSame(0, $this->command(['action' => 'direct_reconcile', 'due' => true])['exit']);
        $this->assertSame($boundary, file_get_contents($this->runtime.'/fiscal-boundary.ndjson'));
        $this->assertDocuments(1);
        $this->assertSame(1, DB::table('business_usage_aliases')->where('kind', 'fiscal')->count());
        $this->assertSame(2, (int) $this->receiver('ecf')->query("SELECT siguiente FROM ncf_sequences WHERE tipo_ncf = 'e31'")->fetchColumn());
    }

    public function test_failed_native_transaction_rolls_back_sequence_and_releases_capacity(): void
    {
        $this->startDirect();
        $db = $this->receiver('ecf');
        $db->exec("CREATE TRIGGER fail_direct_map BEFORE UPDATE OF ecf_document_id ON business_ecf_emissions WHEN NEW.ecf_document_id IS NOT NULL BEGIN SELECT RAISE(ABORT, 'Synthetic mapping failure'); END");
        $this->fiscal('ecf', $this->payload(), 'direct:rollback', status: 500);
        $this->assertDocuments(0);
        $this->assertSame(1, (int) $db->query("SELECT siguiente FROM ncf_sequences WHERE tipo_ncf = 'e31'")->fetchColumn());
        $this->assertSame(0, DB::table('business_usage_reservations')->where('state', 'reserved')->count());
        $db->exec('DROP TRIGGER fail_direct_map');
        $this->fiscal('ecf/solicitudes/direct%3Arollback/reconciliar', status: 200);
        $this->fiscal('ecf', $this->payload(), 'direct:rollback');
        $this->assertDocuments(1);
    }

    public static function revokedContexts(): array
    {
        return [['direct_revoke_credential'], ['direct_revoke_link'], ['direct_change_environment']];
    }

    #[DataProvider('revokedContexts')]
    public function test_context_changes_after_reserve_stop_before_creation(string $mode): void
    {
        $this->startDirect();
        file_put_contents($this->runtime.'/fiscal-mode', $mode);
        $this->fiscal('ecf', $this->payload(), 'direct:revoked', status: 403);
        $this->assertDocuments(0);
        $this->assertSame(1, (int) $this->receiver('ecf')->query("SELECT siguiente FROM ncf_sequences WHERE tipo_ncf = 'e31'")->fetchColumn());
    }

    public function test_disabled_direct_flag_never_falls_back_to_local_plan_and_preserves_replay(): void
    {
        $this->startDirect();
        $this->command(['action' => 'local_plan']);
        $this->fiscal('ecf', $this->payload(), 'direct:before');
        file_put_contents($this->runtime.'/fiscal-mode', 'direct_disabled');
        $this->fiscal('ecf', $this->payload(), 'direct:before');
        $this->fiscal('ecf', $this->payload(), 'direct:after', status: 503);
        $this->assertDocuments(1);
        $this->assertSame(0, (int) $this->receiver('ecf')->query('SELECT ecf_count FROM usage_records')->fetchColumn());
    }

    public function test_verified_standalone_keeps_local_plan_without_business_consumption(): void
    {
        DB::table('business_contract_items')->where('identity_link_id', DB::table('business_identity_links')->where('service', 'ecf')->value('id'))->delete();
        DB::table('business_identity_links')->where('service', 'ecf')->update(['central_company_id' => 2, 'central_subscriber_id' => 2]);
        $this->startDirect();
        $this->command(['action' => 'local_plan']);
        $this->fiscal('ecf', $this->payload(), 'standalone:one');
        $this->assertSame(0, DB::table('business_usage_documents')->count());
        $this->assertSame(1, (int) $this->receiver('ecf')->query('SELECT ecf_count FROM usage_records')->fetchColumn());
        $this->assertSame(0, (int) $this->receiver('ecf')->query('SELECT count(*) FROM business_ecf_emissions')->fetchColumn());
    }

    public function test_adjustment_of_a_pos_original_keeps_its_consumption_and_adds_only_the_note(): void
    {
        $this->startDirect();
        $original = $this->pos()['fiscal'][0]['encf'];
        $this->receiver('ecf')->exec("UPDATE ecf_documents SET status = 'aceptado'");
        $line = $this->payload()['items'][0];
        $line['precio_unitario'] = '10';
        $this->fiscal('ecf/'.$original.'/nota-credito', ['codigo_mod' => 3, 'motivo' => 'Ajuste sintético', 'items' => [$line]], 'direct:pos-note');
        $this->assertDocuments(2);
        $this->assertSame(1, DB::table('business_fiscal_handoffs')->count());
        $this->assertSame(1, (int) $this->receiver('ecf')->query('SELECT count(*) FROM business_ecf_emissions')->fetchColumn());
        $this->assertSame(2, DB::table('business_usage_aliases')->where('kind', 'fiscal')->count());
    }

    public function test_released_adjustment_can_rederive_context_after_another_note(): void
    {
        $this->startDirect();
        $original = $this->fiscal('ecf', $this->payload(), 'direct:original')['encf'];
        $db = $this->receiver('ecf');
        $db->exec("UPDATE ecf_documents SET status = 'aceptado'");
        $db->exec("CREATE TRIGGER fail_direct_map BEFORE UPDATE OF ecf_document_id ON business_ecf_emissions WHEN NEW.ecf_document_id IS NOT NULL BEGIN SELECT RAISE(ABORT, 'Synthetic mapping failure'); END");
        $line = $this->payload()['items'][0];
        $line['precio_unitario'] = '10';
        $payload = ['codigo_mod' => 3, 'motivo' => 'Ajuste sintético', 'items' => [$line]];
        $this->fiscal('ecf/'.$original.'/nota-credito', $payload, 'direct:note-first', status: 500);
        $db->exec('DROP TRIGGER fail_direct_map');
        $this->fiscal('ecf/'.$original.'/nota-credito', $payload, 'direct:note-second');
        $this->fiscal('ecf/solicitudes/direct%3Anote-first/reconciliar', status: 200);
        $this->fiscal('ecf/'.$original.'/nota-credito', $payload, 'direct:note-first');
        $this->assertDocuments(3);
        $this->assertSame(3, DB::table('business_usage_aliases')->where('kind', 'fiscal')->count());
    }

    public function test_json_key_order_is_stable_but_fiscal_changes_block_reconciliation(): void
    {
        $this->startDirect();
        file_put_contents($this->runtime.'/fiscal-mode', 'lost_commit');
        $this->fiscal('ecf', $this->payload(), 'direct:json');
        file_put_contents($this->runtime.'/fiscal-mode', '');
        $db = $this->receiver('ecf');
        $snapshot = json_decode($db->query('SELECT fiscal_snapshot FROM ecf_documents')->fetchColumn(), true);
        $reverse = function (array $value) use (&$reverse): array {
            foreach ($value as $key => $part) {
                if (is_array($part)) {
                    $value[$key] = $reverse($part);
                }
            }

            return array_is_list($value) ? $value : array_reverse($value, true);
        };
        $update = $db->prepare('UPDATE ecf_documents SET fiscal_snapshot = ?');
        $update->execute([json_encode($reverse($snapshot))]);
        $this->assertSame(0, $this->command(['action' => 'direct_reconcile', 'due' => true])['exit']);
        $db->exec('UPDATE business_ecf_emissions SET confirmed_at = NULL');
        foreach (["fecha_emision = '2000-01-01'", "tipo_pago = '2'"] as $change) {
            $db->exec('UPDATE ecf_documents SET '.$change);
            $this->assertSame(1, $this->command(['action' => 'direct_reconcile'])['exit']);
            $restore = $db->prepare('UPDATE ecf_documents SET fecha_emision = ?, tipo_pago = 1');
            $restore->execute([now()->format('Y-m-d')]);
        }
        $snapshot['ret_isr_cents'] = 999;
        $update->execute([json_encode($snapshot)]);
        $this->assertSame(1, $this->command(['action' => 'direct_reconcile'])['exit']);
        $snapshot['ret_isr_cents'] = 0;
        $update->execute([json_encode($snapshot)]);
        $db->exec('UPDATE ecf_document_items SET cantidad = 2');
        $this->assertSame(1, $this->command(['action' => 'direct_reconcile'])['exit']);
        $this->assertDocuments(1);
    }

    public function test_late_cancellation_of_old_generation_preserves_the_next_reservation(): void
    {
        $this->startDirect();
        $db = $this->receiver('ecf');
        $db->exec("CREATE TRIGGER fail_direct_map BEFORE UPDATE OF ecf_document_id ON business_ecf_emissions WHEN NEW.ecf_document_id IS NOT NULL BEGIN SELECT RAISE(ABORT, 'Synthetic mapping failure'); END");
        file_put_contents($this->runtime.'/fiscal-mode', 'lost_cancel');
        $this->fiscal('ecf', $this->payload(), 'direct:generations', status: 500);
        file_put_contents($this->runtime.'/fiscal-mode', '');
        $db->exec('DROP TRIGGER fail_direct_map');
        $old = $db->quote($db->query('SELECT commercial_operation_id FROM business_ecf_emissions')->fetchColumn());
        $next = $this->command(['action' => 'prepare_next_generation'])['operation'];
        $new = $db->quote($next);
        // Deterministic interleaving: the next request becomes active before the old releaser resumes.
        $db->exec("CREATE TRIGGER next_generation AFTER UPDATE OF state ON business_commercial_operations WHEN NEW.id = $old AND NEW.state = 'released' BEGIN
            UPDATE business_ecf_emissions SET generation = 2, commercial_operation_id = $new, released_at = NULL;
            UPDATE ecf_api_requests SET status = 'processing', phase = 'reserved'; END");
        $this->assertSame(0, $this->command(['action' => 'direct_reconcile', 'due' => true])['exit']);
        $map = $db->query('SELECT generation, commercial_operation_id, released_at FROM business_ecf_emissions')->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame(2, (int) $map['generation']);
        $this->assertSame($next, $map['commercial_operation_id']);
        $this->assertNull($map['released_at']);
        $this->assertSame('reserved', $db->query('SELECT state FROM business_commercial_operations WHERE id = '.$new)->fetchColumn());
        $this->assertDocuments(0);
    }

    public static function interruptedWorkers(): array
    {
        return [['stopped'], ['after_complete'], ['after_claim'], ['reconcile_failure'], ['after_release']];
    }

    #[DataProvider('interruptedWorkers')]
    public function test_interrupted_worker_recovers_same_request_and_old_response_cannot_overwrite_it(string $mode): void
    {
        $this->startDirect();
        $pause = in_array($mode, ['after_complete', 'after_claim'], true);
        $process = new Process(['php', base_path('tests/Fixtures/BusinessCommercial/ecf-interrupted-request.php'), $this->runtime]);
        $process->setInput(json_encode(['payload' => $this->payload(), 'reference' => 'direct:process', 'pause' => $pause, 'stop_after_release' => $mode === 'after_release']));
        $process->setTimeout(60);
        try {
            if ($pause) {
                $process->start();
                $deadline = microtime(true) + 15;
                while (! is_file($this->runtime.'/emission-paused')) {
                    if (! $process->isRunning() || microtime(true) > $deadline) {
                        $this->fail('Worker did not pause: '.$process->getOutput().$process->getErrorOutput());
                    }
                    usleep(10000);
                }
            } else {
                $this->assertSame($mode === 'after_release' ? 75 : 73, $process->run(), $process->getOutput().$process->getErrorOutput());
            }
            $this->assertDocuments(0);
            $this->assertSame($mode === 'after_release' ? 0 : 1, DB::table('business_usage_reservations')->where('state', 'reserved')->count());
            $this->assertSame($mode === 'after_release' ? 0 : 1, $this->command(['action' => 'direct_reconcile'])['exit']);
            $db = $this->receiver('ecf');
            $db->exec("UPDATE ecf_api_requests SET updated_at = datetime('now', '-11 minutes')");
            $db->exec("UPDATE business_commercial_operations SET leased_until = datetime('now', '-1 minute')");
            if ($mode === 'reconcile_failure') {
                $db->exec("CREATE TRIGGER fail_recovery BEFORE UPDATE OF status ON ecf_api_requests WHEN NEW.status = 'retryable' BEGIN SELECT RAISE(ABORT, 'Synthetic receipt recovery failure'); END");
                $this->assertSame(1, $this->command(['action' => 'direct_reconcile', 'due' => true])['exit']);
                $this->assertNull($db->query('SELECT released_at FROM business_ecf_emissions')->fetchColumn());
                $this->assertSame('processing', $db->query('SELECT status FROM ecf_api_requests')->fetchColumn());
                $db->exec('DROP TRIGGER fail_recovery');
            }
            $this->assertSame(0, $this->command(['action' => 'direct_reconcile', 'due' => true])['exit']);
            $this->assertSame('retryable', $db->query('SELECT status FROM ecf_api_requests')->fetchColumn());
            $this->assertSame(0, DB::table('business_usage_reservations')->where('state', 'reserved')->count());
            if ($mode === 'after_claim') {
                file_put_contents($this->runtime.'/fiscal-mode', 'resume_old_after_claim');
            }
            $done = $this->fiscal('ecf', $this->payload(), 'direct:process');
            if ($pause) {
                touch($this->runtime.'/continue-emission');
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
                $old = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame(202, $old['status']);
                if ($mode === 'after_complete') {
                    $this->assertSame($done['encf'], $old['body']['encf']);
                } else {
                    $this->assertSame('processing', $old['body']['request_status']);
                }
            }
            $this->assertSame('completed', $db->query('SELECT status FROM ecf_api_requests')->fetchColumn());
            $this->assertSame(2, (int) $db->query('SELECT generation FROM business_ecf_emissions')->fetchColumn());
            $this->assertDocuments(1);
        } finally {
            $process->stop(1);
        }
    }

    public function test_abandoned_external_request_can_finish_a_pending_commercial_release(): void
    {
        $this->startDirect();
        $db = $this->receiver('ecf');
        $db->exec("UPDATE api_credentials SET scopes = '[\"ecf:emit\",\"ecf:external\",\"ecf:read\"]'");
        $payload = ['codigo_mod' => 3, 'fecha_emision' => now()->format('Y-m-d'), 'items' => [['linea' => 7, 'cantidad' => '1']],
            'original' => ['environment' => 'traditional', 'source_hash' => str_repeat('a', 64), 'provenance' => 'Synthetic verified ledger', 'verified_by' => 501,
                'review' => ['proof' => ['identity_evidence' => 'Synthetic identity', 'authenticity_evidence' => 'Synthetic original',
                    'prior_adjustments_evidence' => 'Synthetic history', 'classification_evidence' => 'Synthetic classification']],
                'document' => ['ncf' => 'B0100000001', 'issuer_rnc' => '101010101', 'issued_on' => now()->subDay()->format('Y-m-d'),
                    'currency' => 'DOP', 'payment_type' => '1', 'income_type' => '01', 'receiver' => ['rnc' => '131880681', 'name' => 'Comprador sintético'],
                    'items' => [['line' => 7, 'description' => 'Original sintético', 'quantity' => '2', 'unit_price' => '100', 'discount' => '10',
                        'indicator' => 1, 'nature' => 1, 'unit' => null, 'withheld_isr' => '0', 'withheld_itbis' => '0']],
                    'total' => '224.20', 'tax' => '34.20', 'prior_adjustments' => []]]];
        file_put_contents($this->runtime.'/fiscal-mode', 'external_changed_lost_cancel');
        $this->fiscal('ecf/externos/B0100000001/devolucion', $payload, 'direct:external', status: 422);
        file_put_contents($this->runtime.'/fiscal-mode', '');
        $this->assertSame(1, (int) $db->query('SELECT count(*) FROM business_ecf_emissions WHERE released_at IS NULL')->fetchColumn());
        $this->fiscal('ecf/solicitudes/direct%3Aexternal/abandonar-externo',
            ['encf' => 'B0100000001', 'payload' => $payload, 'evidence' => 'Synthetic abandonment without document'], status: 200);
        $this->assertSame(0, $this->command(['action' => 'direct_reconcile', 'due' => true])['exit']);
        $this->assertSame('abandoned', $db->query('SELECT status FROM ecf_api_requests')->fetchColumn());
        $this->fiscal('ecf/externos/B0100000001/devolucion', $payload, 'direct:external', status: 409);
        $this->assertDocuments(0);
        $this->assertSame(0, DB::table('business_usage_reservations')->where('state', 'reserved')->count());
        $sourceHash = hash('sha256', $db->query('SELECT evidence FROM external_fiscal_originals')->fetchColumn());
        $db->exec('UPDATE external_fiscal_originals SET source_hash = '.$db->quote($sourceHash));
        $note = $this->fiscal('ecf/externos/B0100000001/devolucion', $payload, 'direct:external:corrected');
        $this->assertSame('e340000000001', $note['encf']);
        $replayed = $this->fiscal('ecf/externos/B0100000001/devolucion', $payload, 'direct:external:corrected');
        $this->assertSame($note['encf'], $replayed['encf']);
        $this->assertDocuments(1);
        $this->assertSame(1, DB::table('business_usage_aliases')->where('kind', 'fiscal')->count());
    }
}
