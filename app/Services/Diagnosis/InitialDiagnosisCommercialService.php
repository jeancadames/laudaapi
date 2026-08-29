<?php

namespace App\Services\Diagnosis;

use App\Models\Company;
use App\Models\CompanyTaxProfile;
use App\Models\ContactRequest;
use App\Models\DiagnosisAccessRequest;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InitialDiagnosisCommercialService
{
    public const SOURCE = 'lauda360_initial_diagnosis';
    public const INTENT = 'diagnosis_360';

    public function offer(): array
    {
        $offer = config('lauda360_commercial.initial_diagnosis', []);

        $currency = strtoupper(trim((string) ($offer['currency'] ?? 'DOP')));
        $subtotal = round((float) ($offer['subtotal'] ?? 0), 2);
        $taxRate = round((float) ($offer['tax_rate'] ?? 0), 3);
        $taxAmount = round($subtotal * ($taxRate / 100), 2);
        $total = round($subtotal + $taxAmount, 2);

        if ($subtotal !== 0.0 || $taxAmount !== 0.0 || $total !== 0.0) {
            throw ValidationException::withMessages([
                'diagnosis' => ['El Diagnóstico Inicial debe permanecer en DOP 0.00.'],
            ]);
        }

        return [
            'code' => (string) ($offer['code'] ?? 'lauda360_initial_diagnosis'),
            'name' => (string) ($offer['name'] ?? 'Diagnóstico Inicial LAUDA 360'),
            'invoice_description' => (string) (
                $offer['invoice_description']
                ?? 'Diagnóstico Inicial LAUDA 360 · Evaluación inicial sin costo (cortesía LAUDAAPI)'
            ),
            'currency' => $currency,
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'complimentary' => true,
            'manual_confirmation_required' => true,
        ];
    }

    /** @return array{0: Subscriber, 1: Company, 2: string} */
    public function billingContext(User $user): array
    {
        $subscriber = $user->activeSubscribers()
            ->wherePivotIn('role', ['owner', 'admin'])
            ->orderBy('subscribers.id')
            ->first();

        if (! $subscriber) {
            throw ValidationException::withMessages([
                'tenant' => ['Solo owner/admin puede solicitar el Diagnóstico 360.'],
            ]);
        }

        $company = Company::query()
            ->where('subscriber_id', $subscriber->id)
            ->where('active', true)
            ->orderBy('id')
            ->first();

        if (! $company) {
            throw ValidationException::withMessages([
                'company' => ['Completa primero el Perfil de Empresa.'],
            ]);
        }

        $role = $subscriber->users()
            ->whereKey($user->id)
            ->first()
            ?->pivot
            ?->role;

        if (! in_array($role, ['owner', 'admin'], true)) {
            throw ValidationException::withMessages([
                'tenant' => ['Solo owner/admin puede solicitar el Diagnóstico 360.'],
            ]);
        }

        return [$subscriber, $company, (string) $role];
    }

    public function ensure(User $user): DiagnosisAccessRequest
    {
        [$subscriber, $company] = $this->billingContext($user);
        $offer = $this->offer();

        return DB::transaction(function () use ($user, $subscriber, $company, $offer) {
            $company = Company::query()
                ->whereKey($company->id)
                ->lockForUpdate()
                ->firstOrFail();

            $historical = DiagnosisAccessRequest::query()
                ->where('user_id', $user->id)
                ->whereNotNull('diagnosis_assessment_id')
                ->latest('id')
                ->first();

            if (
                $historical
                && data_get($historical->meta, 'source') !== self::SOURCE
            ) {
                return $historical;
            }

            $workflow = DiagnosisAccessRequest::query()
                ->where('user_id', $user->id)
                ->where('meta->source', self::SOURCE)
                ->latest('id')
                ->first();

            if (! $workflow) {
                $profile = CompanyTaxProfile::query()
                    ->where('company_id', $company->id)
                    ->first();

                $contact = ContactRequest::query()->create([
                    'name' => $user->name,
                    'email' => strtolower(trim((string) $user->email)),
                    'phone' => $profile?->billing_phone,
                    'company' => $company->name,
                    'topic' => 'Solicitud de acceso al Diagnóstico LAUDA 360',
                    'message' => 'Solicitud iniciada desde app.laudaapi.com.',
                    'terms' => true,
                    'metadata' => [
                        'request_type' => 'digital_diagnosis_access_request',
                        'source' => 'apphub_native',
                        'apphub_user_id' => $user->id,
                        'subscriber_id' => $subscriber->id,
                        'company_id' => $company->id,
                        'complimentary' => true,
                    ],
                ]);

                $workflow = DiagnosisAccessRequest::query()->create([
                    'contact_request_id' => $contact->id,
                    'user_id' => $user->id,
                    'status' => DiagnosisAccessRequest::STATUS_PENDING,
                    'meta' => [
                        'source' => self::SOURCE,
                        'apphub_native' => true,
                        'subscriber_id' => $subscriber->id,
                        'company_id' => $company->id,
                        'requested_at' => now()->toIso8601String(),
                    ],
                ]);

                AuditService::log(
                    'diagnosis_initial_apphub_requested',
                    $workflow,
                    [
                        'user_id' => $user->id,
                        'subscriber_id' => $subscriber->id,
                        'company_id' => $company->id,
                    ],
                    ['user_id' => $user->id]
                );
            }

            /*
             * S10-F4.12-D:
             * si el workflow nació en Welcome antes de existir el tenant,
             * conservar ese ContactRequest y enriquecer su metadata.
             * No crear una segunda solicitud.
             */
            if ($workflow->contact_request_id) {
                $contact = ContactRequest::query()
                    ->whereKey($workflow->contact_request_id)
                    ->first();

                if ($contact) {
                    $contactMeta = is_array($contact->metadata)
                        ? $contact->metadata
                        : [];

                    $contact->forceFill([
                        'metadata' => array_merge(
                            $contactMeta,
                            [
                                'request_type' =>
                                    'digital_diagnosis_access_request',
                                'apphub_user_id' => $user->id,
                                'subscriber_id' => $subscriber->id,
                                'company_id' => $company->id,
                                'complimentary' => true,
                            ]
                        ),
                    ])->save();
                }
            }

            $meta = is_array($workflow->meta) ? $workflow->meta : [];
            $invoiceId = (int) data_get($meta, 'initial_diagnosis.invoice_id', 0);

            $invoice = $invoiceId > 0
                ? Invoice::query()
                    ->whereKey($invoiceId)
                    ->where('company_id', $company->id)
                    ->first()
                : null;

            if (! $invoice) {
                $invoice = Invoice::query()
                    ->where('company_id', $company->id)
                    ->where('billing_snapshot->source', self::SOURCE)
                    ->where(
                        'billing_snapshot->diagnosis_access_request_id',
                        $workflow->id
                    )
                    ->first();
            }

            if (! $invoice) {
                $invoice = Invoice::query()->create([
                    'company_id' => $company->id,
                    'subscription_id' => null,
                    'number' => sprintf('L360-DI-%06d', $workflow->id),
                    'status' => 'issued',
                    'issued_on' => now()->toDateString(),
                    'due_on' => null,
                    'period_start' => null,
                    'period_end' => null,
                    'currency' => $offer['currency'],
                    'subtotal' => 0,
                    'discount_total' => 0,
                    'tax_total' => 0,
                    'total' => 0,
                    'amount_paid' => 0,
                    'billing_snapshot' => [
                        'source' => self::SOURCE,
                        'one_time' => true,
                        'complimentary' => true,
                        'manual_confirmation_required' => true,
                        'diagnosis_access_request_id' => $workflow->id,
                        'offer' => [
                            'code' => $offer['code'],
                            'name' => $offer['name'],
                        ],
                        'customer' => [
                            'user_id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'subscriber_id' => $subscriber->id,
                            'company_id' => $company->id,
                            'company_name' => $company->name,
                        ],
                        'commercial' => [
                            'currency' => $offer['currency'],
                            'subtotal' => '0.00',
                            'tax_rate' => '0.000',
                            'tax_amount' => '0.00',
                            'total' => '0.00',
                            'complimentary' => true,
                        ],
                    ],
                    'document_class' => null,
                    'document_type' => null,
                    'fiscal_number' => null,
                    'security_code' => null,
                    'fiscal_meta' => [
                        'fiscal_document_pending' => true,
                        'source' => self::SOURCE,
                        'complimentary' => true,
                    ],
                    'provider' => null,
                    'provider_invoice_id' => null,
                    'hosted_invoice_url' => null,
                    'payment_url' => null,
                ]);

                InvoiceItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'service_id' => null,
                    'service_plan_id' => null,
                    'description' => $offer['invoice_description'],
                    'quantity' => 1,
                    'unit_price' => 0,
                    'line_subtotal' => 0,
                    'discount_amount' => 0,
                    'tax_rate' => 0,
                    'tax_amount' => 0,
                    'line_total' => 0,
                    'meta' => [
                        'source' => self::SOURCE,
                        'complimentary' => true,
                        'diagnosis_access_request_id' => $workflow->id,
                    ],
                ]);

                AuditService::log(
                    'diagnosis_initial_complimentary_invoice_issued',
                    $invoice,
                    [
                        'diagnosis_access_request_id' => $workflow->id,
                        'invoice_number' => $invoice->number,
                        'total' => '0.00',
                        'currency' => $invoice->currency,
                    ],
                    ['user_id' => $user->id]
                );
            }

            $meta['source'] = self::SOURCE;
            $meta['apphub_native'] = true;
            $meta['subscriber_id'] = $subscriber->id;
            $meta['company_id'] = $company->id;
            $meta['initial_diagnosis'] = array_merge(
                is_array($meta['initial_diagnosis'] ?? null)
                    ? $meta['initial_diagnosis']
                    : [],
                [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->number,
                    'complimentary' => true,
                    'currency' => $offer['currency'],
                    'subtotal' => '0.00',
                    'tax_amount' => '0.00',
                    'total' => '0.00',
                    'confirmation_status' => data_get(
                        $meta,
                        'initial_diagnosis.confirmation_status',
                        'pending'
                    ),
                    'invoice_issued_at' => $invoice->issued_on?->toDateString(),
                ]
            );

            $workflow->forceFill([
                'user_id' => $user->id,
                'meta' => $meta,
            ])->save();

            return $workflow->fresh(['contactRequest', 'assessment']);
        });
    }

    public function nativeWorkflow(User $user): ?DiagnosisAccessRequest
    {
        return DiagnosisAccessRequest::query()
            ->where('user_id', $user->id)
            ->where('meta->source', self::SOURCE)
            ->with('assessment')
            ->latest('id')
            ->first();
    }

    public function historicalWorkflow(User $user): ?DiagnosisAccessRequest
    {
        return DiagnosisAccessRequest::query()
            ->where('user_id', $user->id)
            ->whereNotNull('diagnosis_assessment_id')
            ->with('assessment')
            ->latest('id')
            ->first();
    }

    public function state(User $user, Company $company): array
    {
        $workflow = $this->nativeWorkflow($user);
        $historical = false;

        if (! $workflow) {
            $workflow = $this->historicalWorkflow($user);
            $historical = (bool) $workflow;
        }

        $offer = $this->offer();

        if (! $workflow) {
            return [
                'exists' => false,
                'historical' => false,
                'needs_initialization' => true,
                'workflow' => null,
                'invoice' => null,
                'assessment' => null,
                'offer' => $offer,
            ];
        }

        $invoiceId = (int) data_get(
            $workflow->meta,
            'initial_diagnosis.invoice_id',
            0
        );

        $invoice = $invoiceId > 0
            ? Invoice::query()
                ->whereKey($invoiceId)
                ->where('company_id', $company->id)
                ->first()
            : null;

        $assessment = $workflow->assessment;

        return [
            'exists' => true,
            'historical' => $historical,
            'needs_initialization' => ! $historical && ! $invoice,
            'workflow' => [
                'public_id' => $workflow->public_id,
                'status' => $workflow->status,
                'confirmation_status' => (string) data_get(
                    $workflow->meta,
                    'initial_diagnosis.confirmation_status',
                    $workflow->status === DiagnosisAccessRequest::STATUS_ACTIVE
                        ? 'confirmed'
                        : 'pending'
                ),
                'requested_at' => data_get($workflow->meta, 'requested_at'),
                'confirmed_at' => data_get(
                    $workflow->meta,
                    'initial_diagnosis.confirmed_at'
                ),
            ],
            'invoice' => $invoice ? [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'status' => $invoice->status,
                'currency' => $invoice->currency,
                'subtotal' => (string) $invoice->subtotal,
                'tax_total' => (string) $invoice->tax_total,
                'total' => (string) $invoice->total,
                'amount_paid' => (string) $invoice->amount_paid,
                'issued_on' => $invoice->issued_on?->toDateString(),
                'url' => url('/subscriber/invoices/'.$invoice->id),
            ] : null,
            'assessment' => $assessment ? [
                'id' => $assessment->id,
                'status' => $assessment->status,
                'current_step' => $assessment->current_step,
                'submitted_at' => $assessment->submitted_at?->toIso8601String(),
                'published_at' => $assessment->published_at?->toIso8601String(),
                'url' => route('diagnosis.show', $assessment, false),
            ] : null,
            'offer' => $offer,
        ];
    }
}
