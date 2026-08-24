<?php

namespace App\Services\Diagnosis;

use App\Models\Company;
use App\Models\ContactRequest;
use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisExpandedReportOrder;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DiagnosisExpandedReportCommercialService
{
    public function __construct(
        private readonly DiagnosisExpandedReportService $reportService
    ) {
    }

    public function requestPurchase(
        DiagnosisAssessment $assessment,
        User $user
    ): DiagnosisExpandedReportOrder {
        $this->assertAssessmentCanContinue($assessment, $user);

        return DB::transaction(function () use ($assessment, $user) {
            $existing = DiagnosisExpandedReportOrder::query()
                ->where('diagnosis_assessment_id', $assessment->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $workflow = DiagnosisAccessRequest::query()
                ->where('diagnosis_assessment_id', $assessment->id)
                ->first();

            $price = $this->reportService->commercialSnapshot();

            $order = DiagnosisExpandedReportOrder::create([
                'diagnosis_assessment_id' => $assessment->id,
                'user_id' => $user->id,
                'contact_request_id' => $workflow?->contact_request_id,
                'status' => DiagnosisExpandedReportOrder::STATUS_REQUESTED,
                'currency' => $price['currency'],
                'subtotal' => $price['subtotal'],
                'tax_rate' => $price['tax_rate'],
                'tax_amount' => $price['tax_amount'],
                'total' => $price['total'],
                'requested_at' => now(),
                'meta' => [
                    'offer_code' => config(
                        'lauda360_commercial.expanded_report.code',
                        'lauda360_expanded_report'
                    ),
                    'offer_name' => config(
                        'lauda360_commercial.expanded_report.name',
                        'Informe Ampliado LAUDA 360'
                    ),
                    'source' => 'diagnosis_lauda360',
                ],
            ]);

            AuditService::log(
                'diagnosis_expanded_report_requested',
                $order,
                [
                    'assessment_id' => $assessment->id,
                    'user_id' => $user->id,
                    'total' => (string) $order->total,
                    'currency' => $order->currency,
                ],
                ['user_id' => $user->id]
            );

            return $order->fresh();
        });
    }

    public function prepareInvoice(
        DiagnosisExpandedReportOrder $order,
        User $actor
    ): Invoice {
        return DB::transaction(function () use ($order, $actor) {
            $locked = DiagnosisExpandedReportOrder::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->with(['assessment.user', 'invoice'])
                ->firstOrFail();

            if ($locked->status === DiagnosisExpandedReportOrder::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'order' => ['La solicitud comercial está cancelada.'],
                ]);
            }

            if ($locked->invoice) {
                return $locked->invoice;
            }

            $assessment = $locked->assessment;
            $customer = $assessment?->user;

            if (! $assessment || ! $customer) {
                throw ValidationException::withMessages([
                    'order' => [
                        'La solicitud no tiene diagnóstico y usuario completos.',
                    ],
                ]);
            }

            [$subscriber, $company] = $this->ensureBillingIdentity(
                $customer,
                $assessment
            );

            $contact = $locked->contact_request_id
                ? ContactRequest::query()->find($locked->contact_request_id)
                : null;

            $invoice = Invoice::create([
                'company_id' => $company->id,
                'subscription_id' => null,
                'number' => $this->invoiceNumber($locked),
                'status' => 'issued',
                'issued_on' => now()->toDateString(),
                'due_on' => null,
                'period_start' => null,
                'period_end' => null,
                'currency' => $locked->currency,
                'subtotal' => $locked->subtotal,
                'discount_total' => 0,
                'tax_total' => $locked->tax_amount,
                'total' => $locked->total,
                'amount_paid' => 0,
                'billing_snapshot' => [
                    'source' => 'lauda360_expanded_report',
                    'one_time' => true,
                    'diagnosis_assessment_id' => $assessment->id,
                    'expanded_report_order_id' => $locked->id,
                    'offer' => [
                        'code' => data_get($locked->meta, 'offer_code'),
                        'name' => data_get($locked->meta, 'offer_name'),
                    ],
                    'customer' => [
                        'user_id' => $customer->id,
                        'name' => $customer->name,
                        'email' => $customer->email,
                        'organization_name' => $assessment->organization_name,
                        'contact_name' => $contact?->name,
                        'contact_email' => $contact?->email,
                        'contact_phone' => $contact?->phone,
                    ],
                    'commercial' => [
                        'currency' => $locked->currency,
                        'subtotal' => (string) $locked->subtotal,
                        'tax_rate' => (string) $locked->tax_rate,
                        'tax_amount' => (string) $locked->tax_amount,
                        'total' => (string) $locked->total,
                    ],
                ],
                'document_class' => null,
                'document_type' => null,
                'fiscal_number' => null,
                'security_code' => null,
                'fiscal_meta' => [
                    'fiscal_document_pending' => true,
                    'source' => 'lauda360_expanded_report',
                ],
                'provider' => null,
                'provider_invoice_id' => null,
                'hosted_invoice_url' => null,
                'payment_url' => null,
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'service_id' => null,
                'description' => data_get(
                    $locked->meta,
                    'offer_name',
                    'Informe Ampliado LAUDA 360'
                ),
                'quantity' => 1,
                'unit_price' => $locked->subtotal,
                'line_subtotal' => $locked->subtotal,
                'discount_amount' => 0,
                'tax_rate' => $locked->tax_rate,
                'tax_amount' => $locked->tax_amount,
                'line_total' => $locked->total,
                'meta' => [
                    'source' => 'lauda360_expanded_report',
                    'diagnosis_assessment_id' => $assessment->id,
                    'expanded_report_order_id' => $locked->id,
                ],
            ]);

            $invoice->refresh();

            $locked->forceFill([
                'subscriber_id' => $subscriber->id,
                'company_id' => $company->id,
                'invoice_id' => $invoice->id,
                'status' => DiagnosisExpandedReportOrder::STATUS_INVOICED,
                'invoiced_at' => now(),
            ])->save();

            AuditService::log(
                'diagnosis_expanded_report_invoiced',
                $locked,
                [
                    'assessment_id' => $assessment->id,
                    'subscriber_id' => $subscriber->id,
                    'company_id' => $company->id,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->number,
                    'subscription_id' => null,
                    'actor_user_id' => $actor->id,
                ],
                ['user_id' => $actor->id]
            );

            return $invoice;
        });
    }

    public function recordFullPayment(
        DiagnosisExpandedReportOrder $order,
        User $actor,
        string $method,
        ?string $reference = null
    ): Payment {
        $method = strtolower(trim($method));

        if (! in_array($method, [
            'bank_transfer',
            'cash',
            'check',
            'other',
        ], true)) {
            throw ValidationException::withMessages([
                'method' => ['Método de pago manual no permitido.'],
            ]);
        }

        $reference = filled($reference)
            ? trim((string) $reference)
            : null;

        if (
            in_array($method, ['bank_transfer', 'check'], true)
            && $reference === null
        ) {
            throw ValidationException::withMessages([
                'reference' => [
                    'La referencia es obligatoria para transferencia o cheque.',
                ],
            ]);
        }

        return DB::transaction(function () use (
            $order,
            $actor,
            $method,
            $reference
        ) {
            $locked = DiagnosisExpandedReportOrder::query()
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
                    'invoice' => ['No se puede pagar una factura anulada.'],
                ]);
            }

            $outstanding = round(
                max(
                    0,
                    (float) $invoice->total - (float) $invoice->amount_paid
                ),
                2
            );

            if ($outstanding <= 0) {
                if ($invoice->status === 'paid') {
                    $locked->forceFill([
                        'status' => DiagnosisExpandedReportOrder::STATUS_PAID,
                        'paid_at' => $locked->paid_at ?? now(),
                    ])->save();

                    $existing = Payment::query()
                        ->where('invoice_id', $invoice->id)
                        ->whereNotNull('paid_at')
                        ->latest('id')
                        ->first();

                    if ($existing) {
                        return $existing;
                    }
                }

                throw ValidationException::withMessages([
                    'invoice' => ['La factura no tiene balance pendiente.'],
                ]);
            }

            $payment = Payment::create([
                'company_id' => $invoice->company_id,
                'invoice_id' => $invoice->id,
                'method' => $method,
                'currency' => $invoice->currency,
                'amount' => $outstanding,
                'paid_at' => now(),
                'reference' => $reference,
                'meta' => [
                    'source' => 'lauda360_expanded_report',
                    'expanded_report_order_id' => $locked->id,
                    'diagnosis_assessment_id' => $locked->diagnosis_assessment_id,
                    'recorded_by_user_id' => $actor->id,
                ],
            ]);

            $invoice->refresh();

            if ($invoice->status !== 'paid') {
                $paid = round(
                    (float) Payment::query()
                        ->where('invoice_id', $invoice->id)
                        ->whereNotNull('paid_at')
                        ->sum('amount'),
                    2
                );

                if ($paid + 0.005 >= (float) $invoice->total) {
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
                'user_id' => $locked->user_id,
                'company_id' => $invoice->company_id,
                'subscription_id' => null,
                'payable_type' => Invoice::class,
                'payable_id' => $invoice->id,
                'provider' => 'manual',
                'provider_mode' => 'live',
                'provider_transaction_id' => $reference
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
                'discount_amount' => $invoice->discount_total,
                'fee_amount' => null,
                'net_amount' => $payment->amount,
                'currency' => $payment->currency,
                'exchange_rate' => null,
                'amount_local' => null,
                'local_currency' => null,
                'idempotency_key' => sprintf(
                    'lauda360-expanded-report-order-%d-payment-%d',
                    $locked->id,
                    $payment->id
                ),
                'metadata' => [
                    'source' => 'lauda360_expanded_report',
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'expanded_report_order_id' => $locked->id,
                    'diagnosis_assessment_id' => $locked->diagnosis_assessment_id,
                    'recorded_by_user_id' => $actor->id,
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
                'status' => DiagnosisExpandedReportOrder::STATUS_PAID,
                'paid_at' => $payment->paid_at,
            ])->save();

            AuditService::log(
                'diagnosis_expanded_report_paid',
                $locked,
                [
                    'assessment_id' => $locked->diagnosis_assessment_id,
                    'invoice_id' => $invoice->id,
                    'payment_id' => $payment->id,
                    'amount' => (string) $payment->amount,
                    'currency' => $payment->currency,
                    'method' => $payment->method,
                    'actor_user_id' => $actor->id,
                ],
                ['user_id' => $actor->id]
            );

            return $payment->fresh();
        });
    }

    public function hasPaidAccess(
        DiagnosisAssessment $assessment
    ): bool {
        $order = DiagnosisExpandedReportOrder::query()
            ->where('diagnosis_assessment_id', $assessment->id)
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
        $order = DiagnosisExpandedReportOrder::query()
            ->where('diagnosis_assessment_id', $assessment->id)
            ->with([
                'invoice:id,number,status,currency,total,amount_paid,issued_on,due_on',
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
            'subtotal' => (string) $order->subtotal,
            'tax_rate' => (string) $order->tax_rate,
            'tax_amount' => (string) $order->tax_amount,
            'total' => (string) $order->total,
            'requested_at' => $order->requested_at?->toISOString(),
            'invoiced_at' => $order->invoiced_at?->toISOString(),
            'paid_at' => $order->paid_at?->toISOString(),
            'paid_access' =>
                $order->isPaid()
                && $invoice?->status === 'paid',
            'invoice' => $invoice
                ? [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'status' => $invoice->status,
                    'currency' => $invoice->currency,
                    'total' => (string) $invoice->total,
                    'amount_paid' => (string) $invoice->amount_paid,
                    'issued_on' => $invoice->issued_on?->toDateString(),
                    'due_on' => $invoice->due_on?->toDateString(),
                ]
                : null,
        ];
    }

    private function assertAssessmentCanContinue(
        DiagnosisAssessment $assessment,
        User $user
    ): void {
        if ((int) $assessment->user_id !== (int) $user->id) {
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
                    'Debe existir un resultado oficial publicado antes de solicitar el Informe Ampliado.',
                ],
            ]);
        }
    }

    /**
     * @return array{0:Subscriber,1:Company}
     */
    private function ensureBillingIdentity(
        User $user,
        DiagnosisAssessment $assessment
    ): array {
        $company = Company::query()
            ->where('owner_user_id', $user->id)
            ->first();

        if ($company && $company->subscriber_id) {
            $subscriber = Subscriber::query()
                ->find($company->subscriber_id);

            if ($subscriber) {
                $this->ensurePivot($subscriber, $user);

                return [$subscriber, $company];
            }
        }

        $subscriberId = (int) DB::table('subscriber_user')
            ->where('user_id', $user->id)
            ->where('active', 1)
            ->orderByDesc('id')
            ->value('subscriber_id');

        $subscriber = $subscriberId > 0
            ? Subscriber::query()->find($subscriberId)
            : null;

        if (! $subscriber) {
            $subscriber = Subscriber::create([
                'name' => $this->organizationName($assessment),
                'slug' => $this->uniqueSlug(
                    'subscribers',
                    $this->organizationName($assessment)
                ),
                'country_code' => 'DO',
                'currency' => 'DOP',
                'timezone' => 'America/Santo_Domingo',
                'provider' => null,
                'provider_mode' => 'live',
                'provider_customer_id' => null,
                'active' => true,
                'meta' => [
                    'source' => 'lauda360_expanded_report',
                    'created_from_diagnosis_assessment_id' => $assessment->id,
                ],
            ]);
        }

        $this->ensurePivot($subscriber, $user);

        if ($company) {
            if (
                $company->subscriber_id
                && (int) $company->subscriber_id !== (int) $subscriber->id
            ) {
                throw ValidationException::withMessages([
                    'company' => [
                        'La empresa existente pertenece a otro subscriber.',
                    ],
                ]);
            }

            if (! $company->subscriber_id) {
                $company->forceFill([
                    'subscriber_id' => $subscriber->id,
                ])->save();
            }

            return [$subscriber, $company];
        }

        $company = Company::query()
            ->where('subscriber_id', $subscriber->id)
            ->first();

        if ($company) {
            if (! $company->owner_user_id) {
                $company->forceFill([
                    'owner_user_id' => $user->id,
                ])->save();
            }

            return [$subscriber, $company];
        }

        $company = Company::create([
            'name' => $this->organizationName($assessment),
            'slug' => $this->uniqueSlug(
                'companies',
                $this->organizationName($assessment)
            ),
            'currency' => 'DOP',
            'timezone' => 'America/Santo_Domingo',
            'owner_user_id' => $user->id,
            'subscriber_id' => $subscriber->id,
            'active' => true,
        ]);

        return [$subscriber, $company];
    }

    private function ensurePivot(
        Subscriber $subscriber,
        User $user
    ): void {
        DB::table('subscriber_user')->insertOrIgnore([
            'subscriber_id' => $subscriber->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('subscriber_user')
            ->where('subscriber_id', $subscriber->id)
            ->where('user_id', $user->id)
            ->update([
                'role' => 'owner',
                'active' => 1,
                'updated_at' => now(),
            ]);
    }

    private function invoiceNumber(
        DiagnosisExpandedReportOrder $order
    ): string {
        return sprintf(
            'L360-IA-%06d',
            $order->id
        );
    }

    private function organizationName(
        DiagnosisAssessment $assessment
    ): string {
        $name = trim((string) $assessment->organization_name);

        return $name !== ''
            ? $name
            : 'Cliente LAUDA 360';
    }

    private function uniqueSlug(
        string $table,
        string $name
    ): string {
        $base = Str::slug($name) ?: 'cliente-lauda360';
        $slug = $base;
        $suffix = 2;

        while (
            DB::table($table)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }
}
