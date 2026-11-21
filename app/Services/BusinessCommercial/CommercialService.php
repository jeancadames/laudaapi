<?php

namespace App\Services\BusinessCommercial;

use App\Models\BusinessContract;
use App\Services\Billing\BusinessContractPeriods;
use App\Services\Entitlements\BusinessContractCoverage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Central admission and reconciliation. Native applications retain authorization of their own records. */
final class CommercialService
{
    public function handle(string $service, array $data): array
    {
        abort_unless(config('business_commercial_bridge.enabled') && config('business_identity.enabled'), 503);
        foreach (['business_contracts', 'business_contract_items', 'business_usage_documents', 'business_usage_aliases', 'business_usage_reservations', 'business_usage_events'] as $table) {
            abort_unless(Schema::hasTable($table), 503, 'Falta preparar el registro comercial.');
        }
        $data = Validator::make($data, [
            'action' => ['required', Rule::in(['snapshot', 'reserve', 'reference', 'commit', 'release', 'reconcile', 'cancel', 'fiscal_publish', 'fiscal_receive', 'fiscal_complete'])],
            'link_id' => ['required', 'uuid'], 'local_company_id' => ['required', 'integer', 'min:1'],
            'central_company_id' => ['required', 'integer', 'min:1'], 'central_subscriber_id' => ['required', 'integer', 'min:1'],
            'operation_key' => ['required_unless:action,snapshot', 'uuid'],
            'handoff' => ['required_if:action,fiscal_publish,fiscal_receive,fiscal_complete', 'array:document_id,target_company_id,reference,environment,payload_hash,fiscal,source_service'],
            'handoff.source_service' => ['sometimes', 'in:pos,bys'],
            'handoff.document_id' => ['required_with:handoff', 'uuid'],
            'handoff.target_company_id' => ['required_if:action,fiscal_publish', 'integer', 'min:1'],
            'handoff.reference' => ['required_with:handoff', 'string', 'max:128', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]*$/D'],
            'handoff.environment' => ['required_with:handoff', Rule::in(['testecf', 'certecf', 'ecf'])],
            'handoff.payload_hash' => ['required_with:handoff', 'regex:/^[a-f0-9]{64}$/D'],
            'handoff.fiscal' => ['required_if:action,fiscal_complete', 'array:issuer,ncf'],
            'handoff.fiscal.issuer' => ['required_with:handoff.fiscal', 'string', 'regex:/^(?:[0-9]{9}|[0-9]{11})$/D'],
            'handoff.fiscal.ncf' => ['required_with:handoff.fiscal', 'string', 'regex:/^E(?:31|32|43|47)[0-9]{10}$/D'],
            'document' => ['required_if:action,reserve,reference', 'array:source_reference,fiscal,document_id'],
            'document.source_reference' => ['required_with:document', 'string', 'max:128', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9_.:\/-]*$/D'],
            'document.document_id' => ['sometimes', 'uuid'],
            'document.fiscal' => ['sometimes', 'array:issuer,ncf'],
            'document.fiscal.issuer' => ['required_with:document.fiscal', 'string', 'regex:/^(?:[0-9]{9}|[0-9]{11})$/D'],
            'document.fiscal.ncf' => ['required_with:document.fiscal', 'string', 'regex:/^(?:E[0-9]{12}|B[0-9]{10})$/D'],
        ])->validate();

        return DB::transaction(function () use ($service, $data): array {
            // One company mutex serializes admission across all four services and contracts.
            $company = DB::table('companies')->where('id', $data['central_company_id'])->lockForUpdate()->first();
            abort_unless($company && (int) $company->subscriber_id === (int) $data['central_subscriber_id'], 403);
            $link = DB::table('business_identity_links')->where('id', $data['link_id'])->where('service', $service)
                ->where('central_company_id', $company->id)->where('central_subscriber_id', $company->subscriber_id)
                ->where('local_company_id', $data['local_company_id'])->lockForUpdate()->first();
            abort_unless($link && $link->active, 403, 'El vínculo comercial no está activo.');
            if (str_starts_with($data['action'], 'fiscal_')) {
                return app(FiscalHandoffService::class)->handle($link, $data);
            }
            if ($data['action'] === 'cancel') {
                return $this->cancel($link, $data);
            }
            $map = DB::table('business_contract_items')->where('identity_link_id', $link->id)
                ->where('local_company_id', $link->local_company_id)->first();
            if (! $map) {
                abort_unless($data['action'] === 'snapshot', 409, 'Esta empresa no tiene contrato Business para la solución.');
                // Never silently fall back to a local plan when a Business contract owns this company/service.
                $owned = BusinessContract::where('company_id', $company->id)->get()
                    ->contains(fn (BusinessContract $contract) => isset($contract->snapshot['products'][$service]));
                abort_if($owned, 409, 'El vínculo no coincide con el contrato Business.');

                return ['managed' => false, 'access' => false];
            }
            $contract = BusinessContract::whereKey($map->business_contract_id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $contract->company_id === (int) $company->id && (int) $contract->subscriber_id === (int) $company->subscriber_id, 409);
            if (in_array($data['action'], ['commit', 'release', 'reconcile'], true)) {
                // Finalize already admitted work even if the paid period has since expired.
                return $this->settle($link, $data);
            }
            $access = DB::table('subscription_items')->join('services', 'services.id', '=', 'subscription_items.service_id')
                ->where('subscription_items.id', $map->subscription_item_id)->where('services.service_key', $service)
                ->where('services.active', true)->where('subscription_items.status', 'active')
                ->where(fn ($query) => BusinessContractCoverage::constrain($query))->exists();
            $snapshot = $this->snapshot($contract, $access);
            if ($data['action'] === 'snapshot') {
                return $snapshot;
            }
            abort_unless($access, 403, 'El contrato Business no tiene cobertura vigente.');
            abort_if($service === 'cumplimiento' && $data['action'] !== 'reference', 422, 'Cumplimiento referencia documentos; no origina otro consumo.');

            return $this->reserve($contract, $link, $data, $snapshot);
        }, 3);
    }

