<?php

namespace Tests\Feature\BusinessCommercial;

use App\Services\BusinessIdentity\IdentityToken;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BusinessCommercialTestCase;

class CommercialTest extends BusinessCommercialTestCase
{
    public static function services(): array
    {
        return array_map(fn ($service) => [$service], ['ecf', 'pos', 'bys', 'cumplimiento']);
    }

    #[DataProvider('services')]
    public function test_all_solutions_read_the_same_paid_contract_without_writes(string $service): void
    {
        $result = $this->exchange($service, 'snapshot');
        $this->assertTrue($result['managed']);
        $this->assertTrue($result['access']);
        $this->assertSame($this->contract->snapshot_hash, $result['snapshot_hash']);
        $this->assertSame($this->quote->snapshot['capacity'], $result['capacity']);
        $this->assertSame($this->quote->snapshot['capacity_policy'], $result['capacity_policy']);
        $this->assertSame(500, $result['documents']['remaining']);
        $this->assertSame(0, DB::table('business_usage_documents')->count());
    }

    public function test_reservation_commit_retries_and_reconciliation_count_once(): void
    {
        $operation = $this->operation();
        $first = $this->exchange('pos', 'reserve', $operation);
        $this->assertSame('reserved', $first['state']);
        $this->assertSame($first, $this->exchange('pos', 'reserve', $operation));
        $this->assertSame(1, $this->exchange('bys', 'snapshot')['documents']['reserved']);
        $committed = $this->exchange('pos', 'commit', ['operation_key' => $operation['operation_key']]);
        $this->assertSame('committed', $committed['state']);
        $this->assertSame($committed, $this->exchange('pos', 'commit', ['operation_key' => $operation['operation_key']]));
        $this->assertSame($committed, $this->exchange('pos', 'reconcile', ['operation_key' => $operation['operation_key']]));
        $this->assertSame($committed, $this->exchange('pos', 'reserve', $operation));
        $usage = $this->exchange('ecf', 'snapshot')['documents'];
        $this->assertSame([1, 0, 499], [$usage['consumed'], $usage['reserved'], $usage['remaining']]);
        $this->assertSame(2, DB::table('business_usage_events')->count());
        $this->exchange('pos', 'release', ['operation_key' => $operation['operation_key']], 409);
    }

    public function test_shared_origin_pos_ecf_bys_and_cumplimiento_consumes_one_document(): void
    {
        $sale = $this->operation();
        $first = $this->exchange('pos', 'reserve', $sale);
        $this->exchange('pos', 'commit', ['operation_key' => $sale['operation_key']]);
        $fiscal = ['issuer' => '101010101', 'ncf' => 'E310000000001'];
        $emission = $this->operation('invoice:9', ['document_id' => $first['document_id'], 'fiscal' => $fiscal]);
        $ecf = $this->exchange('ecf', 'reserve', $emission);
        $this->exchange('ecf', 'commit', ['operation_key' => $emission['operation_key']]);
        $manual = $this->operation('purchase:7', ['fiscal' => $fiscal]);
        $bys = $this->exchange('bys', 'reserve', $manual);
        $this->exchange('bys', 'commit', ['operation_key' => $manual['operation_key']]);
        $xml = $this->operation('xml:8', ['fiscal' => $fiscal]);
        $this->exchange('bys', 'reserve', $xml);
        $this->exchange('bys', 'commit', ['operation_key' => $xml['operation_key']]);
        $reference = $this->exchange('cumplimiento', 'reference', $this->operation('fiscal:5', ['fiscal' => $fiscal]));
        $this->assertSame('referenced', $reference['state']);
        $this->assertSame([$first['document_id']], array_unique([$ecf['document_id'], $bys['document_id'], $reference['document_id']]));
        $this->assertSame(1, $this->exchange('pos', 'snapshot')['documents']['consumed']);
        $this->assertSame(1, DB::table('business_usage_documents')->count());
    }

    public function test_idempotency_conflict_does_not_mutate_aliases(): void
    {
        $operation = $this->operation();
        $this->exchange('pos', 'reserve', $operation);
        $operation['document']['source_reference'] = 'sale:2';
        $this->exchange('pos', 'reserve', $operation, 409);
        $this->assertSame(1, DB::table('business_usage_aliases')->count());
    }

