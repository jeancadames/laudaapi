<?php

namespace App\Services\BusinessCommercial;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Called under CommercialService's company mutex; each source attests its own committed document. */
final class FiscalHandoffService
{
    public function handle(object $link, array $data): array
    {
        abort_unless(Schema::hasTable('business_fiscal_handoffs'), 503, 'Falta preparar la entrega fiscal Business.');
        $input = $data['handoff'];
        $sourceService = $input['source_service'] ?? 'pos';
        if ($data['action'] === 'fiscal_publish') {
            abort_unless($link->service === $sourceService && in_array($sourceService, ['pos', 'bys'], true), 403);
            $reservation = DB::table('business_usage_reservations')->where('identity_link_id', $link->id)
                ->where('operation_key', $data['operation_key'])->first();
            abort_unless($reservation && $reservation->state === 'committed', 409, 'Primero confirma el consumo del documento.');
            $document = DB::table('business_usage_documents')->find($reservation->document_id);
            abort_unless($document && $document->consumed_at && $document->public_id === $input['document_id'], 409);
            $target = DB::table('business_identity_links')->where('service', 'ecf')->where('central_company_id', $link->central_company_id)
                ->where('central_subscriber_id', $link->central_subscriber_id)->where('local_company_id', $input['target_company_id'])->first();
            abort_unless($target && $target->active, 403, 'La cuenta e-CF de destino no pertenece al vínculo vigente.');
            $row = DB::table('business_fiscal_handoffs')->where('document_id', $document->id)->first();
            if (! $row) {
                abort_if(DB::table('business_fiscal_handoffs')->where('target_link_id', $target->id)
                    ->where('environment', $input['environment'])->where('reference', $input['reference'])->exists(), 409);
                DB::table('business_fiscal_handoffs')->insert([
                    'id' => $data['operation_key'], 'document_id' => $document->id, 'source_reservation_id' => $reservation->id,
                    'target_link_id' => $target->id, 'reference' => $input['reference'], 'environment' => $input['environment'],
                    'payload_hash' => $input['payload_hash'], 'created_at' => now(), 'updated_at' => now(),
                ]);
                $row = DB::table('business_fiscal_handoffs')->find($data['operation_key']);
            }
            abort_unless($row->id === $data['operation_key'] && $row->source_reservation_id === $reservation->id
                && $row->target_link_id === $target->id, 409, 'El documento ya tiene otra entrega fiscal.');
        } else {
            abort_unless($link->service === 'ecf', 403);
            $row = DB::table('business_fiscal_handoffs')->where('id', $data['operation_key'])->where('target_link_id', $link->id)->first();
            abort_unless($row, 409, 'El origen todavía no ha confirmado esta entrega fiscal.');
            $document = DB::table('business_usage_documents')->find($row->document_id);
            $reservation = DB::table('business_usage_reservations')->find($row->source_reservation_id);
            $source = DB::table('business_identity_links')->find($reservation->identity_link_id);
            abort_unless($source && $source->active && $source->service === $sourceService
                && (int) $source->central_company_id === (int) $link->central_company_id
                && $document && $document->public_id === $input['document_id'] && $document->consumed_at
                && $reservation->state === 'committed', 403, 'El origen de la entrega no está vigente.');
        }
        abort_unless(DB::table('business_contract_items')->where('business_contract_id', $reservation->business_contract_id)
            ->where('identity_link_id', $row->target_link_id)->exists(), 403, 'El contrato de origen no incluye esta cuenta e-CF.');
        abort_unless($row->reference === $input['reference'] && $row->environment === $input['environment']
            && hash_equals($row->payload_hash, $input['payload_hash']), 409, 'La entrega fiscal tiene otro contenido o destino.');
        if ($data['action'] === 'fiscal_receive') {
            DB::table('business_fiscal_handoffs')->where('id', $row->id)->whereNull('received_at')->update(['received_at' => now(), 'updated_at' => now()]);
        }
        if ($data['action'] === 'fiscal_complete') {
            abort_unless($row->received_at, 409);
            $fiscal = $input['fiscal'];
            abort_unless(in_array(substr($fiscal['ncf'], 0, 3), $sourceService === 'bys' ? ['E43', 'E47'] : ['E31', 'E32'], true), 422);
            abort_if(trim(substr($fiscal['ncf'], 3), '0') === '', 422);
            $hash = hash('sha256', json_encode(['fiscal', $fiscal['issuer'], $fiscal['ncf']], JSON_THROW_ON_ERROR));
            abort_if($row->fiscal_identity_hash && ! hash_equals($row->fiscal_identity_hash, $hash), 409, 'La entrega ya identifica otro comprobante.');
            $alias = DB::table('business_usage_aliases')->where('company_id', $link->central_company_id)->where('identity_hash', $hash)->first();
            abort_if($alias && (int) $alias->document_id !== (int) $row->document_id, 409, 'La identidad fiscal ya corresponde a otro consumo.');
            abort_if(DB::table('business_usage_aliases')->where('document_id', $row->document_id)->where('kind', 'fiscal')->where('identity_hash', '!=', $hash)->exists(), 409);
            if (! $alias) {
                DB::table('business_usage_aliases')->insert(['company_id' => $link->central_company_id, 'document_id' => $row->document_id,
                    'identity_hash' => $hash, 'kind' => 'fiscal', 'created_at' => now()]);
            }
            DB::table('business_fiscal_handoffs')->where('id', $row->id)->update(['fiscal_identity_hash' => $hash,
                'completed_at' => $row->completed_at ?? now(), 'updated_at' => now()]);
        }

        return ['handoff_id' => $row->id, 'document_id' => $document->public_id, 'reference' => $row->reference,
            'environment' => $row->environment, 'payload_hash' => $row->payload_hash, ...($sourceService === 'bys' ? ['source_service' => 'bys'] : [])];
    }
}