    private function snapshot(BusinessContract $contract, bool $access): array
    {
        [$start, $end] = UsagePeriod::containing($contract->activated_at, $contract->timezone, CarbonImmutable::now());
        $capacity = $contract->snapshot['capacity'];
        abort_unless(($contract->snapshot['capacity_cycle'] ?? null) === 'monthly' && is_int($capacity['documents'] ?? null), 409, 'Capacidad contractual inválida.');
        $documents = DB::table('business_usage_documents')->where('business_contract_id', $contract->id)->where('period_starts_at', $start);
        $consumed = (clone $documents)->whereNotNull('consumed_at')->count();
        $pending = (clone $documents)->whereNull('consumed_at')->whereExists(fn ($query) => $query->selectRaw('1')
            ->from('business_usage_reservations')->whereColumn('document_id', 'business_usage_documents.id')->where('state', 'reserved'))->count();

        return ['managed' => true, 'access' => $access, 'contract_id' => $contract->id, 'snapshot_hash' => $contract->snapshot_hash,
            'products' => array_keys($contract->snapshot['products']), 'capacity' => $capacity, 'billing_cycle' => $contract->snapshot['billing_cycle'],
            'capacity_policy' => $contract->snapshot['capacity_policy'] ?? null,
            'timezone' => $contract->timezone, 'coverage' => BusinessContractPeriods::display($contract),
            'documents' => ['calendar' => 'activation_monthly_no_overflow', 'starts_at' => $start->toIso8601String(), 'ends_at' => $end->toIso8601String(),
                'limit' => $capacity['documents'], 'consumed' => $consumed, 'reserved' => $pending, 'remaining' => max(0, $capacity['documents'] - $consumed - $pending)]];
    }

