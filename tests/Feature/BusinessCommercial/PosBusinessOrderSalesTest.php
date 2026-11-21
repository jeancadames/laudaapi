<?php

namespace Tests\Feature\BusinessCommercial;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\PosFiscalHandoffTestCase;

final class PosBusinessOrderSalesTest extends PosFiscalHandoffTestCase
{
    private function order(array $input = [], int $status = 302): array
    {
        return $this->pos(['order' => true, ...$input], $status);
    }

    private function payload(string $key = 'order:1'): array
    {
        return ['idempotency_key' => $key,
            'payments' => [['payment_method_id' => 501, 'payment_type' => 'cash', 'amount' => 118]]];
    }

    private function noSale(array $result): void
    {
        foreach (['sales', 'payments', 'movements', 'receipts', 'maps'] as $count) {
            $this->assertSame(0, $result['native'][$count], $count);
        }
        $this->assertSame(100, $result['native']['stock']);
        $this->assertNull($result['order']['sale_id']);
    }

    public function test_ecommerce_order_uses_native_payment_inventory_and_fiscal_handoff_once(): void
    {
        $this->startServer(true);
        $first = $this->order(['ecommerce' => true]);
        $this->assertSame('ecommerce', $first['order']['source']);
        $this->assertSame('released_to_operations', $first['order']['ecommerce_checkout_status']);
        $this->assertSame(1, $first['native']['sales']);
        $this->assertSame(1, $first['native']['payments']);
        $this->assertSame(1, $first['native']['movements']);
        $this->assertSame(99, $first['native']['stock']);
        $this->assertSame(['committed'], $first['native']['outbox']);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
        $this->assertSame(1, DB::table('business_fiscal_handoffs')->count());
        $this->assertCount(1, $first['fiscal']);
        $this->assertArrayNotHasKey('receptor', json_decode($first['fiscal'][0]['payload'], true));
        $this->assertNotEmpty($first['fiscal'][0]['encf'], json_encode($first).' '.$this->logs());
        $this->assertSame(1, (int) $this->receiver('ecf')->query('SELECT count(*) FROM ecf_documents')->fetchColumn());
        $again = $this->order();
        $this->assertSame('true', $again['replayed']);
        $this->assertSame($first['native'], $again['native']);
        $this->assertSame($first['location'], $again['location']);
        $this->assertSame('true', $this->order(['mode' => 'orders_disabled'])['replayed']);
        $this->order(['payload' => $this->payload('different')], 422);
        $this->assertSame(1, DB::table('business_usage_reservations')->count());
    }

    public function test_cashier_uses_collection_permission_and_replay_ignores_route_sync_metadata(): void
    {
        $this->startServer();
        $this->order(['action' => 'setup'], 200);
        $db = $this->receiver('pos');
        $db->exec("UPDATE users SET role = 'cashier'");
        $db->exec('UPDATE branch_user SET can_create_sales = 1, can_collect_payments = 0');
        $this->noSale($this->order([], 403));
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
        $db->exec('UPDATE branch_user SET can_create_sales = 0, can_collect_payments = 1');
        $first = $this->order();
        $this->assertSame(1, $first['native']['sales']);
        $db->exec("UPDATE orders SET route_sync_status = 'synced', route_synced_at = datetime('now')");
        $this->assertSame('true', $this->order()['replayed']);
        $db->exec('UPDATE branch_user SET can_collect_payments = 0, can_create_sales = 1');
        $this->order([], 403);
        $this->assertSame(1, DB::table('business_usage_reservations')->count());
    }

    public function test_quota_key_and_flags_block_before_native_effects(): void
    {
        $snapshot = $this->contract->snapshot;
        $snapshot['capacity']['documents'] = 0;
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->startServer();
        $this->noSale($this->order(['mode' => 'unavailable'], 503));
        $this->noSale($this->order([], 429));
        $this->noSale($this->order(['mode' => 'orders_disabled'], 503));
        $payload = $this->payload();
        unset($payload['idempotency_key']);
        $this->noSale($this->order(['payload' => $payload], 422));
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
    }

