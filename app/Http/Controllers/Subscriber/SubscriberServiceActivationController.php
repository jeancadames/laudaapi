<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\ActivationRequest;
use App\Models\Company;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Services\AuditService;
use App\Services\Billing\ServicePricingEngine;
use App\Services\Entitlements\ServiceEntitlementPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            /*
             * Se conserva "trial" temporalmente en validation para que
             * clientes/UI legacy reciban una respuesta controlada en vez
             * de un 422. El branch trial queda bloqueado inmediatamente.
             */
            'mode' => [
                'required',
                'in:trial,billed',
            ],
        ]);

        $serviceId = (int) $data['service_id'];
        $mode = (string) $data['mode'];

        /*
         * PASO 9C-B
         *
         * El trial directo ya no es una vía de activación real.
         * No resolver Company, Subscription, Service request ni ejecutar
         * ningún mutation/provisioning antes de bloquearlo.
         */
        if ($mode === 'trial') {
            AuditService::log(
                'legacy_service_trial_activation_blocked_t360',
                null,
                [
                    'user_id' =>
                        (int) $user->id,
                    'service_id' =>
                        $serviceId,
                    'reason' =>
                        'service_activation_requires_lauda360_golive',
                    'hardening_step' =>
                        '9C-B',
                ],
                [
                    'user_id' =>
                        (int) $user->id,
                ]
            );

            return back()->with(
                'error',
                'La activación trial directa ya no está disponible. '
                .'La activación real del servicio ocurre después '
                .'del Go-Live correspondiente en LAUDA 360.'
            );
        }

        $company = $this->resolveCompany(
            $user
        );

        if (
            ! $company
            || ! $company->subscriber_id
        ) {
            AuditService::log(
                'service_activation_denied',
                null,
                [
                    'reason' =>
                        'no_company_or_subscriber',
                    'user_id' =>
                        (int) $user->id,
                    'service_id' =>
                        $serviceId,
                ],
                [
                    'user_id' =>
                        (int) $user->id,
                ]
            );

            return back()->with(
                'error',
                'No tienes compañía/suscriptor asignado.'
            );
        }

        /*
         * Solo una Subscription legacy ya existente y elegible puede
         * registrar intención billed. Esta ruta NO crea Subscription.
         */
        $subscription = Subscription::query()
            ->where(
                'subscriber_id',
                $company->subscriber_id
            )
            ->whereIn(
                'status',
                ServiceEntitlementPolicy::SUBSCRIPTION_STATUSES
            )
            ->orderByRaw(
                "FIELD(status,'active','trialing')"
            )
            ->latest('id')
            ->first();

        if (! $subscription) {
            AuditService::log(
                'service_activation_denied',
                null,
                [
                    'reason' =>
                        'no_eligible_existing_subscription',
                    'user_id' =>
                        (int) $user->id,
                    'subscriber_id' =>
                        (int) $company->subscriber_id,
                    'service_id' =>
                        $serviceId,
                    'hardening_step' =>
                        '9C-B',
                ],
                [
                    'user_id' =>
                        (int) $user->id,
                ]
            );

            return back()->with(
                'error',
                'No existe una suscripción elegible. '
                .'Las nuevas suscripciones se activan '
                .'desde LAUDA 360 después del Go-Live.'
            );
        }

        $activation = ActivationRequest::query()
            ->where(
                'user_id',
                $user->id
            )
            ->whereIn(
                'status',
                ActivationRequest::ACCESS_ALLOWED_STATUSES
            )
            ->latest('id')
            ->first();

        if (! $activation) {
            AuditService::log(
                'service_activation_denied',
                null,
                [
                    'reason' =>
                        'no_activation_request',
                    'user_id' =>
                        (int) $user->id,
                    'service_id' =>
                        $serviceId,
                ],
                [
                    'user_id' =>
                        (int) $user->id,
                ]
            );

            return back()->with(
                'error',
                'No tienes una solicitud de activación válida.'
            );
        }

        $service = Service::query()
            ->findOrFail(
                $serviceId
            );

        if (! (bool) $service->active) {
            AuditService::log(
                'service_activation_denied',
                $service,
                [
                    'reason' =>
                        'service_inactive',
                    'user_id' =>
                        (int) $user->id,
                    'activation_request_id' =>
                        (int) $activation->id,
                    'subscription_id' =>
                        (int) $subscription->id,
                ],
                [
                    'user_id' =>
                        (int) $user->id,
                ]
            );

            return back()->with(
                'error',
                'Este servicio no está disponible.'
            );
        }

        $requestRow = DB::table(
            'activation_request_service'
        )
            ->where(
                'activation_request_id',
                $activation->id
            )
            ->where(
                'service_id',
                $serviceId
            )
            ->first();

        if (! $requestRow) {
            AuditService::log(
                'service_activation_denied',
                $service,
                [
                    'reason' =>
                        'no_service_request_row',
                    'user_id' =>
                        (int) $user->id,
                    'activation_request_id' =>
                        (int) $activation->id,
                    'subscription_id' =>
                        (int) $subscription->id,
                ],
                [
                    'user_id' =>
                        (int) $user->id,
                ]
            );

            return back()->with(
                'error',
                'Debes solicitar el servicio antes de continuar.'
            );
        }

        $requestStatus = strtolower(
            (string) ($requestRow->status ?? '')
        );

        if (
            ! in_array(
                $requestStatus,
                [
                    'pending',
                    'pending_payment',
                ],
                true
            )
        ) {
            return back()->with(
                'error',
                "La solicitud no está en estado procesable "
                ."(status: {$requestStatus})."
            );
        }

        /*
         * Si el servicio ya está realmente activo, preservar compatibilidad
         * y cerrar la solicitud colgada sin crear/reactivar ningún item.
         */
        $alreadyActive = SubscriptionItem::query()
            ->where(
                'subscription_id',
                $subscription->id
            )
            ->where(
                'service_id',
                $serviceId
            )
            ->whereIn(
                'status',
                ServiceEntitlementPolicy::ITEM_STATUSES
            )
            ->exists();

        if ($alreadyActive) {
            DB::table(
                'activation_request_service'
            )
                ->where(
                    'id',
                    $requestRow->id
                )
                ->update([
                    'status' =>
                        'active',
                    'updated_at' =>
                        now(),
                ]);

            AuditService::log(
                'service_activation_denied_already_active',
                $service,
                [
                    'reason' =>
                        'already_active',
                    'user_id' =>
                        (int) $user->id,
                    'activation_request_id' =>
                        (int) $activation->id,
                    'subscription_id' =>
                        (int) $subscription->id,
                    'hardening_step' =>
                        '9C-B',
                ],
                [
                    'user_id' =>
                        (int) $user->id,
                ]
            );

            return back()->with(
                'error',
                'Este servicio ya está activo.'
            );
        }

        try {
            /*
             * Aun siendo request-only, conservar pricing/currency guards
             * para que el snapshot comercial sea válido.
             */
            $this->resolveCommercialCurrency(
                $service,
                $subscription
            );

            DB::transaction(function () use (
                $service,
                $activation,
                $subscription,
                $company,
                $user,
                $requestRow
            ) {
                DB::table(
                    'activation_request_service'
                )
                    ->where(
                        'id',
                        $requestRow->id
                    )
                    ->update([
                        'status' =>
                            'pending_payment',
                        'meta' =>
                            json_encode([
                                'activation_mode' =>
                                    'billed',
                                'payment_required' =>
                                    true,
                                'requested_at' =>
                                    now()->toISOString(),
                                'billing_cycle' =>
                                    $subscription->billing_cycle,
                                'price_snapshot' =>
                                    $this->buildPriceSnapshot(
                                        $service,
                                        $subscription
                                    ),
                                'hardening_step' =>
                                    '9C-B',
                                'entitlement_granted' =>
                                    false,
                            ]),
                        'updated_at' =>
                            now(),
                    ]);

                AuditService::log(
                    'service_activation_pending_payment',
                    $service,
                    [
                        'user_id' =>
                            (int) $user->id,
                        'activation_request_id' =>
                            (int) $activation->id,
                        'subscriber_id' =>
                            (int) $company->subscriber_id,
                        'company_id' =>
                            (int) $company->id,
                        'subscription_id' =>
                            (int) $subscription->id,
                        'service_id' =>
                            (int) $service->id,
                        'service_slug' =>
                            (string) $service->slug,
                        'service_title' =>
                            (string) $service->title,
                        'billing_model' =>
                            (string) $service->billing_model,
                        'billable' =>
                            (bool) $service->billable,
                        'hardening_step' =>
                            '9C-B',
                        'entitlement_granted' =>
                            false,
                    ],
                    [
                        'user_id' =>
                            (int) $user->id,
                    ]
                );
            });
        } catch (\Throwable $e) {
            AuditService::log(
                'service_activation_failed',
                $service ?? null,
                [
                    'user_id' =>
                        (int) $user->id,
                    'activation_request_id' =>
                        $activation?->id,
                    'subscription_id' =>
                        $subscription?->id,
                    'service_id' =>
                        $serviceId,
                    'mode' =>
                        $mode,
                    'error' =>
                        $e->getMessage(),
                    'hardening_step' =>
                        '9C-B',
                ],
                [
                    'user_id' =>
                        (int) $user->id,
                ]
            );

            report(
                $e
            );

            return back()->with(
                'error',
                'Falló la solicitud comercial: '
                .$e->getMessage()
            );
        }

        return back()->with(
            'success',
            'Solicitud marcada como pendiente de pago. '
            .'Esto no activa ni habilita el servicio.'
        );
    }

    private function buildPriceSnapshot(
        Service $service,
        Subscription $subscription
    ): array {
        $quote = app(
            ServicePricingEngine::class
        )->quote(
            $service,
            $subscription
        );

        $quote['amount_due_now'] =
            $quote['billing_model']
                === ServicePricingEngine::MODEL_USAGE
                    ? 0
                    : $quote['amount'];

        return $quote;
    }

    private function resolveCommercialCurrency(
        Service $service,
        Subscription $subscription
    ): string {
        $serviceCurrency = strtoupper(
            trim(
                (string) ($service->currency ?? '')
            )
        );

        $subscriptionCurrency = strtoupper(
            trim(
                (string) ($subscription->currency ?? 'DOP')
            )
        );

        $serviceCurrency = $serviceCurrency !== ''
            ? $serviceCurrency
            : $subscriptionCurrency;

        $subscriptionCurrency =
            $subscriptionCurrency !== ''
                ? $subscriptionCurrency
                : 'DOP';

        foreach ([
            'service' =>
                $serviceCurrency,
            'subscription' =>
                $subscriptionCurrency,
        ] as $field => $currency) {
            if (
                ! preg_match(
                    '/^[A-Z]{3}$/',
                    $currency
                )
            ) {
                throw ValidationException::withMessages([
                    'currency' =>
                        "La moneda {$field} debe usar "
                        ."un código ISO de tres letras.",
                ]);
            }
        }

        if (
            $serviceCurrency
            !== $subscriptionCurrency
        ) {
            throw ValidationException::withMessages([
                'currency' =>
                    'La moneda del Service debe coincidir '
                    .'con la moneda de la Subscription. '
                    .'No se permite conversión FX implícita.',
            ]);
        }

        return $serviceCurrency;
    }

    private function resolveCompany(
        $user
    ): ?Company {
        $company = null;

        if (! empty($user->company_id)) {
            $company = Company::query()
                ->find(
                    $user->company_id
                );
        }

        if (! $company) {
            $company = Company::query()
                ->where(
                    'owner_user_id',
                    $user->id
                )
                ->first();
        }

        if (
            ! $company
            && ! empty($user->subscriber_id)
        ) {
            $company = Company::query()
                ->where(
                    'subscriber_id',
                    $user->subscriber_id
                )
                ->first();
        }

        return $company;
    }
}
