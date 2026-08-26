<?php

namespace App\Models;

use App\Services\Billing\InvoiceReconciliationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Payment extends Model
{
    protected $fillable = [
        'company_id',
        'invoice_id',
        'method',
        'currency',
        'amount',
        'paid_at',
        'reference',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (Payment $payment): void {
            app(
                InvoiceReconciliationService::class
            )->assertPaymentConsistency(
                $payment
            );
        });

        static::saved(function (Payment $payment): void {
            if (! self::shouldResyncInvoice($payment)) {
                Cache::forget('admin.dashboard.stats');

                return;
            }

            self::reconcileInvoiceReferences(
                $payment
            );
        });

        static::deleted(function (Payment $payment): void {
            self::reconcileInvoiceReferences(
                $payment
            );
        });
    }

    private static function shouldResyncInvoice(
        Payment $payment
    ): bool {
        if ($payment->wasRecentlyCreated) {
            return true;
        }

        return $payment->wasChanged('invoice_id')
            || $payment->wasChanged('amount')
            || $payment->wasChanged('paid_at')
            || $payment->wasChanged('currency')
            || $payment->wasChanged('company_id');
    }

    private static function reconcileInvoiceReferences(
        Payment $payment
    ): void {
        Cache::forget('admin.dashboard.stats');

        $currentInvoiceId =
            (int) ($payment->invoice_id ?? 0);

        $originalInvoiceId =
            (int) (
                $payment->getOriginal('invoice_id')
                ?? 0
            );

        $invoiceIds = array_values(
            array_unique(
                array_filter([
                    $originalInvoiceId,
                    $currentInvoiceId,
                ])
            )
        );

        /*
         * PASO 9E-C:
         * Si se tocan dos invoices, adquirir locks en orden global.
         */
        sort($invoiceIds, SORT_NUMERIC);

        foreach ($invoiceIds as $invoiceId) {
            app(
                InvoiceReconciliationService::class
            )->reconcilePayments(
                (int) $invoiceId
            );
        }
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Invoice::class);
    }
}
