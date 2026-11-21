<?php

namespace Tests\Feature\BusinessCommercial;

use Illuminate\Support\Facades\DB;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\BusinessCommercialTransportTestCase;

final class PosBusinessCashSalesTest extends BusinessCommercialTransportTestCase
{
    private function pos(array $input = [], int $status = 200): array
    {
        $process = new Process(['php', base_path('tests/Fixtures/BusinessCommercial/pos-cash.php'), $this->runtime]);
        $process->setInput(json_encode($input, JSON_THROW_ON_ERROR));
        $process->setTimeout(90);
        $process->mustRun();
        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($status, $result['status'], json_encode($result).' '.($status !== $result['status'] ? @file_get_contents($this->runtime.'/pos/storage/logs/application.log') : ''));

        return $result;
    }

    private function receiver(): PDO
    {
        return new PDO('sqlite:'.$this->runtime.'/pos/database.sqlite');
    }

    private function payload(string $key = 'cash:1', int $quantity = 1): array
    {
        return ['idempotency_key' => $key, 'items' => [['product_id' => 501, 'quantity' => $quantity, 'unit_price' => 100]],
            'payments' => [['payment_method_id' => 501, 'payment_type' => 'cash', 'amount' => 118 * $quantity]]];
    }

    private function assertNoSale(array $result): void
    {
        foreach (['sales', 'payments', 'movements', 'receipts', 'maps'] as $count) {
            $this->assertSame(0, $result['native'][$count], $count);
        }
        $this->assertSame(100, $result['native']['stock']);
    }

    public function test_real_cash_sale_persists_one_payment_stock_movement_and_consumption_across_http_replays(): void
    {
        $this->startServer();
        $first = $this->pos();
        $this->assertSame(1, $first['native']['sales']);
        $this->assertSame(1, $first['native']['payments']);
        $this->assertSame(1, $first['native']['movements']);
        $this->assertSame(99, $first['native']['stock']);
        $this->assertSame(1, $first['native']['receipts']);
        $this->assertSame(1, $first['native']['maps']);
        $this->assertSame(['committed'], $first['native']['outbox']);
        $again = $this->pos();
        $this->assertSame('true', $again['replayed']);
        $this->assertSame($first['body'], $again['body']);
        $this->assertSame($first['native'], $again['native']);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
        $this->assertSame(1, DB::table('business_usage_reservations')->count());
        $this->assertSame(2, DB::table('business_usage_events')->count());
        $this->assertSame(118.0, (float) $this->receiver()->query('SELECT total FROM sales')->fetchColumn());
        $this->assertSame(118.0, (float) $this->receiver()->query('SELECT amount FROM sale_payments')->fetchColumn());
        $this->pos(['payload' => $this->payload(quantity: 2)], 409);
        $this->assertSame('true', $this->pos(['mode' => 'disabled'])['replayed']);
        $this->assertSame('true', $this->pos(['mode' => 'bridge_disabled'])['replayed']);
        $this->pos(['mode' => 'disabled', 'payload' => $this->payload('new')], 503);
        $this->receiver()->exec('UPDATE business_identity_links SET active = 0');
        $this->assertSame('true', $this->pos()['replayed']);
        $this->pos(['payload' => $this->payload('new')], 403);
        $this->receiver()->exec("UPDATE users SET role = 'seller'");
        $this->pos([], 403);
        $this->assertSame(1, (int) $this->receiver()->query('SELECT count(*) FROM sales')->fetchColumn());
        $this->pos(['action' => 'direct'], 503);
        $this->pos(['action' => 'service'], 503);
        $this->pos(['action' => 'down'], 500);
    }

    public function test_unknown_transport_and_quota_denial_never_create_a_sale(): void
    {
        $snapshot = $this->contract->snapshot;
        $snapshot['capacity']['documents'] = 0;
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->startServer();
        $this->assertNoSale($this->pos(['mode' => 'unavailable'], 503));
        $this->assertNoSale($this->pos([], 429));
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
        $payload = $this->payload();
        unset($payload['idempotency_key']);
        $this->assertNoSale($this->pos(['payload' => $payload], 422));
        $this->assertSame(0, DB::table('business_usage_documents')->count());
    }

