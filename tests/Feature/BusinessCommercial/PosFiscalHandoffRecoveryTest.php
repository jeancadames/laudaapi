<?php

namespace Tests\Feature\BusinessCommercial;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\PosFiscalHandoffTestCase;

final class PosFiscalHandoffRecoveryTest extends PosFiscalHandoffTestCase
{
    public static function lostReplies(): array
    {
        return ['publication' => ['lost_fiscal_publish', ''], 'admission' => ['', 'lost_fiscal_receive'],
            'emission response' => ['lost_emission_reply', ''], 'fiscal identity' => ['', 'lost_fiscal_complete'],
            'external transmission' => ['', 'lost_send']];
    }

    #[DataProvider('lostReplies')]
    public function test_lost_replies_recover_without_repeating_native_operations(string $posMode, string $ecfMode): void
    {
        $this->startServer(true);
        file_put_contents($this->runtime.'/fiscal-mode', $ecfMode);
        $first = $this->pos(['mode' => $posMode]);
        $this->assertSame(1, $first['native']['sales']);
        $this->assertSame(1, $first['native']['payments']);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
        if ($posMode === 'lost_fiscal_publish' || $ecfMode === 'lost_fiscal_receive') {
            $this->assertSame(0, (int) $this->receiver('ecf')->query('SELECT count(*) FROM ecf_documents')->fetchColumn());
        }
        if ($ecfMode === 'lost_fiscal_complete') {
            $this->assertSame(0, (int) $this->receiver('ecf')->query('SELECT count(*) FROM business_pos_emissions WHERE confirmed_at IS NOT NULL')->fetchColumn());
            $this->command(true);
            $this->assertSame(0, (int) $this->receiver('ecf')->query('SELECT count(*) FROM business_pos_emissions WHERE confirmed_at IS NOT NULL')->fetchColumn());
        }
        file_put_contents($this->runtime.'/fiscal-mode', '');
        $recovered = $this->pos(['action' => 'fiscal_reconcile']);
        $this->assertSame('e310000000001', $recovered['fiscal'][0]['encf'], json_encode($recovered).' '.$this->logs());
        $this->command(false);
        $this->assertSame(1, DB::table('business_fiscal_handoffs')->whereNotNull('completed_at')->count());
        $this->assertSame(1, (int) $this->receiver('ecf')->query('SELECT count(*) FROM ecf_documents')->fetchColumn());
        $this->assertSame(2, (int) $this->receiver('ecf')->query('SELECT siguiente FROM ncf_sequences')->fetchColumn());
        $this->assertSame('true', $this->pos()['replayed']);
        $calls = array_map(fn ($line) => json_decode($line, true), file($this->runtime.'/fiscal-boundary.ndjson', FILE_IGNORE_NEW_LINES));
        $sends = array_filter($calls, fn ($call) => str_contains($call['path'], '/recepcion/'));
        $this->assertCount(1, $sends, json_encode($calls));
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public function test_mapping_failure_rolls_back_document_and_sequence_then_retries_same_receipt(): void
    {
        $this->startServer(true);
        $this->receiver('ecf')->exec("CREATE TRIGGER synthetic_map_failure BEFORE UPDATE OF ecf_document_id ON business_pos_emissions BEGIN SELECT RAISE(ABORT, 'synthetic map failure'); END");
        $first = $this->pos();
        $this->assertSame(1, $first['native']['payments']);
        $this->assertSame(0, (int) $this->receiver('ecf')->query('SELECT count(*) FROM ecf_documents')->fetchColumn());
        $this->assertSame(1, (int) $this->receiver('ecf')->query('SELECT siguiente FROM ncf_sequences')->fetchColumn());
        $this->receiver('ecf')->exec('DROP TRIGGER synthetic_map_failure');
        $recovered = $this->pos(['action' => 'fiscal_reconcile']);
        $this->assertSame('e310000000001', $recovered['fiscal'][0]['encf'], json_encode($recovered).' '.$this->logs());
        $this->assertSame(1, (int) $this->receiver('ecf')->query('SELECT count(*) FROM ecf_api_requests')->fetchColumn());
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    private function command(bool $dryRun): void
    {
        $process = new Process(['php', base_path('tests/Fixtures/BusinessCommercial/ecf-command.php'), $this->runtime]);
        $process->setInput(json_encode(['dry_run' => $dryRun]));
        $process->setTimeout(30)->mustRun();
        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(0, $result['exit'], json_encode($result));
    }
}
