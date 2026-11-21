<?php

namespace Tests\Feature\BusinessCommercial;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\BusinessCommercialTestCase;

final class CommercialCancellationTest extends BusinessCommercialTestCase
{
    public function test_cancellation_before_reservation_is_durable_idempotent_and_blocks_late_admission(): void
    {
        $operation = $this->operation();
        $key = ['operation_key' => $operation['operation_key']];
        $cancelled = $this->exchange('pos', 'cancel', $key);
        $this->assertSame($key + ['state' => 'cancelled'], $cancelled);
        $this->assertSame($cancelled, $this->exchange('pos', 'cancel', $key));
        $this->exchange('pos', 'reserve', $operation, 409);
        $this->exchange('pos', 'reference', $operation, 409);
        $this->exchange('pos', 'commit', $key, 404);
        $this->assertSame(1, DB::table('business_usage_cancellations')->count());
        $this->assertSame(0, DB::table('business_usage_documents')->count());
    }

    public function test_cancellation_after_reservation_releases_only_its_own_source(): void
    {
        $operation = $this->operation();
        $reserved = $this->exchange('pos', 'reserve', $operation);
        $other = $this->operation('other', ['document_id' => $reserved['document_id']]);
        $this->exchange('ecf', 'reserve', $other);
        $released = $this->exchange('pos', 'cancel', ['operation_key' => $operation['operation_key']]);
        $this->assertSame('released', $released['state']);
        $this->assertSame($released, $this->exchange('pos', 'cancel', ['operation_key' => $operation['operation_key']]));
        $this->assertSame(1, $this->exchange('pos', 'snapshot')['documents']['reserved']);
        $this->exchange('ecf', 'commit', ['operation_key' => $other['operation_key']]);
        $this->assertSame(1, $this->exchange('pos', 'snapshot')['documents']['consumed']);
    }

    public function test_committed_or_referenced_operations_cannot_be_cancelled(): void
    {
        $operation = $this->operation();
        $doc = $this->exchange('pos', 'reserve', $operation);
        $this->exchange('pos', 'commit', ['operation_key' => $operation['operation_key']]);
        $this->exchange('pos', 'cancel', ['operation_key' => $operation['operation_key']], 409);
        $reference = $this->operation('reference', ['document_id' => $doc['document_id']]);
        $this->exchange('cumplimiento', 'reference', $reference);
        $this->exchange('cumplimiento', 'cancel', ['operation_key' => $reference['operation_key']], 409);
        $this->assertSame(0, DB::table('business_usage_cancellations')->count());
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public function test_cancellation_after_coverage_or_mapping_loss_is_safe_but_revoked_link_remains_blocked(): void
    {
        $operation = $this->operation();
        $this->exchange('pos', 'reserve', $operation);
        DB::table('payments')->update(['amount' => 0]);
        DB::table('business_contract_items')->where('identity_link_id', DB::table('business_identity_links')->where('service', 'pos')->value('id'))->delete();
        $this->assertSame('released', $this->exchange('pos', 'cancel', ['operation_key' => $operation['operation_key']])['state']);
        DB::table('business_identity_links')->where('service', 'pos')->update(['active' => false]);
        $this->exchange('pos', 'cancel', ['operation_key' => $operation['operation_key']], 403);
    }

    public function test_cancellation_is_scoped_to_service_company_and_authenticated_link(): void
    {
        $operation = $this->operation();
        $key = ['operation_key' => $operation['operation_key']];
        $this->exchange('pos', 'reserve', $operation);
        $this->exchange('ecf', 'cancel', $key);
        $this->assertSame('reserved', DB::table('business_usage_reservations')->sole()->state);
        $this->exchange('pos', 'cancel', $key + ['local_company_id' => 502], 403);
        $this->exchange('pos', 'cancel', $key + ['central_company_id' => 2, 'central_subscriber_id' => 2], 403);
        $this->assertSame(1, DB::table('business_usage_cancellations')->count());
    }

    public function test_rejected_reservation_can_be_cancelled_without_allocating_capacity(): void
    {
        $snapshot = $this->contract->snapshot;
        $snapshot['capacity']['documents'] = 0;
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $operation = $this->operation();
        $this->exchange('pos', 'reserve', $operation, 429);
        $this->assertSame('cancelled', $this->exchange('pos', 'cancel', ['operation_key' => $operation['operation_key']])['state']);
        $this->assertSame(0, DB::table('business_usage_documents')->count());
    }

    public function test_cancellation_failure_rolls_back_release_and_audit(): void
    {
        $operation = $this->operation();
        $this->exchange('pos', 'reserve', $operation);
        DB::statement("CREATE TRIGGER fail_cancel BEFORE INSERT ON business_usage_cancellations BEGIN SELECT RAISE(ABORT, 'synthetic cancellation failure'); END");
        $this->withoutExceptionHandling();
        try {
            $this->exchange('pos', 'cancel', ['operation_key' => $operation['operation_key']]);
            $this->fail('Cancellation evidence must be atomic.');
        } catch (QueryException $error) {
            $this->assertStringContainsString('synthetic cancellation failure', $error->getMessage());
        }
        $this->assertSame('reserved', DB::table('business_usage_reservations')->sole()->state);
        $this->assertSame(1, DB::table('business_usage_events')->count());
        $this->assertSame(0, DB::table('business_usage_cancellations')->count());
    }

    public function test_missing_migration_rejects_cancellation_without_changing_existing_wire_operations(): void
    {
        Schema::drop('business_usage_cancellations');
        $operation = $this->operation();
        $this->exchange('pos', 'cancel', ['operation_key' => $operation['operation_key']], 503);
        $this->assertFalse(Schema::hasTable('business_usage_cancellations'));
        $this->assertSame('reserved', $this->exchange('pos', 'reserve', $operation)['state']);
    }
}
