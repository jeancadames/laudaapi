<?php

namespace Tests\Feature\BusinessCommercial;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\PosFiscalHandoffTestCase;

final class PosBusinessServiceInvoicesTest extends PosFiscalHandoffTestCase
{
    private function service(array $input = [], int $status = 200): array
    {
        return $this->pos(['service_invoice' => true, ...$input], $status);
    }

    private function noSale(array $result): void
    {
        foreach (['sales', 'payments', 'movements', 'receipts', 'maps'] as $count) {
            $this->assertSame(0, $result['native'][$count], $count);
        }
        $this->assertSame(100, $result['native']['stock']);
        $this->assertNull($result['service']['order']['invoiced_sale_id']);
        $this->assertSame([], $result['service']['accounts']);
        $this->assertSame(0, $result['service']['charges']);
    }

    public static function conditions(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('conditions')]
    public function test_service_cash_and_credit_invoice_once_without_inventory(bool $credit): void
    {
        $this->startServer();
        $first = $this->service(['service_credit' => $credit]);
        $this->assertSame(1, $first['native']['sales']);
        $this->assertSame($credit ? 0 : 1, $first['native']['payments']);
        $this->assertSame(0, $first['native']['movements']);
        $this->assertSame(100, $first['native']['stock']);
        $this->assertSame(['committed'], $first['native']['outbox']);
        $this->assertSame('invoiced', $first['service']['order']['status']);
        $this->assertCount($credit ? 1 : 0, $first['service']['accounts']);
        $this->assertSame($credit ? 1 : 0, $first['service']['charges']);
        $again = $this->service(['service_credit' => $credit, 'mode' => 'services_disabled']);
        $this->assertSame('true', $again['replayed']);
        $this->assertSame($first['native'], $again['native']);
        $this->assertSame($first['service'], $again['service']);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
        $this->service(['payload' => ['idempotency_key' => 'another']], 422);
        $this->assertSame(1, DB::table('business_usage_reservations')->count());
    }

