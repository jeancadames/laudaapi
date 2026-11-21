<?php

namespace Tests\Feature\BusinessCommercial;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\BusinessCommercialTransportTestCase;

final class BysBusinessPurchasesTest extends BusinessCommercialTransportTestCase
{
    private function startBys(int $localLimit = 0): void
    {
        DB::table('business_identity_links')->where('service', 'bys')->update(['local_company_id' => 1]);
        DB::table('business_contract_items')->where('identity_link_id', DB::table('business_identity_links')->where('service', 'bys')->value('id'))->update(['local_company_id' => 1]);
        $this->startServer();
        $this->bys(['action' => 'setup', 'local_limit' => $localLimit]);
    }

    private function bys(array $input, int $status = 200): array
    {
        $process = new Process(['php', base_path('tests/Fixtures/BusinessCommercial/bys-purchase.php'), $this->runtime]);
        $process->setInput(json_encode($input + ['action' => 'request', 'key' => 'synthetic-operation-1'], JSON_THROW_ON_ERROR));
        $process->setTimeout(90)->mustRun();
        try {
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new \RuntimeException($process->getOutput().$process->getErrorOutput(), previous: $error);
        }
        $this->assertSame($status, $result['status'], $process->getOutput().' '.@file_get_contents($this->runtime.'/bys/storage/logs/application.log'));
        if (array_key_exists('context_clear', $result)) {
            $this->assertTrue($result['context_clear']);
        }

        return $result;
    }

    public function test_native_purchase_and_replay_consume_once_without_local_debit(): void
    {
        $this->startBys();
        $first = $this->bys([], 201)['body'];
        $again = $this->bys([], 201)['body'];
        $this->assertSame($first['id'], $again['id']);
        $this->assertSame('draft', $first['status']);
        $this->assertSame('118.00', $first['total_original']);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
        $db = new \PDO('sqlite:'.$this->runtime.'/bys/database.sqlite');
        $this->assertSame(1, (int) $db->query('SELECT count(*) FROM bys_purchase_documents')->fetchColumn());
        $this->assertSame(0, (int) $db->query('SELECT count(*) FROM bys_purchase_usages')->fetchColumn());
    }

    private function db(): \PDO
    {
        return new \PDO('sqlite:'.$this->runtime.'/bys/database.sqlite');
    }

