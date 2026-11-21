<?php

namespace Tests\Feature\BusinessCommercial;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\BusinessCommercialTransportTestCase;

final class BysBusinessAssistantTest extends BusinessCommercialTransportTestCase
{
    private function startBys(int $limit = 0, bool $standalone = false): void
    {
        $link = DB::table('business_identity_links')->where('service', 'bys');
        if ($standalone) {
            DB::table('business_contract_items')->where('identity_link_id', $link->value('id'))->delete();
            $link->update(['central_company_id' => 2, 'central_subscriber_id' => 2]);
        }
        $link->update(['local_company_id' => 1]);
        DB::table('business_contract_items')->where('identity_link_id', $link->value('id'))->update(['local_company_id' => 1]);
        $this->startServer();
        $this->bys(['action' => 'setup', 'local_limit' => $limit], 200);
    }

    private function worker(array $input): Process
    {
        return (new Process(['php', base_path('tests/Fixtures/BusinessCommercial/bys-purchase.php'), $this->runtime]))
            ->setInput(json_encode($input + ['action' => 'request', 'enabled' => false, 'assistant_enabled' => true, 'shipments_enabled' => true], JSON_THROW_ON_ERROR))->setTimeout(90);
    }

    private function bys(array $input, int $status = 201): array
    {
        $process = $this->worker($input)->mustRun();
        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($status, $result['status'], $process->getOutput().' '.@file_get_contents($this->runtime.'/bys/storage/logs/application.log'));
        if (isset($result['context_clear'])) {
            $this->assertTrue($result['context_clear']);
        }

        return $result;
    }

    private function db(): \PDO
    {
        return new \PDO('sqlite:'.$this->runtime.'/bys/database.sqlite');
    }

    private function draft(string $target = 'purchase_document', string $kind = 'service'): array
    {
        $payload = ['purchase_kind' => $kind, 'currency' => 'DOP', 'exchange_rate' => '1', 'issue_date' => '2026-11-17',
            'supplier_invoice_number' => 'ASSISTANT-1', 'ncf' => $kind === 'international_inventory' ? null : 'E310000000001',
            'lines' => [['description' => 'Compra revisada', 'product_sku' => 'SKU-1', 'quantity' => '2', 'unit_price_original' => '100', 'tax_rate' => '18']]];
        $draft = $this->bys(['path' => '/bys/assistant-drafts', 'payload' => ['supplier_id' => 1, 'input_payload' => $payload]])['body'];

        return ['path' => '/bys/assistant-drafts/'.$draft['id'].'/convert', 'payload' => ['target' => $target, 'purchase_kind' => $kind]];
    }

    private function counts(int $orders, int $documents, int $shipments, int $usage = 0): void
    {
        foreach (['bys_purchase_orders' => $orders, 'bys_purchase_documents' => $documents, 'bys_import_shipments' => $shipments,
            'bys_purchase_usages' => $usage, 'bys_purchase_receipts' => 0, 'bys_accounts_payable_documents' => 0] as $table => $expected) {
            $this->assertSame($expected, (int) $this->db()->query('SELECT count(*) FROM '.$table)->fetchColumn(), $table);
        }
    }

    public static function destinations(): array
    {
        return [
            ['purchase_order', 'service', 1, 0, 0], ['purchase_document', 'service', 0, 1, 0],
            ['import_shipment', 'international_inventory', 0, 0, 1], ['full_flow', 'service', 0, 1, 0],
            ['full_flow', 'local_inventory', 1, 1, 0], ['full_flow', 'international_inventory', 1, 1, 1],
        ];
    }

