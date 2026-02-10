<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\ActivationRequest;
use App\Models\Company;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SubscriberMyServicesController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        if (!$user) abort(403);

        $company = $this->resolveCompany($user);
        if (!$company || !$company->subscriber_id) {
            return redirect()->route('subscriber')
                ->with('error', 'No tienes compañía/suscriptor asignado.');
        }

        // Última suscripción del subscriber (trialing/active/etc.)
        $subscription = Subscription::query()
            ->where('subscriber_id', $company->subscriber_id)
            ->latest('id')
            ->first();

        // Activation request "activa" (para solicitudes de servicios)
        $activation = ActivationRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ActivationRequest::ACTIVE_STATUSES ?? [
                ActivationRequest::STATUS_PENDING,
                ActivationRequest::STATUS_ACCEPTED,
                ActivationRequest::STATUS_TRIALING,
            ])
            ->latest('id')
            ->first();

        // -----------------------------
        // 1) Servicios activos en subscription_items
        // -----------------------------
        $activeItems = collect();
        $activeServiceIds = [];

        if ($subscription) {
            $activeItems = SubscriptionItem::query()
                ->where('subscription_id', $subscription->id)
                ->whereIn('status', ['active', 'trialing'])
                ->get(['id', 'service_id', 'status', 'quantity', 'meta', 'created_at']);

            $activeServiceIds = $activeItems->pluck('service_id')->unique()->values()->all();
        }

        // -----------------------------
        // 2) Servicios solicitados (activation_request_service)
        // -----------------------------
        $requestedRows = collect();
        $requestedServiceIds = [];

        if ($activation) {
            $requestedRows = DB::table('activation_request_service')
                ->where('activation_request_id', $activation->id)
                // ✅ incluir pending_payment para que se vea en UI
                ->whereIn('status', ['pending', 'pending_payment', 'cancelled'])
                ->get(['service_id', 'status', 'created_at', 'updated_at', 'meta']);

            $requestedServiceIds = $requestedRows->pluck('service_id')->unique()->values()->all();
        }

        // Unificar IDs para traer catálogo
        $allIds = collect($activeServiceIds)->merge($requestedServiceIds)->unique()->values()->all();

        $services = empty($allIds)
            ? collect()
            : Service::query()
            ->whereIn('id', $allIds)
            ->get([
                'id',
                'parent_id',
                'title',
                'slug',
                'icon',
                'badge',
                'short_description',

                // ✅ importante para activar/pagar
                'billing_model',
                'billable',
                'currency',
                'monthly_price',
                'yearly_price',
                'block_size',
                'included_units',
                'unit_name',
                'overage_unit_price',

                'required_plan',
                'active',
            ])
            ->keyBy('id');

        // Categorías (padres) para agrupar
        $parentIds = $services->pluck('parent_id')->filter()->unique()->values()->all();
        $parents = empty($parentIds)
            ? collect()
            : Service::query()
            ->whereIn('id', $parentIds)
            ->get(['id', 'title', 'slug', 'icon'])
            ->keyBy('id');

        // Build view models
        $activeMapped = $activeItems->map(function ($it) use ($services, $parents) {
            $s = $services->get($it->service_id);

            return [
                // ✅ CLAVE: id del subscription_item para cancelar
                'subscription_item_id' => (int) $it->id,

                'service_id' => (int) $it->service_id,
                'title' => $s?->title ?? '—',
                'slug' => $s?->slug ?? null,
                'icon' => $s?->icon ?? null,
                'badge' => $s?->badge ?? null,
                'short_description' => $s?->short_description ?? null,

                'category' => $s?->parent_id ? [
                    'id' => $s->parent_id,
                    'title' => $parents->get($s->parent_id)?->title,
                    'slug' => $parents->get($s->parent_id)?->slug,
                ] : null,

                // ✅ snapshot útil en UI
                'billable' => (bool) ($s?->billable ?? false),
                'billing_model' => $s?->billing_model ?? null,
                'currency' => $s?->currency ?? null,
                'monthly_price' => $s?->monthly_price ?? null,
                'yearly_price' => $s?->yearly_price ?? null,

                'source' => 'subscription',
                'status' => (string) $it->status,
                'quantity' => (int) $it->quantity,
                'created_at' => optional($it->created_at)->toDateTimeString(),
            ];
        });

        $requestedMapped = $requestedRows->map(function ($row) use ($services, $parents) {
            $serviceId = (int) $row->service_id;
            $s = $services->get($serviceId);

            return [
                'service_id' => $serviceId,
                'title' => $s?->title ?? '—',
                'slug' => $s?->slug ?? null,
                'icon' => $s?->icon ?? null,
                'badge' => $s?->badge ?? null,
                'short_description' => $s?->short_description ?? null,

                'category' => $s?->parent_id ? [
                    'id' => $s->parent_id,
                    'title' => $parents->get($s->parent_id)?->title,
                    'slug' => $parents->get($s->parent_id)?->slug,
                ] : null,

                // ✅ clave para activar/pagar
                'billable' => (bool) ($s?->billable ?? false),
                'billing_model' => $s?->billing_model ?? null,
                'currency' => $s?->currency ?? null,
                'monthly_price' => $s?->monthly_price ?? null,
                'yearly_price' => $s?->yearly_price ?? null,
                'required_plan' => $s?->required_plan ?? null,
                'catalog_active' => (bool) ($s?->active ?? true),

                'source' => 'request',
                'status' => (string) $row->status, // pending/pending_payment/cancelled
                'created_at' => (string) $row->created_at,
                'updated_at' => (string) $row->updated_at,
            ];
        });

        return Inertia::render('Subscriber/Services/My', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'currency' => $company->currency,
                'timezone' => $company->timezone,
            ],

            'activation_request' => $activation ? [
                'id' => $activation->id,
                'status' => $activation->status,
            ] : null,

            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'billing_cycle' => $subscription->billing_cycle,
                'currency' => $subscription->currency,
                'trial_ends_at_human' => $subscription->trial_ends_at?->format('Y-m-d'),
                'period_end_human' => $subscription->current_period_end?->format('Y-m-d'),
            ] : null,

            'active_services' => $activeMapped->values(),
            'requested_services' => $requestedMapped->values(),
        ]);
    }

    private function resolveCompany($user): ?Company
    {
        $company = null;

        if (!empty($user->company_id)) {
            $company = Company::query()->find($user->company_id);
        }
        if (!$company) {
            $company = Company::query()->where('owner_user_id', $user->id)->first();
        }
        if (!$company && !empty($user->subscriber_id)) {
            $company = Company::query()->where('subscriber_id', $user->subscriber_id)->first();
        }

        return $company;
    }
}
