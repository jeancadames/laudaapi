<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\ActivationRequest;
use App\Models\ActivationRequestService;
use App\Models\Company;
use App\Models\Service;
use App\Models\ServicePlan;
use App\Models\StandaloneServiceSettlement;
use App\Models\Subscriber;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Services\Billing\StandaloneServiceCheckoutService;
use App\Services\Subscribers\CompanyContextResolver;
use App\Services\Subscribers\SubscriberResolver;
use App\Services\Subscribers\TenantAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SubscriberAppStoreController extends Controller
{
    public function show(
        Request $request,
        string $serviceKey,
        SubscriberResolver $subscriberResolver,
        CompanyContextResolver $companyResolver,
        TenantAccessService $tenantAccessService,
        StandaloneServiceCheckoutService $checkoutService
    ): Response {
        [
            $user,
            $subscriber,
            $company,
        ] = $this->tenantAdminContext(
            $request,
            $subscriberResolver,
            $companyResolver,
            $tenantAccessService
        );

        $service = $this->storeService($serviceKey);

        $activeItem = $this->activeEntitlement(
            $subscriber,
            $service
        );

        $plans = ServicePlan::query()
            ->where('service_id', $service->id)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(
                fn (ServicePlan $plan): array =>
                    $this->planPayload(
                        $checkoutService,
                        $subscriber,
                        $company,
                        $service,
                        $plan
                    )
            )
            ->values();

        return Inertia::render('App/Store/Show', [
            'company' => [
                'id' => (int) $company->id,
                'name' => (string) (
                    $company->name
                    ?? $company->business_name
                    ?? 'Mi empresa'
                ),
                'currency' => (string) (
                    $company->currency
                    ?? $subscriber->currency
                    ?? 'DOP'
                ),
            ],
            'service' => [
                'id' => (int) $service->id,
                'service_key' => (string) $service->service_key,
                'slug' => (string) $service->slug,
                'title' => (string) $service->title,
                'short_description' => $service->short_description,
                'description' => $service->description,
                'billable' => (bool) $service->billable,
                'active' => (bool) $service->active,
            ],
            'plans' => $plans,
            'active_entitlement' => $activeItem
                ? [
                    'subscription_item_id' => (int) $activeItem->id,
                    'status' => (string) $activeItem->status,
                    'service_plan_id' => $activeItem->service_plan_id
                        ? (int) $activeItem->service_plan_id
                        : null,
                    'service_plan_name' =>
                        $activeItem->servicePlan?->name,
                    'billing_cycle' =>
                        $activeItem->billing_cycle,
                ]
                : null,
            'store' => [
                'starter_activation_deferred' => true,
                'plan_change_deferred' => true,
                'checkout_requires_activation_request' => false,
            ],
        ]);
    }

    public function checkout(
        Request $request,
        string $serviceKey,
        SubscriberResolver $subscriberResolver,
        CompanyContextResolver $companyResolver,
        TenantAccessService $tenantAccessService,
        StandaloneServiceCheckoutService $checkoutService
    ): RedirectResponse {
        [
            $user,
            $subscriber,
            $company,
        ] = $this->tenantAdminContext(
            $request,
            $subscriberResolver,
            $companyResolver,
            $tenantAccessService
        );

        $service = $this->storeService($serviceKey);

        $data = $request->validate([
            'service_plan_id' => [
                'required',
                'integer',
            ],
            'billing_cycle' => [
                'required',
                'string',
                'in:monthly,yearly',
            ],
        ]);

        $plan = ServicePlan::query()
            ->whereKey((int) $data['service_plan_id'])
            ->where('service_id', $service->id)
            ->where('active', true)
            ->first();

        if (! $plan) {
            throw ValidationException::withMessages([
                'service_plan_id' => [
                    'El plan seleccionado no está disponible para esta solución.',
                ],
            ]);
        }

        $billingCycle = strtolower(
            trim((string) $data['billing_cycle'])
        );

        if ($this->isFreePlan($plan)) {
            return back()->with(
                'error',
                'Starter gratis estará disponible junto al flujo seguro de cambio de plan.'
            );
        }

        if ($this->activeEntitlement($subscriber, $service)) {
            return back()->with(
                'error',
                (string) $service->title
                    .' ya está activo para esta empresa. '
                    .'Los cambios de plan se habilitarán en S10-F5.'
            );
        }

        /*
         * B1 compatibility bridge:
         *
         * La experiencia App Store NO exige que el cliente cree, gestione
         * ni vea una ActivationRequest. El ledger standalone existente
         * todavía usa ActivationRequestService como mutex/evidencia.
         *
         * Por eso generamos evidencia técnica interna, específica para
         * service + plan + billing cycle, justo al iniciar checkout.
         */
        $requestRow = $this->checkoutEvidence(
            $user,
            $company,
            $service,
            $plan,
            $billingCycle
        );

        try {
            $settlement = $checkoutService->checkout(
                $requestRow,
                $subscriber,
                $company,
                $service,
                $billingCycle,
                (int) $user->id,
                $plan
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'No se pudo preparar el checkout: '.$e->getMessage()
            );
        }

        return redirect()
            ->route(
                'subscriber.invoices.show',
                ['invoice' => (int) $settlement->invoice_id]
            )
            ->with(
                'success',
                'Factura preparada. El acceso se habilitará cuando se confirme el pago.'
            );
    }

    /**
     * @return array{0: User, 1: Subscriber, 2: Company}
     */
    private function tenantAdminContext(
        Request $request,
        SubscriberResolver $subscriberResolver,
        CompanyContextResolver $companyResolver,
        TenantAccessService $tenantAccessService
    ): array {
        $user = $request->user();

        abort_unless(
            $user instanceof User
            && ($user->role ?? null) === 'subscriber',
            403
        );

        $subscriberId = (int) (
            $subscriberResolver->resolve($user)
            ?? 0
        );

        abort_unless($subscriberId > 0, 403);

        $access = $tenantAccessService->resolve(
            $user,
            $subscriberId
        );

        abort_unless(
            ($access['mode'] ?? null) === 'subscriber.admin'
            && (bool) ($access['can_browse_store'] ?? false),
            403
        );

        $subscriber = Subscriber::query()
            ->findOrFail($subscriberId);

        $company = $companyResolver->resolve(
            $user,
            $subscriberId
        );

        abort_unless(
            $company instanceof Company
            && (int) $company->subscriber_id === $subscriberId,
            403
        );

        return [
            $user,
            $subscriber,
            $company,
        ];
    }

    private function storeService(string $serviceKey): Service
    {
        /*
         * B3 habilita únicamente soluciones ya incorporadas
         * al App Store moderno. Se amplía una por una.
         */
        abort_unless(
            in_array($serviceKey, ['social', 'crm', 'pos'], true),
            404
        );

        return Service::query()
            ->where('service_key', $serviceKey)
            ->where('active', true)
            ->firstOrFail();
    }

    private function activeEntitlement(
        Subscriber $subscriber,
        Service $service
    ): ?SubscriptionItem {
        return SubscriptionItem::query()
            ->with('servicePlan')
            ->where('service_id', $service->id)
            ->whereIn('status', ['active', 'trialing'])
            ->whereHas(
                'subscription',
                function ($query) use ($subscriber): void {
                    $query
                        ->where('subscriber_id', $subscriber->id)
                        ->whereIn('status', ['active', 'trialing']);
                }
            )
            ->latest('id')
            ->first();
    }

    private function planPayload(
        StandaloneServiceCheckoutService $checkoutService,
        Subscriber $subscriber,
        Company $company,
        Service $service,
        ServicePlan $plan
    ): array {
        $snapshot = is_array($plan->source_snapshot)
            ? $plan->source_snapshot
            : [];

        $isFree = $this->isFreePlan($plan);

        return [
            'id' => (int) $plan->id,
            'code' => (string) $plan->code,
            'name' => (string) $plan->name,
            'description' => $plan->description,
            'currency' => (string) $plan->currency,
            'billing_model' => (string) $plan->billing_model,
            'features' => $this->featurePayload($plan),
            'limits' => is_array($plan->limits)
                ? $plan->limits
                : [],
            'is_featured' => (bool) (
                $snapshot['isFeatured']
                ?? false
            ),
            'is_free' => $isFree,
            'activation_available' => ! $isFree,
            'activation_reason' => $isFree
                ? $plan->name.' gratis · Próximamente'
                : null,
            'billing_options' => [
                'monthly' => $this->billingOption(
                    $checkoutService,
                    $subscriber,
                    $company,
                    $service,
                    $plan,
                    'monthly',
                    $isFree
                ),
                'yearly' => $this->billingOption(
                    $checkoutService,
                    $subscriber,
                    $company,
                    $service,
                    $plan,
                    'yearly',
                    $isFree
                ),
            ],
        ];
    }

    private function featurePayload(ServicePlan $plan): array
    {
        $features = is_array($plan->features)
            ? $plan->features
            : [];

        if (array_is_list($features)) {
            $normalized = [];

            foreach ($features as $feature) {
                $label = trim((string) $feature);

                if ($label !== '') {
                    $normalized[$label] = true;
                }
            }

            return $normalized;
        }

        return $features;
    }

    private function billingOption(
        StandaloneServiceCheckoutService $checkoutService,
        Subscriber $subscriber,
        Company $company,
        Service $service,
        ServicePlan $plan,
        string $cycle,
        bool $isFree
    ): array {
        if ($isFree) {
            return [
                'cycle' => $cycle,
                'label' => $cycle === 'monthly'
                    ? 'Mensual'
                    : 'Anual',
                'available' => false,
                'amount_due' => 0,
                'currency' => (string) $plan->currency,
                'service_plan_id' => (int) $plan->id,
                'reason' =>
                    'La activación gratuita se habilitará con S10-F5.',
            ];
        }

        try {
            $preview = $checkoutService->previewQuote(
                $subscriber,
                $company,
                $service,
                $cycle,
                $plan
            );

            return [
                'cycle' => $cycle,
                'label' => $cycle === 'monthly'
                    ? 'Mensual'
                    : 'Anual',
                'available' => true,
                'amount_due' => round(
                    (float) ($preview['amount_due'] ?? 0),
                    2
                ),
                'currency' => (string) (
                    $preview['currency']
                    ?? $plan->currency
                ),
                'billing_model' =>
                    $preview['quote']['billing_model']
                    ?? $plan->billing_model,
                'quantity' =>
                    $preview['quote']['quantity']
                    ?? 1,
                'unit_price' =>
                    $preview['quote']['unit_price']
                    ?? null,
                'service_plan_id' => (int) $plan->id,
                'reason' => null,
            ];
        } catch (\Throwable $e) {
            $reason = $e instanceof ValidationException
                ? $this->firstValidationMessage($e)
                : $e->getMessage();

            return [
                'cycle' => $cycle,
                'label' => $cycle === 'monthly'
                    ? 'Mensual'
                    : 'Anual',
                'available' => false,
                'amount_due' => null,
                'currency' => (string) $plan->currency,
                'service_plan_id' => (int) $plan->id,
                'reason' => $reason,
            ];
        }
    }

    private function checkoutEvidence(
        User $user,
        Company $company,
        Service $service,
        ServicePlan $plan,
        string $billingCycle
    ): ActivationRequestService {
        $topic = sprintf(
            'App Store: %s/%s/%s',
            (string) $service->service_key,
            (string) $plan->code,
            $billingCycle
        );

        $activation = ActivationRequest::query()
            ->where('user_id', $user->id)
            ->where('system', 'LAUDAAPI App Store')
            ->where('topic', $topic)
            ->latest('id')
            ->first();

        if ($activation) {
            $existingRow = ActivationRequestService::query()
                ->where('activation_request_id', $activation->id)
                ->where('service_id', $service->id)
                ->first();

            if ($existingRow) {
                $existingSettlement =
                    StandaloneServiceSettlement::query()
                        ->where(
                            'activation_request_service_id',
                            $existingRow->id
                        )
                        ->latest('id')
                        ->first();

                /*
                 * Sin settlement: reintento seguro de un checkout que falló
                 * antes de crear factura.
                 *
                 * pending_payment/activated: reutiliza el mutex existente
                 * para conservar la idempotencia del ledger.
                 *
                 * revoked/failed/otros terminales: crea evidencia nueva,
                 * permitiendo una contratación futura legítima.
                 */
                if (
                    ! $existingSettlement
                    || in_array(
                        (string) $existingSettlement->status,
                        ['pending_payment', 'activated'],
                        true
                    )
                ) {
                    return $existingRow;
                }
            }
        }

        $activation = ActivationRequest::query()->create([
            'user_id' => $user->id,
            'name' => $user->name,
            'company' => (
                $company->name
                ?? $company->business_name
                ?? 'Mi empresa'
            ),
            'role' => 'Tenant Admin',
            'email' => $user->email,
            'topic' => $topic,
            'system' => 'LAUDAAPI App Store',
            'volume' => 'self-service',
            'terms' => true,
            'status' => ActivationRequest::STATUS_ACCEPTED,
            'metadata' => [
                'source' => 'app_store_compatibility_bridge',
                'customer_facing_activation_request' => false,
                'service_id' => (int) $service->id,
                'service_key' => (string) $service->service_key,
                'service_plan_id' => (int) $plan->id,
                'service_plan_code' => (string) $plan->code,
                'billing_cycle' => $billingCycle,
            ],
        ]);

        ActivationRequestService::query()->create([
            'activation_request_id' => $activation->id,
            'service_id' => $service->id,
            'status' => 'pending',
            'meta' => json_encode(
                [
                    'source' => 'app_store_compatibility_bridge',
                    'customer_facing_activation_request' => false,
                    'service_plan_id' => (int) $plan->id,
                    'service_plan_code' => (string) $plan->code,
                    'billing_cycle' => $billingCycle,
                    'entitlement_granted' => false,
                ],
                JSON_UNESCAPED_SLASHES
            ),
        ]);

        return ActivationRequestService::query()
            ->where('activation_request_id', $activation->id)
            ->where('service_id', $service->id)
            ->firstOrFail();
    }

    private function isFreePlan(ServicePlan $plan): bool
    {
        return round(
            (float) ($plan->monthly_price ?? 0),
            2
        ) <= 0
            && round(
                (float) ($plan->yearly_price ?? 0),
                2
            ) <= 0;
    }

    private function firstValidationMessage(
        ValidationException $exception
    ): string {
        foreach ($exception->errors() as $messages) {
            if (is_array($messages) && isset($messages[0])) {
                return (string) $messages[0];
            }
        }

        return $exception->getMessage();
    }
}
