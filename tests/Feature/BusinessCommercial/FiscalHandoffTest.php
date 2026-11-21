<?php

namespace Tests\Feature\BusinessCommercial;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BusinessCommercialTestCase;

final class FiscalHandoffTest extends BusinessCommercialTestCase
{
    public function test_bys_and_pos_evidence_cannot_be_exchanged_and_each_source_keeps_its_fiscal_types(): void
    {
        $operation = $this->operation('bys:purchase:1');
        $receipt = $this->exchange('bys', 'reserve', $operation);
        $this->exchange('bys', 'commit', ['operation_key' => $operation['operation_key']]);
        $data = ['operation_key' => $operation['operation_key'], 'handoff' => ['document_id' => $receipt['document_id'],
            'target_company_id' => 501, 'reference' => 'bys:fiscal:1', 'environment' => 'ecf',
            'payload_hash' => str_repeat('b', 64), 'source_service' => 'bys']];
        $this->exchange('pos', 'fiscal_publish', $data, 403);
        $published = $this->exchange('bys', 'fiscal_publish', $data);
        $wrong = $data;
        unset($wrong['handoff']['source_service']);
        $this->exchange('ecf', 'fiscal_receive', $wrong, 403);
        $this->assertSame($published, $this->exchange('ecf', 'fiscal_receive', $data));
        $data['handoff']['fiscal'] = ['issuer' => '101010101', 'ncf' => 'E310000000001'];
        $this->exchange('ecf', 'fiscal_complete', $data, 422);
        $data['handoff']['fiscal']['ncf'] = 'E430000000001';
        $this->assertSame($published, $this->exchange('ecf', 'fiscal_complete', $data));
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());

        $pos = $this->handoff();
        $this->exchange('pos', 'fiscal_publish', $pos);
        $pos['handoff']['source_service'] = 'bys';
        $this->exchange('ecf', 'fiscal_receive', $pos, 403);
    }

    private function handoff(bool $commit = true): array
    {
        $operation = $this->operation();
        $receipt = $this->exchange('pos', 'reserve', $operation);
        if ($commit) {
            $this->exchange('pos', 'commit', ['operation_key' => $operation['operation_key']]);
        }

        return ['operation_key' => $operation['operation_key'], 'handoff' => ['document_id' => $receipt['document_id'],
            'target_company_id' => 501, 'reference' => 'pos:fiscal:1', 'environment' => 'testecf', 'payload_hash' => str_repeat('a', 64)]];
    }

    public function test_publish_receive_complete_and_reference_share_one_consumption(): void
    {
        $data = $this->handoff();
        $published = $this->exchange('pos', 'fiscal_publish', $data);
        $this->assertSame($published, $this->exchange('pos', 'fiscal_publish', $data));
        unset($data['handoff']['target_company_id']);
        $this->assertSame($published, $this->exchange('ecf', 'fiscal_receive', $data));
        $data['handoff']['fiscal'] = ['issuer' => '101010101', 'ncf' => 'E310000000001'];
        $this->assertSame($published, $this->exchange('ecf', 'fiscal_complete', $data));
        $this->assertSame($published, $this->exchange('ecf', 'fiscal_complete', $data));
        $reference = $this->exchange('cumplimiento', 'reference', $this->operation('fiscal:received', ['fiscal' => $data['handoff']['fiscal']]));
        $this->assertSame($published['document_id'], $reference['document_id']);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
        $this->assertSame(1, DB::table('business_usage_aliases')->where('kind', 'fiscal')->count());
    }

    public function test_uncommitted_source_missing_contract_product_and_wrong_destination_cannot_publish(): void
    {
        $data = $this->handoff(false);
        $this->exchange('pos', 'fiscal_publish', $data, 409);
        $this->exchange('pos', 'commit', ['operation_key' => $data['operation_key']]);
        $wrong = $data;
        $wrong['handoff']['target_company_id'] = 999;
        $this->exchange('pos', 'fiscal_publish', $wrong, 403);
        $link = DB::table('business_identity_links')->where('service', 'ecf')->sole();
        DB::table('business_contract_items')->where('identity_link_id', $link->id)->delete();
        $this->exchange('pos', 'fiscal_publish', $data, 403);
        $this->assertSame(0, DB::table('business_fiscal_handoffs')->count());
    }

    public function test_reference_environment_payload_and_document_are_immutable(): void
    {
        $data = $this->handoff();
        $this->exchange('pos', 'fiscal_publish', $data);
        foreach (['reference' => 'pos:another', 'environment' => 'ecf', 'payload_hash' => str_repeat('b', 64), 'document_id' => (string) Str::uuid()] as $key => $value) {
            $changed = $data;
            $changed['handoff'][$key] = $value;
            $this->exchange('pos', 'fiscal_publish', $changed, 409);
            $this->exchange('ecf', 'fiscal_receive', $changed, $key === 'document_id' ? 403 : 409);
        }
        $this->exchange('bys', 'fiscal_publish', $data, 403);
        $this->exchange('pos', 'fiscal_receive', $data, 403);
        DB::table('business_identity_links')->where('service', 'pos')->update(['active' => false]);
        $this->exchange('ecf', 'fiscal_receive', $data, 403);
    }

    public function test_completed_fiscal_identity_cannot_change_or_collide_with_another_consumption(): void
    {
        $data = $this->handoff();
        $this->exchange('pos', 'fiscal_publish', $data);
        $data['handoff']['fiscal'] = ['issuer' => '101010101', 'ncf' => 'E310000000001'];
        $this->exchange('ecf', 'fiscal_complete', $data, 409);
        $this->exchange('ecf', 'fiscal_receive', $data);
        $other = $this->operation('another:document', ['fiscal' => $data['handoff']['fiscal']]);
        $this->exchange('bys', 'reserve', $other);
        $this->exchange('bys', 'commit', ['operation_key' => $other['operation_key']]);
        $this->exchange('ecf', 'fiscal_complete', $data, 409);
        $data['handoff']['fiscal']['ncf'] = 'E310000000002';
        $this->exchange('ecf', 'fiscal_complete', $data);
        $data['handoff']['fiscal']['ncf'] = 'E310000000003';
        $this->exchange('ecf', 'fiscal_complete', $data, 409);
        $this->assertSame(2, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }
}