    public function test_lost_reservation_response_recovers_original_intent_without_a_second_reservation(): void
    {
        $this->startServer();
        $failed = $this->pos(['mode' => 'lost_reserve'], 503);
        $this->assertNoSale($failed);
        $this->assertSame(['reserving'], $failed['native']['outbox']);
        $this->assertSame(1, DB::table('business_usage_reservations')->count());
        $this->pos(['payload' => $this->payload(quantity: 2)], 409);
        $sale = $this->pos(['mode' => 'due']);
        $this->assertSame(1, $sale['native']['sales']);
        $this->assertSame(['committed'], $sale['native']['outbox']);
        $this->assertSame(1, DB::table('business_usage_reservations')->count());
    }

    public function test_lost_commit_reply_and_after_commit_exception_preserve_the_native_result(): void
    {
        $this->startServer();
        $sale = $this->pos(['mode' => 'lost_commit']);
        $this->assertSame(1, $sale['native']['sales']);
        $this->assertSame(['local_committed'], $sale['native']['outbox']);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
        $dry = $this->pos(['action' => 'reconcile', 'options' => ['--dry-run' => true]]);
        $this->assertSame([], $dry['actions']);
        $this->assertSame($sale['native'], $dry['native']);
        $this->assertSame(0, $this->pos(['action' => 'reconcile', 'mode' => 'due'])['code']);
        $this->assertSame($sale['body'], $this->pos()['body']);
        $second = $this->pos(['payload' => $this->payload('second'), 'mode' => 'after_commit_failure']);
        $this->assertSame('true', $second['replayed']);
        $this->assertSame(2, $second['native']['sales']);
        $this->assertSame(2, $second['native']['payments']);
        $this->assertSame(2, $second['native']['maps']);
        $this->assertSame(['committed', 'committed'], $second['native']['outbox']);
        $this->assertSame($second['body'], $this->pos(['payload' => $this->payload('second')])['body']);
    }

