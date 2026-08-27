<?php

namespace App\Services\Billing;

use App\Models\ActivationRequestService;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServicePlan;
use App\Models\StandaloneServiceSettlement;
use App\Models\Subscriber;
use App\Services\AuditService;
use App\Services\Entitlements\CentralEntitlementActivationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StandaloneServiceSettlementService
{
    private const MONEY_EPSILON = 0.005;

    /**
     * Registra la evidencia comercial creada por checkout.
     *
     * No concede entitlement.
     */
    public function registerPending(
        ActivationRequestService $requestRow,
        Subscriber $subscriber,
        Company $company,
        Service $service,
        Invoice $invoice,
        InvoiceItem $invoiceItem,
        string $billingCycle,
        array $priceSnapshot,
        ?ServicePlan $servicePlan = null
    ): StandaloneServiceSettlement {
        $billingCycle = strtolower(
            trim($billingCycle)
        );

        $this->assertBillingCycle(
            $billingCycle
        );

        $this->assertIdentity(
            $subscriber,
            $company
        );

        $this->assertCommercialService(
            $service
        );

        $this->assertRequestEvidence(
            $requestRow,
            $service
        );

        $this->assertInvoiceEvidence(
            $invoice,
            $invoiceItem,
            $company,
            $service
        );

        $currency = strtoupper(
            trim(
                (string) $invoice->currency
            )
        );

        $this->assertCurrency(
            $currency
        );

        $amountDue = round(
            (float) $invoiceItem->line_total,
            2
        );

        if ($amountDue <= 0) {
            throw ValidationException::withMessages([
                'amount_due' => [
                    'El settlement payment-gated requiere '
                    .'un monto mayor que cero.',
                ],
            ]);
        }

                if (
            $servicePlan
            && (
                ! $servicePlan->active
                || (int) $servicePlan->service_id
                    !== (int) $service->id
            )
        ) {
            throw ValidationException::withMessages([
                'service_plan' => [
                    'El plan no está activo o no pertenece '
                    .'al Service del checkout.',
                ],
            ]);
        }

return DB::transaction(function () use (
            $requestRow,
            $subscriber,
            $company,
            $service,
            $invoice,
            $invoiceItem,
            $billingCycle,
            $priceSnapshot,
            $currency,
            $amountDue,
            $servicePlan
        ): StandaloneServiceSettlement {
            /*
             * La request row es el mutex del checkout standalone.
             */
            $lockedRequest = DB::table(
                'activation_request_service'
            )
                ->where(
                    'id',
                    $requestRow->id
                )
                ->lockForUpdate()
                ->first();

            if (! $lockedRequest) {
                throw ValidationException::withMessages([
                    'activation_request_service' => [
                        'La solicitud de Service ya no existe.',
                    ],
                ]);
            }

            if (
                (int) $lockedRequest->activation_request_id
                !== (int) $requestRow->activation_request_id
                || (int) $lockedRequest->service_id
                    !== (int) $service->id
            ) {
                throw ValidationException::withMessages([
                    'activation_request_service' => [
                        'La solicitud cambió durante el checkout.',
                    ],
                ]);
            }

            $existing =
                StandaloneServiceSettlement::query()
                    ->where(
                        'activation_request_service_id',
                        $requestRow->id
                    )
                    ->lockForUpdate()
                    ->first();

            if ($existing) {
                $this->assertSameCheckoutEvidence(
                    $existing,
                    $subscriber,
                    $company,
                    $service,
                    $invoice,
                    $invoiceItem,
                    $billingCycle,
                    $currency,
                    $servicePlan
                );

                return $existing;
            }

            return StandaloneServiceSettlement::query()
                ->create([
                    'activation_request_service_id' =>
                        $requestRow->id,
                    'activation_request_id' =>
                        $requestRow->activation_request_id,
                    'subscriber_id' =>
                        $subscriber->id,
                    'company_id' =>
                        $company->id,
                    'service_id' =>
                        $service->id,
                    'service_plan_id' =>
                        $servicePlan?->id,
                    'invoice_id' =>
                        $invoice->id,
                    'invoice_item_id' =>
                        $invoiceItem->id,
                    'subscription_id' =>
                        null,
                    'subscription_item_id' =>
                        null,
                    'status' =>
                        StandaloneServiceSettlement::STATUS_PENDING_PAYMENT,
                    'billing_cycle' =>
                        $billingCycle,
                    'currency' =>
                        $currency,
                    'amount_due' =>
                        $amountDue,
                    'amount_paid' =>
                        round(
                            (float) $invoice->amount_paid,
                            2
                        ),
                    'settled_at' =>
                        null,
                    'activated_at' =>
                        null,
                    'revoked_at' =>
                        null,
                    'failure_reason' =>
                        null,
                    'evidence_snapshot' => [
                        'source' =>
                            'standalone_checkout',
                        'activation_request_service_id' =>
                            (int) $requestRow->id,
                        'activation_request_id' =>
                            (int) $requestRow
                                ->activation_request_id,
                        'service_id' =>
                            (int) $service->id,
                        'service_plan_id' =>
                            $servicePlan?->id,
                        'invoice_id' =>
                            (int) $invoice->id,
                        'invoice_item_id' =>
                            (int) $invoiceItem->id,
                        'billing_cycle' =>
                            $billingCycle,
                        'currency' =>
                            $currency,
                        'amount_due' =>
                            $amountDue,
                        'price_snapshot' =>
                            $priceSnapshot,
                        'registered_at' =>
                            now()->toISOString(),
                    ],
                ]);
        }, 3);
    }

    /**
     * Se invoca después de que InvoiceReconciliationService
     * haya determinado status=paid.
     *
     * Payment individual NO es el idempotency key:
     * una factura puede tener múltiples payments.
     */
    public function settle(
        StandaloneServiceSettlement $settlement,
        ?int $userId = null
    ): StandaloneServiceSettlement {
        if (
            $settlement->status
            === StandaloneServiceSettlement::STATUS_ACTIVATED
        ) {
            return $settlement;
        }

        return DB::transaction(function () use (
            $settlement,
            $userId
        ): StandaloneServiceSettlement {
            $locked =
                StandaloneServiceSettlement::query()
                    ->whereKey(
                        $settlement->id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            if (
                $locked->status
                === StandaloneServiceSettlement::STATUS_ACTIVATED
            ) {
                return $locked;
            }

            if (
                ! in_array(
                    $locked->status,
                    [
                        StandaloneServiceSettlement::STATUS_PENDING_PAYMENT,
                        StandaloneServiceSettlement::STATUS_REVOKED,
                    ],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'settlement' => [
                        'Solo un settlement pending_payment o revoked '
                        .'puede activarse.',
                    ],
                ]);
            }

            $requestRow =
                ActivationRequestService::query()
                    ->whereKey(
                        $locked->activation_request_service_id
                    )
                    ->first();

            if (! $requestRow) {
                throw ValidationException::withMessages([
                    'activation_request_service' => [
                        'La solicitud standalone ya no existe.',
                    ],
                ]);
            }

            if (
                (int) $requestRow->activation_request_id
                    !== (int) $locked->activation_request_id
                || (int) $requestRow->service_id
                    !== (int) $locked->service_id
            ) {
                throw ValidationException::withMessages([
                    'activation_request_service' => [
                        'La solicitud no coincide con el ledger.',
                    ],
                ]);
            }

            $requestStatus = strtolower(
                trim(
                    (string) $requestRow->status
                )
            );

            if (
                ! in_array(
                    $requestStatus,
                    [
                        'pending_payment',
                        'active',
                        'cancelled',
                    ],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'activation_request_service' => [
                        'La solicitud debe permanecer '
                        .'pending_payment para liquidarse.',
                    ],
                ]);
            }

            $subscriber = Subscriber::query()
                ->findOrFail(
                    $locked->subscriber_id
                );

            $company = Company::query()
                ->findOrFail(
                    $locked->company_id
                );

            $service = Service::query()
                ->findOrFail(
                    $locked->service_id
                );

            $invoice = Invoice::query()
                ->whereKey(
                    $locked->invoice_id
                )
                ->lockForUpdate()
                ->firstOrFail();

            $invoiceItem = InvoiceItem::query()
                ->whereKey(
                    $locked->invoice_item_id
                )
                ->firstOrFail();

            $this->assertIdentity(
                $subscriber,
                $company
            );

            $this->assertCommercialService(
                $service
            );

            $this->assertInvoiceEvidence(
                $invoice,
                $invoiceItem,
                $company,
                $service
            );

            if (
                strtolower(
                    (string) $invoice->status
                ) !== 'paid'
            ) {
                throw ValidationException::withMessages([
                    'invoice' => [
                        'El Invoice debe estar paid según '
                        .'InvoiceReconciliationService.',
                    ],
                ]);
            }

            $currency = strtoupper(
                trim(
                    (string) $invoice->currency
                )
            );

            if (
                $currency
                !== strtoupper(
                    (string) $locked->currency
                )
            ) {
                throw ValidationException::withMessages([
                    'currency' => [
                        'La moneda del Invoice cambió '
                        .'respecto al checkout.',
                    ],
                ]);
            }

            $invoiceTotal = round(
                (float) $invoice->total,
                2
            );

            $invoicePaid = round(
                (float) $invoice->amount_paid,
                2
            );

            if (
                $invoiceTotal <= 0
                || $invoicePaid
                    + self::MONEY_EPSILON
                    < $invoiceTotal
            ) {
                throw ValidationException::withMessages([
                    'invoice' => [
                        'La evidencia de pago completo '
                        .'no es suficiente.',
                    ],
                ]);
            }

            /*
             * El instante de activación es cuando la factura
             * llegó a estar completamente pagada. Como puede
             * haber múltiples Payments, usamos el último paid_at.
             */
            $paidAtRaw = Payment::query()
                ->where(
                    'invoice_id',
                    $invoice->id
                )
                ->whereNotNull(
                    'paid_at'
                )
                ->max(
                    'paid_at'
                );

            if (! $paidAtRaw) {
                throw ValidationException::withMessages([
                    'payment' => [
                        'Invoice paid sin Payment paid_at verificable.',
                    ],
                ]);
            }

            $paidAt = CarbonImmutable::parse(
                $paidAtRaw
            );

            /*
             * Único owner económico.
             * No crear Subscription/Item directamente aquí.
             */
            $activation = app(
                CentralEntitlementActivationService::class
            )->activateCommercial(
                $subscriber,
                $company,
                $service,
                CentralEntitlementActivationService::SOURCE_STANDALONE_SETTLEMENT,
                $userId,
                $locked->billing_cycle,
                $paidAt,
                [
                    'standalone_settlement_id' =>
                        (int) $locked->id,
                    'activation_request_service_id' =>
                        (int) $requestRow->id,
                    'activation_request_id' =>
                        (int) $locked->activation_request_id,
                    'invoice_id' =>
                        (int) $invoice->id,
                    'invoice_item_id' =>
                        (int) $invoiceItem->id,
                    'service_plan_id' =>
                        $locked->service_plan_id !== null
                            ? (int) $locked->service_plan_id
                            : null,
                    'billing_cycle' =>
                        (string) $locked->billing_cycle,
                    'invoice_status' =>
                        (string) $invoice->status,
                    'invoice_amount_paid' =>
                        $invoicePaid,
                ]
            );

            $item = $activation['item'];
            $subscription =
                $activation['subscription'];

            $meta = $requestRow->meta;

            if (is_string($meta)) {
                $meta = json_decode(
                    $meta,
                    true
                );
            }

            if (! is_array($meta)) {
                $meta = [];
            }

            $meta = array_merge(
                $meta,
                [
                    'activation_mode' =>
                        'billed',
                    'payment_required' =>
                        true,
                    'entitlement_granted' =>
                        true,
                    'standalone_settlement_id' =>
                        (int) $locked->id,
                    'invoice_id' =>
                        (int) $invoice->id,
                    'invoice_item_id' =>
                        (int) $invoiceItem->id,
                    'subscription_id' =>
                        (int) $subscription->id,
                    'subscription_item_id' =>
                        (int) $item->id,
                    'activated_at' =>
                        now()->toISOString(),
                ]
            );

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
                    'meta' =>
                        json_encode(
                            $meta,
                            JSON_UNESCAPED_SLASHES
                        ),
                    'updated_at' =>
                        now(),
                ]);

            $paymentIds = Payment::query()
                ->where(
                    'invoice_id',
                    $invoice->id
                )
                ->whereNotNull(
                    'paid_at'
                )
                ->orderBy('id')
                ->pluck('id')
                ->map(
                    fn ($id): int => (int) $id
                )
                ->all();

            $evidence =
                $locked->evidence_snapshot;

            if (! is_array($evidence)) {
                $evidence = [];
            }

            $evidence['settlement'] = [
                'invoice_status' =>
                    (string) $invoice->status,
                'invoice_total' =>
                    $invoiceTotal,
                'invoice_amount_paid' =>
                    $invoicePaid,
                'payment_ids' =>
                    $paymentIds,
                'fully_paid_at' =>
                    $paidAt->toIso8601String(),
                'subscription_id' =>
                    (int) $subscription->id,
                'subscription_item_id' =>
                    (int) $item->id,
                'item_activation' =>
                    $activation['item_activation'],
                'subscription_created' =>
                    $activation['subscription_created'],
            ];

            $locked->forceFill([
                'subscription_id' =>
                    $subscription->id,
                'subscription_item_id' =>
                    $item->id,
                'status' =>
                    StandaloneServiceSettlement::STATUS_ACTIVATED,
                'amount_paid' =>
                    $invoicePaid,
                'settled_at' =>
                    $paidAt,
                'activated_at' =>
                    now(),
                'revoked_at' =>
                    null,
                'failure_reason' =>
                    null,
                'evidence_snapshot' =>
                    $evidence,
            ])->save();

            AuditService::log(
                'standalone_service_entitlement_activated',
                $service,
                [
                    'standalone_settlement_id' =>
                        (int) $locked->id,
                    'activation_request_id' =>
                        (int) $locked->activation_request_id,
                    'activation_request_service_id' =>
                        (int) $requestRow->id,
                    'subscriber_id' =>
                        (int) $subscriber->id,
                    'company_id' =>
                        (int) $company->id,
                    'service_id' =>
                        (int) $service->id,
                    'invoice_id' =>
                        (int) $invoice->id,
                    'invoice_item_id' =>
                        (int) $invoiceItem->id,
                    'subscription_id' =>
                        (int) $subscription->id,
                    'subscription_item_id' =>
                        (int) $item->id,
                    'source' =>
                        CentralEntitlementActivationService::SOURCE_STANDALONE_SETTLEMENT,
                ],
                [
                    'user_id' =>
                        $userId,
                ]
            );

            return $locked->fresh();
        }, 3);
    }

    /**
     * Revoca el entitlement de un settlement ya activado cuando
     * la evidencia canónica del Invoice deja de estar paid.
     *
     * Idempotente: revoked vuelve a sí mismo.
     */
    public function revoke(
        StandaloneServiceSettlement $settlement,
        ?int $userId = null,
        string $reason = 'invoice_no_longer_paid'
    ): StandaloneServiceSettlement {
        if (
            $settlement->status
            === StandaloneServiceSettlement::STATUS_REVOKED
        ) {
            return $settlement;
        }

        return DB::transaction(function () use (
            $settlement,
            $userId,
            $reason
        ): StandaloneServiceSettlement {
            $locked =
                StandaloneServiceSettlement::query()
                    ->whereKey(
                        $settlement->id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            if (
                $locked->status
                === StandaloneServiceSettlement::STATUS_REVOKED
            ) {
                return $locked;
            }

            if (
                $locked->status
                !== StandaloneServiceSettlement::STATUS_ACTIVATED
            ) {
                throw ValidationException::withMessages([
                    'settlement' => [
                        'Solo un settlement activated puede revocarse.',
                    ],
                ]);
            }

            $invoice = Invoice::query()
                ->whereKey($locked->invoice_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                strtolower(
                    (string) $invoice->status
                ) === 'paid'
            ) {
                throw ValidationException::withMessages([
                    'invoice' => [
                        'Un Invoice que continúa paid no puede revocar '
                        .'el entitlement.',
                    ],
                ]);
            }

            $subscription = \App\Models\Subscription::query()
                ->findOrFail(
                    $locked->subscription_id
                );

            $item = \App\Models\SubscriptionItem::query()
                ->findOrFail(
                    $locked->subscription_item_id
                );

            if (
                (int) $item->subscription_id
                    !== (int) $subscription->id
                || (int) $item->service_id
                    !== (int) $locked->service_id
            ) {
                throw ValidationException::withMessages([
                    'subscription_item' => [
                        'El entitlement no coincide con el settlement.',
                    ],
                ]);
            }

            $requestRow =
                ActivationRequestService::query()
                    ->whereKey(
                        $locked->activation_request_service_id
                    )
                    ->firstOrFail();

            $revocation = app(
                CentralEntitlementActivationService::class
            )->revokeCommercialItem(
                $subscription,
                $item,
                CentralEntitlementActivationService::SOURCE_STANDALONE_SETTLEMENT,
                $userId,
                [
                    'standalone_settlement_id' =>
                        (int) $locked->id,
                    'activation_request_service_id' =>
                        (int) $requestRow->id,
                    'activation_request_id' =>
                        (int) $locked->activation_request_id,
                    'invoice_id' =>
                        (int) $invoice->id,
                    'invoice_item_id' =>
                        (int) $locked->invoice_item_id,
                    'invoice_status' =>
                        (string) $invoice->status,
                    'invoice_amount_paid' =>
                        round(
                            (float) $invoice->amount_paid,
                            2
                        ),
                    'reason' =>
                        $reason,
                ]
            );

            $requestMeta = $requestRow->meta;

            if (is_string($requestMeta)) {
                $requestMeta = json_decode(
                    $requestMeta,
                    true
                );
            }

            if (! is_array($requestMeta)) {
                $requestMeta = [];
            }

            $requestMeta = array_merge(
                $requestMeta,
                [
                    'entitlement_granted' =>
                        false,
                    'standalone_settlement_id' =>
                        (int) $locked->id,
                    'subscription_id' =>
                        (int) $subscription->id,
                    'subscription_item_id' =>
                        (int) $item->id,
                    'revocation_reason' =>
                        $reason,
                    'revoked_at' =>
                        now()->toISOString(),
                ]
            );

            DB::table(
                'activation_request_service'
            )
                ->where(
                    'id',
                    $requestRow->id
                )
                ->update([
                    'status' =>
                        'cancelled',
                    'meta' =>
                        json_encode(
                            $requestMeta,
                            JSON_UNESCAPED_SLASHES
                        ),
                    'updated_at' =>
                        now(),
                ]);

            $evidence = $locked->evidence_snapshot;

            if (! is_array($evidence)) {
                $evidence = [];
            }

            $history = $evidence['revocations']
                ?? [];

            if (! is_array($history)) {
                $history = [];
            }

            $history[] = [
                'reason' =>
                    $reason,
                'invoice_id' =>
                    (int) $invoice->id,
                'invoice_status' =>
                    (string) $invoice->status,
                'invoice_total' =>
                    round(
                        (float) $invoice->total,
                        2
                    ),
                'invoice_amount_paid' =>
                    round(
                        (float) $invoice->amount_paid,
                        2
                    ),
                'subscription_id' =>
                    (int) $subscription->id,
                'subscription_item_id' =>
                    (int) $item->id,
                'item_revocation' =>
                    $revocation['item_revocation'],
                'revoked_at' =>
                    now()->toISOString(),
            ];

            $evidence['revocations'] = $history;
            $evidence['last_revocation'] =
                end($history);

            $locked->forceFill([
                'status' =>
                    StandaloneServiceSettlement::STATUS_REVOKED,
                'amount_paid' =>
                    round(
                        (float) $invoice->amount_paid,
                        2
                    ),
                'revoked_at' =>
                    now(),
                'failure_reason' =>
                    null,
                'evidence_snapshot' =>
                    $evidence,
            ])->save();

            $service = Service::query()
                ->findOrFail(
                    $locked->service_id
                );

            AuditService::log(
                'standalone_service_entitlement_revoked',
                $service,
                [
                    'standalone_settlement_id' =>
                        (int) $locked->id,
                    'activation_request_id' =>
                        (int) $locked->activation_request_id,
                    'activation_request_service_id' =>
                        (int) $requestRow->id,
                    'subscriber_id' =>
                        (int) $locked->subscriber_id,
                    'company_id' =>
                        (int) $locked->company_id,
                    'service_id' =>
                        (int) $locked->service_id,
                    'invoice_id' =>
                        (int) $invoice->id,
                    'subscription_id' =>
                        (int) $subscription->id,
                    'subscription_item_id' =>
                        (int) $item->id,
                    'invoice_status' =>
                        (string) $invoice->status,
                    'invoice_amount_paid' =>
                        round(
                            (float) $invoice->amount_paid,
                            2
                        ),
                    'reason' =>
                        $reason,
                    'source' =>
                        CentralEntitlementActivationService::SOURCE_STANDALONE_SETTLEMENT,
                ],
                [
                    'user_id' =>
                        $userId,
                ]
            );

            return $locked->fresh();
        }, 3);
    }

    public function revokeUnpaidInvoice(
        Invoice $invoice,
        ?int $userId = null,
        string $reason = 'invoice_no_longer_paid'
    ): array {
        if (
            strtolower(
                (string) $invoice->status
            ) === 'paid'
        ) {
            return [];
        }

        return StandaloneServiceSettlement::query()
            ->where(
                'invoice_id',
                $invoice->id
            )
            ->whereIn(
                'status',
                [
                    StandaloneServiceSettlement::STATUS_ACTIVATED,
                    StandaloneServiceSettlement::STATUS_REVOKED,
                ]
            )
            ->orderBy('id')
            ->get()
            ->map(
                fn (
                    StandaloneServiceSettlement $settlement
                ): StandaloneServiceSettlement =>
                    $this->revoke(
                        $settlement,
                        $userId,
                        $reason
                    )
            )
            ->all();
    }

    public function recordPostReconciliationRevocationFailure(
        Invoice $invoice,
        \Throwable $exception
    ): void {
        $message = mb_substr(
            trim($exception->getMessage()),
            0,
            10000
        );

        StandaloneServiceSettlement::query()
            ->where(
                'invoice_id',
                $invoice->id
            )
            ->where(
                'status',
                StandaloneServiceSettlement::STATUS_ACTIVATED
            )
            ->orderBy('id')
            ->get()
            ->each(function (
                StandaloneServiceSettlement $settlement
            ) use (
                $invoice,
                $message,
                $exception
            ): void {
                $evidence =
                    $settlement->evidence_snapshot;

                if (! is_array($evidence)) {
                    $evidence = [];
                }

                $evidence[
                    'last_post_reconciliation_revocation_failure'
                ] = [
                    'invoice_id' =>
                        (int) $invoice->id,
                    'invoice_status' =>
                        (string) $invoice->status,
                    'invoice_amount_paid' =>
                        round(
                            (float) $invoice->amount_paid,
                            2
                        ),
                    'exception' =>
                        get_class($exception),
                    'message' =>
                        $message,
                    'failed_at' =>
                        now()->toISOString(),
                    'retryable' =>
                        true,
                ];

                $settlement->forceFill([
                    'failure_reason' =>
                        $message,
                    'evidence_snapshot' =>
                        $evidence,
                ])->save();

                AuditService::log(
                    'standalone_service_entitlement_revocation_failed',
                    $settlement,
                    [
                        'standalone_settlement_id' =>
                            (int) $settlement->id,
                        'service_id' =>
                            (int) $settlement->service_id,
                        'invoice_id' =>
                            (int) $invoice->id,
                        'failure_reason' =>
                            $message,
                        'retryable' =>
                            true,
                    ]
                );
            });
    }

    /**
     * Adapter futuro para InvoiceReconciliationService.
     *
     * Si el Invoice no está paid, no concede nada.
     */
    public function settlePaidInvoice(
        Invoice $invoice,
        ?int $userId = null
    ): array {
        if (
            strtolower(
                (string) $invoice->status
            ) !== 'paid'
        ) {
            return [];
        }

        return StandaloneServiceSettlement::query()
            ->where(
                'invoice_id',
                $invoice->id
            )
            ->whereIn(
                'status',
                [
                    StandaloneServiceSettlement::STATUS_PENDING_PAYMENT,
                    StandaloneServiceSettlement::STATUS_ACTIVATED,
                    StandaloneServiceSettlement::STATUS_REVOKED,
                ]
            )
            ->orderBy('id')
            ->get()
            ->map(
                fn (
                    StandaloneServiceSettlement $settlement
                ): StandaloneServiceSettlement =>
                    $this->settle(
                        $settlement,
                        $userId
                    )
            )
            ->all();
    }

    public function recordPostReconciliationFailure(
        Invoice $invoice,
        \Throwable $exception
    ): void {
        $message = mb_substr(
            trim($exception->getMessage()),
            0,
            10000
        );

        StandaloneServiceSettlement::query()
            ->where('invoice_id', $invoice->id)
            ->where(
                'status',
                StandaloneServiceSettlement::STATUS_PENDING_PAYMENT
            )
            ->orderBy('id')
            ->get()
            ->each(function (
                StandaloneServiceSettlement $settlement
            ) use ($invoice, $message, $exception): void {
                $evidence = $settlement->evidence_snapshot;
                if (! is_array($evidence)) {
                    $evidence = [];
                }

                $evidence['last_post_reconciliation_failure'] = [
                    'invoice_id' => (int) $invoice->id,
                    'invoice_status' => (string) $invoice->status,
                    'invoice_amount_paid' => round((float) $invoice->amount_paid, 2),
                    'exception' => get_class($exception),
                    'message' => $message,
                    'failed_at' => now()->toISOString(),
                    'retryable' => true,
                ];

                $settlement->forceFill([
                    'failure_reason' => $message,
                    'evidence_snapshot' => $evidence,
                ])->save();

                AuditService::log(
                    'standalone_service_settlement_post_reconciliation_failed',
                    $settlement,
                    [
                        'standalone_settlement_id' => (int) $settlement->id,
                        'activation_request_service_id' => (int) $settlement->activation_request_service_id,
                        'service_id' => (int) $settlement->service_id,
                        'invoice_id' => (int) $invoice->id,
                        'failure_reason' => $message,
                        'retryable' => true,
                    ]
                );
            });
    }

    private function assertIdentity(
        Subscriber $subscriber,
        Company $company
    ): void {
        if (! (bool) $subscriber->active) {
            throw ValidationException::withMessages([
                'subscriber' => [
                    'El Subscriber debe estar activo.',
                ],
            ]);
        }

        if (! (bool) $company->active) {
            throw ValidationException::withMessages([
                'company' => [
                    'La Company debe estar activa.',
                ],
            ]);
        }

        if (
            (int) $company->subscriber_id
            !== (int) $subscriber->id
        ) {
            throw ValidationException::withMessages([
                'company' => [
                    'La Company pertenece a otro Subscriber.',
                ],
            ]);
        }
    }

    private function assertCommercialService(
        Service $service
    ): void {
        if (! (bool) $service->active) {
            throw ValidationException::withMessages([
                'service' => [
                    'El Service debe estar activo.',
                ],
            ]);
        }

        if (! (bool) $service->billable) {
            throw ValidationException::withMessages([
                'service' => [
                    'El Service todavía no está habilitado '
                    .'para compra standalone.',
                ],
            ]);
        }
    }

    private function assertRequestEvidence(
        ActivationRequestService $requestRow,
        Service $service
    ): void {
        if (
            ! $requestRow->id
            || ! $requestRow->activation_request_id
            || (int) $requestRow->service_id
                !== (int) $service->id
        ) {
            throw ValidationException::withMessages([
                'activation_request_service' => [
                    'La solicitud no identifica '
                    .'correctamente el Service.',
                ],
            ]);
        }

        $status = strtolower(
            trim(
                (string) $requestRow->status
            )
        );

        if (
            ! in_array(
                $status,
                [
                    'pending',
                    'pending_payment',
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'activation_request_service' => [
                    'La solicitud no está disponible '
                    .'para checkout standalone.',
                ],
            ]);
        }
    }

    private function assertInvoiceEvidence(
        Invoice $invoice,
        InvoiceItem $invoiceItem,
        Company $company,
        Service $service
    ): void {
        if (
            (int) $invoice->company_id
            !== (int) $company->id
        ) {
            throw ValidationException::withMessages([
                'invoice' => [
                    'El Invoice pertenece a otra Company.',
                ],
            ]);
        }

        if (
            strtolower(
                (string) $invoice->status
            ) === 'void'
        ) {
            throw ValidationException::withMessages([
                'invoice' => [
                    'Un Invoice void no puede respaldar '
                    .'una activación standalone.',
                ],
            ]);
        }

        if (
            (int) $invoiceItem->invoice_id
            !== (int) $invoice->id
        ) {
            throw ValidationException::withMessages([
                'invoice_item' => [
                    'El InvoiceItem pertenece a otro Invoice.',
                ],
            ]);
        }

        if (
            (int) $invoiceItem->service_id
            !== (int) $service->id
        ) {
            throw ValidationException::withMessages([
                'invoice_item' => [
                    'El InvoiceItem pertenece a otro Service.',
                ],
            ]);
        }
    }

    private function assertSameCheckoutEvidence(
        StandaloneServiceSettlement $settlement,
        Subscriber $subscriber,
        Company $company,
        Service $service,
        Invoice $invoice,
        InvoiceItem $invoiceItem,
        string $billingCycle,
        string $currency,
        ?ServicePlan $servicePlan = null
    ): void {
        $expected = [
            'subscriber_id' =>
                (int) $subscriber->id,
            'company_id' =>
                (int) $company->id,
            'service_id' =>
                (int) $service->id,
            'service_plan_id' =>
                (int) ($servicePlan?->id ?? 0),
            'invoice_id' =>
                (int) $invoice->id,
            'invoice_item_id' =>
                (int) $invoiceItem->id,
            'billing_cycle' =>
                $billingCycle,
            'currency' =>
                $currency,
        ];

        $actual = [
            'subscriber_id' =>
                (int) $settlement->subscriber_id,
            'company_id' =>
                (int) $settlement->company_id,
            'service_id' =>
                (int) $settlement->service_id,
            'service_plan_id' =>
                (int) ($settlement->service_plan_id ?? 0),
            'invoice_id' =>
                (int) $settlement->invoice_id,
            'invoice_item_id' =>
                (int) $settlement->invoice_item_id,
            'billing_cycle' =>
                (string) $settlement->billing_cycle,
            'currency' =>
                strtoupper(
                    (string) $settlement->currency
                ),
        ];

        if ($actual !== $expected) {
            throw ValidationException::withMessages([
                'settlement' => [
                    'La solicitud ya tiene otra evidencia '
                    .'de checkout registrada.',
                ],
            ]);
        }
    }

    private function assertBillingCycle(
        string $billingCycle
    ): void {
        if (
            ! in_array(
                $billingCycle,
                [
                    'monthly',
                    'yearly',
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'billing_cycle' => [
                    'El ciclo debe ser monthly o yearly.',
                ],
            ]);
        }
    }

    private function assertCurrency(
        string $currency
    ): void {
        if (
            ! preg_match(
                '/^[A-Z]{3}$/',
                $currency
            )
        ) {
            throw ValidationException::withMessages([
                'currency' => [
                    'La moneda debe usar código ISO '
                    .'de tres letras.',
                ],
            ]);
        }
    }
}