    public function test_late_credit_rejection_rolls_back_sql_and_releases_reservation(): void
    {
        $this->startServer();
        $failed = $this->service(['service_credit' => true, 'mode' => 'late_service_validation'], 422);
        $this->noSale($failed);
        $this->assertSame(['released'], $failed['native']['outbox']);
        $this->assertSame(0, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
        $this->assertSame(1, $this->service(['service_credit' => true])['native']['sales']);
    }

    public static function changes(): array
    {
        return [['service_changed', 409], ['service_cancelled', 422], ['service_membership_revoked', 403], ['revoke_role', 403], ['revoke_link', 403], ['fenced', 409]];
    }

    #[DataProvider('changes')]
    public function test_changes_during_reservation_do_not_create_invoice(string $mode, int $status): void
    {
        $this->startServer();
        $this->noSale($this->service(['mode' => $mode], $status));
        $this->assertSame(0, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public function test_lost_reserve_and_crash_before_sql_recover_without_consuming_twice(): void
    {
        $this->startServer();
        $this->noSale($this->service(['mode' => 'lost_reserve'], 503));
        $this->receiver('pos')->exec('UPDATE business_commercial_operations SET next_attempt_at = NULL');
        $this->noSale($this->service(['mode' => 'crash_after_begin'], 500));
        $recovered = $this->service(['action' => 'reconcile', 'mode' => 'due']);
        $this->assertSame(0, $recovered['code'], $recovered['output']);
        $this->assertSame('retryable', $recovered['native']['intents'][0]['state']);
        $this->assertSame(1, $this->service()['native']['sales']);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public function test_quota_and_disabled_and_foreign_orders_do_not_create_effects(): void
    {
        $snapshot = $this->contract->snapshot;
        $snapshot['capacity']['documents'] = 0;
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->startServer();
        $this->noSale($this->service(['mode' => 'services_disabled'], 503));
        $this->service(['service_order_id' => 999], 404);
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
        $db = $this->receiver('pos');
        $db->exec("UPDATE service_orders SET status = 'cancelled'");
        $this->noSale($this->service([], 422));
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
        $db->exec("UPDATE service_orders SET status = 'completed'");
        $this->noSale($this->service([], 429));
    }

    public function test_manager_uses_service_permissions_and_replay_checks_membership(): void
    {
        $this->startServer();
        $this->service(['action' => 'setup']);
        $db = $this->receiver('pos');
        $db->exec("UPDATE users SET role = 'manager'; UPDATE branch_user SET can_create_sales = 0, can_collect_payments = 0");
        $this->assertSame(1, $this->service()['native']['sales']);
        $db->exec('UPDATE branch_user SET is_active = 0');
        $this->service([], 403);
    }

    public function test_lost_commit_and_later_credit_payment_preserve_original_evidence(): void
    {
        $this->startServer();
        $first = $this->service(['service_credit' => true, 'mode' => 'lost_commit']);
        $this->assertSame(1, $first['native']['sales']);
        $payment = $this->service(['service_credit' => true, 'action' => 'service_payment']);
        $this->assertSame(1, $payment['service']['payments']);
        $this->assertEquals(100, $payment['service']['accounts'][0]['balance']);
        $recovered = $this->service(['action' => 'reconcile', 'mode' => 'due']);
        $this->assertSame(0, $recovered['code'], $recovered['output']);
        $this->assertSame('true', $this->service(['service_credit' => true])['replayed']);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public function test_after_commit_error_returns_receipt_and_rejects_corrupted_lines(): void
    {
        $this->startServer();
        $first = $this->service(['mode' => 'service_after_commit']);
        $this->assertSame('true', $first['replayed']);
        $this->receiver('pos')->exec("UPDATE sale_items SET description = 'Corrupted invoice'");
        $this->service([], 409);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
        $this->assertSame(0, DB::table('business_usage_cancellations')->count());
    }

    public function test_missing_evidence_cannot_cancel_an_executed_invoice(): void
    {
        $this->startServer();
        $failed = $this->service(['mode' => 'service_evidence_loss'], 500);
        $this->assertSame(1, $failed['native']['sales']);
        $recovered = $this->service(['action' => 'reconcile', 'mode' => 'due']);
        $this->assertNotSame(0, $recovered['code']);
        $this->assertSame('executed', $recovered['native']['intents'][0]['state']);
        $this->assertSame(0, DB::table('business_usage_cancellations')->count());
    }

    #[DataProvider('conditions')]
    public function test_fiscal_handoff_uses_same_document_for_service_cash_and_credit(bool $credit): void
    {
        $this->startServer(true);
        $first = $this->service(['service_credit' => $credit]);
        $this->assertCount(1, $first['fiscal']);
        $this->assertNotEmpty($first['fiscal'][0]['encf'], json_encode($first).' '.$this->logs());
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
        $this->assertSame(1, DB::table('business_fiscal_handoffs')->count());
        $this->assertSame(1, (int) $this->receiver('ecf')->query('SELECT count(*) FROM ecf_documents')->fetchColumn());
        $this->assertSame('true', $this->service(['service_credit' => $credit])['replayed']);
    }

    public function test_verified_standalone_can_invoice_without_business_key(): void
    {
        DB::table('business_contract_items')->where('identity_link_id', DB::table('business_identity_links')->where('service', 'pos')->value('id'))->delete();
        DB::table('business_identity_links')->where('service', 'pos')->update(['central_company_id' => 2, 'central_subscriber_id' => 2]);
        $this->startServer();
        $first = $this->service(['payload' => ['payments' => []]]);
        $this->assertSame(1, $first['native']['sales']);
        $this->assertSame([], $first['native']['outbox']);
        $this->assertSame(['snapshot'], $first['actions']);
    }

    public function test_cash_invoice_without_initial_payment_accepts_later_partial_payment(): void
    {
        $this->startServer();
        $payload = ['idempotency_key' => 'service:unpaid', 'invoice_number' => '', 'notes' => 'Original'];
        $first = $this->service(['payload' => $payload, 'mode' => 'lost_commit']);
        $this->assertSame(0, $first['native']['payments']);
        $paid = $this->service(['action' => 'service_payment']);
        $this->assertSame(1, $paid['native']['payments']);
        $this->assertEquals(18, $this->receiver('pos')->query('SELECT paid_amount FROM sales')->fetchColumn());
        $this->assertEquals(100, $this->receiver('pos')->query('SELECT balance_due FROM sales')->fetchColumn());
        $this->assertSame(0, $this->service(['action' => 'reconcile', 'mode' => 'due'])['code']);
        $again = $this->service(['payload' => $payload]);
        $this->assertSame('true', $again['replayed']);
        $this->assertSame(1, $again['native']['sales']);
        $this->assertSame(1, $again['native']['payments']);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public function test_corrupt_credit_source_and_account_block_replay_and_reconciliation(): void
    {
        $this->startServer();
        $this->service(['service_credit' => true, 'mode' => 'lost_commit']);
        $db = $this->receiver('pos');
        foreach ([['service_order_items', 'description', 'Servicio sintético', 'Changed'],
            ['accounts_receivable', 'original_amount', 118, 999],
            ['customer_account_movements', 'amount', 118, 999]] as [$table, $field, $original, $corrupt]) {
            $db->exec("UPDATE $table SET $field = ".$db->quote((string) $corrupt));
            $this->service(['service_credit' => true], 409);
            $this->assertNotSame(0, $this->service(['action' => 'reconcile', 'mode' => 'due'])['code']);
            $db->exec("UPDATE $table SET $field = ".$db->quote((string) $original));
        }
        $this->assertSame(0, $this->service(['action' => 'reconcile', 'mode' => 'due'])['code']);
        $this->assertSame('true', $this->service(['service_credit' => true])['replayed']);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public function test_missing_key_direct_sql_entry_and_lost_reserve_changed_source_are_blocked(): void
    {
        $this->startServer();
        $this->noSale($this->service(['payload' => []], 422));
        $this->pos(['action' => 'service'], 503);
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
        $this->noSale($this->service(['mode' => 'lost_reserve'], 503));
        $db = $this->receiver('pos');
        $db->exec("UPDATE service_order_items SET description = 'Changed between attempts'");
        $this->noSale($this->service(['mode' => 'due'], 409));
        $this->assertSame(0, $this->service(['action' => 'reconcile', 'mode' => 'due'])['code']);
        $db->exec("UPDATE service_order_items SET description = 'Servicio sintético'");
        $this->assertSame(1, $this->service()['native']['sales']);
    }
}
