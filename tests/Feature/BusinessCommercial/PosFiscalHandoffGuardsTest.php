<?php

namespace Tests\Feature\BusinessCommercial;

use Illuminate\Support\Facades\DB;
use Tests\Support\PosFiscalHandoffTestCase;

final class PosFiscalHandoffGuardsTest extends PosFiscalHandoffTestCase
{
    public function test_unverified_origins_and_changed_contents_never_create_another_document(): void
    {
        $this->startServer(true);
        $first = $this->pos();
        $fiscal = $first['fiscal'][0];
        $payload = json_decode($fiscal['payload'], true, flags: JSON_THROW_ON_ERROR);
        $this->fiscal('ecf', $payload, 'pos:another', status: 409);
        foreach (['origin', 'items', 'expected_totals'] as $field) {
            $changed = $payload;
            if ($field === 'origin') {
                $changed['origin']['document_reference'] = '999';
            } elseif ($field === 'items') {
                $changed['items'][0]['descripcion'] = 'Another invoice';
            } else {
                $changed['expected_totals']['total_cents'] = 1;
            }
            $this->fiscal('ecf', $changed, $fiscal['reference'], status: 409);
        }
        $legacy = $payload;
        unset($legacy['business']);
        $this->fiscal('ecf', $legacy, $fiscal['reference'], status: 409);
        $this->fiscal('ecf', $legacy, 'pos:without-proof', status: 422);
        file_put_contents($this->runtime.'/fiscal-mode', 'disabled');
        $this->fiscal('ecf', $payload, $fiscal['reference']);
        $this->fiscal('ecf', $payload, 'pos:disabled', status: 503);
        $this->receiver('ecf')->exec("UPDATE api_credentials SET scopes = '[\"ecf:read\"]'");
        $this->fiscal('ecf', $payload, $fiscal['reference'], status: 403);
        $issuer = $this->fiscal('emisor', method: 'get', status: 200);
        $this->assertFalse($issuer['can_emit']);
        $this->assertSame(1, (int) $this->receiver('ecf')->query('SELECT count(*) FROM ecf_documents')->fetchColumn());
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public function test_revoked_receiver_link_blocks_new_emission_but_preserves_paid_sale(): void
    {
        $this->startServer(true);
        file_put_contents($this->runtime.'/fiscal-mode', 'revoke_after_receive');
        $first = $this->pos();
        $this->assertSame(1, $first['native']['payments']);
        $this->assertSame(0, (int) $this->receiver('ecf')->query('SELECT count(*) FROM ecf_documents')->fetchColumn());
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
        $this->assertSame(1, (int) $this->receiver('ecf')->query('SELECT siguiente FROM ncf_sequences')->fetchColumn());
        $this->assertSame('true', $this->pos()['replayed']);
    }

    public function test_a_replaced_execution_token_cannot_create_and_recovery_uses_a_new_token(): void
    {
        $this->startServer(true);
        file_put_contents($this->runtime.'/fiscal-mode', 'replace_execution_token');
        $first = $this->pos();
        $this->assertSame(1, $first['native']['payments']);
        $this->assertSame(0, (int) $this->receiver('ecf')->query('SELECT count(*) FROM ecf_documents')->fetchColumn());
        $this->assertSame('11111111-1111-4111-8111-111111111111', $this->receiver('ecf')->query('SELECT execution_token FROM business_pos_emissions')->fetchColumn());
        file_put_contents($this->runtime.'/fiscal-mode', '');
        $recovered = $this->pos(['action' => 'fiscal_reconcile']);
        $this->assertSame('e310000000001', $recovered['fiscal'][0]['encf'], json_encode($recovered).' '.$this->logs());
        $this->assertNotSame('11111111-1111-4111-8111-111111111111', $this->receiver('ecf')->query('SELECT execution_token FROM business_pos_emissions')->fetchColumn());
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }
}
