<?php

namespace App\Services\BusinessCommercial;

use App\Services\Billing\BusinessCatalogRules;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Native active access inventory, separate from monthly document consumption. */
final class CapacityService
{
    public function handle(string $service, array $data): array
    {
        abort_unless(config('business_commercial_bridge.capacities_enabled') && Schema::hasTable('business_capacity_sources'), 503, 'El control de capacidades Business no está preparado.');
        $data = Validator::make($data, [
            'action' => ['required', Rule::in(['inspect', 'start_inventory', 'inventory_user', 'inventory_users', 'finish_inventory', 'acquire_user', 'confirm_user', 'release_user', 'list_users', 'user_status'])],
            'link_id' => ['required', 'uuid'], 'local_company_id' => ['required', 'integer', 'min:1'],
            'central_company_id' => ['required', 'integer', 'min:1'], 'central_subscriber_id' => ['required', 'integer', 'min:1'],
            'operation_key' => ['required_unless:action,inspect,list_users,user_status', 'uuid'],
            'previous_key' => ['present_if:action,acquire_user', 'nullable', 'uuid'],
            'owner_user_id' => ['required_if:action,start_inventory', 'integer', 'min:1'],
            'local_user_id' => ['required_if:action,inventory_user,acquire_user,confirm_user,release_user,user_status', 'integer', 'min:1'],
            'local_user_ids' => ['required_if:action,inventory_users', 'array', 'min:1', 'max:25'],
            'local_user_ids.*' => ['integer', 'min:1', 'distinct'],
            'inventory_count' => ['required_if:action,finish_inventory', 'integer', 'min:0'],
            'inventory_hash' => ['required_if:action,finish_inventory', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'after' => ['sometimes', 'integer', 'min:0'],
        ])->validate();

        return DB::transaction(function () use ($service, $data): array {
            DB::table('companies')->where('id', $data['central_company_id'])->lockForUpdate()->firstOrFail();
            $context = app(CommercialService::class)->handle($service, [...$data, 'action' => 'snapshot']);
            if (! $context['managed']) {
                abort_unless($data['action'] === 'inspect', 409, 'La empresa no tiene contratación Business.');

                return $context;
            }
            try {
                (new BusinessCatalogRules)->capacityPolicy($context['capacity_policy'] ?? null);
            } catch (DomainException) {
                abort(409, 'El contrato requiere una política de capacidad explícita y compatible.');
            }
            abort_unless(is_int($context['capacity']['users'] ?? null) && $context['capacity']['users'] >= 0, 409, 'Capacidad contractual de usuarios inválida.');
            $source = DB::table('business_capacity_sources')->where('business_contract_id', $context['contract_id'])->where('identity_link_id', $data['link_id'])->first();
            $action = $data['action'];
            if ($action === 'start_inventory') {
                if ($source && $source->inventory_key === $data['operation_key']) {
                    abort_unless((int) $source->owner_user_id === $data['owner_user_id'], 409, 'La clave de inventario ya tiene otro propietario.');
                } else {
                    abort_if($source && $source->ready, 409, 'El inventario inicial ya fue cerrado.');
                    if ($source) {
                        DB::table('business_capacity_users')->where('source_id', $source->id)->delete();
                    }
                    DB::table('business_capacity_sources')->updateOrInsert(['business_contract_id' => $context['contract_id'], 'identity_link_id' => $data['link_id']],
                        ['owner_user_id' => $data['owner_user_id'], 'inventory_key' => $data['operation_key'], 'ready' => false, 'created_at' => $source?->created_at ?? now(), 'updated_at' => now()]);
                    $source = DB::table('business_capacity_sources')->where('business_contract_id', $context['contract_id'])->where('identity_link_id', $data['link_id'])->sole();
                    $this->event($source->id, $data);
                }
            } elseif (in_array($action, ['inventory_user', 'inventory_users', 'finish_inventory'], true)) {
                abort_unless($source && $source->inventory_key === $data['operation_key'], 409, 'El inventario cambió. Reinicia la comprobación.');
                if (in_array($action, ['inventory_user', 'inventory_users'], true)) {
                    abort_if($source->ready, 409, 'El inventario inicial ya está cerrado.');
                    foreach ($action === 'inventory_user' ? [$data['local_user_id']] : $data['local_user_ids'] as $id) {
                        $this->put($source->id, [...$data, 'local_user_id' => (int) $id], 'active');
                    }
                } else {
                    $ids = DB::table('business_capacity_users')->where('source_id', $source->id)->orderBy('local_user_id')->pluck('local_user_id')->map(fn ($id) => (int) $id)->all();
                    abort_unless(count($ids) === $data['inventory_count'] && hash_equals(hash('sha256', json_encode($ids, JSON_THROW_ON_ERROR)), $data['inventory_hash']), 409, 'El inventario está incompleto.');
                    if (! $source->ready) {
                        DB::table('business_capacity_sources')->where('id', $source->id)->update(['ready' => true, 'updated_at' => now()]);
                        $this->event($source->id, $data);
                    }
                }
            } elseif (in_array($action, ['acquire_user', 'confirm_user', 'release_user'], true)) {
                abort_unless($source && $source->ready, 409, 'Completa el inventario inicial de usuarios.');
                $row = DB::table('business_capacity_users')->where('source_id', $source->id)->where('local_user_id', $data['local_user_id'])->first();
                if ($action === 'acquire_user') {
                    abort_if(DB::table('business_capacity_events')->where('source_id', $source->id)->where('operation_key', $data['operation_key'])->where('action', 'release_user')->exists(), 409, 'La operación de ocupación ya fue liberada.');
                    abort_unless($context['access'], 403, 'El contrato no tiene cobertura vigente.');
                    abort_unless($this->ready($context), 409, 'Completa el inventario de todas las soluciones contratadas.');
                    abort_unless(($row?->operation_key ?? null) === $data['previous_key'] || $row?->operation_key === $data['operation_key'], 409, 'La ocupación cambió. Repite la comprobación.');
                    $before = $this->count($context);
                    $this->put($source->id, $data, $row?->state ?? 'reserved');
                    $after = $this->count($context);
                    abort_if($after > $context['capacity']['users'] && $after > $before, 429, 'Se agotó la capacidad de usuarios Business.');
                } elseif ($row && $row->operation_key === $data['operation_key']) {
                    if ($action === 'release_user') {
                        DB::table('business_capacity_users')->where('id', $row->id)->delete();
                    } else {
                        DB::table('business_capacity_users')->where('id', $row->id)->update(['state' => 'active', 'updated_at' => now()]);
                    }
                    $this->event($source->id, $data);
                }
                if ($action === 'release_user' && (! $row || $row->operation_key !== $data['operation_key'])) {
                    $this->event($source->id, $data);
                }
                $current = DB::table('business_capacity_users')->where('source_id', $source->id)->where('local_user_id', $data['local_user_id'])->first();

                return ['managed' => true, 'contract_id' => $context['contract_id'], 'local_user_id' => $data['local_user_id'],
                    'operation_key' => $current?->operation_key, 'state' => $current?->state ?? 'released'];
            }
            if ($action === 'user_status') {
                $row = $source ? DB::table('business_capacity_users')->where('source_id', $source->id)->where('local_user_id', $data['local_user_id'])->first() : null;

                return ['managed' => true, 'contract_id' => $context['contract_id'], 'local_user_id' => $data['local_user_id'],
                    'operation_key' => $row?->operation_key, 'state' => $row?->state ?? 'released'];
            }
            if ($action === 'list_users') {
                abort_unless($source && $source->ready, 409, 'El inventario no está preparado.');
                $users = DB::table('business_capacity_users')->where('source_id', $source->id)->where('local_user_id', '>', $data['after'] ?? 0)
                    ->orderBy('local_user_id')->limit(25)->get(['local_user_id', 'operation_key', 'state']);

                return ['managed' => true, 'contract_id' => $context['contract_id'], 'users' => $users->map(fn ($row) => ['local_user_id' => (int) $row->local_user_id, 'operation_key' => $row->operation_key, 'state' => $row->state])->all()];
            }
            $source = DB::table('business_capacity_sources')->where('business_contract_id', $context['contract_id'])->where('identity_link_id', $data['link_id'])->first();

            return [...$context, 'inventory_ready' => (bool) $source?->ready, 'inventory_key' => $source?->inventory_key,
                'owner_user_id' => $source ? (int) $source->owner_user_id : null, 'all_inventories_ready' => $this->ready($context), 'users_consumed' => $this->count($context)];
        }, 3);
    }

    private function ready(array $context): bool
    {
        $services = DB::table('business_capacity_sources as source')->join('business_identity_links as link', 'link.id', '=', 'source.identity_link_id')
            ->where('source.business_contract_id', $context['contract_id'])->where('source.ready', true)->pluck('link.service')->all();

        return array_diff($context['products'], $services) === [];
    }

    private function count(array $context): int
    {
        $rows = DB::table('business_capacity_users as seat')->join('business_capacity_sources as source', 'source.id', '=', 'seat.source_id')
            ->join('business_identity_links as link', 'link.id', '=', 'source.identity_link_id')
            ->leftJoin('business_identity_accounts as account', fn ($join) => $join->on('account.service', '=', 'link.service')->on('account.local_user_id', '=', 'seat.local_user_id'))
            ->where('source.business_contract_id', $context['contract_id'])
            ->get(['seat.local_user_id', 'source.owner_user_id', 'link.service', 'account.central_user_id']);
        $people = [];
        foreach ($rows as $row) {
            if (! $context['capacity_policy']['owner_counts'] && (int) $row->local_user_id === (int) $row->owner_user_id) {
                continue;
            }
            $identity = $row->central_user_id === null ? 'local:'.$row->service.':'.$row->local_user_id : 'central:'.$row->central_user_id;
            $key = $context['capacity_policy']['users'] === 'per_solution' ? $row->service.':'.$identity : $identity;
            $people[$key] = true;
        }

        return count($people);
    }

    private function put(int $source, array $data, string $state): void
    {
        $old = DB::table('business_capacity_users')->where('source_id', $source)->where('local_user_id', $data['local_user_id'])->first();
        if ($old && $old->operation_key === $data['operation_key']) {
            return;
        }
        DB::table('business_capacity_users')->updateOrInsert(['source_id' => $source, 'local_user_id' => $data['local_user_id']],
            ['operation_key' => $data['operation_key'], 'state' => $state, 'created_at' => $old?->created_at ?? now(), 'updated_at' => now()]);
        $this->event($source, $data);
    }

    private function event(int $source, array $data): void
    {
        DB::table('business_capacity_events')->insert(['source_id' => $source, 'local_user_id' => $data['local_user_id'] ?? null,
            'operation_key' => $data['operation_key'], 'action' => $data['action'], 'created_at' => now()]);
    }
}