    public function test_canonical_conflicts_require_reconciliation_without_merging(): void
    {
        $fiscal = ['issuer' => '101010101', 'ncf' => 'E310000000001'];
        $this->exchange('bys', 'reserve', $this->operation('first', ['fiscal' => $fiscal]));
        $second = $this->exchange('pos', 'reserve', $this->operation('second'));
        $this->exchange('ecf', 'reserve', $this->operation('third', ['document_id' => $second['document_id'], 'fiscal' => $fiscal]), 409);
        $this->assertSame(2, DB::table('business_usage_documents')->count());
        $this->assertSame(2, DB::table('business_usage_reservations')->count());
    }

    public function test_one_source_cannot_be_reused_for_a_different_fiscal_document(): void
    {
        $this->exchange('bys', 'reserve', $this->operation('first', ['fiscal' => ['issuer' => '101010101', 'ncf' => 'E310000000001']]));
        $this->exchange('bys', 'reserve', $this->operation('first', ['fiscal' => ['issuer' => '101010101', 'ncf' => 'E310000000002']]), 409);
        $this->assertSame(2, DB::table('business_usage_aliases')->count());
    }

    public function test_release_requires_every_source_and_never_refunds_a_committed_document(): void
    {
        $one = $this->operation();
        $document = $this->exchange('pos', 'reserve', $one);
        $two = $this->operation('invoice', ['document_id' => $document['document_id']]);
        $this->exchange('ecf', 'reserve', $two);
        $this->exchange('pos', 'release', ['operation_key' => $one['operation_key']]);
        $this->assertSame(1, $this->exchange('pos', 'snapshot')['documents']['reserved']);
        $receipt = $this->exchange('ecf', 'release', ['operation_key' => $two['operation_key']]);
        $this->assertSame($receipt, $this->exchange('ecf', 'release', ['operation_key' => $two['operation_key']]));
        $this->assertSame(500, $this->exchange('pos', 'snapshot')['documents']['remaining']);
        $this->exchange('pos', 'commit', ['operation_key' => $one['operation_key']], 409);
        $this->assertSame(4, DB::table('business_usage_events')->count());
    }

    public function test_capacity_exhaustion_allows_same_document_and_reading(): void
    {
        // Synthetic capacity one: operational snapshots are immutable through the model.
        $snapshot = $this->contract->snapshot;
        $snapshot['capacity']['documents'] = 1;
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $first = $this->exchange('pos', 'reserve', $this->operation());
        $this->exchange('bys', 'reserve', $this->operation('different'), 429);
        $this->exchange('ecf', 'reserve', $this->operation('same', ['document_id' => $first['document_id']]));
        $this->assertSame(0, $this->exchange('cumplimiento', 'snapshot')['documents']['remaining']);
        $this->assertSame(1, DB::table('business_usage_documents')->count());
    }

    public function test_compliance_can_only_reference_confirmed_origin(): void
    {
        $operation = $this->operation();
        $doc = $this->exchange('pos', 'reserve', $operation);
        $reference = $this->operation('compliance', ['document_id' => $doc['document_id']]);
        $this->exchange('cumplimiento', 'reserve', $reference, 422);
        $this->exchange('cumplimiento', 'reference', $reference, 409);
        $this->exchange('pos', 'commit', ['operation_key' => $operation['operation_key']]);
        $receipt = $this->exchange('cumplimiento', 'reference', $reference);
        $this->assertSame($receipt, $this->exchange('cumplimiento', 'reference', $reference));
        $this->exchange('cumplimiento', 'commit', ['operation_key' => $reference['operation_key']], 409);
    }

    public function test_cross_company_and_cross_service_keys_cannot_read_or_settle(): void
    {
        $operation = $this->operation();
        $this->exchange('pos', 'reserve', $operation);
        $this->exchange('ecf', 'commit', ['operation_key' => $operation['operation_key']], 404);
        $this->exchange('pos', 'snapshot', ['local_company_id' => 502], 403);
        $this->exchange('pos', 'snapshot', ['central_company_id' => 2, 'central_subscriber_id' => 2], 403);
        $this->exchange('pos', 'reserve', $this->operation('forged', ['document_id' => (string) Str::uuid()]), 409);
    }

