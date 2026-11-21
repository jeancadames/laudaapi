<?php

namespace Tests\Feature\BusinessCommercial;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\PosFiscalHandoffTestCase;

final class PosFiscalHandoffTest extends PosFiscalHandoffTestCase
{
    public function test_native_cash_invoice_reaches_real_ecf_kernel_with_one_consumption_and_replays(): void
    {
        $this->startServer(true);
        $first = $this->pos();
        $this->assertCount(1, $first['fiscal']);
        $fiscal = $first['fiscal'][0];
        $this->assertSame('e310000000001', $fiscal['encf'], json_encode($first).' '.$this->logs());
        $this->assertSame(1, (int) $this->receiver('ecf')->query('SELECT count(*) FROM ecf_documents')->fetchColumn());
        $this->assertSame(0, (int) $this->receiver('ecf')->query('SELECT count(*) FROM subscriptions')->fetchColumn());
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
        $this->assertSame(1, DB::table('business_fiscal_handoffs')->whereNotNull('completed_at')->count());
        $this->assertSame(1, DB::table('business_usage_aliases')->where('kind', 'fiscal')->count());
        $this->assertSame('true', $this->pos()['replayed']);
        $payload = json_decode($fiscal['payload'], true, flags: JSON_THROW_ON_ERROR);
        $replay = $this->fiscal('ecf', $payload, $fiscal['reference']);
        $this->assertSame('e310000000001', $replay['encf']);
        $payload['business']['document_id'] = '11111111-1111-4111-8111-111111111111';
        $this->fiscal('ecf', $payload, $fiscal['reference'], status: 409);
        $this->assertSame(1, (int) $this->receiver('ecf')->query('SELECT count(*) FROM ecf_documents')->fetchColumn());
        $this->assertSame(1, (int) $this->receiver('pos')->query('SELECT count(*) FROM sale_payments')->fetchColumn());
        $this->receiver('ecf')->exec("UPDATE business_pos_emissions SET confirmed_at = NULL, evidence_hash = 'altered'");
        $process = new Process(['php', base_path('tests/Fixtures/BusinessCommercial/ecf-command.php'), $this->runtime]);
        $process->setInput('{}');
        $process->setTimeout(30)->mustRun();
        $this->assertSame(1, json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['exit']);
        $this->assertSame(1, (int) $this->receiver('ecf')->query('SELECT count(*) FROM ecf_documents')->fetchColumn());

    }

    public function test_existing_fiscal_result_is_recovered_with_central_bridge_disabled(): void
    {
        $this->startServer(true);
        $first = $this->pos(['mode' => 'lost_emission_reply']);
        $this->assertNull($first['fiscal'][0]['encf']);
        $recovered = $this->pos(['action' => 'fiscal_reconcile', 'mode' => 'fiscal_disabled']);
        $this->assertSame('e310000000001', $recovered['fiscal'][0]['encf'], json_encode($recovered));
        $this->assertNotContains('fiscal_publish', $recovered['actions']);
        $this->assertSame(1, (int) $this->receiver('ecf')->query('SELECT count(*) FROM ecf_documents')->fetchColumn());
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public function test_e32_uses_the_same_business_document_without_debiting_an_exhausted_local_plan(): void
    {
        $this->startServer(true);
        $process = new Process(['php', base_path('tests/Fixtures/BusinessCommercial/ecf-command.php'), $this->runtime]);
        $process->setInput(json_encode(['action' => 'exhausted_plan']));
        $process->setTimeout(30)->mustRun();
        $first = $this->pos(['payload' => ['idempotency_key' => 'cash:e32', 'ecf_type' => '32',
            'items' => [['product_id' => 501, 'quantity' => 1, 'unit_price' => 100]],
            'payments' => [['payment_method_id' => 501, 'payment_type' => 'cash', 'amount' => 118]]]]);
        $this->assertSame('e320000000001', $first['fiscal'][0]['encf'], json_encode($first).' '.$this->logs());
        $this->assertSame(1, (int) $this->receiver('ecf')->query('SELECT ecf_count FROM usage_records')->fetchColumn());
        $this->assertSame(1, DB::table('business_fiscal_handoffs')->whereNotNull('completed_at')->count());
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }
}