    private function documents(int $count): void
    {
        $this->assertSame($count, (int) $this->db()->query('SELECT count(*) FROM bys_purchase_documents')->fetchColumn());
        $this->assertSame($count, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public function test_orders_do_not_consume_and_each_derived_document_keeps_its_native_order(): void
    {
        $this->startBys();
        $order = $this->bys(['path' => '/bys/purchase-orders', 'payload' => ['supplier_id' => 1,
            'purchase_kind' => 'service', 'currency' => 'DOP', 'lines' => [['description' => 'Servicio', 'quantity_ordered' => 1, 'unit_price_original' => 100]]]], 201)['body'];
        $this->documents(0);
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
        $this->assertSame(0, (int) $this->db()->query('SELECT count(*) FROM bys_purchase_usages')->fetchColumn());
        $input = ['path' => '/bys/purchase-orders/'.$order['id'].'/documents', 'payload' => ['document_type' => 'local_fiscal_invoice', 'ncf' => 'E310000000001']];
        $first = $this->bys($input, 201)['body'];
        $this->assertSame($order['id'], $first['purchase_order_id']);
        $this->assertSame($first['id'], $this->bys($input, 201)['body']['id']);
        $this->documents(1);
        $input['key'] = 'synthetic-document-2';
        $input['payload']['ncf'] = 'E310000000002';
        $this->bys($input, 201);
        $this->documents(2);
    }

    public function test_quota_exhaustion_releases_and_retries_same_key_after_capacity_returns(): void
    {
        $this->startBys();
        $snapshot = $this->contract->snapshot;
        $snapshot['capacity']['documents'] = 0;
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->bys([], 429);
        $this->documents(0);
        $this->assertSame('retryable', $this->db()->query('SELECT state FROM bys_business_purchase_intents')->fetchColumn());
        $snapshot['capacity']['documents'] = 5;
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->bys([], 201);
        $this->documents(1);
        $this->assertSame(2, (int) $this->db()->query('SELECT generation FROM bys_business_purchase_intents')->fetchColumn());
    }

    public static function losses(): array
    {
        return [['lost_reserve', 503], ['lost_commit', 201]];
    }

    #[DataProvider('losses')]
    public function test_response_loss_reconciles_without_another_native_write(string $mode, int $status): void
    {
        $this->startBys();
        $this->bys(['mode' => $mode], $status);
        $before = (int) $this->db()->query('SELECT count(*) FROM bys_purchase_documents')->fetchColumn();
        $this->assertSame(0, $this->bys(['action' => 'reconcile', 'due' => true])['exit']);
        $this->assertSame($before, (int) $this->db()->query('SELECT count(*) FROM bys_purchase_documents')->fetchColumn());
        $this->bys([], 201);
        $this->documents(1);
    }

    public static function revocations(): array
    {
        return [['revoke_user', 403], ['revoke_link', 403], ['change_supplier', 409]];
    }

    #[DataProvider('revocations')]
    public function test_authority_and_supplier_are_rechecked_after_reservation(string $mode, int $status): void
    {
        $this->startBys();
        $this->bys(['mode' => $mode], $status);
        $this->documents(0);
        $this->assertSame($mode === 'revoke_link' ? 1 : 0, DB::table('business_usage_reservations')->where('state', 'reserved')->count());
    }

    public function test_native_rollback_releases_capacity_and_retry_preserves_numbering(): void
    {
        $this->startBys();
        $this->bys(['mode' => 'rollback'], 500);
        $this->documents(0);
        $this->assertSame(0, DB::table('business_usage_reservations')->where('state', 'reserved')->count());
        $this->db()->exec('DROP TRIGGER synthetic_rollback');
        $document = $this->bys([], 201)['body'];
        $this->documents(1);
        $this->assertStringEndsWith('000001', $document['document_number']);
    }

    public function test_native_identity_still_rejects_duplicate_while_central_deduplicates_known_fiscal_origin(): void
    {
        $this->startBys();
        $operation = (string) Str::uuid();
        $this->client('ecf', 'reserve', ['operation_key' => $operation,
            'document' => ['source_reference' => 'synthetic:known-fiscal', 'fiscal' => ['issuer' => '101010101', 'ncf' => 'E310000000001']]]);
        $this->client('ecf', 'commit', ['operation_key' => $operation]);
        $this->bys([], 201);
        $this->documents(1);
        $this->bys(['key' => 'another-key'], 422);
        $this->documents(1);
    }

    public function test_changed_payload_with_completed_key_cannot_create_another_document(): void
    {
        $this->startBys();
        $this->bys([], 201);
        $this->bys(['payload' => ['document_type' => 'commercial_invoice']], 409);
        $this->documents(1);
    }

    public function test_flags_session_csrf_key_and_company_remain_required(): void
    {
        $this->startBys();
        $this->bys(['guest' => true], 401);
        $this->bys(['bad_csrf' => true], 419);
        $this->bys(['company' => 999], 404);
        $this->bys(['key' => null], 422);
        $this->bys(['enabled' => false], 503);
        $this->documents(0);
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
    }

    public function test_native_plan_features_and_company_settings_are_not_replaced_by_business_name(): void
    {
        $this->startBys();
        $features = json_decode($this->db()->query("SELECT features FROM plans WHERE slug = 'business'")->fetchColumn(), true);
        $features['manual_entry'] = false;
        $this->db()->prepare("UPDATE plans SET features = ? WHERE slug = 'business'")->execute([json_encode($features)]);
        $this->bys([], 403);
        $this->documents(0);
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
    }

    public function test_standalone_keeps_local_quota_and_no_business_consumption(): void
    {
        DB::table('business_contract_items')->where('identity_link_id', DB::table('business_identity_links')->where('service', 'bys')->value('id'))->delete();
        DB::table('business_identity_links')->where('service', 'bys')->update(['central_company_id' => 2, 'central_subscriber_id' => 2]);
        $this->startBys(1);
        $this->bys([], 201);
        $this->assertSame(1, (int) $this->db()->query('SELECT count(*) FROM bys_purchase_usages')->fetchColumn());
        $this->assertSame(0, DB::table('business_usage_documents')->count());
        $this->bys(['key' => 'second', 'payload' => ['document_type' => 'commercial_invoice']], 422);
    }

    public function test_reconciliation_verifies_creation_evidence_but_allows_native_corrections(): void
    {
        $this->startBys();
        $document = $this->bys(['mode' => 'lost_commit'], 201)['body'];
        $this->bys(['method' => 'PATCH', 'path' => '/bys/purchase-documents/'.$document['id'],
            'payload' => ['expected_revision' => $document['revision'], 'correction_reason' => 'Corrección sintética', 'ncf' => 'E310000000003']], 200);
        $this->assertSame(0, $this->bys(['action' => 'reconcile', 'due' => true])['exit']);
        $this->documents(1);
        $this->db()->exec("UPDATE bys_business_purchase_intents SET evidence_hash = 'altered', state = 'executed'");
        $this->assertSame(1, $this->bys(['action' => 'reconcile', 'dry_run' => false])['exit']);
        $this->bys([], 409);
        $this->documents(1);
    }

    public function test_unsupported_native_entry_cannot_fall_back_to_local_usage(): void
    {
        $this->startBys();
        $this->bys(['action' => 'unguarded'], 503);
        $this->documents(0);
        $this->assertSame(0, (int) $this->db()->query('SELECT count(*) FROM bys_purchase_usages')->fetchColumn());
    }

    public function test_completed_intents_do_not_starve_later_reconciliation_with_a_small_limit(): void
    {
        $this->startBys();
        $this->bys([], 201);
        $this->bys(['key' => 'second', 'mode' => 'lost_commit', 'payload' => ['document_type' => 'commercial_invoice']], 201);
        $this->assertSame(0, $this->bys(['action' => 'reconcile', 'due' => true, 'limit' => 1])['exit']);
        $this->documents(2);
        $this->assertSame(2, (int) $this->db()->query("SELECT count(*) FROM bys_business_purchase_intents WHERE state = 'confirmed'")->fetchColumn());
    }

    public static function invalidReconciliationOptions(): array
    {
        return [
            'zero company' => [['--company' => '0']],
            'negative company' => [['--company' => '-1']],
            'empty company' => [['--company' => '']],
            'non-numeric company' => [['--company' => 'all']],
            'zero limit' => [['--limit' => '0']],
            'negative limit' => [['--limit' => '-1']],
            'non-numeric limit' => [['--limit' => 'one']],
            'excessive limit' => [['--limit' => '1001']],
        ];
    }

    #[DataProvider('invalidReconciliationOptions')]
    public function test_reconciliation_rejects_invalid_scope_without_touching_pending_work(array $options): void
    {
        $this->startBys();
        $this->bys(['mode' => 'lost_commit'], 201);
        $before = $this->db()->query('SELECT * FROM bys_business_purchase_intents ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
        $operations = $this->db()->query('SELECT * FROM business_commercial_operations ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
        foreach ([true, false] as $dryRun) {
            $result = $this->bys(['action' => 'reconcile', 'options' => $options, 'dry_run' => $dryRun]);
            $this->assertSame(2, $result['exit']);
            $this->assertSame($before, $this->db()->query('SELECT * FROM bys_business_purchase_intents ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC));
            $this->assertSame($operations, $this->db()->query('SELECT * FROM business_commercial_operations ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC));
        }
        $this->documents(1);
    }

    public function test_reconciliation_keeps_explicit_company_scope_and_allows_omitted_scope(): void
    {
        $this->startBys();
        $this->bys(['mode' => 'lost_commit'], 201);
        $this->db()->exec('UPDATE business_commercial_operations SET next_attempt_at = NULL');
        $before = $this->db()->query('SELECT * FROM bys_business_purchase_intents ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
        $operations = $this->db()->query('SELECT * FROM business_commercial_operations ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertSame(0, $this->bys(['action' => 'reconcile', 'options' => ['--company' => 2]])['exit']);
        $this->assertSame(0, $this->bys(['action' => 'reconcile', 'options' => ['--company' => 1, '--limit' => 1000], 'dry_run' => true])['exit']);
        $this->assertSame($before, $this->db()->query('SELECT * FROM bys_business_purchase_intents ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC));
        $this->assertSame($operations, $this->db()->query('SELECT * FROM business_commercial_operations ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC));
        $this->assertSame(0, $this->bys(['action' => 'reconcile', 'options' => ['--company' => null, '--limit' => 1]])['exit']);
        $this->assertSame('confirmed', $this->db()->query('SELECT state FROM bys_business_purchase_intents')->fetchColumn());
        $this->documents(1);
    }

    public function test_stale_worker_is_fenced_after_cancellation_and_new_generation(): void
    {
        $this->startBys();
        $worker = new Process(['php', base_path('tests/Fixtures/BusinessCommercial/bys-purchase.php'), $this->runtime]);
        $worker->setInput(json_encode(['action' => 'request', 'key' => 'synthetic-operation-1', 'mode' => 'pause_after_reserve']));
        $worker->setTimeout(60)->start();
        try {
            $deadline = microtime(true) + 30;
            while (! is_file($this->runtime.'/bys-paused')) {
                if (! $worker->isRunning() || microtime(true) > $deadline) {
                    $this->fail('Worker failed before pause: '.$worker->getOutput().$worker->getErrorOutput());
                }
                usleep(10000);
            }
            $this->bys([], 409);
            $this->assertSame(0, $this->bys(['action' => 'reconcile', 'dry_run' => true])['exit']);
            $this->assertSame('pending', $this->db()->query('SELECT state FROM bys_business_purchase_intents')->fetchColumn());
            $this->db()->exec("UPDATE bys_business_purchase_intents SET leased_until = datetime('now', '-1 minute')");
            $this->assertSame(1, $this->bys(['action' => 'reconcile', 'due' => true])['exit']);
            $this->db()->exec("UPDATE business_commercial_operations SET leased_until = datetime('now', '-1 minute')");
            $this->assertSame(0, $this->bys(['action' => 'reconcile', 'due' => true])['exit']);
            $this->bys([], 201);
            touch($this->runtime.'/bys-resume');
            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $this->assertSame(409, json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR)['status']);
            $this->documents(1);
            $this->assertSame(2, (int) $this->db()->query('SELECT generation FROM bys_business_purchase_intents')->fetchColumn());
            $this->assertSame('confirmed', $this->db()->query('SELECT state FROM bys_business_purchase_intents')->fetchColumn());
        } finally {
            touch($this->runtime.'/bys-resume');
            $worker->stop(1);
        }
    }

    public function test_completed_purchase_replays_without_new_coverage_or_an_available_snapshot(): void
    {
        $this->startBys();
        $document = $this->bys([], 201)['body'];
        $again = $this->bys(['enabled' => false, 'mode' => 'lost_snapshot'], 201)['body'];
        $this->assertSame($document['id'], $again['id']);
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['period_ends_at' => now()->subMinute()]);
        $this->assertSame($document['id'], $this->bys([], 201)['body']['id']);
        $this->documents(1);
        $this->assertSame(1, DB::table('business_usage_reservations')->count());
    }

    public function test_inherited_supplier_keeps_the_known_fiscal_origin(): void
    {
        $this->startBys();
        $operation = (string) Str::uuid();
        $this->client('ecf', 'reserve', ['operation_key' => $operation,
            'document' => ['source_reference' => 'synthetic:known-fiscal', 'fiscal' => ['issuer' => '101010101', 'ncf' => 'E310000000001']]]);
        $this->client('ecf', 'commit', ['operation_key' => $operation]);
        $order = $this->bys(['path' => '/bys/purchase-orders', 'payload' => ['supplier_id' => 1,
            'purchase_kind' => 'service', 'currency' => 'DOP', 'lines' => [['description' => 'Servicio', 'quantity_ordered' => 1, 'unit_price_original' => 100]]]], 201)['body'];
        $document = $this->bys(['payload' => ['purchase_order_id' => $order['id'], 'document_type' => 'local_fiscal_invoice',
            'ncf' => 'E310000000001', 'purchase_kind' => 'service']], 201)['body'];
        $this->assertSame(1, $document['supplier_id']);
        $this->documents(1);
    }

    public function test_order_rechecks_the_native_actor_after_commercial_snapshot(): void
    {
        $this->startBys();
        $this->bys(['mode' => 'revoke_user_after_snapshot', 'path' => '/bys/purchase-orders', 'payload' => ['supplier_id' => 1,
            'purchase_kind' => 'service', 'currency' => 'DOP', 'lines' => [['description' => 'Servicio', 'quantity_ordered' => 1, 'unit_price_original' => 100]]]], 403);
        $this->assertSame(0, (int) $this->db()->query('SELECT count(*) FROM bys_purchase_orders')->fetchColumn());
        $this->documents(0);
    }

    public function test_standalone_link_keeps_unadapted_native_channels_available(): void
    {
        DB::table('business_contract_items')->where('identity_link_id', DB::table('business_identity_links')->where('service', 'bys')->value('id'))->delete();
        DB::table('business_identity_links')->where('service', 'bys')->update(['central_company_id' => 2, 'central_subscriber_id' => 2]);
        $this->startBys(2);
        $this->bys(['action' => 'unguarded']);
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
    }

    public function test_historical_supplier_format_uses_the_same_central_fiscal_identity(): void
    {
        $this->startBys();
        $this->db()->exec("UPDATE bys_suppliers SET rnc = '101-01010-1' WHERE id = 1");
        $operation = (string) Str::uuid();
        $this->client('ecf', 'reserve', ['operation_key' => $operation,
            'document' => ['source_reference' => 'synthetic:historical-fiscal', 'fiscal' => ['issuer' => '101010101', 'ncf' => 'E310000000001']]]);
        $this->client('ecf', 'commit', ['operation_key' => $operation]);
        $this->bys([], 201);
        $this->documents(1);
        $this->assertSame('101-01010-1', $this->db()->query('SELECT rnc FROM bys_suppliers WHERE id = 1')->fetchColumn());
    }
}
