<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\ActivationRequest;
use App\Models\Company;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriberServiceActivationController extends Controller
{
    public function activate(Request $request)
    {
        $user = $request->user();
        if (!$user) abort(403);

        $data = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'mode' => ['required', 'in:trial,billed'], // explícito
        ]);

        $serviceId = (int) $data['service_id'];
        $mode = (string) $data['mode'];

        $company = $this->resolveCompany($user);
        if (!$company || !$company->subscriber_id) {
            AuditService::log('service_activation_denied', null, [
                'reason' => 'no_company_or_subscriber',
                'user_id' => $user->id,
                'service_id' => $serviceId,
            ], ['user_id' => $user->id]);

            return back()->with('error', 'No tienes compañía/suscriptor asignado.');
        }

        $subscription = Subscription::query()
            ->where('subscriber_id', $company->subscriber_id)
            ->latest('id')
            ->first();

        if (!$subscription) {
            AuditService::log('service_activation_denied', null, [
                'reason' => 'no_subscription',
                'user_id' => $user->id,
                'subscriber_id' => $company->subscriber_id,
                'service_id' => $serviceId,
            ], ['user_id' => $user->id]);

            return back()->with('error', 'No tienes suscripción para activar servicios.');
        }

        // Activación válida (accepted/trialing/converted)
        $activation = ActivationRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ActivationRequest::ACCESS_ALLOWED_STATUSES ?? [
                ActivationRequest::STATUS_ACCEPTED,
                ActivationRequest::STATUS_TRIALING,
                ActivationRequest::STATUS_CONVERTED,
            ])
            ->latest('id')
            ->first();

        if (!$activation) {
            AuditService::log('service_activation_denied', null, [
                'reason' => 'no_activation_request',
                'user_id' => $user->id,
                'service_id' => $serviceId,
            ], ['user_id' => $user->id]);

            return back()->with('error', 'No tienes una solicitud de activación válida.');
        }

        $service = Service::query()->findOrFail($serviceId);

        if (!(bool) $service->active) {
            AuditService::log('service_activation_denied', $service, [
                'reason' => 'service_inactive',
                'user_id' => $user->id,
                'activation_request_id' => $activation->id,
                'subscription_id' => $subscription->id,
            ], ['user_id' => $user->id]);

            return back()->with('error', 'Este servicio no está disponible.');
        }

        // Debe existir solicitud (activation_request_service)
        $reqRow = DB::table('activation_request_service')
            ->where('activation_request_id', $activation->id)
            ->where('service_id', $serviceId)
            ->first();

        if (!$reqRow) {
            AuditService::log('service_activation_denied', $service, [
                'reason' => 'no_service_request_row',
                'user_id' => $user->id,
                'activation_request_id' => $activation->id,
                'subscription_id' => $subscription->id,
            ], ['user_id' => $user->id]);

            return back()->with('error', 'Debes solicitar el servicio antes de activarlo.');
        }

        $reqStatus = strtolower((string) ($reqRow->status ?? ''));
        if (!in_array($reqStatus, ['pending', 'pending_payment'], true)) {
            return back()->with('error', "La solicitud no está en estado activable (status: {$reqStatus}).");
        }

        // Si ya existe item activo/trialing
        $alreadyActive = SubscriptionItem::query()
            ->where('subscription_id', $subscription->id)
            ->where('service_id', $serviceId)
            ->whereIn('status', ['trialing', 'active'])
            ->exists();

        if ($alreadyActive) {
            // auto-fix solicitud si quedó colgada
            DB::table('activation_request_service')
                ->where('id', $reqRow->id)
                ->update(['status' => 'active', 'updated_at' => now()]);

            AuditService::log('service_activation_denied_already_active', $service, [
                'reason' => 'already_active',
                'user_id' => $user->id,
                'activation_request_id' => $activation->id,
                'subscription_id' => $subscription->id,
            ], ['user_id' => $user->id]);

            return back()->with('error', 'Este servicio ya está activo.');
        }

        // ✅ Regla: Trial solo si el subscriber está realmente en trial vigente
        if ($mode === 'trial') {
            $trialOk = ($subscription->status === 'trialing')
                && ($subscription->trial_ends_at === null || $subscription->trial_ends_at >= now());

            if (!$trialOk) {
                return back()->with('error', 'Tu trial no está vigente. Debes activar con pago.');
            }
        }

        try {
            DB::transaction(function () use ($mode, $service, $activation, $subscription, $company, $user, $reqRow) {

                if ($mode === 'trial') {
                    // Trial: $0 y pasa a activos
                    $item = SubscriptionItem::create($this->buildTrialItem($service, $subscription));

                    DB::table('activation_request_service')
                        ->where('id', $reqRow->id)
                        ->update([
                            'status' => 'active',
                            'meta' => json_encode([
                                'activation_mode' => 'trial',
                                'subscription_id' => $subscription->id,
                                'subscription_item_id' => $item->id,
                                'activated_at' => now()->toISOString(),
                            ]),
                            'updated_at' => now(),
                        ]);

                    AuditService::log('service_activated_trial', $service, [
                        'user_id' => $user->id,
                        'activation_request_id' => $activation->id,
                        'subscriber_id' => $company->subscriber_id,
                        'company_id' => $company->id,
                        'subscription_id' => $subscription->id,
                        'subscription_item_id' => $item->id,
                        'service_id' => $service->id,
                        'service_slug' => $service->slug,
                        'service_title' => $service->title,
                        'billing_model' => $service->billing_model,
                        'billable' => (bool) $service->billable,
                    ], ['user_id' => $user->id]);

                    return;
                }

                // billed: por ahora solo marcamos pending_payment (checkout luego)
                DB::table('activation_request_service')
                    ->where('id', $reqRow->id)
                    ->update([
                        'status' => 'pending_payment',
                        'meta' => json_encode([
                            'activation_mode' => 'billed',
                            'payment_required' => true,
                            'requested_at' => now()->toISOString(),
                            'billing_cycle' => $subscription->billing_cycle,
                            'price_snapshot' => $this->buildPriceSnapshot($service, $subscription),
                        ]),
                        'updated_at' => now(),
                    ]);

                AuditService::log('service_activation_pending_payment', $service, [
                    'user_id' => $user->id,
                    'activation_request_id' => $activation->id,
                    'subscriber_id' => $company->subscriber_id,
                    'company_id' => $company->id,
                    'subscription_id' => $subscription->id,
                    'service_id' => $service->id,
                    'service_slug' => $service->slug,
                    'service_title' => $service->title,
                    'billing_model' => $service->billing_model,
                    'billable' => (bool) $service->billable,
                ], ['user_id' => $user->id]);
            });
        } catch (\Throwable $e) {
            AuditService::log('service_activation_failed', $service ?? null, [
                'user_id' => $user->id,
                'activation_request_id' => $activation?->id,
                'subscription_id' => $subscription?->id,
                'service_id' => $serviceId,
                'mode' => $mode,
                'error' => $e->getMessage(),
            ], ['user_id' => $user->id]);

            report($e);
            return back()->with('error', 'Falló la activación: ' . $e->getMessage());
        }

        return back()->with(
            'success',
            $mode === 'trial'
                ? 'Servicio activado (trial). Ya aparece en Servicios activos.'
                : 'Solicitud marcada como pendiente de pago. Completa el pago para activarlo.'
        );
    }

    private function buildTrialItem(Service $service, Subscription $subscription): array
    {
        return [
            'subscription_id' => $subscription->id,
            'service_id' => $service->id,
            'status' => 'trialing',

            'billing_model' => $service->billing_model ?? 'flat',
            'quantity' => 1,

            'unit_price' => 0,
            'amount' => 0,
            'currency' => $service->currency ?: ($subscription->currency ?? 'DOP'),

            'block_size' => $service->block_size,
            'unit_name' => $service->unit_name,
            'included_units' => $service->included_units,
            'overage_unit_price' => $service->overage_unit_price,

            'meta' => [
                'activation_mode' => 'trial',
                'activated_at' => now()->toISOString(),
            ],
        ];
    }

    /**
     * Snapshot para cobrar después (billed).
     * - flat/seat_block: usa monthly_price/yearly_price según billing_cycle del subscription
     * - usage: deja unit_price=0, pero guarda overage_unit_price y unidades
     */
    private function buildPriceSnapshot(Service $service, Subscription $subscription): array
    {
        $cycle = strtolower((string) ($subscription->billing_cycle ?? 'monthly'));
        $model = strtolower((string) ($service->billing_model ?? 'flat'));

        $price = $cycle === 'yearly'
            ? (float) ($service->yearly_price ?? 0)
            : (float) ($service->monthly_price ?? 0);

        if ($model === 'usage') {
            return [
                'billing_model' => 'usage',
                'currency' => $service->currency ?: ($subscription->currency ?? 'DOP'),
                'included_units' => (int) ($service->included_units ?? 0),
                'unit_name' => $service->unit_name,
                'overage_unit_price' => (float) ($service->overage_unit_price ?? 0),
                'cycle' => $cycle,
                'amount_due_now' => 0,
            ];
        }

        // flat / seat_block: price representa el “precio base” del ciclo
        return [
            'billing_model' => $model,
            'currency' => $service->currency ?: ($subscription->currency ?? 'DOP'),
            'cycle' => $cycle,
            'unit_price' => $price,
            'quantity' => 1,
            'amount_due_now' => $price,
            'block_size' => $service->block_size,
        ];
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
