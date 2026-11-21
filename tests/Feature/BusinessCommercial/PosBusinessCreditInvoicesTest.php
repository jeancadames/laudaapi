<?php

namespace Tests\Feature\BusinessCommercial;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\PosFiscalHandoffTestCase;

final class PosBusinessCreditInvoicesTest extends PosFiscalHandoffTestCase
{
    private function credit(array $input = [], int $status = 302): array
    {
        return $this->pos(['credit' => true, ...$input], $status);
    }

    private function noInvoices(array $result): void
    {
        foreach (['sales', 'payments', 'movements', 'receipts', 'maps'] as $field) {
            $this->assertSame(0, $result['native'][$field], $field);
        }
        $this->assertSame(100, $result['native']['stock']);
        $this->assertSame(0, $result['credit']['accounts']);
        $this->assertSame(0, $result['credit']['charges']);
        foreach ($result['credit']['notes'] as $note) {
            $this->assertNull($note['invoice_id']);
        }
    }

    public function test_individual_credit_uses_native_account_and_original_charge_once(): void
    {
        $this->startServer();
        $first = $this->credit();
        $this->assertSame(1, $first['native']['sales']);
        $this->assertSame(1, $first['credit']['accounts']);
        $this->assertSame(1, $first['credit']['charges']);
        $this->assertSame(0, $first['native']['payments']);
        $this->assertSame(0, $first['native']['movements']);
        $this->assertSame(100, $first['native']['stock']);
        $this->assertSame(['committed'], $first['native']['outbox']);
        $this->assertSame('completed', $first['credit']['intents'][0]['state']);
        $again = $this->credit();
        $this->assertSame('true', $again['replayed']);
        $this->assertSame($first['native'], $again['native']);
        $this->assertSame($first['credit'], $again['credit']);
        $this->assertSame('true', $this->credit(['mode' => 'credit_disabled'])['replayed']);
        $this->credit(['payload' => ['idempotency_key' => 'new']], 422);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public function test_bulk_creates_distinct_documents_for_two_notes_of_the_same_order(): void
    {
        $this->startServer();
        $first = $this->credit(['bulk' => true]);
        $this->assertSame(2, $first['native']['sales']);
        $this->assertSame(2, $first['credit']['accounts']);
        $this->assertSame(2, $first['credit']['charges']);
        $this->assertSame(1, $first['native']['receipts']);
        $this->assertSame(0, $first['native']['movements']);
        $this->assertSame(['committed', 'committed'], $first['native']['outbox']);
        $this->assertCount(2, array_unique(array_column($first['credit']['items'], 'central_document_id')));
        $this->assertSame('true', $this->credit(['bulk' => true])['replayed']);
        $this->assertSame(2, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public function test_quota_for_only_one_child_cancels_the_entire_batch_before_native_effects(): void
    {
        $snapshot = $this->contract->snapshot;
        $snapshot['capacity']['documents'] = 1;
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->startServer();
        $failed = $this->credit(['bulk' => true], 429);
        $this->noInvoices($failed);
        $this->assertSame('retryable', $failed['credit']['intents'][0]['state']);
        $this->assertSame(0, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
        $this->assertSame(0, DB::table('business_usage_reservations')->where('state', 'reserved')->count());
        $snapshot['capacity']['documents'] = 2;
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $done = $this->credit(['bulk' => true]);
        $this->assertSame(2, $done['native']['sales']);
        $this->assertSame(2, $done['credit']['intents'][0]['generation']);
    }

    public function test_last_account_failure_rolls_back_all_invoices_charges_and_sequences(): void
    {
        $this->startServer();
        $failed = $this->credit(['bulk' => true, 'mode' => 'late_credit_validation'], 422);
        $this->noInvoices($failed);
        $this->assertSame(['released', 'released'], $failed['native']['outbox']);
        $this->assertSame('retryable', $failed['credit']['intents'][0]['state']);
        $this->assertSame(0, (int) $this->receiver('pos')->query('SELECT count(*) FROM fiscal_distribution_approvals WHERE consumed_at IS NOT NULL')->fetchColumn());
        $done = $this->credit(['bulk' => true, 'payload' => ['idempotency_key' => 'credit:1', 'notes' => 'Corrected']]);
        $this->assertSame(2, $done['native']['sales']);
        $this->assertSame(2, $done['credit']['charges']);
        $this->assertSame(2, $done['credit']['intents'][0]['generation']);
        $this->assertSame([1, 2], array_map('intval', $this->receiver('pos')->query('SELECT annual_sequence FROM sales ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN)));
    }

    public function test_individual_approvals_do_not_authorize_a_batch_exceeding_the_order_commitment(): void
    {
        $this->startServer();
        $failed = $this->credit(['bulk' => true, 'mode' => 'credit_batch_exceeds_commitment'], 422);
        $this->noInvoices($failed);
        $this->assertArrayHasKey('fiscal_distribution', $failed['body']['errors']);
        $this->assertSame(['released', 'released'], $failed['native']['outbox']);
        $this->assertSame(0, (int) $this->receiver('pos')->query('SELECT count(*) FROM fiscal_distribution_approvals WHERE consumed_at IS NOT NULL')->fetchColumn());
        $this->assertSame(0, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public function test_missing_second_approval_rolls_back_the_entire_batch(): void
    {
        $this->startServer();
        $this->credit(['action' => 'setup'], 200);
        $this->receiver('pos')->exec('DELETE FROM fiscal_distribution_approvals WHERE source_id = 502');
        $failed = $this->credit(['bulk' => true], 422);
        $this->noInvoices($failed);
        $this->assertArrayHasKey('fiscal_distribution', $failed['body']['errors']);
        $this->assertSame(['released', 'released'], $failed['native']['outbox']);
        $this->assertSame(0, (int) $this->receiver('pos')->query('SELECT count(*) FROM fiscal_distribution_approvals WHERE consumed_at IS NOT NULL')->fetchColumn());
    }

    public function test_single_note_batch_preserves_explicit_approval_of_individual_excess(): void
    {
        $this->startServer();
        $done = $this->credit(['bulk' => true, 'mode' => 'credit_individual_exceeds_commitment',
            'payload' => ['idempotency_key' => 'credit:1', 'invoices' => [['delivery_note_id' => 501]]]]);
        $this->assertSame(1, $done['native']['sales']);
        $this->assertSame(['committed'], $done['native']['outbox']);
        $this->assertSame(1, $done['credit']['accounts']);
    }

    public function test_disabled_admission_and_verified_standalone_preserve_their_boundaries(): void
    {
        DB::table('business_contract_items')->where('identity_link_id', DB::table('business_identity_links')->where('service', 'pos')->value('id'))->delete();
        DB::table('business_identity_links')->where('service', 'pos')->update(['central_company_id' => 2, 'central_subscriber_id' => 2]);
        $this->startServer();
        $disabled = $this->credit(['mode' => 'credit_disabled'], 503);
        $this->noInvoices($disabled);
        $this->assertSame([], $disabled['actions']);
        $done = $this->credit(['bulk' => true, 'payload' => ['invoices' => [['delivery_note_id' => 501], ['delivery_note_id' => 502]]]]);
        $this->assertSame(2, $done['native']['sales']);
        $this->assertSame(2, $done['credit']['accounts']);
        $this->assertSame([], $done['native']['outbox']);
        $this->assertSame(['snapshot'], $done['actions']);
        $this->assertSame(0, DB::table('business_usage_documents')->count());
    }

    public function test_lost_second_reserve_binds_full_manifest_and_command_releases_all_children(): void
    {
        $this->startServer();
        $failed = $this->credit(['bulk' => true, 'mode' => 'lost_second_reserve'], 503);
        $this->noInvoices($failed);
        $this->assertCount(2, $failed['credit']['items']);
        $this->credit(['bulk' => true, 'payload' => ['idempotency_key' => 'credit:1', 'notes' => 'Changed']], 409);
        $dry = $this->credit(['bulk' => true, 'action' => 'reconcile', 'options' => ['--dry-run' => true]], 200);
        $this->assertSame([], $dry['actions']);
        $this->assertSame($failed['native'], $dry['native']);
        $recovered = $this->credit(['bulk' => true, 'action' => 'reconcile', 'mode' => 'due'], 200);
        $this->noInvoices($recovered);
        $this->assertSame(0, $recovered['code']);
        $this->assertSame(['released', 'released'], $recovered['native']['outbox']);
        $done = $this->credit(['bulk' => true]);
        $this->assertSame(2, $done['credit']['intents'][0]['generation']);
        $this->assertSame(2, $done['native']['sales']);
    }

    #[DataProvider('interruptedAdmission')]
    public function test_interrupted_admission_never_creates_partial_native_effects(string $mode, int $status): void
    {
        $this->startServer();
        $failed = $this->credit(['bulk' => true, 'mode' => $mode], $status);
        $this->noInvoices($failed);
        $this->assertSame(0, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
        if ($mode === 'revoke_role') {
            $this->receiver('pos')->exec("UPDATE users SET role = 'owner'");
        }
        if ($mode === 'revoke_link') {
            $this->receiver('pos')->exec('UPDATE business_identity_links SET active = 1');
        }
        $recovered = $this->credit(['action' => 'reconcile', 'mode' => 'due'], 200);
        $this->assertSame(0, $recovered['code']);
        $this->assertSame('retryable', $recovered['credit']['intents'][0]['state']);
        $this->noInvoices($recovered);
    }

    public static function interruptedAdmission(): array
    {
        return [['credit_changed', 409], ['credit_fenced', 409], ['revoke_role', 403],
            ['revoke_link', 403], ['credit_orphan_prepare', 500], ['crash_after_begin', 500]];
    }

    public function test_billing_membership_is_independent_of_cash_permissions_and_foreign_notes_are_rejected(): void
    {
        $this->startServer();
        $this->credit(['action' => 'setup'], 200);
        $db = $this->receiver('pos');
        $db->exec("UPDATE users SET role = 'billing'");
        $db->exec('UPDATE branch_user SET can_create_sales = 0, can_collect_payments = 0, is_active = 0');
        $this->noInvoices($this->credit([], 403));
        $db->exec('UPDATE branch_user SET is_active = 1');
        $this->credit(['note_id' => 999999], 404);
        $db->exec('UPDATE delivery_notes SET branch_id = 999999 WHERE id = 502');
        $this->noInvoices($this->credit(['bulk' => true], 404));
        $first = $this->credit();
        $this->assertSame(1, $first['credit']['accounts']);
        $db->exec('UPDATE branch_user SET is_active = 0');
        $this->credit([], 403);
        $this->assertSame(1, DB::table('business_usage_reservations')->count());
    }

    public function test_lost_commit_can_recover_both_results_and_later_payments_do_not_change_evidence(): void
    {
        $this->startServer();
        $first = $this->credit(['bulk' => true, 'mode' => 'lost_commit']);
        $this->assertSame(2, $first['native']['sales']);
        $this->assertSame(['local_committed', 'local_committed'], $first['native']['outbox']);
        $paid = $this->credit(['action' => 'credit_payment'], 200);
        $this->assertTrue($paid['body']['created']);
        $this->assertSame(1, $paid['credit']['payments']);
        $this->assertSame(1, $paid['credit']['payment_movements']);
        $this->assertSame(18.0, (float) $paid['credit']['balances'][0]['paid_amount']);
        $this->assertSame(100.0, (float) $paid['credit']['balances'][0]['balance']);
        $this->assertSame(118.0, (float) $paid['credit']['balances'][1]['balance']);
        $this->assertSame($first['native'], $paid['native']);
        $this->assertSame([], $paid['actions']);
        $done = $this->credit(['action' => 'reconcile', 'mode' => 'due'], 200);
        $this->assertSame(0, $done['code']);
        $this->assertSame(['committed', 'committed'], $done['native']['outbox']);
        $again = $this->credit(['bulk' => true]);
        $this->assertSame('true', $again['replayed']);
        $this->assertSame(2, $again['credit']['charges']);
        $this->assertSame($paid['credit']['balances'], $again['credit']['balances']);
        $this->assertSame(1, $again['credit']['payments']);
        $this->assertSame(1, $again['credit']['payment_movements']);
        $this->assertSame(2, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
        $this->receiver('pos')->exec("UPDATE customer_account_movements SET amount = 999 WHERE movement_type = 'charge'");
        $this->credit(['bulk' => true], 409);
    }

    public function test_missing_manifest_or_evidence_cannot_cancel_an_executed_batch(): void
    {
        $this->startServer();
        $first = $this->credit(['bulk' => true, 'mode' => 'lost_commit']);
        $this->assertSame(2, $first['native']['sales']);
        $db = $this->receiver('pos');
        $db->exec('UPDATE pos_business_credit_items SET sale_id = NULL, evidence_hash = NULL, central_document_id = NULL');
        $db->exec('UPDATE pos_business_credit_intents SET receipt_id = NULL');
        $db->exec('DELETE FROM pos_operation_receipts');
        $done = $this->credit(['action' => 'reconcile', 'mode' => 'due'], 200);
        $this->assertSame(1, $done['code']);
        $this->assertSame('executed', $done['credit']['intents'][0]['state']);
        $this->assertSame(0, DB::table('business_usage_cancellations')->count());
    }

    public function test_changed_invoice_lines_block_recovery_until_original_evidence_is_restored(): void
    {
        $this->startServer();
        $first = $this->credit(['bulk' => true, 'mode' => 'lost_commit']);
        $db = $this->receiver('pos');
        $line = $db->query('SELECT id, description FROM sale_items ORDER BY id LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
        $update = $db->prepare('UPDATE sale_items SET description = ? WHERE id = ?');
        $update->execute(['Changed invoice line', $line['id']]);
        $this->credit(['bulk' => true], 409);
        $blocked = $this->credit(['action' => 'reconcile', 'mode' => 'due'], 200);
        $this->assertSame(1, $blocked['code']);
        $this->assertSame($first['native'], $blocked['native']);
        $this->assertSame(0, DB::table('business_usage_cancellations')->count());

        $update->execute([$line['description'], $line['id']]);
        $done = $this->credit(['action' => 'reconcile', 'mode' => 'due'], 200);
        $this->assertSame(0, $done['code']);
        $this->assertSame(['committed', 'committed'], $done['native']['outbox']);
        $this->assertSame('true', $this->credit(['bulk' => true])['replayed']);
        $db->exec("UPDATE sales SET ecf_type = '32'");
        $this->credit(['bulk' => true], 409);
    }

    public function test_bulk_fiscal_handoff_emits_and_consumes_each_invoice_once(): void
    {
        $this->startServer(true);
        $first = $this->credit(['bulk' => true]);
        $this->assertSame(2, $first['native']['sales']);
        $this->assertCount(2, $first['fiscal']);
        foreach ($first['fiscal'] as $document) {
            $this->assertNotEmpty($document['encf'], json_encode($document).' '.$this->logs());
        }
        $this->assertSame(2, (int) $this->receiver('ecf')->query('SELECT count(*) FROM ecf_documents')->fetchColumn());
        $this->assertSame(2, DB::table('business_fiscal_handoffs')->count());
        $this->assertSame(2, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
        $this->assertSame('true', $this->credit(['bulk' => true])['replayed']);
    }
}