    private function reserve(BusinessContract $contract, object $link, array $data, array $snapshot): array
    {
        abort_if(Schema::hasTable('business_usage_cancellations') && DB::table('business_usage_cancellations')
            ->where('identity_link_id', $link->id)->where('operation_key', $data['operation_key'])->exists(), 409, 'La operación fue cancelada definitivamente.');
        $input = $data['document'];
        $source = hash('sha256', json_encode(['source', $link->service, (int) $link->local_company_id, $input['source_reference']], JSON_THROW_ON_ERROR));
        $aliases = [$source => 'source'];
        if (isset($input['fiscal'])) {
            $fiscal = $input['fiscal'];
            abort_if(trim(substr($fiscal['ncf'], 3), '0') === '', 422, 'La secuencia fiscal debe ser distinta de cero.');
            $aliases[hash('sha256', json_encode(['fiscal', $fiscal['issuer'], $fiscal['ncf']], JSON_THROW_ON_ERROR))] = 'fiscal';
        }
        ksort($aliases);
        $hash = hash('sha256', json_encode([$data['action'], $aliases, $input['document_id'] ?? null], JSON_THROW_ON_ERROR));
        $existing = DB::table('business_usage_reservations')->where('identity_link_id', $link->id)->where('operation_key', $data['operation_key'])->first();
        if ($existing) {
            abort_unless(hash_equals($existing->request_hash, $hash), 409, 'La clave de operación ya tiene otro contenido.');

            return $this->receipt($existing);
        }
        $ids = DB::table('business_usage_aliases')->where('company_id', $contract->company_id)->whereIn('identity_hash', array_keys($aliases))->pluck('document_id')->all();
        if (isset($input['document_id'])) {
            $referenced = DB::table('business_usage_documents')->where('public_id', $input['document_id'])->where('company_id', $contract->company_id)->first();
            abort_unless($referenced, 409, 'El documento referenciado no pertenece a esta empresa.');
            $ids[] = $referenced->id;
        }
        $ids = array_values(array_unique($ids));
        abort_if(count($ids) > 1, 409, 'Las identidades apuntan a consumos distintos. Requieren conciliación.');
        $document = $ids ? DB::table('business_usage_documents')->where('id', $ids[0])->first() : null;
        if ($data['action'] === 'reference') {
            abort_unless($document && $document->consumed_at !== null, 409, 'Primero debe confirmarse el documento de origen.');
        }
        if ($document && isset($input['fiscal'])) {
            $fiscalHash = array_search('fiscal', $aliases, true);
            abort_if(DB::table('business_usage_aliases')->where('document_id', $document->id)->where('kind', 'fiscal')
                ->where('identity_hash', '!=', $fiscalHash)->exists(), 409, 'El documento ya tiene otra identidad fiscal.');
        }
        $inUse = $document && ($document->consumed_at !== null || DB::table('business_usage_reservations')->where('document_id', $document->id)->where('state', 'reserved')->exists());
        if (! $inUse) {
            abort_unless($snapshot['documents']['remaining'] > 0, 429, 'Se agotó la capacidad mensual de documentos Business.');
        }
        $now = now();
        $period = ['business_contract_id' => $contract->id,
            'period_starts_at' => CarbonImmutable::parse($snapshot['documents']['starts_at']),
            'period_ends_at' => CarbonImmutable::parse($snapshot['documents']['ends_at']), 'updated_at' => $now];
        if (! $document) {
            $id = DB::table('business_usage_documents')->insertGetId([...$period, 'public_id' => (string) Str::uuid(), 'company_id' => $contract->company_id, 'created_at' => $now]);
            $document = DB::table('business_usage_documents')->where('id', $id)->first();
        } elseif (! $inUse) {
            DB::table('business_usage_documents')->where('id', $document->id)->update($period);
        }
        foreach ($aliases as $identity => $kind) {
            if (DB::table('business_usage_aliases')->where('company_id', $contract->company_id)->where('identity_hash', $identity)->exists()) {
                continue;
            }
            DB::table('business_usage_aliases')->insert(['company_id' => $contract->company_id, 'document_id' => $document->id,
                'identity_hash' => $identity, 'kind' => $kind, 'created_at' => $now]);
        }
        $state = $data['action'] === 'reference' ? 'referenced' : 'reserved';
        $id = (string) Str::uuid();
        DB::table('business_usage_reservations')->insert(['id' => $id, 'document_id' => $document->id, 'identity_link_id' => $link->id,
            'operation_key' => $data['operation_key'], 'request_hash' => $hash, 'state' => $state,
            'business_contract_id' => $contract->id, 'period_starts_at' => $period['period_starts_at'], 'period_ends_at' => $period['period_ends_at'],
            'valid_until' => CarbonImmutable::parse($snapshot['coverage']['ends_at'])->min(CarbonImmutable::now()->addSeconds(120))->min($period['period_ends_at']),
            'created_at' => $now, 'updated_at' => $now]);
        $this->event($id, $state);

        return $this->receipt(DB::table('business_usage_reservations')->where('id', $id)->first());
    }

