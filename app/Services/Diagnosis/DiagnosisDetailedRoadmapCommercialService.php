<?php

namespace App\Services\Diagnosis;

use App\Models\Company;
use App\Models\ContactRequest;
use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisDetailedRoadmapOrder;
use App\Models\DiagnosisExpandedReport;
use App\Models\DiagnosisExpandedReportOrder;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\AuditService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DiagnosisDetailedRoadmapCommercialService
{
    public function commercialSnapshot(
        ?DiagnosisExpandedReportOrder $expandedOrder = null,
        ?CarbonInterface $requestedAt = null
    ): array {
        $offer = config(
            'lauda360_commercial.detailed_roadmap',
            []
        );

        $currency = strtoupper(
            trim(
                (string)
                    ($offer['currency'] ?? 'DOP')
            )
        );

        $baseSubtotal = round(
            (float)
                ($offer['subtotal'] ?? 0),
            2
        );

        $taxRate = round(
            (float)
                ($offer['tax_rate'] ?? 0),
            3
        );

        $configuredCredit = round(
            max(
                0,
                (float)
                    ($offer[
                        'expanded_report_credit'
                    ] ?? 0)
            ),
            2
        );

        $windowDays = max(
            0,
            (int)
                ($offer[
                    'expanded_report_credit_window_days'
                ] ?? 30)
        );

        $at = $requestedAt ?? now();

        $decision = $this->creditDecision(
            $expandedOrder,
            $at,
            $windowDays
        );

        $creditAmount = $decision['eligible']
            ? min(
                $configuredCredit,
                $baseSubtotal
            )
            : 0.0;

        $netSubtotal = round(
            max(
                0,
                $baseSubtotal - $creditAmount
            ),
            2
        );

        $taxAmount = round(
            $netSubtotal * ($taxRate / 100),
            2
        );

        return [
            'currency' => $currency,
            'base_subtotal' =>
                $baseSubtotal,
            'credit_eligible' =>
                $decision['eligible'],
            'credit_reason' =>
                $decision['reason'],
            'credit_amount' =>
                round($creditAmount, 2),
            'net_subtotal' =>
                $netSubtotal,
            'tax_rate' =>
                $taxRate,
            'tax_amount' =>
                $taxAmount,
            'total' => round(
                $netSubtotal + $taxAmount,
                2
            ),
            'credit_window_days' =>
                $windowDays,
            'credit_source_order_id' =>
                $expandedOrder?->id,
            'credit_source_paid_at' =>
                $decision['paid_at']
                    ?->toISOString(),
            'credit_expires_at' =>
                $decision['expires_at']
                    ?->toISOString(),
            'requested_at' =>
                $at->toISOString(),
        ];
    }

    public function requestPurchase(
        DiagnosisAssessment $assessment,
        User $user
    ): DiagnosisDetailedRoadmapOrder {
        $this->assertAssessmentCanContinue(
            $assessment,
            $user
        );

        return DB::transaction(
            function () use (
                $assessment,
                $user
            ): DiagnosisDetailedRoadmapOrder {
                $lockedAssessment =
                    DiagnosisAssessment::query()
                        ->whereKey(
                            $assessment->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertAssessmentCanContinue(
                    $lockedAssessment,
                    $user
                );

                $existing =
                    DiagnosisDetailedRoadmapOrder::query()
                        ->where(
                            'diagnosis_assessment_id',
                            $lockedAssessment->id
                        )
                        ->lockForUpdate()
                        ->first();

                if ($existing) {
                    return $existing;
                }

                $expandedOrder =
                    DiagnosisExpandedReportOrder::query()
                        ->where(
                            'diagnosis_assessment_id',
                            $lockedAssessment->id
                        )
                        ->with(
                            'invoice:id,status'
                        )
                        ->first();

                $requestedAt = now();

                $price = $this->commercialSnapshot(
                    $expandedOrder,
                    $requestedAt
                );

                $workflow =
                    DiagnosisAccessRequest::query()
                        ->where(
                            'diagnosis_assessment_id',
                            $lockedAssessment->id
                        )
                        ->first();

                $order =
                    DiagnosisDetailedRoadmapOrder::create([
                        'diagnosis_assessment_id' =>
                            $lockedAssessment->id,
                        'user_id' => $user->id,
                        'contact_request_id' =>
                            $workflow?->contact_request_id,
                        'expanded_report_order_id' =>
                            $expandedOrder?->id,
                        'status' =>
                            DiagnosisDetailedRoadmapOrder::STATUS_REQUESTED,
                        'currency' =>
                            $price['currency'],
                        'base_subtotal' =>
                            $price['base_subtotal'],
                        'credit_eligible' =>
                            $price['credit_eligible'],
                        'credit_amount' =>
                            $price['credit_amount'],
                        'net_subtotal' =>
                            $price['net_subtotal'],
                        'tax_rate' =>
                            $price['tax_rate'],
                        'tax_amount' =>
                            $price['tax_amount'],
                        'total' =>
                            $price['total'],
                        'credit_window_days' =>
                            $price['credit_window_days'],
                        'credit_source_paid_at' =>
                            $price['credit_source_paid_at'],
                        'credit_expires_at' =>
                            $price['credit_expires_at'],
                        'requested_at' =>
                            $requestedAt,
                        'meta' => [
                            'offer_code' =>
                                config(
                                    'lauda360_commercial.detailed_roadmap.code',
                                    'lauda360_detailed_roadmap'
                                ),
                            'offer_name' =>
                                config(
                                    'lauda360_commercial.detailed_roadmap.name',
                                    'Roadmap Detallado LAUDA 360'
                                ),
                            'source' =>
                                'diagnosis_lauda360',
                            'credit_policy' => [
                                'source' =>
                                    'expanded_report_order.paid_at',
                                'decision_at' =>
                                    $requestedAt->toISOString(),
                                'eligible' =>
                                    $price['credit_eligible'],
                                'reason' =>
                                    $price['credit_reason'],
                                'window_days' =>
                                    $price['credit_window_days'],
                                'source_order_id' =>
                                    $expandedOrder?->id,
                                'source_paid_at' =>
                                    $price['credit_source_paid_at'],
                                'expires_at' =>
                                    $price['credit_expires_at'],
                            ],
                        ],
                    ]);

                AuditService::log(
                    'diagnosis_detailed_roadmap_requested',
                    $order,
                    [
                        'assessment_id' =>
                            $lockedAssessment->id,
                        'user_id' =>
                            $user->id,
                        'base_subtotal' =>
                            (string) $order->base_subtotal,
                        'credit_eligible' =>
                            (bool) $order->credit_eligible,
                        'credit_amount' =>
                            (string) $order->credit_amount,
                        'total' =>
                            (string) $order->total,
                        'currency' =>
                            $order->currency,
                    ],
                    ['user_id' => $user->id]
                );

                return $order->fresh();
            }
        );
    }

    public function prepareInvoice(
        DiagnosisDetailedRoadmapOrder $order,
        User $actor
    ): Invoice {
        return DB::transaction(
            function () use (
                $order,
                $actor
            ): Invoice {
                $locked =
                    DiagnosisDetailedRoadmapOrder::query()
                        ->whereKey($order->id)
                        ->lockForUpdate()
                        ->with([
                            'assessment.user',
                            'invoice',
                        ])
                        ->firstOrFail();

                if (
                    $locked->status
                    === DiagnosisDetailedRoadmapOrder::STATUS_CANCELLED
                ) {
                    throw ValidationException::withMessages([
                        'order' => [
                            'La solicitud comercial está cancelada.',
                        ],
                    ]);
                }

                if ($locked->invoice) {
                    return $locked->invoice;
                }

                $assessment = $locked->assessment;
                $customer = $assessment?->user;

                if (
                    ! $assessment
                    || ! $customer
                ) {
                    throw ValidationException::withMessages([
                        'order' => [
                            'La solicitud no tiene diagnóstico y usuario completos.',
                        ],
                    ]);
                }

                [$subscriber, $company] =
                    $this->ensureBillingIdentity(
                        $customer,
                        $assessment
                    );

                $contact =
                    $locked->contact_request_id
                    ? ContactRequest::query()
                        ->find(
                            $locked->contact_request_id
                        )
                    : null;

                $invoice = Invoice::create([
                    'company_id' =>
                        $company->id,
                    'subscription_id' => null,
                    'number' =>
                        $this->invoiceNumber($locked),
                    'status' => 'issued',
                    'issued_on' =>
                        now()->toDateString(),
                    'due_on' => null,
                    'period_start' => null,
                    'period_end' => null,
                    'currency' =>
                        $locked->currency,
                    'subtotal' =>
                        $locked->base_subtotal,
                    'discount_total' =>
                        $locked->credit_amount,
                    'tax_total' =>
                        $locked->tax_amount,
                    'total' =>
                        $locked->total,
                    'amount_paid' => 0,
                    'billing_snapshot' => [
                        'source' =>
                            'lauda360_detailed_roadmap',
                        'one_time' => true,
                        'diagnosis_assessment_id' =>
                            $assessment->id,
                        'detailed_roadmap_order_id' =>
                            $locked->id,
                        'expanded_report_order_id' =>
                            $locked->expanded_report_order_id,
                        'offer' => [
                            'code' =>
                                data_get(
                                    $locked->meta,
                                    'offer_code'
                                ),
                            'name' =>
                                data_get(
                                    $locked->meta,
                                    'offer_name'
                                ),
                        ],
                        'customer' => [
                            'user_id' => $customer->id,
                            'name' => $customer->name,
                            'email' => $customer->email,
                            'organization_name' =>
                                $assessment->organization_name,
                            'contact_name' => $contact?->name,
                            'contact_email' => $contact?->email,
                            'contact_phone' => $contact?->phone,
                        ],
                        'commercial' => [
                            'currency' =>
                                $locked->currency,
                            'base_subtotal' =>
                                (string) $locked->base_subtotal,
                            'credit_eligible' =>
                                (bool) $locked->credit_eligible,
                            'credit_amount' =>
                                (string) $locked->credit_amount,
                            'net_subtotal' =>
                                (string) $locked->net_subtotal,
                            'tax_rate' =>
                                (string) $locked->tax_rate,
                            'tax_amount' =>
                                (string) $locked->tax_amount,
                            'total' =>
                                (string) $locked->total,
                            'credit_window_days' =>
                                $locked->credit_window_days,
                            'credit_source_paid_at' =>
                                $locked->credit_source_paid_at?->toISOString(),
                            'credit_expires_at' =>
                                $locked->credit_expires_at?->toISOString(),
                            'credit_reason' =>
                                data_get(
                                    $locked->meta,
                                    'credit_policy.reason'
                                ),
                        ],
                    ],
                    'document_class' => null,
                    'document_type' => null,
                    'fiscal_number' => null,
                    'security_code' => null,
                    'fiscal_meta' => [
                        'fiscal_document_pending' => true,
                        'source' =>
                            'lauda360_detailed_roadmap',
                    ],
                    'provider' => null,
                    'provider_invoice_id' => null,
                    'hosted_invoice_url' => null,
                    'payment_url' => null,
                ]);

                InvoiceItem::create([
                    'invoice_id' =>
                        $invoice->id,
                    'service_id' => null,
                    'description' =>
                        data_get(
                            $locked->meta,
                            'offer_name',
                            'Roadmap Detallado LAUDA 360'
                        ),
                    'quantity' => 1,
                    'unit_price' =>
                        $locked->base_subtotal,
                    'line_subtotal' =>
                        $locked->base_subtotal,
                    'discount_amount' =>
                        $locked->credit_amount,
                    'tax_rate' =>
                        $locked->tax_rate,
                    'tax_amount' =>
                        $locked->tax_amount,
                    'line_total' =>
                        $locked->total,
                    'meta' => [
                        'source' =>
                            'lauda360_detailed_roadmap',
                        'diagnosis_assessment_id' =>
                            $assessment->id,
                        'detailed_roadmap_order_id' =>
                            $locked->id,
                        'expanded_report_order_id' =>
                            $locked->expanded_report_order_id,
                        'credit_eligible' =>
                            (bool) $locked->credit_eligible,
                        'credit_amount' =>
                            (string) $locked->credit_amount,
                    ],
                ]);

                $invoice->refresh();

                if (
                    round((float) $invoice->subtotal, 2)
                        !== round((float) $locked->base_subtotal, 2)
                    || round((float) $invoice->discount_total, 2)
                        !== round((float) $locked->credit_amount, 2)
                    || round((float) $invoice->tax_total, 2)
                        !== round((float) $locked->tax_amount, 2)
                    || round((float) $invoice->total, 2)
                        !== round((float) $locked->total, 2)
                ) {
                    throw ValidationException::withMessages([
                        'invoice' => [
                            'Los totales de la factura no coinciden con el snapshot comercial del Roadmap.',
                        ],
                    ]);
                }

                $locked->forceFill([
                    'subscriber_id' =>
                        $subscriber->id,
                    'company_id' =>
                        $company->id,
                    'invoice_id' =>
                        $invoice->id,
                    'status' =>
                        DiagnosisDetailedRoadmapOrder::STATUS_INVOICED,
                    'invoiced_at' => now(),
                ])->save();

                AuditService::log(
                    'diagnosis_detailed_roadmap_invoiced',
                    $locked,
                    [
                        'assessment_id' => $assessment->id,
                        'subscriber_id' => $subscriber->id,
                        'company_id' => $company->id,
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->number,
                        'subscription_id' => null,
                        'credit_amount' =>
                            (string) $locked->credit_amount,
                        'actor_user_id' => $actor->id,
                    ],
                    ['user_id' => $actor->id]
                );

                return $invoice;
            }
        );
    }

    public function recordFullPayment(
        DiagnosisDetailedRoadmapOrder $order,
        User $actor,
        string $method,
        ?string $reference = null
    ): Payment {
        $method = strtolower(trim($method));

        if (! in_array(
            $method,
            [
                'bank_transfer',
                'cash',
                'check',
                'other',
            ],
            true
        )) {
            throw ValidationException::withMessages([
                'method' => [
                    'Método de pago manual no permitido.',
                ],
            ]);
        }

        $reference = filled($reference)
            ? trim((string) $reference)
            : null;

        if (
            in_array(
                $method,
                ['bank_transfer', 'check'],
                true
            )
            && $reference === null
        ) {
            throw ValidationException::withMessages([
                'reference' => [
                    'La referencia es obligatoria para transferencia o cheque.',
                ],
            ]);
        }

        return DB::transaction(
            function () use (
                $order,
                $actor,
                $method,
                $reference
            ): Payment {
                $locked =
                    DiagnosisDetailedRoadmapOrder::query()
                        ->whereKey($order->id)
                        ->lockForUpdate()
                        ->with('invoice')
                        ->firstOrFail();

                $invoice = $locked->invoice;

                if (! $invoice) {
                    throw ValidationException::withMessages([
                        'invoice' => [
                            'Debe preparar la factura antes de registrar el pago.',
                        ],
                    ]);
                }

                if ($invoice->status === 'void') {
                    throw ValidationException::withMessages([
                        'invoice' => [
                            'No se puede pagar una factura anulada.',
                        ],
                    ]);
                }

                $outstanding = round(
                    max(
                        0,
                        (float) $invoice->total
                            - (float) $invoice->amount_paid
                    ),
                    2
                );

                if ($outstanding <= 0) {
                    if ($invoice->status === 'paid') {
                        $locked->forceFill([
                            'status' =>
                                DiagnosisDetailedRoadmapOrder::STATUS_PAID,
                            'paid_at' =>
                                $locked->paid_at ?? now(),
                        ])->save();

                        $existing =
                            Payment::query()
                                ->where(
                                    'invoice_id',
                                    $invoice->id
                                )
                                ->whereNotNull('paid_at')
                                ->latest('id')
                                ->first();

                        if ($existing) {
                            return $existing;
                        }
                    }

                    throw ValidationException::withMessages([
                        'invoice' => [
                            'La factura no tiene balance pendiente.',
                        ],
                    ]);
                }

                $payment = Payment::create([
                    'company_id' =>
                        $invoice->company_id,
                    'invoice_id' =>
                        $invoice->id,
                    'method' =>
                        $method,
                    'currency' =>
                        $invoice->currency,
                    'amount' =>
                        $outstanding,
                    'paid_at' => now(),
                    'reference' => $reference,
                    'meta' => [
                        'source' =>
                            'lauda360_detailed_roadmap',
                        'detailed_roadmap_order_id' =>
                            $locked->id,
                        'diagnosis_assessment_id' =>
                            $locked->diagnosis_assessment_id,
                        'recorded_by_user_id' =>
                            $actor->id,
                    ],
                ]);

                $invoice->refresh();

                if ($invoice->status !== 'paid') {
                    $paid = round(
                        (float) Payment::query()
                            ->where(
                                'invoice_id',
                                $invoice->id
                            )
                            ->whereNotNull('paid_at')
                            ->sum('amount'),
                        2
                    );

                    if (
                        $paid + 0.005
                        >= (float) $invoice->total
                    ) {
                        $invoice->forceFill([
                            'amount_paid' => $paid,
                            'status' => 'paid',
                        ])->saveQuietly();
                    }
                }

                $invoice->refresh();

                if ($invoice->status !== 'paid') {
                    throw ValidationException::withMessages([
                        'payment' => [
                            'La factura no pudo sincronizarse como pagada.',
                        ],
                    ]);
                }

                PaymentTransaction::create([
                    'user_id' =>
                        $locked->user_id,
                    'company_id' =>
                        $invoice->company_id,
                    'subscription_id' => null,
                    'payable_type' =>
                        Invoice::class,
                    'payable_id' =>
                        $invoice->id,
                    'provider' => 'manual',
                    'provider_mode' => 'live',
                    'provider_transaction_id' =>
                        $reference
                        ?: 'manual-payment-' . $payment->id,
                    'provider_payment_intent_id' => null,
                    'provider_checkout_session_id' => null,
                    'provider_customer_id' => null,
                    'status' => 'succeeded',
                    'payment_method' => $method,
                    'payment_method_brand' => null,
                    'payment_method_last4' => null,
                    'amount' => $payment->amount,
                    'tax_amount' => $invoice->tax_total,
                    'discount_amount' =>
                        $invoice->discount_total,
                    'fee_amount' => null,
                    'net_amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'exchange_rate' => null,
                    'amount_local' => null,
                    'local_currency' => null,
                    'idempotency_key' => sprintf(
                        'lauda360-detailed-roadmap-order-%d-payment-%d',
                        $locked->id,
                        $payment->id
                    ),
                    'metadata' => [
                        'source' =>
                            'lauda360_detailed_roadmap',
                        'payment_id' => $payment->id,
                        'invoice_id' => $invoice->id,
                        'detailed_roadmap_order_id' =>
                            $locked->id,
                        'diagnosis_assessment_id' =>
                            $locked->diagnosis_assessment_id,
                        'recorded_by_user_id' =>
                            $actor->id,
                    ],
                    'authorized_at' => $payment->paid_at,
                    'captured_at' => $payment->paid_at,
                    'paid_at' => $payment->paid_at,
                    'refunded_at' => null,
                    'refunded_amount' => null,
                    'failure_code' => null,
                    'failure_message' => null,
                ]);

                $locked->forceFill([
                    'status' =>
                        DiagnosisDetailedRoadmapOrder::STATUS_PAID,
                    'paid_at' =>
                        $payment->paid_at,
                ])->save();

                AuditService::log(
                    'diagnosis_detailed_roadmap_paid',
                    $locked,
                    [
                        'assessment_id' =>
                            $locked->diagnosis_assessment_id,
                        'invoice_id' =>
                            $invoice->id,
                        'payment_id' =>
                            $payment->id,
                        'amount' =>
                            (string) $payment->amount,
                        'currency' =>
                            $payment->currency,
                        'method' =>
                            $payment->method,
                        'credit_amount' =>
                            (string) $locked->credit_amount,
                        'actor_user_id' =>
                            $actor->id,
                    ],
                    ['user_id' => $actor->id]
                );

                return $payment->fresh();
            }
        );
    }

    public function hasPaidAccess(
        DiagnosisAssessment $assessment
    ): bool {
        $order =
            DiagnosisDetailedRoadmapOrder::query()
                ->where(
                    'diagnosis_assessment_id',
                    $assessment->id
                )
                ->with('invoice:id,status')
                ->first();

        if (
            ! $order
            || ! $order->isPaid()
            || ! $order->invoice
        ) {
            return false;
        }

        return $order->invoice->status === 'paid';
    }

    public function state(
        DiagnosisAssessment $assessment
    ): ?array {
        $order =
            DiagnosisDetailedRoadmapOrder::query()
                ->where(
                    'diagnosis_assessment_id',
                    $assessment->id
                )
                ->with([
                    'invoice:id,number,status,currency,subtotal,discount_total,tax_total,total,amount_paid,issued_on,due_on',
                ])
                ->first();

        if (! $order) {
            return null;
        }

        $invoice = $order->invoice;

        return [
            'id' => $order->id,
            'status' => $order->status,
            'currency' => $order->currency,
            'base_subtotal' =>
                (string) $order->base_subtotal,
            'credit_eligible' =>
                (bool) $order->credit_eligible,
            'credit_amount' =>
                (string) $order->credit_amount,
            'net_subtotal' =>
                (string) $order->net_subtotal,
            'tax_rate' =>
                (string) $order->tax_rate,
            'tax_amount' =>
                (string) $order->tax_amount,
            'total' =>
                (string) $order->total,
            'credit_window_days' =>
                $order->credit_window_days,
            'credit_source_paid_at' =>
                $order->credit_source_paid_at?->toISOString(),
            'credit_expires_at' =>
                $order->credit_expires_at?->toISOString(),
            'credit_reason' =>
                data_get(
                    $order->meta,
                    'credit_policy.reason'
                ),
            'requested_at' =>
                $order->requested_at?->toISOString(),
            'invoiced_at' =>
                $order->invoiced_at?->toISOString(),
            'paid_at' =>
                $order->paid_at?->toISOString(),
            'paid_access' =>
                $order->isPaid()
                && $invoice?->status === 'paid',
            'invoice' => $invoice
                ? [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'status' => $invoice->status,
                    'currency' => $invoice->currency,
                    'subtotal' =>
                        (string) $invoice->subtotal,
                    'discount_total' =>
                        (string) $invoice->discount_total,
                    'tax_total' =>
                        (string) $invoice->tax_total,
                    'total' =>
                        (string) $invoice->total,
                    'amount_paid' =>
                        (string) $invoice->amount_paid,
                    'issued_on' =>
                        $invoice->issued_on?->toDateString(),
                    'due_on' =>
                        $invoice->due_on?->toDateString(),
                ]
                : null,
        ];
    }

    private function creditDecision(
        ?DiagnosisExpandedReportOrder $order,
        CarbonInterface $requestedAt,
        int $windowDays
    ): array {
        if (! $order) {
            return [
                'eligible' => false,
                'reason' =>
                    'no_expanded_report_order',
                'paid_at' => null,
                'expires_at' => null,
            ];
        }

        $invoice = $order->invoice;

        if (
            ! $order->isPaid()
            || ! $invoice
            || $invoice->status !== 'paid'
            || ! $order->paid_at
        ) {
            return [
                'eligible' => false,
                'reason' =>
                    'expanded_report_not_paid',
                'paid_at' => $order->paid_at,
                'expires_at' =>
                    $order->paid_at
                        ?->copy()
                        ->addDays($windowDays),
            ];
        }

        $paidAt = $order->paid_at;
        $expiresAt =
            $paidAt->copy()->addDays($windowDays);

        if ($requestedAt->lt($paidAt)) {
            return [
                'eligible' => false,
                'reason' =>
                    'payment_date_invalid',
                'paid_at' => $paidAt,
                'expires_at' => $expiresAt,
            ];
        }

        if ($requestedAt->gt($expiresAt)) {
            return [
                'eligible' => false,
                'reason' =>
                    'credit_window_expired',
                'paid_at' => $paidAt,
                'expires_at' => $expiresAt,
            ];
        }

        return [
            'eligible' => true,
            'reason' =>
                'expanded_report_paid_within_window',
            'paid_at' => $paidAt,
            'expires_at' => $expiresAt,
        ];
    }

    private function assertAssessmentCanContinue(
        DiagnosisAssessment $assessment,
        User $user
    ): void {
        if (
            (int) $assessment->user_id
            !== (int) $user->id
        ) {
            throw ValidationException::withMessages([
                'assessment' => [
                    'Este diagnóstico no pertenece al usuario actual.',
                ],
            ]);
        }

        if (
            $assessment->status !== 'reviewed'
            || $assessment->published_at === null
        ) {
            throw ValidationException::withMessages([
                'assessment' => [
                    'Debe existir un resultado oficial publicado antes de solicitar el Roadmap Detallado.',
                ],
            ]);
        }

        if (
            ! DiagnosisExpandedReport::query()
                ->where(
                    'diagnosis_assessment_id',
                    $assessment->id
                )
                ->where(
                    'status',
                    DiagnosisExpandedReport::STATUS_PUBLISHED
                )
                ->whereNotNull('published_at')
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'expanded_report' => [
                    'Debe existir un Informe Ampliado publicado antes de solicitar el Roadmap Detallado.',
                ],
            ]);
        }
    }

    private function ensureBillingIdentity(
        User $user,
        DiagnosisAssessment $assessment
    ): array {
        $company =
            Company::query()
                ->where(
                    'owner_user_id',
                    $user->id
                )
                ->first();

        if ($company && $company->subscriber_id) {
            $subscriber =
                Subscriber::query()
                    ->find(
                        $company->subscriber_id
                    );

            if ($subscriber) {
                $this->ensurePivot(
                    $subscriber,
                    $user
                );

                return [
                    $subscriber,
                    $company,
                ];
            }
        }

        $subscriberId = (int)
            DB::table('subscriber_user')
                ->where(
                    'user_id',
                    $user->id
                )
                ->where('active', 1)
                ->orderByDesc('id')
                ->value('subscriber_id');

        $subscriber =
            $subscriberId > 0
            ? Subscriber::query()
                ->find($subscriberId)
            : null;

        if (! $subscriber) {
            $subscriber =
                Subscriber::create([
                    'name' =>
                        $this->organizationName(
                            $assessment
                        ),
                    'slug' =>
                        $this->uniqueSlug(
                            'subscribers',
                            $this->organizationName(
                                $assessment
                            )
                        ),
                    'country_code' => 'DO',
                    'currency' => 'DOP',
                    'timezone' =>
                        'America/Santo_Domingo',
                    'provider' => null,
                    'provider_mode' => 'live',
                    'provider_customer_id' => null,
                    'active' => true,
                    'meta' => [
                        'source' =>
                            'lauda360_detailed_roadmap',
                        'created_from_diagnosis_assessment_id' =>
                            $assessment->id,
                    ],
                ]);
        }

        $this->ensurePivot(
            $subscriber,
            $user
        );

        if ($company) {
            if (
                $company->subscriber_id
                && (int) $company->subscriber_id
                    !== (int) $subscriber->id
            ) {
                throw ValidationException::withMessages([
                    'company' => [
                        'La empresa existente pertenece a otro subscriber.',
                    ],
                ]);
            }

            if (! $company->subscriber_id) {
                $company->forceFill([
                    'subscriber_id' =>
                        $subscriber->id,
                ])->save();
            }

            return [
                $subscriber,
                $company,
            ];
        }

        $company =
            Company::query()
                ->where(
                    'subscriber_id',
                    $subscriber->id
                )
                ->first();

        if ($company) {
            if (! $company->owner_user_id) {
                $company->forceFill([
                    'owner_user_id' =>
                        $user->id,
                ])->save();
            }

            return [
                $subscriber,
                $company,
            ];
        }

        $company = Company::create([
            'name' =>
                $this->organizationName(
                    $assessment
                ),
            'slug' =>
                $this->uniqueSlug(
                    'companies',
                    $this->organizationName(
                        $assessment
                    )
                ),
            'currency' => 'DOP',
            'timezone' =>
                'America/Santo_Domingo',
            'owner_user_id' => $user->id,
            'subscriber_id' => $subscriber->id,
            'active' => true,
        ]);

        return [
            $subscriber,
            $company,
        ];
    }

    private function ensurePivot(
        Subscriber $subscriber,
        User $user
    ): void {
        DB::table(
            'subscriber_user'
        )->insertOrIgnore([
            'subscriber_id' =>
                $subscriber->id,
            'user_id' =>
                $user->id,
            'role' => 'owner',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(
            'subscriber_user'
        )
            ->where(
                'subscriber_id',
                $subscriber->id
            )
            ->where(
                'user_id',
                $user->id
            )
            ->update([
                'role' => 'owner',
                'active' => 1,
                'updated_at' => now(),
            ]);
    }

    private function invoiceNumber(
        DiagnosisDetailedRoadmapOrder $order
    ): string {
        return sprintf(
            'L360-RD-%06d',
            $order->id
        );
    }

    private function organizationName(
        DiagnosisAssessment $assessment
    ): string {
        $name = trim(
            (string) $assessment->organization_name
        );

        return $name !== ''
            ? $name
            : 'Cliente LAUDA 360';
    }

    private function uniqueSlug(
        string $table,
        string $name
    ): string {
        $base =
            Str::slug($name)
            ?: 'cliente-lauda360';

        $slug = $base;
        $suffix = 2;

        while (
            DB::table($table)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug =
                $base . '-' . $suffix;

            $suffix++;
        }

        return $slug;
    }
}