    public function test_late_native_validation_releases_and_allows_corrected_input_under_the_same_http_key(): void
    {
        $this->startServer();
        $failed = $this->pos(['mode' => 'late_validation'], 422);
        $this->assertNoSale($failed);
        $this->assertSame(['released'], $failed['native']['outbox']);
        $this->assertSame('retryable', $failed['native']['intents'][0]['state']);
        $this->assertSame(1, DB::table('business_usage_cancellations')->count());
        $corrected = $this->pos(['payload' => $this->payload(quantity: 2)]);
        $this->assertSame(1, $corrected['native']['sales']);
        $this->assertSame(98, $corrected['native']['stock']);
        $this->assertSame(2, $corrected['native']['intents'][0]['generation']);
        $this->assertSame(['released', 'committed'], $corrected['native']['outbox']);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public function test_lost_cancellation_reply_keeps_corrected_input_blocked_until_reconciliation(): void
    {
        $this->startServer();
        $failed = $this->pos(['mode' => 'late_validation_lost_cancel'], 422);
        $this->assertNoSale($failed);
        $this->assertSame('cancelling', $failed['native']['intents'][0]['state']);
        $this->assertSame(['release_pending'], $failed['native']['outbox']);
        $this->assertSame(1, DB::table('business_usage_cancellations')->count());
        $this->receiver()->exec("UPDATE business_commercial_operations SET next_attempt_at = datetime('now', '+5 minutes')");
        $this->assertNoSale($this->pos(['payload' => $this->payload(quantity: 2)], 409));
        $corrected = $this->pos(['mode' => 'due', 'payload' => $this->payload(quantity: 2)]);
        $this->assertSame(1, $corrected['native']['sales']);
        $this->assertSame(98, $corrected['native']['stock']);
        $this->assertSame(['released', 'committed'], $corrected['native']['outbox']);
        $this->assertSame(2, DB::table('business_usage_reservations')->count());
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    #[DataProvider('interruptedAdmissions')]
    public function test_interrupted_admission_is_fenced_and_recovery_never_repeats_a_sale(string $mode, int $status): void
    {
        $this->startServer();
        $this->assertNoSale($this->pos(['mode' => $mode], $status));
        if ($mode === 'map_failure') {
            $this->receiver()->exec('DROP TRIGGER fixture_map_failure');
        }
        if ($mode === 'revoke_link') {
            $this->receiver()->exec('UPDATE business_identity_links SET active = 1');
        }
        if (in_array($mode, ['revoke_role', 'revoke_branch'], true)) {
            $this->receiver()->exec("UPDATE users SET role = 'owner'");
            $this->receiver()->exec('UPDATE branch_user SET can_create_sales = 1');
        }
        $recovered = $this->pos(['action' => 'reconcile', 'mode' => 'due']);
        $this->assertSame(0, $recovered['code']);
        $this->assertNoSale($recovered);
        $this->assertSame('retryable', $recovered['native']['intents'][0]['state']);
        $this->assertSame(0, DB::table('business_usage_reservations')->where('state', 'reserved')->count());
        $sale = $this->pos();
        $this->assertSame(1, $sale['native']['sales']);
        $this->assertSame(2, $sale['native']['intents'][0]['generation']);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public static function interruptedAdmissions(): array
    {
        return [['crash_after_begin', 500], ['orphan_prepare', 500], ['map_failure', 500], ['fenced', 409],
            ['expired_before_native', 409], ['revoke_role', 422], ['revoke_link', 403], ['revoke_branch', 403]];
    }

    public function test_catch_cannot_return_a_receipt_after_permissions_are_revoked(): void
    {
        $this->startServer();
        $result = $this->pos(['mode' => 'after_commit_revoked'], 422);
        $this->assertSame(1, $result['native']['sales']);
        $this->assertSame(1, $result['native']['payments']);
        $this->assertSame(['executing'], $result['native']['outbox']);
        $this->pos([], 403);
        $this->assertSame(0, $this->pos(['action' => 'reconcile'])['code']);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public function test_catch_cannot_return_a_receipt_from_a_newer_generation_or_corrupted_evidence(): void
    {
        $this->startServer();
        $result = $this->pos(['mode' => 'after_commit_generation'], 409);
        $this->assertSame(1, $result['native']['sales']);
        $this->receiver()->exec('UPDATE pos_business_cash_intents SET generation = 1');
        $this->assertSame(0, $this->pos(['action' => 'reconcile'])['code']);
        $this->receiver()->exec("UPDATE pos_operation_receipts SET response_body = '{}'");
        $this->pos([], 409);
        $this->assertSame(1, (int) $this->receiver()->query('SELECT count(*) FROM sales')->fetchColumn());
    }

    public function test_confirmed_standalone_with_an_identity_link_preserves_native_optional_keys(): void
    {
        DB::table('business_contract_items')->where('identity_link_id', DB::table('business_identity_links')->where('service', 'pos')->value('id'))->delete();
        DB::table('business_identity_links')->where('service', 'pos')->update(['central_company_id' => 2, 'central_subscriber_id' => 2]);
        $this->startServer();
        $payload = $this->payload();
        unset($payload['idempotency_key']);
        $standalone = $this->pos(['payload' => $payload]);
        $this->assertSame(1, $standalone['native']['sales']);
        $this->assertSame([], $standalone['native']['outbox']);
        $this->assertSame(['snapshot'], $standalone['actions']);
        $this->receiver()->exec('DELETE FROM business_identity_links');
        $unlinked = $this->pos(['payload' => $payload]);
        $this->assertSame(2, $unlinked['native']['sales']);
        $this->assertSame([], $unlinked['actions']);
        $this->assertSame(0, DB::table('business_usage_documents')->count());
    }

    #[DataProvider('missingNativeEvidence')]
    public function test_executed_intent_with_missing_native_evidence_never_releases_or_repeats_the_sale(string $mode, int $status): void
    {
        $this->startServer();
        $damaged = $this->pos(['mode' => $mode], $status);
        $this->assertSame(1, $damaged['native']['sales']);
        $this->assertSame(1, $damaged['native']['payments']);
        $this->assertSame(99, $damaged['native']['stock']);
        $this->assertSame(0, $damaged['native']['receipts']);
        $this->assertSame(0, $damaged['native']['maps']);
        $this->assertSame('executed', $damaged['native']['intents'][0]['state']);
        $this->assertSame(1, $this->pos(['action' => 'reconcile'])['code']);
        $retry = $this->pos([], 409);
        $this->assertSame($damaged['native'], $retry['native']);
        $this->assertSame('reserved', DB::table('business_usage_reservations')->sole()->state);
        $this->assertSame(0, DB::table('business_usage_cancellations')->count());
    }

    public static function missingNativeEvidence(): array
    {
        return [['lost_native_evidence', 500], ['lost_native_evidence_validation', 422]];
    }
}