    #[DataProvider('destinations')]
    public function test_destinations_keep_native_results_and_only_documents_consume(string $target, string $kind, int $orders, int $documents, int $shipments): void
    {
        $this->startBys();
        $input = $this->draft($target, $kind);
        $this->bys(['path' => str_replace('/convert', '/preview', $input['path']), 'payload' => $input['payload']], 200);
        $this->counts(0, 0, 0);
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
        $first = $this->bys($input)['body'];
        $repeat = $this->bys($input + ['assistant_enabled' => false, 'mode' => 'lost_snapshot'])['body'];
        $this->assertSame($first, $repeat);
        $this->counts($orders, $documents, $shipments);
        $this->assertSame($documents, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
        $this->assertSame($documents, DB::table('business_usage_reservations')->count());
        $this->assertSame('converted', $first['draft']['status']);
        foreach ($first['created'] as $record) {
            $this->assertSame($record['type'] === 'purchase_document' ? 'received' : 'draft', $record['status']);
        }
        if ($documents && $shipments) {
            $this->assertSame((int) $first['created']['purchase_document']['id'], (int) $this->db()->query('SELECT purchase_document_id FROM bys_import_shipments')->fetchColumn());
            $this->assertSame(1, (int) $this->db()->query('SELECT count(*) FROM bys_import_shipment_lines WHERE purchase_document_line_id IS NOT NULL')->fetchColumn());
        }
        $changed = $input;
        $changed['payload']['lines'] = [['quantity' => '3', 'unit_price_original' => '100']];
        $this->bys($changed, 409);
    }

    #[DataProvider('destinations')]
    public function test_standalone_destinations_keep_the_native_purchase_quota(string $target, string $kind, int $orders, int $documents, int $shipments): void
    {
        $this->startBys(1, standalone: true);
        $input = $this->draft($target, $kind);
        $this->bys($input + ['assistant_enabled' => false]);
        $this->bys($input + ['assistant_enabled' => false]);
        $this->counts($orders, $documents, $shipments, 1);
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
        $other = $this->draft($target, $kind);
        $this->bys($other + ['assistant_enabled' => false], 422);
        $this->counts($orders, $documents, $shipments, 1);
    }

    public function test_quota_rejection_keeps_the_draft_and_another_reviewer_can_resume(): void
    {
        $this->startBys();
        $input = $this->draft('full_flow', 'international_inventory');
        $snapshot = $this->contract->snapshot;
        $snapshot['capacity']['documents'] = 0;
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->bys($input, 429);
        $this->counts(0, 0, 0);
        $this->assertSame('draft', $this->db()->query('SELECT status FROM bys_purchase_assistant_drafts')->fetchColumn());
        $snapshot['capacity']['documents'] = 5;
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->bys(['action' => 'add_actor'], 200);
        $this->bys($input + ['actor' => 502]);
        $this->counts(1, 1, 1);
        $this->assertSame(502, (int) $this->db()->query('SELECT created_by_user_id FROM bys_purchase_documents')->fetchColumn());
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public function test_invalid_late_cost_rolls_back_the_whole_conversion_then_retries(): void
    {
        $this->startBys();
        $input = $this->draft('full_flow', 'international_inventory');
        $input['payload']['costs'] = [['category' => 'invalid', 'currency' => 'DOP', 'amount_original' => '100']];
        $this->bys($input, 422);
        $this->counts(0, 0, 0);
        $this->assertSame(0, (int) $this->db()->query('SELECT count(*) FROM bys_issued_numbers')->fetchColumn());
        $this->assertSame('draft', $this->db()->query('SELECT status FROM bys_purchase_assistant_drafts')->fetchColumn());
        $this->assertSame(0, DB::table('business_usage_reservations')->where('state', 'reserved')->count());
        $input['payload']['costs'][0]['category'] = 'freight';
        $this->bys($input);
        $this->counts(1, 1, 1);
        $this->assertSame(1, (int) $this->db()->query('SELECT count(*) FROM bys_import_costs')->fetchColumn());
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public static function losses(): array
    {
        return [['lost_reserve', 503], ['lost_commit', 201]];
    }

    #[DataProvider('losses')]
    public function test_lost_replies_recover_one_complete_flow(string $mode, int $status): void
    {
        $this->startBys();
        $input = $this->draft('full_flow', 'international_inventory');
        $this->bys($input + ['mode' => $mode], $status);
        if ($mode === 'lost_commit') {
            $this->db()->exec("UPDATE bys_purchase_documents SET metadata = '{}'");
        }
        $this->assertSame(0, $this->bys(['action' => 'reconcile', 'due' => true], 200)['exit']);
        $this->bys($input);
        $this->counts(1, 1, 1);
        $this->assertSame('confirmed', $this->db()->query('SELECT state FROM bys_business_purchase_intents')->fetchColumn());
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public static function revocations(): array
    {
        return [['revoke_user', 403], ['cancel_assistant', 409], ['change_assistant', 409], ['change_supplier', 409], ['disable_assistant_module', 403]];
    }

    #[DataProvider('revocations')]
    public function test_conversion_rechecks_review_and_authority_after_reservation(string $mode, int $status): void
    {
        $this->startBys();
        $input = $this->draft('full_flow', 'local_inventory');
        $this->bys($input + ['mode' => $mode], $status);
        $this->counts(0, 0, 0);
        $this->assertSame(0, DB::table('business_usage_reservations')->where('state', 'reserved')->count());
    }

    public static function interruptions(): array
    {
        return [['pause_after_begin', 409], ['pause_after_native_commit', 201]];
    }

    #[DataProvider('interruptions')]
    public function test_interrupted_conversion_is_fenced_and_reconciled_without_recreation(string $mode, int $status): void
    {
        $this->startBys();
        $input = $this->draft('full_flow', 'international_inventory');
        $worker = $this->worker($input + ['mode' => $mode]);
        $worker->start();
        try {
            $deadline = microtime(true) + 35;
            while (! is_file($this->runtime.'/bys-paused')) {
                if (! $worker->isRunning() || microtime(true) > $deadline) {
                    $this->fail('No pause: '.$worker->getOutput().$worker->getErrorOutput());
                }
                usleep(10000);
            }
            if ($mode === 'pause_after_begin') {
                $this->bys($input, 409);
                $changed = $input;
                $changed['payload']['target'] = 'purchase_order';
                $this->bys($changed, 409);
                $changed['payload']['target'] = 'import_shipment';
                $this->bys($changed, 409);
                $this->db()->exec("UPDATE bys_business_purchase_intents SET leased_until = datetime('now', '-1 minute')");
            }
            $this->assertSame(0, $this->bys(['action' => 'reconcile', 'due' => true], 200)['exit']);
            $this->bys($input);
            touch($this->runtime.'/bys-resume');
            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $this->assertSame($status, json_decode($worker->getOutput(), true)['status'], $worker->getOutput());
            $this->counts(1, 1, 1);
            $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
        } finally {
            touch($this->runtime.'/bys-resume');
            $worker->stop(1);
        }
    }

    public function test_session_company_flags_and_native_review_remain_required(): void
    {
        $this->startBys();
        $input = $this->draft();
        $this->bys($input + ['guest' => true], 401);
        $this->bys($input + ['bad_csrf' => true], 419);
        $this->bys($input + ['company' => 999], 404);
        $this->bys($input + ['assistant_enabled' => false], 503);
        $input['payload']['payload'] = ['total_original' => '1'];
        $this->bys($input, 422);
        $this->counts(0, 0, 0);
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
    }

    public function test_known_fiscal_document_does_not_consume_again(): void
    {
        $this->startBys();
        $operation = (string) Str::uuid();
        $this->client('ecf', 'reserve', ['operation_key' => $operation,
            'document' => ['source_reference' => 'synthetic:assistant', 'fiscal' => ['issuer' => '101010101', 'ncf' => 'E310000000001']]]);
        $this->client('ecf', 'commit', ['operation_key' => $operation]);
        $this->bys($this->draft());
        $this->counts(0, 1, 0);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    private function shipment(): array
    {
        return ['path' => '/bys/import-shipments', 'payload' => ['supplier_id' => 1, 'currency' => 'DOP',
            'lines' => [['description' => 'Mercancía', 'quantity' => '2', 'goods_value_original' => '200', 'weight_kg' => '3', 'volume_cbm' => '4']]]];
    }

    public function test_direct_shipment_uses_import_permission_without_reserving_a_document(): void
    {
        $this->startBys();
        $this->bys(['action' => 'add_actor'], 200);
        $this->db()->exec("UPDATE bys_company_members SET role = 'warehouse' WHERE user_id = 502");
        $input = $this->shipment() + ['actor' => 502, 'assistant_enabled' => false];
        $this->bys($input + ['shipments_enabled' => false, 'enabled' => true], 503);
        $this->bys($input);
        $this->counts(0, 0, 1);
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
        $this->assertSame(502, (int) $this->db()->query('SELECT created_by_user_id FROM bys_import_shipments')->fetchColumn());
        $this->db()->exec("UPDATE bys_company_members SET role = 'viewer' WHERE user_id = 502");
        $this->bys($input, 403);
    }

    public function test_standalone_direct_shipment_keeps_its_quota_with_business_flag_off(): void
    {
        $this->startBys(1, standalone: true);
        $input = $this->shipment() + ['shipments_enabled' => false];
        $this->bys($input);
        $this->bys($input, 422);
        $this->counts(0, 0, 1, 1);
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
    }

    public function test_preparations_need_no_document_capacity_and_use_independent_flags(): void
    {
        $this->startBys();
        $snapshot = $this->contract->snapshot;
        $snapshot['capacity']['documents'] = 0;
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->bys($this->draft('purchase_order', 'service') + ['shipments_enabled' => false]);
        $this->bys($this->draft('import_shipment', 'international_inventory') + ['shipments_enabled' => false]);
        $this->bys($this->shipment() + ['assistant_enabled' => false]);
        $this->counts(1, 0, 2);
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
        $this->assertSame(0, (int) $this->db()->query('SELECT count(*) FROM bys_business_purchase_intents')->fetchColumn());
    }

    public static function preparations(): array
    {
        return [['assistant'], ['shipment']];
    }

    #[DataProvider('preparations')]
    public function test_preparations_recheck_authority_after_the_commercial_snapshot(string $channel): void
    {
        $this->startBys();
        $input = $channel === 'assistant' ? $this->draft('purchase_order', 'service') : $this->shipment();
        $this->bys($input + ['mode' => 'revoke_user_after_snapshot'], 403);
        $this->counts(0, 0, 0);
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
    }
}
