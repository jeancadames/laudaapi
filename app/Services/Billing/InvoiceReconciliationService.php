<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceReconciliationService
{
    private const MONEY_EPSILON = 0.005;

    public function recalculateFromItems(int|Invoice $invoice): Invoice
    {
        $invoiceId = $invoice instanceof Invoice
            ? (int) $invoice->id
            : (int) $invoice;

        return DB::transaction(function () use ($invoiceId): Invoice {
            $locked = Invoice::query()
                ->whereKey($invoiceId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'void') {
                $totals = DB::table('invoice_items')
                    ->where('invoice_id', $locked->id)
                    ->selectRaw(
                        'COALESCE(SUM(line_subtotal),0) as subtotal, '
                        .'COALESCE(SUM(discount_amount),0) as discount_total, '
                        .'COALESCE(SUM(tax_amount),0) as tax_total, '
                        .'COALESCE(SUM(line_total),0) as total'
                    )
                    ->first();

                $locked->forceFill([
                    'subtotal' => round((float) ($totals->subtotal ?? 0), 2),
                    'discount_total' => round((float) ($totals->discount_total ?? 0), 2),
                    'tax_total' => round((float) ($totals->tax_total ?? 0), 2),
                    'total' => round((float) ($totals->total ?? 0), 2),
                ])->saveQuietly();
            }

            return $this->reconcilePaymentsLocked(
                $locked->fresh()
            );
        }, 3);
    }

    public function reconcilePayments(int|Invoice $invoice): Invoice
    {
        $invoiceId = $invoice instanceof Invoice
            ? (int) $invoice->id
            : (int) $invoice;

        return DB::transaction(function () use ($invoiceId): Invoice {
            $locked = Invoice::query()
                ->whereKey($invoiceId)
                ->lockForUpdate()
                ->firstOrFail();

            return $this->reconcilePaymentsLocked($locked);
        }, 3);
    }

    public function assertPaymentConsistency(Payment $payment): void
    {
        $invoiceId = (int) ($payment->invoice_id ?? 0);

        if ($invoiceId <= 0) {
            throw ValidationException::withMessages([
                'invoice_id' => ['El pago debe estar asociado a una factura.'],
            ]);
        }

        $invoice = Invoice::query()->find($invoiceId);

        if (! $invoice) {
            throw ValidationException::withMessages([
                'invoice_id' => ['La factura asociada al pago no existe.'],
            ]);
        }

        $invoiceCurrency = $this->normalizeCurrency(
            (string) ($invoice->currency ?? ''),
            'Invoice'
        );

        $rawPaymentCurrency = trim(
            (string) ($payment->currency ?? '')
        );

        $paymentCurrency = $rawPaymentCurrency === ''
            ? $invoiceCurrency
            : $this->normalizeCurrency(
                $rawPaymentCurrency,
                'Payment'
            );

        if ($paymentCurrency !== $invoiceCurrency) {
            throw ValidationException::withMessages([
                'currency' => [
                    'La moneda del Payment debe coincidir con la moneda del Invoice. '
                    .'No se permite conversión FX implícita.',
                ],
            ]);
        }

        $payment->currency = $paymentCurrency;

        $invoiceCompanyId = (int) ($invoice->company_id ?? 0);
        $paymentCompanyId = (int) ($payment->company_id ?? 0);

        if ($paymentCompanyId <= 0 && $invoiceCompanyId > 0) {
            $payment->company_id = $invoiceCompanyId;
            $paymentCompanyId = $invoiceCompanyId;
        }

        if (
            $invoiceCompanyId > 0
            && $paymentCompanyId !== $invoiceCompanyId
        ) {
            throw ValidationException::withMessages([
                'company_id' => [
                    'La compañía del Payment debe coincidir con la compañía del Invoice.',
                ],
            ]);
        }

        if (
            ! is_numeric($payment->amount)
            || (float) $payment->amount < 0
        ) {
            throw ValidationException::withMessages([
                'amount' => [
                    'El monto del Payment debe ser mayor o igual a cero.',
                ],
            ]);
        }
    }

    private function reconcilePaymentsLocked(Invoice $invoice): Invoice
    {
        $invoiceCurrency = $this->normalizeCurrency(
            (string) ($invoice->currency ?? ''),
            'Invoice'
        );

        $invoiceCompanyId = (int) ($invoice->company_id ?? 0);

        $payments = Payment::query()
            ->where('invoice_id', $invoice->id)
            ->get([
                'id',
                'company_id',
                'currency',
                'amount',
                'paid_at',
            ]);

        foreach ($payments as $payment) {
            $paymentCurrency = $this->normalizeCurrency(
                (string) ($payment->currency ?? ''),
                'Payment'
            );

            if ($paymentCurrency !== $invoiceCurrency) {
                throw ValidationException::withMessages([
                    'currency' => [
                        "Payment {$payment->id} usa una moneda distinta al Invoice.",
                    ],
                ]);
            }

            if (
                $invoiceCompanyId > 0
                && (int) $payment->company_id !== $invoiceCompanyId
            ) {
                throw ValidationException::withMessages([
                    'company_id' => [
                        "Payment {$payment->id} pertenece a otra compañía.",
                    ],
                ]);
            }

            if ((float) $payment->amount < 0) {
                throw ValidationException::withMessages([
                    'amount' => [
                        "Payment {$payment->id} tiene monto negativo.",
                    ],
                ]);
            }
        }

        $paid = round(
            (float) $payments
                ->whereNotNull('paid_at')
                ->sum(
                    fn (Payment $payment): float =>
                        (float) $payment->amount
                ),
            2
        );

        if ($invoice->status === 'void') {
            $invoice->forceFill([
                'amount_paid' => $paid,
            ])->saveQuietly();

            return $invoice->fresh();
        }

        if ($invoice->status === 'draft') {
            $invoice->forceFill([
                'amount_paid' => $paid,
            ])->saveQuietly();

            return $invoice->fresh();
        }

        $total = round(
            max(0, (float) $invoice->total),
            2
        );

        if (
            $total > 0
            && $paid + self::MONEY_EPSILON >= $total
        ) {
            $invoice->forceFill([
                'amount_paid' => $paid,
                'status' => 'paid',
            ])->saveQuietly();

            return $invoice->fresh();
        }

        $status = (
            $invoice->due_on
            && now()->startOfDay()->gt($invoice->due_on)
        )
            ? 'overdue'
            : 'issued';

        $invoice->forceFill([
            'amount_paid' => $paid,
            'status' => $status,
        ])->saveQuietly();

        return $invoice->fresh();
    }

    private function normalizeCurrency(
        string $currency,
        string $entity
    ): string {
        $currency = strtoupper(trim($currency));

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw ValidationException::withMessages([
                'currency' => [
                    "La moneda de {$entity} debe usar un código ISO de tres letras.",
                ],
            ]);
        }

        return $currency;
    }
}