    public function test_lost_reserve_binds_source_across_processes_until_reconciled(): void
    {
        $this->startServer();
        $this->noSale($this->order(['mode' => 'lost_reserve'], 503));
        $db = $this->receiver('pos');
        $db->exec("UPDATE order_items SET description = 'Changed between requests'");
        $this->noSale($this->order(['mode' => 'due'], 409));
        $this->assertSame(1, DB::table('business_usage_reservations')->count());
        $recovered = $this->order(['action' => 'reconcile', 'mode' => 'due'], 200);
        $this->assertSame(0, $recovered['code']);
        $this->noSale($recovered);
        $this->assertSame('retryable', $recovered['native']['intents'][0]['state']);
        $db->exec("UPDATE order_items SET description = 'Producto sintético'");
        $sale = $this->order();
        $this->assertSame(2, $sale['native']['intents'][0]['generation']);
        $this->assertSame(1, $sale['native']['sales']);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    #[DataProvider('changedDuringAdmission')]
    public function test_origin_and_permissions_are_rechecked_after_reservation(string $mode, int $status): void
    {
        $this->startServer();
        $failed = $this->order(['mode' => $mode], $status);
        $this->noSale($failed);
        $this->assertSame(0, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
        if ($mode === 'order_cancelled') {
            $this->assertSame(['released'], $failed['native']['outbox']);
        } else {
            $this->assertSame(['executing'], $failed['native']['outbox']);
        }
    }

    public static function changedDuringAdmission(): array
    {
        return [['order_changed', 409], ['order_cancelled', 422], ['revoke_collection', 403]];
    }

    public function test_late_rollback_preserves_order_and_fiscal_approval_then_retries_once(): void
    {
        $this->startServer();
        $failed = $this->order(['mode' => 'late_validation'], 422);
        $this->noSale($failed);
        $this->assertSame(['released'], $failed['native']['outbox']);
        $this->assertSame('sent_to_cashier', $failed['order']['flow_status']);
        $this->assertSame(0, (int) $this->receiver('pos')->query('SELECT count(*) FROM fiscal_distribution_approvals WHERE consumed_at IS NOT NULL')->fetchColumn());
        $payload = $this->payload() + ['notes' => 'Corrected retry'];
        $done = $this->order(['payload' => $payload]);
        $this->assertSame(2, $done['native']['intents'][0]['generation']);
        $this->assertSame(1, $done['native']['sales']);
        $this->assertSame(1, $done['native']['payments']);
        $this->assertSame(1, (int) $this->receiver('pos')->query('SELECT count(*) FROM fiscal_distribution_approvals WHERE consumed_at IS NOT NULL')->fetchColumn());
    }

    public function test_lost_commit_recovers_without_another_invoice_or_payment(): void
    {
        $this->startServer();
        $first = $this->order(['mode' => 'lost_commit']);
        $this->assertSame(['local_committed'], $first['native']['outbox']);
        $this->assertSame(1, $first['native']['sales']);
        $dry = $this->order(['action' => 'reconcile', 'options' => ['--dry-run' => true]], 200);
        $this->assertSame([], $dry['actions']);
        $this->assertSame($first['native'], $dry['native']);
        $this->assertSame(0, $this->order(['action' => 'reconcile', 'mode' => 'due'], 200)['code']);
        $again = $this->order();
        $this->assertSame('true', $again['replayed']);
        $this->assertSame(1, $again['native']['sales']);
        $this->assertSame(1, $again['native']['payments']);
        $this->assertSame(['committed'], $again['native']['outbox']);
        $this->receiver('pos')->exec('UPDATE business_identity_links SET active = 0');
        $this->assertSame('true', $this->order()['replayed']);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public function test_unreleased_foreign_and_invalid_self_service_orders_do_not_reserve_capacity(): void
    {
        $this->startServer();
        $this->order(['action' => 'setup', 'ecommerce' => true], 200);
        $db = $this->receiver('pos');
        $db->exec("UPDATE orders SET ecommerce_checkout_status = 'pending_approval'");
        $this->noSale($this->order([], 422));
        $db->exec("UPDATE orders SET source = 'self_service'");
        $this->noSale($this->order([], 503));
        $db->exec("UPDATE orders SET source = 'counter'");
        $this->noSale($this->order(['order_id' => 999999], 404));
        $db->exec('UPDATE orders SET branch_id = 999999');
        $this->noSale($this->order([], 404));
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
    }

    public function test_verified_standalone_keeps_optional_keys_and_does_not_consume_business(): void
    {
        DB::table('business_contract_items')->where('identity_link_id', DB::table('business_identity_links')->where('service', 'pos')->value('id'))->delete();
        DB::table('business_identity_links')->where('service', 'pos')->update(['central_company_id' => 2, 'central_subscriber_id' => 2]);
        $this->startServer();
        $payload = $this->payload();
        unset($payload['idempotency_key']);
        $standalone = $this->order(['payload' => $payload]);
        $this->assertSame(1, $standalone['native']['sales']);
        $this->assertSame([], $standalone['native']['outbox']);
        $this->assertSame(['snapshot'], $standalone['actions']);
        $this->assertSame(0, DB::table('business_usage_documents')->count());
    }

    public function test_after_commit_error_recovers_final_source_and_corruption_cannot_be_replayed(): void
    {
        $this->startServer();
        $first = $this->order(['mode' => 'after_commit_failure']);
        $this->assertSame('true', $first['replayed']);
        $this->assertSame(1, $first['native']['sales']);
        $this->assertSame(1, $first['native']['payments']);
        $this->assertSame(['committed'], $first['native']['outbox']);
        $this->receiver('pos')->exec("UPDATE order_items SET description = 'Corrupted final evidence'");
        $this->order([], 409);
        $this->assertSame(1, (int) $this->receiver('pos')->query('SELECT count(*) FROM sales')->fetchColumn());
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
        $this->assertSame(0, DB::table('business_usage_cancellations')->count());
    }
}
