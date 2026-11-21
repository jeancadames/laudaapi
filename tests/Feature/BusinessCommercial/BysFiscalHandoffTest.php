<?php

namespace Tests\Feature\BusinessCommercial;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\PosFiscalHandoffTestCase;

final class BysFiscalHandoffTest extends PosFiscalHandoffTestCase
{
    private function bys(array $input, int $status = 200): array
    {
        $process = new Process(['php', base_path('tests/Fixtures/BusinessCommercial/bys-purchase.php'), $this->runtime]);
        $process->setInput(json_encode($input + ['action' => 'request', 'key' => 'synthetic-purchase'], JSON_THROW_ON_ERROR));
        $process->setTimeout(90)->mustRun();
        try {
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new \RuntimeException($process->getOutput().$process->getErrorOutput(), previous: $error);
        }
        $this->assertSame($status, $result['status'], $process->getOutput().' '.$this->logs().' '.@file_get_contents($this->runtime.'/bys/storage/logs/application.log'));

        return $result;
    }

    private function prepare(string $type = 'e43'): array
    {
        $link = DB::table('business_identity_links')->where('service', 'bys')->sole();
        DB::table('business_identity_links')->where('id', $link->id)->update(['local_company_id' => 1]);
        DB::table('business_contract_items')->where('identity_link_id', $link->id)->update(['local_company_id' => 1]);
        $this->startServer(true);
        $this->bys(['action' => 'setup']);
        $setup = $this->bys(['action' => 'fiscal_setup', 'type' => $type]);
        $this->assertContains('ecf:emit', $setup['capabilities'], json_encode($setup));
        $connection = $setup['connection'];
        $purchase = $this->bys(['payload' => ['supplier_id' => 1, 'document_type' => $type === 'e43' ? 'receipt' : 'commercial_invoice',
            'purchase_kind' => 'service', 'supplier_invoice_number' => 'SYNTHETIC-001', 'issue_date' => today()->format('Y-m-d'),
            'currency' => $type === 'e43' ? 'DOP' : 'USD', 'exchange_rate' => $type === 'e43' ? 1 : 60, 'is_credit' => true,
            'lines' => [['description' => 'Servicio ficticio', 'item_type' => 'service', 'quantity' => 1, 'unit_price_original' => 100,
                'tax_rate' => 0, 'withholding_amount_original' => $type === 'e43' ? 0 : 27]]]], 201)['body'];
        $path = '/bys/purchase-documents/'.$purchase['id'].'/emission/';
        $input = ['connection_id' => $connection, 'type' => $type, 'fiscal_basis' => 'Criterio sintético para pruebas locales.'];
        $preview = $this->bys(['path' => $path.'preview', 'payload' => $input])['body'];
        $confirmed = $this->bys(['path' => $path.'confirm', 'payload' => $input + ['confirmed' => true, 'expected_hash' => $preview['hash']]], 201)['body'];
        $this->assertSame(0, DB::table('business_fiscal_handoffs')->count());

        return compact('path', 'confirmed', 'input', 'preview');
    }

    public static function types(): array
    {
        return [['e43'], ['e47']];
    }

    #[DataProvider('types')]
    public function test_native_purchase_and_fiscal_emission_share_one_consumption(string $type): void
    {
        $prepared = $this->prepare($type);
        if ($type === 'e47') {
            file_put_contents($this->runtime.'/fiscal-mode', 'lost_fiscal_complete');
        }
        $first = $this->bys(['path' => $prepared['path'].'process', 'payload' => []])['body'];
        $this->assertSame(strtoupper($type).'0000000001', $first['encf'], json_encode($first));
        if ($type === 'e47') {
            $this->assertSame(0, (int) $this->receiver('ecf')->query('SELECT count(*) FROM business_bys_emissions WHERE confirmed_at IS NOT NULL')->fetchColumn());
            unlink($this->runtime.'/fiscal-mode');
            $command = new Process(['php', base_path('tests/Fixtures/BusinessCommercial/ecf-command.php'), $this->runtime]);
            $command->setInput('{"action":"bys_reconcile"}')->setTimeout(30)->mustRun();
            $this->assertSame(0, json_decode($command->getOutput(), true)['exit']);
        }
        $this->bys(['path' => $prepared['path'].'process', 'payload' => []]);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
        $this->assertSame(1, DB::table('business_fiscal_handoffs')->whereNotNull('completed_at')->count());
        $this->assertSame(1, (int) $this->receiver('ecf')->query('SELECT count(*) FROM ecf_documents')->fetchColumn());
        $this->assertSame(1, (int) $this->receiver('ecf')->query('SELECT count(*) FROM business_bys_emissions WHERE confirmed_at IS NOT NULL')->fetchColumn());
        $this->assertSame(0, (int) $this->receiver('ecf')->query('SELECT count(*) FROM business_pos_emissions')->fetchColumn());
        $this->assertSame(0, (int) $this->receiver('bys')->query('SELECT count(*) FROM bys_purchase_usages')->fetchColumn());
    }

    public function test_lost_publish_recovers_same_reference_and_disabled_lookup_does_not_create(): void
    {
        $p = $this->prepare();
        $first = $this->bys(['path' => $p['path'].'process', 'payload' => [], 'mode' => 'lost_publish'])['body'];
        $this->assertSame('uncertain', $first['status']);
        $this->assertSame(0, (int) $this->receiver('ecf')->query('SELECT count(*) FROM ecf_documents')->fetchColumn());
        $lookup = $this->bys(['path' => $p['path'].'process', 'payload' => [], 'fiscal_enabled' => false])['body'];
        $this->assertSame('prepared', $lookup['status']);
        $sent = $this->bys(['path' => $p['path'].'process', 'payload' => []])['body'];
        $this->assertSame('E430000000001', $sent['encf']);
        $this->assertSame($first['reference'], $sent['reference']);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public function test_cancel_before_first_attempt_does_not_publish_and_allows_native_reconfirmation(): void
    {
        $p = $this->prepare();
        $this->bys(['path' => $p['path'].'cancel', 'payload' => ['reference' => $p['confirmed']['reference'], 'reason' => 'Revisión sintética antes de emitir']]);
        $preview = $this->bys(['path' => $p['path'].'preview', 'payload' => $p['input']])['body'];
        $confirmed = $this->bys(['path' => $p['path'].'confirm', 'payload' => $p['input'] + ['confirmed' => true, 'expected_hash' => $preview['hash']]], 201)['body'];
        $this->assertNotSame($p['confirmed']['reference'], $confirmed['reference']);
        $this->assertSame(0, DB::table('business_fiscal_handoffs')->count());
        $this->bys(['path' => $p['path'].'process', 'payload' => []]);
        $this->assertSame($confirmed['reference'], DB::table('business_fiscal_handoffs')->sole()->reference);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public function test_revoked_actor_after_publication_cannot_send(): void
    {
        $p = $this->prepare();
        touch($this->runtime.'/direct-fiscal-enabled');
        $withoutHandoff = $p['preview']['payload'];
        unset($withoutHandoff['business']);
        $this->fiscal('ecf', $withoutHandoff, 'bys:unverified', status: 422);
        $first = $this->bys(['path' => $p['path'].'process', 'payload' => [], 'mode' => 'revoke_after_publish'])['body'];
        $this->assertSame('uncertain', $first['status']);
        $this->assertSame(1, DB::table('business_fiscal_handoffs')->count());
        $this->assertSame(0, (int) $this->receiver('ecf')->query('SELECT count(*) FROM ecf_documents')->fetchColumn());
    }
}
