<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\ActivationRequest;
use App\Models\ActivationRequestService;
use App\Models\Company;
use App\Models\Service;
use App\Models\ServicePlan;
use App\Models\Subscriber;
use App\Models\SubscriptionItem;
use App\Services\AuditService;
use App\Services\Billing\StandaloneServiceCheckoutService;
use App\Services\Entitlements\ServiceEntitlementPolicy;
use Illuminate\Http\Request;

class SubscriberServiceActivationController extends Controller
{
    public function activate(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $data = $request->validate([
            'service_id' => [
                'required',
                'integer',
                'exists:services,id',
            ],
            'service_plan_id' => [
                'nullable',
                'integer',
                'exists:service_plans,id',
            ],
            'mode' => [
                'required',
                'in:trial,billed',
            ],
            'billing_cycle' => [
                'required_if:mode,billed',
                'in:monthly,yearly',
            ],
        ]);

        $serviceId = (int) $data['service_id'];
        $servicePlanId =
            isset($data['service_plan_id'])
                ? (int) $data['service_plan_id']
                : null;
        $mode = (string) $data['mode'];
        $billingCycle = (string) ($data['billing_cycle'] ?? '');

        if ($mode === 'trial') {
            AuditService::log(
                'legacy_service_trial_activation_blocked_t360',
                null,
                [
                    'user_id' => (int) $user->id,
                    'service_id' => $serviceId,
                    'reason' => 'direct_trial_activation_disabled',
                    'hardening_step' => 'S10-C',
                ],
                ['user_id' => (int) $user->id]
            );

            return back()->with(
                'error',
                'La activación trial directa no está disponible.'
            );
        }

        $company = $this->resolveCompany($user);

        if (! $company || ! $company->subscriber_id) {
            return back()->with(
                'error',
                'No tienes compañía/suscriptor asignado.'
            );
        }

        $subscriber = Subscriber::query()
            ->find($company->subscriber_id);

        if (! $subscriber || ! $subscriber->active) {
            return back()->with(
                'error',
                'El Subscriber no está activo.'
            );
        }

        $activation = ActivationRequest::query()
            ->where('user_id', $user->id)
            ->whereIn(
                'status',
                ActivationRequest::ACCESS_ALLOWED_STATUSES
            )
            ->latest('id')
            ->first();

        if (! $activation) {
            return back()->with(
                'error',
                'No tienes una solicitud de activación válida.'
            );
        }

        $service = Service::query()->findOrFail($serviceId);

        $servicePlan = null;

        if ($servicePlanId !== null) {
            $servicePlan = ServicePlan::query()
                ->whereKey($servicePlanId)
                ->where('service_id', $service->id)
                ->where('active', true)
                ->first();

            if (! $servicePlan) {
                return back()->with(
                    'error',
                    'El plan seleccionado no está disponible '
                    .'para este servicio.'
                );
            }
        }

        /*
         * S10-F4 plan selection guard.
         * Un Service con ServicePlan activo debe identificar
         * qué plan se está contratando.
         *
         * Servicios legacy sin catálogo mantienen el flujo anterior.
         */
        $hasCommercialPlans =
            ServicePlan::query()
                ->where(
                    'service_id',
                    $service->id
                )
                ->where('active', true)
                ->exists();

        if (
            $mode === 'billed'
            && $hasCommercialPlans
            && ! $servicePlan
        ) {
            return back()->with(
                'error',
                'Selecciona un plan comercial antes de continuar al pago.'
            );
        }

        if (! $service->active) {
            return back()->with(
                'error',
                'Este servicio no está disponible.'
            );
        }

        /*
         * No existe pre-gate Subscription.
         * Solo se evita duplicar un entitlement real ya existente.
         */
        $alreadyActive = SubscriptionItem::query()
            ->where('service_id', $serviceId)
            ->whereIn(
                'status',
                ServiceEntitlementPolicy::ITEM_STATUSES
            )
            ->whereHas(
                'subscription',
                function ($query) use ($subscriber) {
                    $query
                        ->where('subscriber_id', $subscriber->id)
                        ->whereIn(
                            'status',
                            ServiceEntitlementPolicy::SUBSCRIPTION_STATUSES
                        );
                }
            )
            ->exists();

        if ($alreadyActive) {
            return back()->with(
                'error',
                'Este servicio ya está activo.'
            );
        }

        $requestRow = ActivationRequestService::query()
            ->where('activation_request_id', $activation->id)
            ->where('service_id', $serviceId)
            ->first();

        if (! $requestRow) {
            return back()->with(
                'error',
                'Debes solicitar el servicio antes de continuar.'
            );
        }

        $status = strtolower((string) $requestRow->status);

        if (! in_array($status, ['pending', 'pending_payment'], true)) {
            return back()->with(
                'error',
                "La solicitud no está en estado procesable "
                ."(status: {$status})."
            );
        }

        try {
            $settlement = app(
                StandaloneServiceCheckoutService::class
            )->checkout(
                $requestRow,
                $subscriber,
                $company,
                $service,
                $billingCycle,
                (int) $user->id,
                $servicePlan
            );
        } catch (\Throwable $e) {
            AuditService::log(
                'standalone_service_checkout_failed',
                $service,
                [
                    'user_id' => (int) $user->id,
                    'activation_request_id' => (int) $activation->id,
                    'activation_request_service_id' => (int) $requestRow->id,
                    'subscriber_id' => (int) $subscriber->id,
                    'company_id' => (int) $company->id,
                    'service_id' => (int) $service->id,
                    'billing_cycle' => $billingCycle,
                    'error' => $e->getMessage(),
                ],
                ['user_id' => (int) $user->id]
            );

            report($e);

            return back()->with(
                'error',
                'No se pudo preparar el checkout: '
                .$e->getMessage()
            );
        }

        return back()
            ->with(
                'success',
                'Factura preparada. El servicio permanecerá '
                .'pendiente de pago hasta confirmar el cobro.'
            )
            ->with(
                'standalone_settlement_id',
                (int) $settlement->id
            );
    }

    private function resolveCompany($user): ?Company
    {
        $company = null;

        if (! empty($user->company_id)) {
            $company = Company::query()->find($user->company_id);
        }

        if (! $company) {
            $company = Company::query()
                ->where('owner_user_id', $user->id)
                ->first();
        }

        if (! $company && ! empty($user->subscriber_id)) {
            $company = Company::query()
                ->where('subscriber_id', $user->subscriber_id)
                ->first();
        }

        return $company;
    }
}