    private function settle(object $link, array $data): array
    {
        $reservation = DB::table('business_usage_reservations')->where('identity_link_id', $link->id)->where('operation_key', $data['operation_key'])->lockForUpdate()->first();
        abort_unless($reservation, 404, 'No existe una reserva para esta operación.');
        if ($data['action'] === 'reconcile') {
            return $this->receipt($reservation);
        }
        $target = $data['action'] === 'commit' ? 'committed' : 'released';
        if ($reservation->state !== $target) {
            abort_unless($reservation->state === 'reserved', 409, 'La operación ya tiene un resultado definitivo.');
            DB::table('business_usage_reservations')->where('id', $reservation->id)->update(['state' => $target, 'updated_at' => now()]);
            if ($target === 'committed') {
                DB::table('business_usage_documents')->where('id', $reservation->document_id)->whereNull('consumed_at')->update(['consumed_at' => now(), 'updated_at' => now()]);
            }
            $this->event($reservation->id, $target);
            $reservation->state = $target;
        }

        return $this->receipt($reservation);
    }

    /** The same company lock orders cancellation before or after any delayed admission. */
    private function cancel(object $link, array $data): array
    {
        abort_unless(Schema::hasTable('business_usage_cancellations'), 503, 'Falta preparar el registro de cancelaciones.');
        $exists = DB::table('business_usage_reservations')->where('identity_link_id', $link->id)->where('operation_key', $data['operation_key'])->exists();
        $receipt = $exists ? $this->settle($link, [...$data, 'action' => 'release'])
            : ['operation_key' => $data['operation_key'], 'state' => 'cancelled'];
        if (! DB::table('business_usage_cancellations')->where('identity_link_id', $link->id)->where('operation_key', $data['operation_key'])->exists()) {
            DB::table('business_usage_cancellations')->insert(['identity_link_id' => $link->id,
                'operation_key' => $data['operation_key'], 'created_at' => now()]);
        }

        return $receipt;
    }

    private function receipt(object $reservation): array
    {
        $document = DB::table('business_usage_documents')->where('id', $reservation->document_id)->first();

        return ['reservation_id' => $reservation->id, 'operation_key' => $reservation->operation_key, 'document_id' => $document->public_id,
            'state' => $reservation->state, 'consumed' => $document->consumed_at !== null,
            'valid_until' => CarbonImmutable::parse($reservation->valid_until, 'UTC')->toIso8601String(),
            'period_starts_at' => CarbonImmutable::parse($reservation->period_starts_at, 'UTC')->toIso8601String(),
            'period_ends_at' => CarbonImmutable::parse($reservation->period_ends_at, 'UTC')->toIso8601String()];
    }

    private function event(string $id, string $state): void
    {
        DB::table('business_usage_events')->insert(['reservation_id' => $id, 'event' => $state, 'created_at' => now()]);
    }
}
