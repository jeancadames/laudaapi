<?php

namespace Tests\Feature\BusinessCommercial;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\BusinessCommercialTransportTestCase;

final class BysCommercialReferencesTest extends BusinessCommercialTransportTestCase
{
    private function bys(array $input = [], int $status = 200): array
    {
        $process = new Process(['php', base_path('tests/Fixtures/BusinessCommercial/bys-purchase.php'), $this->runtime]);
        $process->setInput(json_encode($input + ['action' => 'request', 'key' => 'synthetic-reference', 'references_enabled' => true]));
        $process->setTimeout(90)->mustRun();
        try {
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new \RuntimeException($process->getOutput().$process->getErrorOutput(), previous: $error);
        }
        $this->assertSame($status, $result['status'], $process->getOutput().' '.@file_get_contents($this->runtime.'/bys/storage/logs/application.log'));

        return $result;
    }

    private function startBys(): void
    {
        $link = DB::table('business_identity_links')->where('service', 'bys')->sole();
        DB::table('business_identity_links')->where('id', $link->id)->update(['local_company_id' => 1]);
        DB::table('business_contract_items')->where('identity_link_id', $link->id)->update(['local_company_id' => 1]);
        $this->startServer();
        $this->bys(['action' => 'setup', 'company_rnc' => '131234567']);
    }

    private function payload(): array
    {
        return ['supplier_id' => 1, 'document_type' => 'commercial_invoice', 'purchase_kind' => 'service',
            'supplier_invoice_number' => 'SYNTHETIC-001', 'issue_date' => today()->format('Y-m-d'), 'currency' => 'DOP', 'exchange_rate' => 1,
            'is_credit' => true, 'lines' => [['description' => 'Servicio ficticio', 'quantity' => 1, 'unit_price_original' => 100, 'tax_rate' => 18]]];
    }

    private function references(): array
    {
        return (new \PDO('sqlite:'.$this->runtime.'/bys/database.sqlite'))->query('SELECT * FROM business_document_references')->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function test_later_fiscal_identity_is_attached_to_the_original_consumption_and_review_stays_native(): void
    {
        $this->startBys();
        $purchase = $this->bys(['payload' => $this->payload()], 201)['body'];
        $original = DB::table('business_usage_documents')->sole()->public_id;
        $updated = $this->bys(['path' => '/bys/purchase-documents/'.$purchase['id'], 'method' => 'PATCH',
            'payload' => ['expected_revision' => $purchase['revision'], 'correction_reason' => 'Llegó el comprobante revisado',
                'document_type' => 'local_fiscal_invoice', 'ncf' => 'E310000000001']])['body'];
        $this->assertSame('draft', $updated['status']);
        $this->assertNotNull($this->references()[0]['referenced_at']);
        $this->assertSame($original, json_decode($this->references()[0]['document'], true)['document_id']);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
        $fiscal = ['issuer' => '101010101', 'ncf' => 'E310000000001'];
        $result = $this->exchange('cumplimiento', 'reference', $this->operation('later:compliance', ['fiscal' => $fiscal]));
        $this->assertSame($original, $result['document_id']);
    }

    public function test_own_fiscal_document_uses_company_issuer_and_snapshot_replay_has_one_reference(): void
    {
        $this->startBys();
        $payload = $this->payload();
        $payload['document_type'] = 'receipt';
        $payload['ncf'] = 'E430000000001';
        $payload['lines'][0]['tax_rate'] = 0;
        $purchase = $this->bys(['payload' => $payload], 201)['body'];
        $first = $this->bys(['action' => 'prepare_snapshot', 'document' => $purchase['id']]);
        $again = $this->bys(['action' => 'prepare_snapshot', 'document' => $purchase['id']]);
        $this->assertSame($first['snapshot'], $again['snapshot']);
        $refs = $this->references();
        $this->assertCount(1, $refs);
        $this->assertNotNull($refs[0]['referenced_at']);
        $this->assertSame('131234567', json_decode($refs[0]['document'], true)['fiscal']['issuer']);
        $this->assertSame(1, DB::table('business_usage_aliases')->where('kind', 'fiscal')->count());
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public function test_discovery_recovers_a_native_correction_received_while_references_were_disabled(): void
    {
        $this->startBys();
        $purchase = $this->bys(['payload' => $this->payload()], 201)['body'];
        $this->bys(['references_enabled' => false, 'path' => '/bys/purchase-documents/'.$purchase['id'], 'method' => 'PATCH',
            'payload' => ['expected_revision' => $purchase['revision'], 'correction_reason' => 'Llegó el comprobante revisado',
                'document_type' => 'local_fiscal_invoice', 'ncf' => 'E310000000001']]);
        $this->assertSame([], $this->references());
        $this->bys(['action' => 'references_reconcile', 'discover' => 'bys_purchase', 'dry_run' => true]);
        $this->assertSame([], $this->references());
        $this->assertSame(0, $this->bys(['action' => 'references_reconcile', 'discover' => 'bys_purchase'])['exit']);
        $this->assertNotNull($this->references()[0]['referenced_at']);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public function test_reviewed_inbox_links_existing_purchase_without_another_consumption(): void
    {
        $this->startBys();
        $payload = ['supplier_id' => 1, 'document_type' => 'local_fiscal_invoice', 'purchase_kind' => 'service', 'ncf' => 'E310000000001',
            'issue_date' => '2026-11-17', 'currency' => 'DOP', 'exchange_rate' => '1', 'is_credit' => false,
            'lines' => [['description' => 'Servicio revisado', 'quantity' => '1', 'unit_price_original' => '100', 'tax_rate' => '18']]];
        $purchase = $this->bys(['payload' => $payload], 201)['body'];
        $inbox = $this->bys(['action' => 'prepare_inbox'])['body'];
        $result = $this->bys(['inbox_enabled' => true, 'path' => '/bys/ecf-inbox/'.$inbox['id'].'/confirm',
            'payload' => ['expected_revision' => $inbox['revision'], 'reviewed' => true, 'confirmed_recipient' => true,
                'reason' => 'Evidencia sintética revisada por el operador.', 'payload' => $payload]])['body'];
        $this->assertSame($purchase['id'], $result['document']['purchase_document_id']);
        $this->assertSame('linked', $result['document']['status']);
        $this->assertCount(1, $this->references());
        $this->assertNotNull($this->references()[0]['referenced_at']);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }
}