    public static function coverageFailures(): array
    {
        return [['payment'], ['company'], ['subscriber'], ['service'], ['item'], ['subscription'], ['activation_flag']];
    }

    #[DataProvider('coverageFailures')]
    public function test_fresh_coverage_fails_closed_but_admitted_work_can_be_reconciled(string $failure): void
    {
        $operation = $this->operation();
        $this->exchange('pos', 'reserve', $operation);
        match ($failure) {
            'payment' => DB::table('payments')->update(['amount' => 0]),
            'company' => DB::table('companies')->where('id', 1)->update(['active' => false]),
            'subscriber' => DB::table('subscribers')->where('id', 1)->update(['active' => false]),
            'service' => DB::table('services')->where('service_key', 'pos')->update(['active' => false]),
            'item' => DB::table('subscription_items')->update(['status' => 'cancelled']),
            'subscription' => DB::table('subscriptions')->update(['status' => 'expired']),
            'activation_flag' => config()->set('business_commercial.activation_enabled', false),
        };
        $result = $this->exchange('pos', 'snapshot');
        $this->assertTrue($result['managed']);
        $this->assertFalse($result['access']);
        $this->exchange('pos', 'reserve', $this->operation('new'), 403);
        $this->assertSame('committed', $this->exchange('pos', 'commit', ['operation_key' => $operation['operation_key']])['state']);
    }

    public function test_revocation_blocks_including_old_receipts(): void
    {
        $operation = $this->operation();
        $this->exchange('pos', 'reserve', $operation);
        DB::table('business_identity_links')->where('service', 'pos')->update(['active' => false]);
        $this->exchange('pos', 'reconcile', ['operation_key' => $operation['operation_key']], 403);
        $this->exchange('pos', 'snapshot', [], 403);
    }

    public function test_lost_confirmation_after_period_end_keeps_original_usage_period(): void
    {
        $operation = $this->operation();
        $first = $this->exchange('pos', 'reserve', $operation);
        $this->travelTo($this->contract->period_ends_at->addDay());
        $this->assertFalse($this->exchange('pos', 'snapshot')['access']);
        $result = $this->exchange('pos', 'commit', ['operation_key' => $operation['operation_key']]);
        $this->assertSame($first['period_starts_at'], $result['period_starts_at']);
        $this->assertSame('committed', $result['state']);
    }

    public function test_expired_permits_remain_reserved_until_explicit_reconciliation(): void
    {
        $operation = $this->operation();
        $receipt = $this->exchange('pos', 'reserve', $operation);
        $this->travelTo(now()->addMinutes(3));
        $this->assertSame($receipt, $this->exchange('pos', 'reserve', $operation));
        $this->assertSame(1, $this->exchange('pos', 'snapshot')['documents']['reserved']);
    }

