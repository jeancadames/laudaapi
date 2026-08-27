<?php

namespace App\Services\Billing;

use App\Models\ActivationRequestService;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use App\Models\ServicePlan;
use App\Models\StandaloneServiceSettlement;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StandaloneServiceCheckoutService
{
    /**
     * Preview no persistente para UI y checkout.
     *
     * ServicePricingEngine sigue siendo la única fuente de monto.
     * Una Subscription activa existente fija ciclo y moneda.
     */
    public function previewQuote(
        Subscriber $subscriber,
        Company $company,
        Service $service,
        string $billingCycle,
        ?ServicePlan $servicePlan = null
    ): array {
        $billingCycle = strtolower(trim($billingCycle));

        $this->assertIdentity($subscriber, $company);
        $this->assertService($service);

        if (! in_array($billingCycle, ['monthly', 'yearly'], true)) {
            throw ValidationException::withMessages([
                'billing_cycle' => [
                    'El ciclo debe ser monthly o yearly.',
                ],
            ]);
        }

        $effectiveAt = now();

        $activeSubscription = Subscription::query()
            ->where('subscriber_id', $subscriber->id)
            ->where('status', 'active')
            ->where(function ($query) use ($effectiveAt) {
                $query
                    ->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $effectiveAt);
            })
            ->latest('id')
            ->first();

        if ($servicePlan) {
            $this->assertPlan($service, $servicePlan);

            $currency = $this->resolvePlanCurrency(
                $subscriber,
                $company,
                $servicePlan
            );

            if (
                $activeSubscription
                && strtoupper((string) $activeSubscription->currency)
                    !== $currency
            ) {
                throw ValidationException::withMessages([
                    'currency' => [
                        'La Subscription activa existente usa otra moneda.',
                    ],
                ]);
            }

            $pricingProbe = new Subscription([
                'subscriber_id' => $subscriber->id,
                'billing_cycle' => $billingCycle,
                'currency' => $currency,
            ]);

            $quote = app(ServicePricingEngine::class)->quotePlan(
                $service,
                $servicePlan,
                $pricingProbe,
                $billingCycle
            );

            $amountDue = round((float) ($quote['amount'] ?? 0), 2);

            if ($amountDue <= 0) {
                throw ValidationException::withMessages([
                    'service_plan' => [
                        'El plan requiere un monto inicial '
                        .'mayor que cero para checkout billed.',
                    ],
                ]);
            }

            return [
                'billing_cycle' => $billingCycle,
                'currency' => $currency,
                'amount_due' => $amountDue,
                'subscription_locked' => false,
                'subscription_cycle_locked' => false,
                'active_subscription_id' =>
                    $activeSubscription?->id,
                'service_plan_id' => (int) $servicePlan->id,
                'quote' => $quote,
            ];
        }

        /*
         * Legacy: sin plan explícito conserva S10-C.
         */
        $currency = $this->resolveCurrency(
            $subscriber,
            $company,
            $service
        );

        if ($activeSubscription) {
            $activeCycle = strtolower(
                trim((string) $activeSubscription->billing_cycle)
            );

            if ($activeCycle !== $billingCycle) {
                throw ValidationException::withMessages([
                    'billing_cycle' => [
                        'La Subscription activa existente '
                        .'usa otro ciclo de facturación.',
                    ],
                ]);
            }

            if (
                strtoupper((string) $activeSubscription->currency)
                !== $currency
            ) {
                throw ValidationException::withMessages([
                    'currency' => [
                        'La Subscription activa existente usa otra moneda.',
                    ],
                ]);
            }

            $pricingProbe = $activeSubscription;
        } else {
            $pricingProbe = new Subscription([
                'subscriber_id' => $subscriber->id,
                'billing_cycle' => $billingCycle,
                'currency' => $currency,
            ]);
        }

        $quote = app(ServicePricingEngine::class)->quote(
            $service,
            $pricingProbe
        );

        $amountDue = round((float) ($quote['amount'] ?? 0), 2);

        if ($amountDue <= 0) {
            throw ValidationException::withMessages([
                'service' => [
                    'El Service requiere un monto '
                    .'inicial mayor que cero para este ciclo.',
                ],
            ]);
        }

        return [
            'billing_cycle' => $billingCycle,
            'currency' => $currency,
            'amount_due' => $amountDue,
            'subscription_locked' =>
                $activeSubscription !== null,
            'subscription_cycle_locked' =>
                $activeSubscription !== null,
            'active_subscription_id' =>
                $activeSubscription?->id,
            'service_plan_id' => null,
            'quote' => $quote,
        ];
    }

    public function checkout(
        ActivationRequestService $requestRow,
        Subscriber $subscriber,
        Company $company,
        Service $service,
        string $billingCycle = 'monthly',
        ?int $userId = null,
        ?ServicePlan $servicePlan = null
    ): StandaloneServiceSettlement {
        $billingCycle = strtolower(
            trim($billingCycle)
        );

        if (
            (int) $requestRow->service_id
            !== (int) $service->id
        ) {
            throw ValidationException::withMessages([
                'service' => [
                    'La solicitud no corresponde '
                    .'al Service seleccionado.',
                ],
            ]);
        }

        $preview = $this->previewQuote(
            $subscriber,
            $company,
            $service,
            $billingCycle,
            $servicePlan
        );

        $billingCycle =
            (string) $preview['billing_cycle'];

        $currency =
            (string) $preview['currency'];

        $quote =
            (array) $preview['quote'];

        $amountDue =
            (float) $preview['amount_due'];

        return DB::transaction(function () use (
            $requestRow,
            $subscriber,
            $company,
            $service,
            $billingCycle,
            $currency,
            $quote,
            $amountDue,
            $userId,
            $servicePlan
        ): StandaloneServiceSettlement {
            $lockedRequest = ActivationRequestService::query()
                ->whereKey($requestRow->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedRequest->service_id !== (int) $service->id) {
                throw ValidationException::withMessages([
                    'service' =>
                        'La solicitud cambió de Service durante el checkout.',
                ]);
            }

            $requestStatus = strtolower((string) $lockedRequest->status);

            if (! in_array(
                $requestStatus,
                ['pending', 'pending_payment'],
                true
            )) {
                throw ValidationException::withMessages([
                    'status' =>
                        'La solicitud debe estar pending o pending_payment.',
                ]);
            }

            /*
             * Idempotencia antes de crear Invoice.
             */
            $existing = StandaloneServiceSettlement::query()
                ->where(
                    'activation_request_service_id',
                    $lockedRequest->id
                )
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $this->assertExistingSettlement(
                    $existing,
                    $subscriber,
                    $company,
                    $service,
                    $billingCycle,
                    $currency,
                    $servicePlan
                );

                return $existing;
            }

            $invoiceNumber = sprintf(
                'LAUDA-SVC-%010d',
                (int) $lockedRequest->id
            );

            if (Invoice::query()->where('number', $invoiceNumber)->exists()) {
                throw ValidationException::withMessages([
                    'invoice' =>
                        'Existe una factura standalone sin ledger '
                        .'asociado para esta solicitud.',
                ]);
            }

            $now = now();

            $priceSnapshot = array_merge(
                $quote,
                [
                    'billing_cycle' => $billingCycle,
                    'amount_due_now' => $amountDue,
                ]
            );

            $invoice = Invoice::query()->create([
                'company_id' => $company->id,
                'subscription_id' => null,
                'number' => $invoiceNumber,
                'status' => 'issued',
                'issued_on' => $now->toDateString(),
                'due_on' => null,
                'period_start' => null,
                'period_end' => null,
                'currency' => $currency,
                'subtotal' => $amountDue,
                'discount_total' => 0,
                'tax_total' => 0,
                'total' => $amountDue,
                'amount_paid' => 0,
                'billing_snapshot' => [
                    'source' => 'standalone_service_checkout',
                    'activation_request_id' =>
                        (int) $lockedRequest->activation_request_id,
                    'activation_request_service_id' =>
                        (int) $lockedRequest->id,
                    'subscriber_id' => (int) $subscriber->id,
                    'company_id' => (int) $company->id,
                    'service_id' => (int) $service->id,
                    'billing_cycle' => $billingCycle,
                    'price_snapshot' => $priceSnapshot,
                    'entitlement_granted' => false,
                ],
                'document_class' => null,
                'document_type' => null,
                'fiscal_number' => null,
                'security_code' => null,
                'fiscal_meta' => [
                    'fiscal_document_pending' => true,
                    'source' => 'standalone_service_checkout',
                ],
                'provider' => null,
                'provider_invoice_id' => null,
                'hosted_invoice_url' => null,
                'payment_url' => null,
            ]);

            $invoiceItem = InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'service_id' => $service->id,
                'service_plan_id' => $servicePlan?->id,
                'description' => (string) $service->title,
                'quantity' => max(1, (int) ($quote['quantity'] ?? 1)),
                'unit_price' => round(
                    (float) ($quote['unit_price'] ?? $amountDue),
                    2
                ),
                'line_subtotal' => $amountDue,
                'discount_amount' => 0,
                'tax_rate' => 0,
                'tax_amount' => 0,
                'line_total' => $amountDue,
                'meta' => [
                    'source' => 'standalone_service_checkout',
                    'activation_request_id' =>
                        (int) $lockedRequest->activation_request_id,
                    'activation_request_service_id' =>
                        (int) $lockedRequest->id,
                    'subscriber_id' => (int) $subscriber->id,
                    'company_id' => (int) $company->id,
                    'service_id' => (int) $service->id,
                    'billing_cycle' => $billingCycle,
                    'price_snapshot' => $priceSnapshot,
                ],
            ]);

            $invoice = $invoice->fresh();

            $settlement = app(
                StandaloneServiceSettlementService::class
            )->registerPending(
                $lockedRequest,
                $subscriber,
                $company,
                $service,
                $invoice,
                $invoiceItem,
                $billingCycle,
                $priceSnapshot,
                $servicePlan
            );

            $requestMeta = $this->decodeMeta($lockedRequest->meta);

            DB::table('activation_request_service')
                ->where('id', $lockedRequest->id)
                ->update([
                    'status' => 'pending_payment',
                    'service_plan_id' => $servicePlan?->id,
                    'meta' => json_encode(
                        array_merge(
                            $requestMeta,
                            [
                                'activation_mode' => 'billed',
                                'payment_required' => true,
                                'requested_at' =>
                                    $requestMeta['requested_at']
                                    ?? $now->toISOString(),
                                'checkout_prepared_at' =>
                                    $now->toISOString(),
                                'billing_cycle' => $billingCycle,
                                'price_snapshot' => $priceSnapshot,
                                'invoice_id' => (int) $invoice->id,
                                'invoice_item_id' => (int) $invoiceItem->id,
                                'standalone_service_settlement_id' =>
                                    (int) $settlement->id,
                                'entitlement_granted' => false,
                                'standalone_checkout' => true,
                            ]
                        ),
                        JSON_UNESCAPED_SLASHES
                    ),
                    'updated_at' => $now,
                ]);

            AuditService::log(
                'standalone_service_checkout_created',
                $service,
                [
                    'activation_request_id' =>
                        (int) $lockedRequest->activation_request_id,
                    'activation_request_service_id' =>
                        (int) $lockedRequest->id,
                    'subscriber_id' => (int) $subscriber->id,
                    'company_id' => (int) $company->id,
                    'service_id' => (int) $service->id,
                    'invoice_id' => (int) $invoice->id,
                    'invoice_item_id' => (int) $invoiceItem->id,
                    'settlement_id' => (int) $settlement->id,
                    'billing_cycle' => $billingCycle,
                    'currency' => $currency,
                    'amount_due' => $amountDue,
                    'entitlement_granted' => false,
                ],
                ['user_id' => $userId]
            );

            return $settlement->fresh();
        }, 3);
    }

    private function assertIdentity(
        Subscriber $subscriber,
        Company $company
    ): void {
        if (! $subscriber->active) {
            throw ValidationException::withMessages([
                'subscriber' => 'El Subscriber debe estar activo.',
            ]);
        }

        if (! $company->active) {
            throw ValidationException::withMessages([
                'company' => 'La Company debe estar activa.',
            ]);
        }

        if ((int) $company->subscriber_id !== (int) $subscriber->id) {
            throw ValidationException::withMessages([
                'company' =>
                    'La Company debe pertenecer al mismo Subscriber.',
            ]);
        }
    }

    private function assertService(Service $service): void
    {
        if (! $service->active) {
            throw ValidationException::withMessages([
                'service' => 'El Service debe estar activo.',
            ]);
        }

        if (! $service->billable) {
            throw ValidationException::withMessages([
                'service' =>
                    'El Service todavía no está habilitado '
                    .'para checkout comercial standalone.',
            ]);
        }
    }

    private function resolveCurrency(
        Subscriber $subscriber,
        Company $company,
        Service $service
    ): string {
        $companyCurrency = strtoupper(
            trim((string) (
                $company->currency
                ?: $subscriber->currency
                ?: 'DOP'
            ))
        );

        $serviceCurrency = strtoupper(
            trim((string) (
                $service->currency
                ?: $companyCurrency
            ))
        );

        foreach ([
            'company' => $companyCurrency,
            'service' => $serviceCurrency,
        ] as $field => $currency) {
            if (! preg_match('/^[A-Z]{3}$/', $currency)) {
                throw ValidationException::withMessages([
                    'currency' =>
                        "La moneda {$field} debe usar "
                        .'un código ISO de tres letras.',
                ]);
            }
        }

        if ($companyCurrency !== $serviceCurrency) {
            throw ValidationException::withMessages([
                'currency' =>
                    'La moneda de Company/Subscriber debe coincidir '
                    .'con la moneda del Service. No se permite FX implícito.',
            ]);
        }

        return $companyCurrency;
    }

    private function assertPlan(
        Service $service,
        ServicePlan $servicePlan
    ): void {
        if (
            ! $servicePlan->active
            || (int) $servicePlan->service_id !== (int) $service->id
        ) {
            throw ValidationException::withMessages([
                'service_plan' => [
                    'El plan seleccionado no está activo '
                    .'o no pertenece al Service.',
                ],
            ]);
        }
    }

    private function resolvePlanCurrency(
        Subscriber $subscriber,
        Company $company,
        ServicePlan $servicePlan
    ): string {
        $companyCurrency = strtoupper(
            trim((string) (
                $company->currency
                ?: $subscriber->currency
                ?: 'DOP'
            ))
        );

        $planCurrency = strtoupper(
            trim((string) (
                $servicePlan->currency
                ?: $companyCurrency
            ))
        );

        if (
            ! preg_match('/^[A-Z]{3}$/', $companyCurrency)
            || ! preg_match('/^[A-Z]{3}$/', $planCurrency)
        ) {
            throw ValidationException::withMessages([
                'currency' => [
                    'Company y ServicePlan deben usar '
                    .'moneda ISO de tres letras.',
                ],
            ]);
        }

        if ($companyCurrency !== $planCurrency) {
            throw ValidationException::withMessages([
                'currency' => [
                    'La moneda de Company/Subscriber debe '
                    .'coincidir con la moneda del ServicePlan.',
                ],
            ]);
        }

        return $companyCurrency;
    }

    private function assertExistingSettlement(
        StandaloneServiceSettlement $settlement,
        Subscriber $subscriber,
        Company $company,
        Service $service,
        string $billingCycle,
        string $currency,
        ?ServicePlan $servicePlan = null
    ): void {
        $checks = [
            'subscriber_id' => [
                (int) $settlement->subscriber_id,
                (int) $subscriber->id,
            ],
            'company_id' => [
                (int) $settlement->company_id,
                (int) $company->id,
            ],
            'service_id' => [
                (int) $settlement->service_id,
                (int) $service->id,
            ],
            'service_plan_id' => [
                (int) ($settlement->service_plan_id ?? 0),
                (int) ($servicePlan?->id ?? 0),
            ],
            'billing_cycle' => [
                strtolower((string) $settlement->billing_cycle),
                $billingCycle,
            ],
            'currency' => [
                strtoupper((string) $settlement->currency),
                $currency,
            ],
        ];

        foreach ($checks as $field => [$actual, $expected]) {
            if ($actual !== $expected) {
                throw ValidationException::withMessages([
                    'settlement' =>
                        "El checkout existente no coincide en {$field}.",
                ]);
            }
        }
    }

    private function decodeMeta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if (! is_string($meta) || trim($meta) === '') {
            return [];
        }

        $decoded = json_decode($meta, true);

        return is_array($decoded) ? $decoded : [];
    }
}
