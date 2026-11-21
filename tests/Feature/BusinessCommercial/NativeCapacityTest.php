<?php

namespace Tests\Feature\BusinessCommercial;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\BusinessCommercialTransportTestCase;

final class NativeCapacityTest extends BusinessCommercialTransportTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (require base_path('database/migrations/2026_11_17_000022_create_business_capacity_users.php'))->up();
        config()->set('business_commercial_bridge.capacities_enabled', true);
        $link = DB::table('business_identity_links')->where('service', 'bys')->sole();
        DB::table('business_identity_links')->where('id', $link->id)->update(['local_company_id' => 1]);
        DB::table('business_contract_items')->where('identity_link_id', $link->id)->update(['local_company_id' => 1]);
        foreach (['pos', 'bys', 'ecf', 'cumplimiento'] as $service) {
            DB::table('business_identity_accounts')->insertOrIgnore(['service' => $service, 'central_user_id' => 1, 'local_user_id' => 501, 'created_at' => now()]);
        }
        $this->startServer();
    }

    private function native(string $service, array $input = [], int $status = 200): array
    {
        $process = new Process(['php', base_path('tests/Fixtures/BusinessCommercial/capacity-native.php'), $this->runtime, $service]);
        $process->setTimeout(120)->setInput(json_encode($input ?: ['action' => 'sync'], JSON_THROW_ON_ERROR))->mustRun();
        $response = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($status, $response['status'], $service.' '.json_encode($input).' '.($response['message'] ?? ''));

        return $response['result'] ?? [];
    }

    private function initialize(): void
    {
        foreach (['pos', 'bys', 'ecf', 'cumplimiento'] as $service) {
            $result = $this->native($service);
            $this->assertTrue($result['inventory_ready']);
        }
        $this->assertTrue($result['all_inventories_ready']);
        $this->assertSame(1, $result['users_consumed']);
    }

    private function db(string $service): \PDO
    {
        return new \PDO('sqlite:'.$this->runtime.'/'.$service.'/database.sqlite');
    }

    public function test_native_members_share_capacity_and_deactivation_releases_a_slot_without_f2_circularity(): void
    {
        $this->initialize();
        $this->native('pos', ['action' => 'member', 'user' => 502]);
        $this->native('bys', ['action' => 'member', 'user' => 502]);
        $this->native('pos', ['action' => 'member', 'user' => 503]);
        $this->native('cumplimiento', ['action' => 'member', 'user' => 502]);
        $this->native('bys', ['action' => 'member', 'user' => 503], 429);
        $this->assertSame(0, (int) $this->db('bys')->query('SELECT count(*) FROM bys_company_members WHERE user_id=503 AND is_active=1')->fetchColumn());
        $this->assertSame(5, $this->native('ecf')['users_consumed']);
        $this->native('pos', ['action' => 'member', 'user' => 502, 'active' => false]);
        $this->native('bys', ['action' => 'member', 'user' => 503]);
        $this->assertSame(5, $this->native('ecf')['users_consumed']);
        $this->native('pos', ['action' => 'member', 'user' => 502], 429);
        $this->assertSame(0, (int) $this->db('pos')->query('SELECT is_active FROM users WHERE id=502')->fetchColumn());
        $this->assertSame(0, DB::table('business_usage_documents')->count());
    }

    public function test_lost_admission_and_confirmation_are_recovered_without_duplicate_native_users(): void
    {
        $this->initialize();
        $this->native('pos', ['action' => 'member', 'user' => 502, 'mode' => 'lost_acquire_user'], 503);
        $this->assertSame(0, (int) $this->db('pos')->query('SELECT count(*) FROM users WHERE id=502')->fetchColumn());
        $this->assertSame(1, $this->native('ecf')['users_consumed']);
        $this->native('pos', ['action' => 'member', 'user' => 502, 'mode' => 'lost_confirm_user']);
        $this->assertSame(1, (int) $this->db('pos')->query('SELECT pending FROM business_local_capacity_users WHERE user_id=502')->fetchColumn());
        $this->assertSame(2, $this->native('pos')['users_consumed']);
        $this->assertSame(0, (int) $this->db('pos')->query('SELECT pending FROM business_local_capacity_users WHERE user_id=502')->fetchColumn());
        $this->native('pos', ['action' => 'member', 'user' => 502, 'active' => false, 'mode' => 'unavailable']);
        $this->assertSame(0, (int) $this->db('pos')->query('SELECT is_active FROM users WHERE id=502')->fetchColumn());
        $this->assertSame(1, $this->native('pos')['users_consumed']);
    }

    public function test_cash_limit_excludes_declared_channels_and_closing_works_during_outage(): void
    {
        $this->initialize();
        foreach ([502, 503] as $user) {
            $this->native('pos', ['action' => 'member', 'user' => $user]);
        }
        $one = $this->native('pos', ['action' => 'cash_open']);
        $this->native('pos', ['action' => 'cash_open', 'user' => 502]);
        $this->native('pos', ['action' => 'cash_open', 'user' => 503], 422);
        $cantina = $this->native('pos', ['action' => 'cash_open', 'user' => 503, 'scope' => 'cantina', 'mode' => 'unavailable']);
        $this->assertSame('cantina', $cantina['scope']);
        $this->native('pos', ['action' => 'payment_scope', 'session' => $cantina['id']], 422);
        $this->native('pos', ['action' => 'payment_scope', 'session' => $one['id']]);
        $this->native('pos', ['action' => 'cash_close', 'session' => $one['id'], 'mode' => 'unavailable']);
        $this->native('pos', ['action' => 'payment_scope', 'session' => $one['id']], 422);
        $this->assertSame('open', $this->native('pos', ['action' => 'cash_open'])['status']);
        $this->assertSame(3, (int) $this->db('pos')->query("SELECT count(*) FROM cash_sessions WHERE status='open'")->fetchColumn());
    }

    public function test_branch_reactivation_is_counted_and_existing_branch_is_idempotent(): void
    {
        $this->initialize();
        $this->native('pos', ['action' => 'onboarding', 'code' => 'EXTRA'], 422);
        $this->assertSame(1, (int) $this->db('pos')->query('SELECT count(*) FROM branches')->fetchColumn());
        $this->db('pos')->exec("INSERT INTO branches (company_id,code,name,is_active) VALUES (501,'EXTRA','Inactiva',0)");
        $this->native('pos', ['action' => 'branch', 'code' => 'EXTRA'], 422);
        $this->assertSame(0, (int) $this->db('pos')->query("SELECT is_active FROM branches WHERE code='EXTRA'")->fetchColumn());
        $this->assertSame(1, $this->native('pos', ['action' => 'onboarding', 'code' => 'MAIN'])['branches']);
        $this->native('pos', ['action' => 'branch', 'mode' => 'unavailable'], 503);
    }

    public function test_missing_inventory_blocks_new_access_and_native_permissions_still_apply(): void
    {
        $this->native('pos');
        $this->native('pos', ['action' => 'member', 'user' => 502], 409);
        foreach (['bys', 'ecf', 'cumplimiento'] as $service) {
            $this->native($service);
        }
        $this->native('pos', ['action' => 'member', 'user' => 502]);
        $this->native('pos', ['action' => 'member', 'user' => 503, 'actor' => 502], 403);
        $this->native('pos', ['action' => 'member', 'user' => 503, 'mode' => 'disabled'], 503);
        $this->assertSame(0, (int) $this->db('pos')->query('SELECT count(*) FROM users WHERE id=503')->fetchColumn());
    }

    public function test_initial_inventory_recovers_lost_final_response_and_paginates_existing_overage(): void
    {
        $this->native('pos', ['action' => 'users']);
        $insert = $this->db('pos')->prepare("INSERT INTO users (id,name,email,password,is_active,is_admin,current_company_id,role) VALUES (?,? ,?,'synthetic',1,0,501,'cashier')");
        foreach (range(502, 527) as $id) {
            $insert->execute([$id, 'Existente '.$id, 'existing-'.$id.'@example.test']);
        }
        $this->native('pos', ['action' => 'sync', 'mode' => 'lost_inventory_users'], 503);
        $this->native('pos', ['action' => 'sync', 'mode' => 'lost_finish_inventory'], 503);
        $this->assertSame(0, (int) $this->db('pos')->query('SELECT count(*) FROM business_local_capacity_companies')->fetchColumn());
        $this->assertSame(27, $this->native('pos')['native_users']);
        foreach (['bys', 'ecf', 'cumplimiento'] as $service) {
            $result = $this->native($service);
        }
        $this->assertSame(27, $result['users_consumed']);
        $this->native('pos', ['action' => 'member', 'user' => 528], 429);
        $this->assertSame(27, $this->native('pos')['users_consumed']);
    }

    public function test_legacy_cash_and_changed_binding_require_explicit_reconciliation(): void
    {
        $this->initialize();
        $cash = $this->native('pos', ['action' => 'cash_open']);
        $this->db('pos')->exec('UPDATE cash_sessions SET business_usage_scope=NULL');
        $this->native('pos', ['action' => 'member', 'user' => 502]);
        $this->native('pos', ['action' => 'cash_open', 'user' => 502], 422);
        $this->native('pos', ['action' => 'payment_scope', 'session' => $cash['id']], 422);
        $this->native('pos', ['action' => 'cash_close', 'session' => $cash['id']]);
        $this->native('pos', ['action' => 'cash_open', 'user' => 502]);
        $this->native('pos', ['action' => 'changed_context'], 409);
    }

    public function test_a_post_commit_error_keeps_the_active_native_users_seat_until_reconciliation(): void
    {
        $this->initialize();
        $this->native('pos', ['action' => 'member', 'user' => 502, 'mode' => 'after_commit'], 503);
        $this->assertSame(1, (int) $this->db('pos')->query('SELECT is_active FROM users WHERE id=502')->fetchColumn());
        $this->assertSame(2, $this->native('ecf')['users_consumed']);
        $this->assertSame(0, $this->native('pos', ['action' => 'sync_command'])['exit']);
        $this->assertSame(0, (int) $this->db('pos')->query('SELECT pending FROM business_local_capacity_users WHERE user_id=502')->fetchColumn());
        $this->assertSame(2, $this->native('pos', ['action' => 'sync_command', 'company' => '0'])['exit']);
    }
}
