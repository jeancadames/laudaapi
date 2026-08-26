<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\Billing\ServicePricingTierService;
use App\Services\Billing\ServiceBundleConfigurationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminServiceController extends Controller
{
    /**
     * GET /admin/services/{parent:slug}
     * Muestra el padre y sus hijos (opciones individuales) para administrar.
     */
    public function index(Service $parent)
    {
        // Solo permitir padres reales
        if ($parent->parent_id !== null) {
            abort(404);
        }

        $children = Service::query()
            ->where('parent_id', $parent->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get([
                'id',
                'title',

                // ✅ NUEVO
                'short_description',

                'slug',
                'href',
                'icon',
                'badge',
                'active',
                'billable',
                'type',
                'billing_model',
                'currency',
                'monthly_price',
                'yearly_price',
                'block_size',
                'included_units',
                'unit_name',
                'overage_unit_price',
                'sort_order',
            ]);

        $children->load([
            'pricingTiers' => fn ($query) =>
                $query
                    ->orderBy('billing_cycle')
                    ->orderBy('min_quantity')
                    ->orderBy('sort_order'),
                    'bundleItems.includedService:id,title,service_key,parent_id,currency,active',
            'bundleDiscountRules' => fn ($query) =>
                $query
                    ->orderByDesc('priority')
                    ->orderBy('id'),
]);


        $childrenPayload = $children->map(
            function (Service $service): array {
                $row = $service->toArray();

                unset(
                    $row['bundle_items'],
                    $row['bundle_discount_rules']
                );

                $row['bundle'] = [
                    'enabled' => $service->bundleItems->isNotEmpty(),
                    'items' => $service->bundleItems
                        ->map(
                            fn ($item) => [
                                'id' => $item->id,
                                'service_id' => $item->included_service_id,
                                'required' => (bool) $item->required,
                                'sort_order' => (int) $item->sort_order,
                            ]
                        )
                        ->values()
                        ->all(),
                    'rules' => $service->bundleDiscountRules
                        ->map(
                            fn ($rule) => [
                                'id' => $rule->id,
                                'code' => $rule->code,
                                'name' => $rule->name,
                                'discount_type' => $rule->discount_type,
                                'discount_value' => (float) $rule->discount_value,
                                'currency' => $rule->currency,
                                'priority' => (int) $rule->priority,
                                'active' => (bool) $rule->active,
                            ]
                        )
                        ->values()
                        ->all(),
                ];

                return $row;
            }
        );

        $bundleCandidates = Service::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get([
                'id',
                'title',
                'service_key',
                'parent_id',
                'currency',
                'billable',
                'active',
            ])
            ->map(
                fn (Service $service) => [
                    'id' => $service->id,
                    'title' => $service->title,
                    'service_key' => $service->service_key,
                    'parent_id' => $service->parent_id,
                    'currency' => $service->currency,
                    'billable' => (bool) $service->billable,
                    'active' => (bool) $service->active,
                ]
            )
            ->values();

        return Inertia::render('Admin/Services/Index', [
            'parent' => [
                'id'            => $parent->id,
                'title'         => $parent->title,
                'slug'          => $parent->slug,
                'active'        => (bool) $parent->active,
                'billable'      => (bool) $parent->billable,
                'monthly_price' => $parent->monthly_price,
                'yearly_price'  => $parent->yearly_price,
                'billing_model' => $parent->billing_model,
                'sort_order'    => (int) $parent->sort_order,
            ],
            'children' => $childrenPayload,
            'bundle_candidates' => $bundleCandidates,

        ]);
    }

    /**
     * POST /admin/services/{parent:slug}
     * Crea un hijo dentro del padre.
     */
    public function storeChild(Request $request, Service $parent)
    {
        if ($parent->parent_id !== null) {
            abort(404);
        }

        $data = $this->validateChildPayload($request, isCreate: true);

        $tiers = $data['pricing_tiers'] ?? [];
        unset($data['pricing_tiers']);

        $bundle = $data['bundle'] ?? null;
        unset($data['bundle']);

        // parent fijo
        $data['parent_id'] = $parent->id;

        // Normalización de payload según billing_model
        // per_user usa monthly_price/yearly_price como precio por usuario.
        // seat_block usa service_pricing_tiers para precio.
        $data = $this->normalizeBillingFields($data);

        DB::transaction(function () use ($data, $tiers, $bundle) {
            $service = Service::create($data);

            app(ServicePricingTierService::class)
                ->sync(
                    $service,
                    $tiers
                );

            if (is_array($bundle)) {
                app(ServiceBundleConfigurationService::class)
                    ->sync(
                        $service->fresh(),
                        $bundle
                    );
            }
        });

        return back()->with('success', 'Opción creada correctamente.');
    }

    /**
     * PATCH /admin/services/{service}
     * Actualiza un servicio hijo.
     */
    public function update(Request $request, Service $service)
    {
        // En este admin, actualizamos solo hijos (opciones).
        if ($service->parent_id === null) {
            abort(404);
        }

        $data = $this->validateChildPayload($request, isCreate: false);

        $tiers = $data['pricing_tiers'] ?? [];
        unset($data['pricing_tiers']);

        $bundle = $data['bundle'] ?? null;
        unset($data['bundle']);

        // No permitimos cambiar slug en update
        unset($data['slug']);

        $data = $this->normalizeBillingFields($data);

        DB::transaction(function () use (
            $service,
            $data,
            $tiers,
            $bundle) {
            $service->update($data);

            app(ServicePricingTierService::class)
                ->sync(
                    $service->fresh(),
                    $tiers
                );

            if (is_array($bundle)) {
                app(ServiceBundleConfigurationService::class)
                    ->sync(
                        $service->fresh(),
                        $bundle
                    );
            }
        });

        return back()->with('success', 'Opción actualizada correctamente.');
    }

    /**
     * PATCH /admin/services/toggle/{service}
     * Activa/desactiva un servicio hijo.
     */
    public function toggleActive(Service $service)
    {
        if ($service->parent_id === null) {
            abort(404);
        }

        $service->active = !$service->active;
        $service->save();

        return back()->with('success', 'Estado actualizado.');
    }

    /**
     * Validaciones para create/update de hijo.
     */
    protected function validateChildPayload(Request $request, bool $isCreate): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],

            // ✅ NUEVO: short_description
            // si tu columna es string(160), valida max:160
            'short_description' => ['nullable', 'string', 'max:160'],

            // slug solo en creación (en update lo ignoramos)
            'slug' => $isCreate
                ? ['required', 'string', 'max:255', Rule::unique('services', 'slug')]
                : ['sometimes'],

            'href'  => ['nullable', 'string', 'max:255'],
            'icon'  => ['nullable', 'string', 'max:255'],
            'badge' => ['nullable', 'string', 'max:50'],

            'active'   => ['sometimes', 'boolean'],
            'billable' => ['sometimes', 'boolean'],

            'type' => ['required', Rule::in(['core', 'addon', 'usage', 'external'])],

            'billing_model' => ['required', Rule::in(['flat', 'per_user', 'seat_block', 'usage'])],

            'monthly_price' => ['nullable', 'numeric', 'min:0'],
            'yearly_price'  => ['nullable', 'numeric', 'min:0'],

            'sort_order' => ['nullable', 'integer', 'min:0'],

            // seat_block
            'block_size' => ['nullable', 'integer', 'min:1'],
            'pricing_tiers' => ['nullable', 'array'],
            'pricing_tiers.*.billing_cycle' =>
                ['required', Rule::in(['monthly', 'yearly'])],
            'pricing_tiers.*.min_quantity' =>
                ['required', 'integer', 'min:1'],
            'pricing_tiers.*.max_quantity' =>
                ['nullable', 'integer', 'min:1'],
            'pricing_tiers.*.price' =>
                ['required', 'numeric', 'min:0'],
            'pricing_tiers.*.currency' =>
                ['required', 'string', 'size:3'],
            'pricing_tiers.*.active' =>
                ['sometimes', 'boolean'],
            'pricing_tiers.*.sort_order' =>
                ['nullable', 'integer', 'min:0'],

            // bundle comercial
            'bundle' => ['nullable', 'array'],
            'bundle.enabled' => ['sometimes', 'boolean'],
            'bundle.items' => ['nullable', 'array'],
            'bundle.items.*.service_id' =>
                ['required', 'integer', 'exists:services,id'],
            'bundle.items.*.required' =>
                ['sometimes', 'boolean'],
            'bundle.items.*.sort_order' =>
                ['nullable', 'integer', 'min:0'],
            'bundle.rules' => ['nullable', 'array'],
            'bundle.rules.*.id' =>
                ['nullable', 'integer', 'exists:service_bundle_discount_rules,id'],
            'bundle.rules.*.code' =>
                ['required', 'string', 'max:255'],
            'bundle.rules.*.name' =>
                ['required', 'string', 'max:255'],
            'bundle.rules.*.discount_type' =>
                ['required', Rule::in(['percentage', 'fixed_amount'])],
            'bundle.rules.*.discount_value' =>
                ['required', 'numeric', 'min:0'],
            'bundle.rules.*.currency' =>
                ['nullable', 'string', 'size:3'],
            'bundle.rules.*.priority' =>
                ['nullable', 'integer'],
            'bundle.rules.*.active' =>
                ['sometimes', 'boolean'],

            // usage
            'unit_name'          => ['nullable', 'string', 'max:50'],
            'included_units'     => ['nullable', 'integer', 'min:0'],
            'overage_unit_price' => ['nullable', 'numeric', 'min:0'],
        ];

        return $request->validate($rules);
    }

    /**
     * Normaliza campos según billing_model para evitar basura en DB.
     */
    protected function normalizeBillingFields(array $data): array
    {
        $billing = $data['billing_model'] ?? 'flat';

        // Defaults seguros
        $data['active'] = array_key_exists('active', $data) ? (bool) $data['active'] : true;
        $data['billable'] = array_key_exists('billable', $data) ? (bool) $data['billable'] : true;
        $data['sort_order'] = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;

        // ✅ Normaliza short_description (no guarda strings vacíos)
        if (array_key_exists('short_description', $data)) {
            $sd = trim((string) ($data['short_description'] ?? ''));
            $data['short_description'] = $sd !== '' ? $sd : null;
        }

        if ($billing === 'seat_block') {
            // El precio sale exclusivamente de service_pricing_tiers.
            $data['monthly_price'] = null;
            $data['yearly_price'] = null;
            $data['block_size'] = null;
            $data['unit_name'] = 'usuario';
            $data['included_units'] = null;
            $data['overage_unit_price'] = null;

            return $data;
        }

        if ($billing === 'usage') {
            $data['block_size'] = null;

            $data['unit_name'] = isset($data['unit_name']) && trim((string) $data['unit_name']) !== ''
                ? trim((string) $data['unit_name'])
                : 'units';

            $data['included_units'] = isset($data['included_units'])
                ? (int) $data['included_units']
                : 0;

            return $data;
        }

        // flat: limpia campos no aplicables
        $data['block_size'] = null;
        $data['unit_name'] = null;
        $data['included_units'] = null;
        $data['overage_unit_price'] = null;

        return $data;
    }
}
