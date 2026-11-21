<?php

namespace Tests\Feature\BusinessCommercial;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\PosFiscalHandoffTestCase;

final class FacilFiscalEmissionTest extends PosFiscalHandoffTestCase
{
    private string $draft;

    private function startFacil(): void
    {
        $this->startServer(true);
        touch($this->runtime.'/direct-fiscal-enabled');
        $this->draft = $this->request(['action' => 'setup'])['draft'];
    }

    private function request(array $input, int $status = 200): array
    {
        $process = new Process(['php', base_path('tests/Fixtures/BusinessCommercial/facil-request.php'), $this->runtime]);
        $process->setInput(json_encode($input + ['draft' => $this->draft ?? ''], JSON_THROW_ON_ERROR));
        $process->setTimeout(60)->mustRun();
        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($status, $result['status'], json_encode($result).' '.$this->logs());

        return $result['body'] ?? $result;
    }

    private function command(array $input): array
    {
        $process = new Process(['php', base_path('tests/Fixtures/BusinessCommercial/ecf-command.php'), $this->runtime]);
        $process->setInput(json_encode($input, JSON_THROW_ON_ERROR));
        $process->setTimeout(60)->mustRun();

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function review(): string
    {
        return $this->request(['action' => 'review'])['token'];
    }

    private function confirm(string $token, array $extra = []): array
    {
        return $this->request(['action' => 'confirm', 'payload' => ['token' => $token]] + $extra);
    }

    private function documents(int $count): void
    {
        $this->assertSame($count, (int) $this->receiver('ecf')->query('SELECT count(*) FROM ecf_documents')->fetchColumn());
        $this->assertSame($count, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public function test_review_and_double_confirmation_use_one_business_document_without_local_plan_debit(): void
    {
        $this->startFacil();
        $this->command(['action' => 'exhausted_plan']);
        $token = $this->review();
        $this->documents(0);
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
        $first = $this->confirm($token);
        $this->assertSame('completed', $first['receipt']['request_status']);
        $this->assertSame('e310000000001', $first['receipt']['encf']);
        $again = $this->confirm($token);
        $this->assertTrue($again['replayed']);
        $this->assertSame($first['receipt']['encf'], $again['receipt']['encf']);
        $this->documents(1);
        $this->assertSame(1, DB::table('business_usage_aliases')->where('kind', 'fiscal')->count());
        $this->assertSame(1, (int) $this->receiver('ecf')->query('SELECT ecf_count FROM usage_records')->fetchColumn());
        $this->assertSame(1, (int) $this->receiver('ecf')->query('SELECT count(*) FROM ecf_api_requests')->fetchColumn());
    }

    public static function losses(): array
    {
        return [['lost_reserve', 503, 0], ['lost_commit', 202, 1], ['lost_reference', 202, 1], ['lost_send', 500, 1]];
    }

    #[DataProvider('losses')]
    public function test_lost_responses_reconcile_without_repeating_fiscal_effects(string $mode, int $status, int $count): void
    {
        $this->startFacil();
        $token = $this->review();
        file_put_contents($this->runtime.'/fiscal-mode', $mode);
        $failed = $this->confirm($token);
        $this->assertSame($status, $failed['receipt']['response_status']);
        file_put_contents($this->runtime.'/fiscal-mode', '');
        $boundary = @file_get_contents($this->runtime.'/fiscal-boundary.ndjson');
        $this->assertSame(0, $this->command(['action' => 'direct_reconcile', 'due' => true])['exit']);
        $this->assertSame($boundary, @file_get_contents($this->runtime.'/fiscal-boundary.ndjson'));
        if ($count === 0) {
            $this->assertTrue($this->request(['action' => 'status'])['receipt']['retry_allowed']);
            $this->request(['action' => 'confirm', 'payload' => ['token' => $token]], 409);
            $this->assertSame('completed', $this->confirm($this->review())['receipt']['request_status']);
        } else {
            $this->assertTrue($this->confirm($token)['replayed']);
            $this->assertSame($boundary, file_get_contents($this->runtime.'/fiscal-boundary.ndjson'));
        }
        $this->documents(1);
        $this->assertSame(1, DB::table('business_usage_aliases')->where('kind', 'fiscal')->count());
    }

    public function test_quota_rejection_releases_reservation_and_requires_new_review_for_same_draft(): void
    {
        $this->startFacil();
        $token = $this->review();
        $snapshot = $this->contract->snapshot;
        $snapshot['capacity']['documents'] = 0;
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->assertSame(429, $this->confirm($token)['receipt']['response_status']);
        $this->documents(0);
        $this->assertSame(1, (int) $this->receiver('ecf')->query("SELECT siguiente FROM ncf_sequences WHERE tipo_ncf = 'e31'")->fetchColumn());
        $snapshot['capacity']['documents'] = 5;
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->assertSame(0, $this->command(['action' => 'direct_reconcile', 'due' => true])['exit']);
        $this->assertSame('retryable', $this->request(['action' => 'status'])['receipt']['request_status']);
        $this->request(['action' => 'confirm', 'payload' => ['token' => $token]], 409);
        $this->assertSame('completed', $this->confirm($this->review())['receipt']['request_status']);
        $this->documents(1);
        $this->assertSame(2, (int) $this->receiver('ecf')->query('SELECT generation FROM business_ecf_emissions')->fetchColumn());
    }

    public static function revocations(): array
    {
        return [['direct_revoke_link'], ['direct_change_environment'], ['facil_revoke_user']];
    }

    #[DataProvider('revocations')]
    public function test_revocation_after_reservation_prevents_document_creation(string $mode): void
    {
        $this->startFacil();
        $token = $this->review();
        file_put_contents($this->runtime.'/fiscal-mode', $mode);
        $this->assertSame(403, $this->confirm($token)['receipt']['response_status']);
        $this->documents(0);
        $this->assertSame($mode === 'direct_revoke_link' ? 1 : 0, DB::table('business_usage_reservations')->where('state', 'reserved')->count());
    }

    public function test_standalone_plan_is_preserved(): void
    {
        DB::table('business_contract_items')->where('identity_link_id', DB::table('business_identity_links')->where('service', 'ecf')->value('id'))->delete();
        DB::table('business_identity_links')->where('service', 'ecf')->update(['central_company_id' => 2, 'central_subscriber_id' => 2]);
        $this->startFacil();
        $this->command(['action' => 'local_plan']);
        $this->assertSame('completed', $this->confirm($this->review())['receipt']['request_status']);
        $this->assertSame(0, DB::table('business_usage_documents')->count());
        $this->assertSame(1, (int) $this->receiver('ecf')->query('SELECT ecf_count FROM usage_records')->fetchColumn());
    }

    public function test_flag_csrf_and_session_protect_review_and_confirmation(): void
    {
        $this->startFacil();
        $this->request(['action' => 'review', 'enabled' => false], 503);
        $this->request(['action' => 'review', 'guest' => true], 401);
        $this->request(['action' => 'review', 'bad_csrf' => true], 419);
        $token = $this->review();
        $this->request(['action' => 'confirm', 'enabled' => false, 'payload' => ['token' => $token]], 503);
        $this->documents(0);
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
    }

    public function test_fiscal_requirements_and_changed_draft_still_block_before_reserving(): void
    {
        $this->startFacil();
        $token = $this->review();
        $this->receiver('ecf')->exec('UPDATE ecf_drafts SET version = 2');
        $this->request(['action' => 'confirm', 'payload' => ['token' => $token]], 409);
        $this->receiver('ecf')->exec('UPDATE ecf_drafts SET version = 1');
        $this->receiver('ecf')->exec("UPDATE vault_certificates SET status = 'revocado'");
        $this->request(['action' => 'confirm', 'payload' => ['token' => $token]], 422);
        $this->documents(0);
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
    }

    public function test_lost_snapshot_before_mapping_can_be_verified_and_retried_explicitly(): void
    {
        $this->startFacil();
        $token = $this->review();
        file_put_contents($this->runtime.'/fiscal-mode', 'facil_snapshot_after_receipt');
        $this->assertSame(503, $this->confirm($token)['receipt']['response_status']);
        file_put_contents($this->runtime.'/fiscal-mode', '');
        $this->assertSame(0, (int) $this->receiver('ecf')->query('SELECT count(*) FROM business_ecf_emissions')->fetchColumn());
        $this->fiscal('ecf/solicitudes/'.rawurlencode('facil:'.$this->draft).'/reconciliar', status: 409);
        $this->assertTrue($this->request(['action' => 'recover'])['receipt']['retry_allowed']);
        $this->request(['action' => 'confirm', 'payload' => ['token' => $token]], 409);
        $this->assertSame('completed', $this->confirm($this->review())['receipt']['request_status']);
        $this->documents(1);
    }

    public function test_late_worker_before_mapping_cannot_overwrite_or_emit_after_recovery(): void
    {
        $this->startFacil();
        $token = $this->review();
        file_put_contents($this->runtime.'/fiscal-mode', 'facil_pause_before_map');
        $process = new Process(['php', base_path('tests/Fixtures/BusinessCommercial/facil-request.php'), $this->runtime]);
        $process->setInput(json_encode(['action' => 'confirm', 'draft' => $this->draft, 'payload' => ['token' => $token]]));
        $process->setTimeout(90)->start();
        try {
            $deadline = microtime(true) + 20;
            while (! is_file($this->runtime.'/facil-paused')) {
                if (! $process->isRunning() || microtime(true) > $deadline) {
                    $this->fail('Worker did not pause: '.$process->getOutput().$process->getErrorOutput());
                }
                usleep(10000);
            }
            file_put_contents($this->runtime.'/fiscal-mode', '');
            $this->request(['action' => 'recover'], 409);
            $this->receiver('ecf')->exec("UPDATE ecf_api_requests SET updated_at = datetime('now', '-11 minutes')");
            $this->assertTrue($this->request(['action' => 'recover'])['receipt']['retry_allowed']);
            $done = $this->confirm($this->review());
            touch($this->runtime.'/facil-resume');
            $process->wait();
            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $old = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('completed', $old['body']['receipt']['request_status']);
            $this->assertSame($done['receipt']['encf'], $old['body']['receipt']['encf']);
            $this->documents(1);
        } finally {
            $process->stop(1);
        }
    }

    public function test_transaction_failure_rolls_back_sequence_and_recovery_reuses_receipt(): void
    {
        $this->startFacil();
        $token = $this->review();
        $db = $this->receiver('ecf');
        $db->exec("CREATE TRIGGER fail_facil_map BEFORE UPDATE OF ecf_document_id ON business_ecf_emissions WHEN NEW.ecf_document_id IS NOT NULL BEGIN SELECT RAISE(ABORT, 'Synthetic failure'); END");
        $this->assertSame(500, $this->confirm($token)['receipt']['response_status']);
        $this->documents(0);
        $this->assertSame(1, (int) $db->query("SELECT siguiente FROM ncf_sequences WHERE tipo_ncf = 'e31'")->fetchColumn());
        $db->exec('DROP TRIGGER fail_facil_map');
        $this->assertTrue($this->request(['action' => 'recover'])['receipt']['retry_allowed']);
        $this->assertSame('completed', $this->confirm($this->review())['receipt']['request_status']);
        $this->documents(1);
    }

    public function test_api_cannot_claim_draft_reference(): void
    {
        $this->startFacil();
        $review = $this->request(['action' => 'review']);
        $this->fiscal('ecf', $review['payload'], 'facil:'.$this->draft, status: 409);
        $this->documents(0);
        $this->assertSame('completed', $this->confirm($review['token'])['receipt']['request_status']);
    }

    public function test_e32_and_native_credit_note_preserve_independent_consumption(): void
    {
        $this->startFacil();
        $base = json_decode($this->receiver('ecf')->query('SELECT payload FROM ecf_drafts')->fetchColumn(), true);
        $this->draft = $this->request(['action' => 'setup', 'payload' => array_replace($base, ['tipo_ecf' => 'e32'])])['draft'];
        $this->assertSame('e320000000001', $this->confirm($this->review())['receipt']['encf']);
        $this->receiver('ecf')->exec("UPDATE ecf_documents SET status = 'aceptado'");
        $base['tipo_ecf'] = 'e34';
        $base['ncf_modificado'] = 'E320000000001';
        $base['fecha_ncf_mod'] = now()->format('Y-m-d');
        $base['codigo_mod'] = '3';
        $base['ind_nota_credito'] = '0';
        $base['razon_modificacion'] = 'Ajuste sintético';
        $base['items'][0]['precio_unitario'] = '10';
        $this->draft = $this->request(['action' => 'setup', 'payload' => $base])['draft'];
        $token = $this->review();
        $note = $this->confirm($token);
        $this->assertSame('completed', $note['receipt']['request_status'], json_encode($note));
        $this->assertSame('e340000000001', $note['receipt']['encf']);
        $this->assertTrue($this->confirm($token)['replayed']);
        $this->documents(2);
        $this->assertSame(2, DB::table('business_usage_aliases')->where('kind', 'fiscal')->count());
    }

    public function test_another_account_cannot_review_confirm_recover_or_read_the_draft(): void
    {
        $this->startFacil();
        $token = $this->review();
        $this->receiver('ecf')->exec("INSERT INTO users (id, name, email, password, is_active, email_verified_at) VALUES (502, 'Other synthetic', 'other@example.test', 'unused', 1, datetime('now'))");
        foreach (['review', 'confirm', 'recover', 'status'] as $action) {
            $this->request(['action' => $action, 'user' => 502, 'payload' => ['version' => 1, 'token' => $token]], 404);
        }
        $this->documents(0);
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
    }

    public function test_browser_manual_recovery_and_emission_on_desktop_and_mobile(): void
    {
        $this->startFacil();
        $token = $this->review();
        file_put_contents($this->runtime.'/fiscal-mode', 'facil_snapshot_after_receipt');
        $this->assertSame(503, $this->confirm($token)['receipt']['response_status']);
        file_put_contents($this->runtime.'/fiscal-mode', '');
        touch($this->runtime.'/facil-browser-enabled');
        $this->assertSame(0, $this->command(['action' => 'browser_prepare', 'draft' => $this->draft])['exit']);
        $node = '/home/laudaerp/.nvm/versions/node/v22.23.3/bin/node';
        $build = new Process([$node, base_path('tests/Fixtures/BusinessIdentity/build.mjs'), dirname(base_path()).'/ecflaudaapi',
            $this->runtime.'/ecf', $this->runtime.'/ecf/generated']);
        $build->setTimeout(180)->run();
        file_put_contents(base_path('.lauda-reports/business-20261106/f5-facil-build-20261117.txt'), $build->getOutput().$build->getErrorOutput());
        $this->assertSame(0, $build->getExitCode(), $build->getOutput().$build->getErrorOutput());
        $browser = new Process([$node, base_path('tests/Fixtures/BusinessCommercial/facil-browser.mjs'), $this->runtime,
            base_path('.lauda-reports/business-20261106/f5-facil-ui')]);
        $browser->setTimeout(180)->run();
        $this->assertSame(0, $browser->getExitCode(), $browser->getOutput().$browser->getErrorOutput().' '.$this->logs());
        $root = dirname(base_path()).'/ecflaudaapi';
        file_put_contents($this->runtime.'/ecf/tsconfig.json', json_encode(['extends' => $root.'/tsconfig.json',
            'compilerOptions' => ['baseUrl' => $root, 'types' => [$root.'/node_modules/vite/client'], 'typeRoots' => [$root.'/node_modules/@types'], 'paths' => ['@/*' => [$root.'/resources/js/*'],
                '@/routes/*' => [$this->runtime.'/ecf/generated/routes/*'], '@/actions/*' => [$this->runtime.'/ecf/generated/actions/*'],
                '@/wayfinder' => [$this->runtime.'/ecf/generated/wayfinder']]],
            'include' => [$root.'/resources/js/pages/Facil/Invoice.vue', $root.'/resources/js/lib/facil-display.ts', $root.'/resources/js/types/**/*.d.ts']]));
        $types = new Process([$node, $root.'/node_modules/vue-tsc/bin/vue-tsc.js', '--noEmit', '--pretty', 'false', '-p', $this->runtime.'/ecf/tsconfig.json']);
        $types->setTimeout(120)->run();
        file_put_contents(base_path('.lauda-reports/business-20261106/f5-facil-types-targeted-20261117.txt'), $types->getOutput().$types->getErrorOutput());
        $this->assertSame(0, $types->getExitCode(), $types->getOutput().$types->getErrorOutput());
        file_put_contents($this->runtime.'/facil-auth.mjs', "import {readFileSync} from 'node:fs'; export default async ({page, context, navigate}) => { const f = JSON.parse(readFileSync(".json_encode($this->runtime.'/facil-browser.json').", 'utf8')); await context.addCookies([f.cookie]); await navigate('/facil/borradores/' + f.draft); await page.getByText('E310000000001', {exact:true}).waitFor(); return {authenticated:true}; };");
        $audit = new Process(['bash', '/home/laudaerp/.agents/skills/lauda-ui-ux/scripts/audit.sh', 'pages',
            '--base-url', config('business_identity.issuer'), '--path', '/facil/borradores/'.$this->draft,
            '--auth-module', $this->runtime.'/facil-auth.mjs', '--session-name', 'facil-business-final'], $root);
        // These fixture servers live inside this sandbox; operational DEV proxy settings do not apply.
        $audit->setEnv(array_fill_keys(['HTTPS_PROXY', 'https_proxy', 'HTTP_PROXY', 'http_proxy', 'ALL_PROXY', 'all_proxy'], false));
        $audit->setTimeout(120)->run();
        file_put_contents(base_path('.lauda-reports/business-20261106/f5-facil-audit-20261117.txt'), 'Exit: '.$audit->getExitCode()."\n".$audit->getOutput().$audit->getErrorOutput());

        $this->documents(1);
        $this->assertSame(1, (int) $this->receiver('ecf')->query('SELECT count(*) FROM ecf_api_requests')->fetchColumn());
    }
}
