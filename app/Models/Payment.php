<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Payment extends Model
{
    protected $fillable = [
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
        static::saved(function (Payment $p) {
            // ✅ (opcional) evita recalcular si nada relevante cambió
            if (!self::shouldResyncInvoice($p)) {
                // igual invalidamos cache porque el pago pudo cambiar algo menor (si quieres, quita esto)
                Cache::forget('admin.dashboard.stats');
                return;
            }

            self::afterPaymentChanged($p);
        });

        static::deleted(function (Payment $p) {
            self::afterPaymentChanged($p);
        });

        // Si usas SoftDeletes, habilita esto:
        // static::restored(fn(Payment $p) => self::afterPaymentChanged($p));
    }

    private static function shouldResyncInvoice(Payment $p): bool
    {
        // En created, invoice_id siempre cuenta.
        if (!$p->exists) return true;

        // Si cambió el invoice_id, amount o paid_at => afecta totales.
        return $p->wasChanged('invoice_id') || $p->wasChanged('amount') || $p->wasChanged('paid_at');
    }

    private static function afterPaymentChanged(Payment $payment): void
    {
        // ✅ invalidar cache dashboard (una sola vez)
        Cache::forget('admin.dashboard.stats');

        $currentInvoiceId  = $payment->invoice_id;
        $originalInvoiceId = $payment->getOriginal('invoice_id');

        // ✅ si no hay invoice actual ni original, nada que hacer
        if (!$currentInvoiceId && !$originalInvoiceId) {
            return;
        }

        // ✅ si cambió invoice_id, sincroniza ambas (vieja y nueva)
        $invoiceIds = array_values(array_unique(array_filter([
            $currentInvoiceId,
            $originalInvoiceId,
        ])));

        DB::transaction(function () use ($invoiceIds) {
            foreach ($invoiceIds as $invoiceId) {
                self::syncInvoiceFromPayments((int) $invoiceId);
            }
        });
    }

    /**
     * Recalcula amount_paid y status del invoice usando pagos posteados (paid_at != null)
     * Reglas:
     * - void: no tocar status (solo amount_paid)
     * - draft: no “emitir” desde pagos (solo amount_paid)
     * - si paid >= total => paid
     * - si no => issued u overdue según due_on
     */
    private static function syncInvoiceFromPayments(int $invoiceId): void
    {
        // ✅ lockForUpdate requiere estar dentro de DB::transaction()
        $invoice = \App\Models\Invoice::query()
            ->whereKey($invoiceId)
            ->lockForUpdate()
            ->first();

        if (!$invoice) return;

        // ✅ total pagado REAL (solo pagos posteados)
        $paid = (float) DB::table('payments')
            ->where('invoice_id', $invoiceId)
            ->whereNotNull('paid_at')
            ->sum('amount');

        // ✅ void: no tocar status, solo mantener amount_paid consistente
        if ($invoice->status === 'void') {
            $invoice->forceFill(['amount_paid' => $paid])->saveQuietly();
            return;
        }

        // ✅ draft: nunca lo “emitas” desde pagos
        if ($invoice->status === 'draft') {
            $invoice->forceFill(['amount_paid' => $paid])->saveQuietly();
            return;
        }

        $total = (float) $invoice->total;

        // ✅ pagada completa => paid
        if ($total > 0 && $paid >= $total) {
            $invoice->forceFill([
                'amount_paid' => $paid,
                'status' => 'paid',
            ])->saveQuietly();
            return;
        }

        // ✅ no está pagada completa => issued u overdue (según due_on)
        // (Opcional regla estricta)
        // if (!$invoice->issued_on) { $invoice->forceFill(['amount_paid' => $paid])->saveQuietly(); return; }

        $newStatus = 'issued';
        if ($invoice->due_on && now()->startOfDay()->gt($invoice->due_on)) {
            $newStatus = 'overdue';
        }

        $invoice->forceFill([
            'amount_paid' => $paid,
            'status' => $newStatus,
        ])->saveQuietly();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Invoice::class);
    }
}
