<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Invoice extends Model
{
    protected $fillable = [
        'company_id',
        'subscription_id',
        'number',
        'status',
        'issued_on',
        'due_on',
        'period_start',
        'period_end',
        'currency',
        'subtotal',
        'discount_total',
        'tax_total',
        'total',
        'amount_paid',
        'billing_snapshot',
        'document_class',
        'document_type',
        'fiscal_number',
        'security_code',
        'fiscal_meta',
        'provider',
        'provider_invoice_id',
        'hosted_invoice_url',
        'payment_url',
    ];

    protected $casts = [
        'issued_on' => 'date',
        'due_on' => 'date',
        'period_start' => 'datetime',
        'period_end' => 'datetime',

        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',

        'billing_snapshot' => 'array',
        'fiscal_meta' => 'array',
    ];

    protected static function booted()
    {
        static::saved(fn() => Cache::forget('admin.dashboard.stats'));
        static::deleted(fn() => Cache::forget('admin.dashboard.stats'));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Si todavía no tienes invoice_items, puedes comentar esta relación.
     * Cuando la migres, crea el model InvoiceItem y listo.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
