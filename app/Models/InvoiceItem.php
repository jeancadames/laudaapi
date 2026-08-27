<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Invoice;
use App\Services\Billing\InvoiceReconciliationService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class InvoiceItem extends Model
{
    protected $table = 'invoice_items';

    protected $fillable = [
        'invoice_id',
        'service_id',
        'service_plan_id',
        'description',
        'quantity',
        'unit_price',
        'line_subtotal',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'line_total',
        'meta',
    ];

    protected $casts = [
        'service_plan_id' => 'integer',
        'quantity' => 'integer',

        'unit_price' => 'decimal:2',
        'line_subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:3',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',

        'meta' => 'array',
    ];

    public function servicePlan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(
            ServicePlan::class,
            'service_plan_id'
        );
    }

    protected static function booted(): void
    {
        static::saved(function (InvoiceItem $item): void {
            Cache::forget('admin.dashboard.stats');

            self::reconcileInvoiceReferences(
                $item
            );
        });

        static::deleted(function (InvoiceItem $item): void {
            Cache::forget('admin.dashboard.stats');

            if ($item->invoice_id) {
                app(
                    InvoiceReconciliationService::class
                )->recalculateFromItems(
                    (int) $item->invoice_id
                );
            }
        });
    }

    private static function reconcileInvoiceReferences(
        InvoiceItem $item
    ): void {
        $currentInvoiceId =
            (int) ($item->invoice_id ?? 0);

        $originalInvoiceId =
            (int) (
                $item->getOriginal('invoice_id')
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
            )->recalculateFromItems(
                (int) $invoiceId
            );
        }
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
