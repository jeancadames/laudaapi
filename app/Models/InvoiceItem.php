<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InvoiceItem extends Model
{
    protected $table = 'invoice_items';

    protected $fillable = [
        'invoice_id',
        'service_id',
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
        'quantity' => 'integer',

        'unit_price' => 'decimal:2',
        'line_subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:3',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',

        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(function (InvoiceItem $item) {
            Cache::forget('admin.dashboard.stats');
            self::recalcInvoiceTotals($item->invoice_id);
        });

        static::deleted(function (InvoiceItem $item) {
            Cache::forget('admin.dashboard.stats');
            self::recalcInvoiceTotals($item->invoice_id);
        });
    }

    /**
     * Recalcula totales del invoice basado en sus items.
     * - No toca status.
     * - Respeta void (no recalcula totales si está void).
     * - Usa saveQuietly para evitar loops.
     */
    private static function recalcInvoiceTotals(?int $invoiceId): void
    {
        if (!$invoiceId) return;

        $row = DB::table('invoice_items')
            ->where('invoice_id', $invoiceId)
            ->selectRaw('
                COALESCE(SUM(line_subtotal),0) as subtotal,
                COALESCE(SUM(discount_amount),0) as discount_total,
                COALESCE(SUM(tax_amount),0) as tax_total,
                COALESCE(SUM(line_total),0) as total
            ')
            ->first();

        $invoice = Invoice::query()->find($invoiceId);
        if (!$invoice) return;

        // Si está void, no tocar (pero podrías sincronizar si quieres)
        if ($invoice->status === 'void') return;

        $invoice->forceFill([
            'subtotal' => (float) $row->subtotal,
            'discount_total' => (float) $row->discount_total,
            'tax_total' => (float) $row->tax_total,
            'total' => (float) $row->total,
        ])->saveQuietly();

        // stats puede depender de total/balance, por eso invalidamos de nuevo (barato)
        Cache::forget('admin.dashboard.stats');
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