    public function test_authentication_rejects_missing_tampered_expired_other_peer_and_identity_tokens(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::now());
        $codec = $this->codec('pos');
        $valid = $codec->encode('pos', 'https://hub.example', 'request', $this->data('pos', 'snapshot'), now()->timestamp);
        $tokens = ['', substr($valid, 0, -4).'xxxx',
            $codec->encode('ecf', 'https://hub.example', 'request', $this->data('pos', 'snapshot'), now()->timestamp),
            $codec->encode('pos', 'https://other.example', 'request', [], now()->timestamp),
            $codec->encode('pos', 'https://hub.example', 'request', [], now()->timestamp - 120),
            $codec->encode('pos', 'https://hub.example', 'request', [], now()->timestamp + 31),
            $codec->encode('pos', 'https://hub.example', 'response', [], now()->timestamp),
            (new IdentityToken(config('business_identity.peers.pos.secret')))->encode('pos', 'https://hub.example', 'launch', [], now()->timestamp),
            str_repeat('a', 17000)];
        foreach ($tokens as $token) {
            $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/business/commercial/v1/pos')->assertUnauthorized();
        }
        $this->assertSame(0, DB::table('business_usage_documents')->count());
    }

    public function test_flag_and_missing_migration_reject_without_creating_tables(): void
    {
        config()->set('business_commercial_bridge.enabled', false);
        $this->postJson('/api/business/commercial/v1/pos')->assertStatus(503);
        config()->set('business_commercial_bridge.enabled', true);
        (require base_path('database/migrations/2026_11_07_000010_create_business_document_usage.php'))->down();
        $this->exchange('pos', 'snapshot', [], 503);
    }

    public function test_usage_history_prevents_rollback(): void
    {
        $this->exchange('pos', 'reserve', $this->operation());
        $this->expectException(\RuntimeException::class);
        (require base_path('database/migrations/2026_11_07_000010_create_business_document_usage.php'))->down();
    }

    public function test_database_failure_rolls_back_document_alias_and_reservation(): void
    {
        DB::statement("CREATE TRIGGER fail_business_usage_event BEFORE INSERT ON business_usage_events BEGIN SELECT RAISE(ABORT, 'synthetic event failure'); END");
        $this->withoutExceptionHandling();
        try {
            $this->exchange('pos', 'reserve', $this->operation());
            $this->fail('The event insert should fail.');
        } catch (QueryException $expected) {
            $this->assertStringContainsString('synthetic event failure', $expected->getMessage());
        }
        foreach (['business_usage_documents', 'business_usage_aliases', 'business_usage_reservations', 'business_usage_events'] as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
    }

    public function test_deleted_contract_mapping_cannot_fall_back_to_local_billing(): void
    {
        $link = DB::table('business_identity_links')->where('service', 'pos')->sole();
        DB::table('business_contract_items')->where('identity_link_id', $link->id)->delete();
        $this->exchange('pos', 'snapshot', [], 409);
    }

    public function test_linked_company_without_a_business_contract_is_explicitly_unmanaged(): void
    {
        DB::table('business_identity_links')->where('service', 'pos')->update(['central_company_id' => 2, 'central_subscriber_id' => 2, 'local_company_id' => 502]);
        DB::table('business_contract_items')->where('service_id', DB::table('services')->where('service_key', 'pos')->value('id'))->delete();
        $result = $this->exchange('pos', 'snapshot', ['central_company_id' => 2, 'central_subscriber_id' => 2, 'local_company_id' => 502]);
        $this->assertSame(['managed' => false, 'access' => false], $result);
    }

    public function test_numeric_fiscal_identity_is_rejected_instead_of_creating_a_second_hash(): void
    {
        $this->exchange('bys', 'reserve', $this->operation('numeric', ['fiscal' => ['issuer' => 101010101, 'ncf' => 'E310000000001']]), 422);
        $this->assertSame(0, DB::table('business_usage_documents')->count());
    }

    public function test_committing_one_source_and_releasing_another_keeps_the_consumption(): void
    {
        $one = $this->operation();
        $document = $this->exchange('pos', 'reserve', $one);
        $two = $this->operation('invoice', ['document_id' => $document['document_id']]);
        $this->exchange('ecf', 'reserve', $two);
        $this->exchange('pos', 'commit', ['operation_key' => $one['operation_key']]);
        $this->exchange('ecf', 'release', ['operation_key' => $two['operation_key']]);
        $this->assertSame(1, $this->exchange('pos', 'snapshot')['documents']['consumed']);
        $this->assertSame(0, $this->exchange('pos', 'snapshot')['documents']['reserved']);
    }

    public function test_failed_confirmation_is_atomic_and_can_be_retried(): void
    {
        $operation = $this->operation();
        $this->exchange('pos', 'reserve', $operation);
        DB::statement("CREATE TRIGGER fail_commit_event BEFORE INSERT ON business_usage_events WHEN NEW.event = 'committed' BEGIN SELECT RAISE(ABORT, 'synthetic commit failure'); END");
        $this->withoutExceptionHandling();
        try {
            $this->exchange('pos', 'commit', ['operation_key' => $operation['operation_key']]);
            $this->fail('The confirmation audit should fail.');
        } catch (QueryException $expected) {
            $this->assertStringContainsString('synthetic commit failure', $expected->getMessage());
        }
        $this->assertSame('reserved', DB::table('business_usage_reservations')->sole()->state);
        $this->assertNull(DB::table('business_usage_documents')->sole()->consumed_at);
        DB::statement('DROP TRIGGER fail_commit_event');
        $this->exchange('pos', 'commit', ['operation_key' => $operation['operation_key']]);
        $this->assertSame(2, DB::table('business_usage_events')->count());
    }
}
