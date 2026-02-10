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

class SubscriberServiceCatalogController extends Controller
{
    public function category(Request $request, string $categorySlug)
    {
        $user = $request->user();
        if (!$user) abort(403);

        // Resolver company (igual que dashboard)
        $company = null;
        if (!empty($user->company_id)) $company = Company::query()->find($user->company_id);
        if (!$company) $company = Company::query()->where('owner_user_id', $user->id)->first();
        if (!$company && !empty($user->subscriber_id)) {
            $company = Company::query()->where('subscriber_id', $user->subscriber_id)->first();
        }

        // Activation Request por user
        $activationRequest = ActivationRequest::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        // ✅ Suscripción y permiso para seleccionar servicios
        $subscriptionStatus = null;
        $subscriptionId = null;
        $canSelectServices = false;
        $selectReason = null;

        if ($company?->subscriber_id) {
            $sub = Subscription::query()
                ->where('subscriber_id', $company->subscriber_id)
                ->latest('id')
                ->first();

            $subscriptionStatus = $sub?->status;
            $subscriptionId = $sub?->id;

            // ✅ NUEVA regla: active OR trialing vigente
            [$canSelectServices, $selectReason] = $this->canSelectServicesFromSubscription($sub);
        } else {
            $selectReason = 'No tienes subscriber asociado a tu compañía.';
        }

        // Buscar el "padre" categoría
        $parent = Service::query()
            ->whereNull('parent_id')
            ->where('slug', $categorySlug)
            ->where('active', 1)
            ->firstOrFail([
                'id',
                'title',
                'slug',
                'icon',
                'short_description',
                'description',
            ]);

        // Hijos (catálogo real)
        $services = Service::query()
            ->where('parent_id', $parent->id)
            ->where('active', 1)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get([
                'id',
                'title',
                'slug',
                'icon',
                'badge',
                'short_description',
                'type',
                'billable',
                'billing_model',
                'currency',
                'monthly_price',
                'yearly_price',
                'block_size',
                'included_units',
                'unit_name',
                'overage_unit_price',
                'description',
                'required_plan',
            ]);

        // Requested (activation_request_service)
        $requestedMap = [];
        if ($activationRequest) {
            $requestedMap = DB::table('activation_request_service')
                ->where('activation_request_id', $activationRequest->id)
                ->get(['service_id', 'status'])
                ->keyBy('service_id')
                ->all();
        }

        // Active service ids (subscription_items)
        $activeServiceIds = [];
        if ($subscriptionId) {
            $activeServiceIds = SubscriptionItem::query()
                ->where('subscription_id', $subscriptionId)
                ->whereIn('status', ['active', 'trialing'])
                ->pluck('service_id')
                ->unique()
                ->values()
                ->all();
        }

        // Transform
        $items = $services->map(function ($s) use ($requestedMap, $activeServiceIds) {
            $row = $requestedMap[$s->id] ?? null;

            return [
                'id' => $s->id,
                'title' => $s->title,
                'slug' => $s->slug,
                'icon' => $s->icon,
                'badge' => $s->badge,
                'short_description' => $s->short_description,
                'description' => $s->description,

                'type' => $s->type,
                'billable' => (bool) $s->billable,
                'billing_model' => $s->billing_model,
                'currency' => $s->currency,
                'monthly_price' => $s->monthly_price,
                'yearly_price' => $s->yearly_price,
                'block_size' => $s->block_size,
                'included_units' => $s->included_units,
                'unit_name' => $s->unit_name,
                'overage_unit_price' => $s->overage_unit_price,
                'required_plan' => $s->required_plan,

                'requested' => (bool) $row,
                'request_status' => $row->status ?? null,

                'active' => in_array($s->id, $activeServiceIds, true),
            ];
        })->values();

        return Inertia::render('Subscriber/Services/Category', [
            'company' => $company ? [
                'id' => $company->id,
                'name' => $company->name,
                'currency' => $company->currency,
                'timezone' => $company->timezone,
            ] : null,

            'activation_request' => $activationRequest ? [
                'id' => $activationRequest->id,
                'status' => $activationRequest->status,
            ] : null,

            // ✅ para UI
            'subscription_status' => $subscriptionStatus,
            'can_select_services' => $canSelectServices,
            'can_select_services_reason' => $selectReason, // opcional (pero útil)

            'category' => [
                'id' => $parent->id,
                'title' => $parent->title,
                'slug' => $parent->slug,
                'short_description' => $parent->short_description,
                'description' => $parent->description,
                'icon' => $parent->icon,
            ],

            'services' => $items,
        ]);
    }

    /**
     * ✅ Regla de negocio recomendada:
     * - Puede seleccionar servicios si:
     *   - subscription.status === 'active' (y no vencida)
     *   - subscription.status === 'trialing' (y trial no vencido)
     */
    private function canSelectServicesFromSubscription(?Subscription $sub): array
    {
        if (!$sub) {
            return [false, 'No tienes suscripción creada.'];
        }

        $status = strtolower((string) $sub->status);

        // Si ends_at existe y ya pasó => no
        if ($sub->ends_at && $sub->ends_at->isPast()) {
            return [false, 'La suscripción está vencida.'];
        }

        // ACTIVE => permitido (opcional: validar current_period_end)
        if ($status === 'active') {
            if ($sub->current_period_end && $sub->current_period_end->isPast()) {
                return [false, 'La suscripción activa está fuera de período.'];
            }
            return [true, null];
        }

        // TRIALING => permitido si trial_ends_at no pasó
        if ($status === 'trialing') {
            if ($sub->trial_ends_at && $sub->trial_ends_at->isPast()) {
                return [false, 'El trial ya venció.'];
            }
            return [true, null];
        }

        return [false, "Estado de suscripción no permitido: {$status}."];
    }
}
