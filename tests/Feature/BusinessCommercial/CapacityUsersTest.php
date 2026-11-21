<?php

namespace Tests\Feature\BusinessCommercial;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BusinessCommercialTestCase;

final class CapacityUsersTest extends BusinessCommercialTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (require base_path('database/migrations/2026_11_17_000022_create_business_capacity_users.php'))->up();
        config()->set('business_commercial_bridge.capacities_enabled', true);
        foreach (['pos', 'bys', 'ecf', 'cumplimiento'] as $service) {
            DB::table('business_identity_accounts')->insertOrIgnore(['service' => $service, 'central_user_id' => 1, 'local_user_id' => 501, 'created_at' => now()]);
        }
    }

    private function capacity(string $service, string $action, array $data = [], int $status = 200): array
    {
        $codec = $this->codec($service);
        $token = $codec->encode($service, config('business_identity.issuer'), 'request', $this->data($service, $action, $data), now()->timestamp);
        $response = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/business/capacity/v1/'.$service)->assertStatus($status);
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $reply = $codec->decode($response->json('token'), config('business_identity.issuer'), $service, 'response', now()->timestamp);
        $this->assertSame(hash('sha256', $token), $reply['request_hash']);
        $this->assertSame($status, $reply['status']);

        return $reply['result'] ?? $reply;
    }

    private function inventory(string $service, array $ids = [501]): void
    {
        $key = (string) Str::uuid();
        $this->capacity($service, 'start_inventory', ['operation_key' => $key, 'owner_user_id' => 501]);
        foreach ($ids as $id) {
            $this->capacity($service, 'inventory_user', ['operation_key' => $key, 'local_user_id' => $id]);
        }
        sort($ids, SORT_NUMERIC);
        $this->capacity($service, 'finish_inventory', ['operation_key' => $key, 'inventory_count' => count($ids), 'inventory_hash' => hash('sha256', json_encode($ids))]);
    }

    private function initialize(): void
    {
        foreach (['pos', 'bys', 'ecf', 'cumplimiento'] as $service) {
            $this->inventory($service);
        }
    }

    private function acquire(string $service, int $user, ?string $previous = null, int $status = 200): array
    {
        return $this->capacity($service, 'acquire_user', ['operation_key' => (string) Str::uuid(), 'local_user_id' => $user, 'previous_key' => $previous], $status);
    }

    public function test_incomplete_inventory_does_not_become_zero_and_last_shared_slot_is_admitted_once(): void
    {
        $this->inventory('pos');
        $this->acquire('pos', 502, status: 409);
        foreach (['bys', 'ecf', 'cumplimiento'] as $service) {
            $this->inventory($service);
        }
        $this->assertSame(1, $this->capacity('pos', 'inspect')['users_consumed']);
        foreach ([502, 503, 504, 505] as $user) {
            $this->acquire('pos', $user);
        }
        $this->acquire('bys', 502, status: 429);
        $this->assertSame(5, $this->capacity('bys', 'inspect')['users_consumed']);
        $this->assertSame(8, DB::table('business_capacity_users')->count());
        $this->assertSame(0, DB::table('business_usage_documents')->count());
    }

    public function test_same_local_number_is_separate_until_f2_proves_identity_and_revoke_does_not_release_access(): void
    {
        $this->initialize();
        $this->acquire('pos', 502);
        $this->acquire('bys', 502);
        $this->assertSame(3, $this->capacity('pos', 'inspect')['users_consumed']);
        foreach (['pos', 'bys'] as $service) {
            DB::table('business_identity_accounts')->insert(['service' => $service, 'local_user_id' => 502, 'central_user_id' => 3, 'created_at' => now()]);
        }
        $this->assertSame(2, $this->capacity('pos', 'inspect')['users_consumed']);
        DB::table('business_identity_members')->update(['active' => false]);
        $this->assertSame(2, $this->capacity('pos', 'inspect')['users_consumed']);
    }

    public function test_reactivation_fences_old_release_confirmation_and_acquisition(): void
    {
        $this->initialize();
        $first = $this->acquire('pos', 502);
        $second = $this->acquire('pos', 502, $first['operation_key']);
        $old = ['operation_key' => $first['operation_key'], 'local_user_id' => 502];
        $this->assertSame($second['operation_key'], $this->capacity('pos', 'release_user', $old)['operation_key']);
        $this->assertSame('reserved', $this->capacity('pos', 'confirm_user', $old)['state']);
        $this->acquire('pos', 502, $first['operation_key'], 409);
        $this->assertSame('active', $this->capacity('pos', 'confirm_user', ['operation_key' => $second['operation_key'], 'local_user_id' => 502])['state']);
        $this->assertSame(2, $this->capacity('pos', 'inspect')['users_consumed']);
    }

    public function test_released_operations_cannot_reappear_even_when_release_arrives_first(): void
    {
        $this->initialize();
        foreach ([502, 503] as $user) {
            $key = (string) Str::uuid();
            $data = ['operation_key' => $key, 'local_user_id' => $user, 'previous_key' => null];
            if ($user === 502) {
                $this->capacity('pos', 'acquire_user', $data);
            }
            $this->capacity('pos', 'release_user', $data);
            $this->capacity('pos', 'acquire_user', $data, 409);
        }
        $this->assertSame(1, $this->capacity('pos', 'inspect')['users_consumed']);
    }

    public function test_per_solution_owner_policy_and_bad_contract_limit_are_explicit(): void
    {
        $this->initialize();
        $snapshot = $this->contract->snapshot;
        $snapshot['capacity_policy']['users'] = 'per_solution';
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->assertSame(4, $this->capacity('pos', 'inspect')['users_consumed']);
        $snapshot['capacity_policy']['owner_counts'] = false;
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->assertSame(0, $this->capacity('pos', 'inspect')['users_consumed']);
        $snapshot['capacity']['users'] = 'unlimited';
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->acquire('pos', 502, status: 409);
        $this->assertSame(4, DB::table('business_capacity_users')->count());
    }

    public function test_closed_inventory_is_immutable_and_incomplete_digest_is_rejected(): void
    {
        $key = (string) Str::uuid();
        $this->capacity('pos', 'start_inventory', ['operation_key' => $key, 'owner_user_id' => 501]);
        $this->capacity('pos', 'inventory_user', ['operation_key' => $key, 'local_user_id' => 501]);
        $this->capacity('pos', 'finish_inventory', ['operation_key' => $key, 'inventory_count' => 2, 'inventory_hash' => hash('sha256', '[501,502]')], 409);
        $this->assertFalse($this->capacity('pos', 'inspect')['inventory_ready']);
        $this->capacity('pos', 'finish_inventory', ['operation_key' => $key, 'inventory_count' => 1, 'inventory_hash' => hash('sha256', '[501]')]);
        $this->capacity('pos', 'inventory_user', ['operation_key' => $key, 'local_user_id' => 502], 409);
        $this->capacity('pos', 'start_inventory', ['operation_key' => (string) Str::uuid(), 'owner_user_id' => 502], 409);
        $this->assertSame(1, DB::table('business_capacity_users')->count());
    }

    public function test_releasing_access_does_not_require_paid_coverage_and_lost_confirmations_count_conservatively(): void
    {
        $this->initialize();
        $seat = $this->acquire('pos', 502);
        $this->travelTo($this->contract->period_ends_at);
        $this->acquire('pos', 503, status: 403);
        $this->assertSame(2, $this->capacity('pos', 'inspect')['users_consumed']);
        $this->capacity('pos', 'confirm_user', ['operation_key' => $seat['operation_key'], 'local_user_id' => 502]);
        $this->capacity('pos', 'release_user', ['operation_key' => $seat['operation_key'], 'local_user_id' => 502]);
        $this->assertSame(1, $this->capacity('pos', 'inspect')['users_consumed']);
    }
}
