<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionItem extends Model
{
    // ✅ si lo dejas así, perfecto para prototipo/early stage.
    // En producción, podrías migrar a $fillable explícito.
    protected $guarded = [];

    protected $casts = [
        'subscription_id' => 'integer',
        'service_id' => 'integer',

        'quantity' => 'integer',
        'block_size' => 'integer',
        'included_units' => 'integer',

        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
        'overage_unit_price' => 'decimal:4',

        'meta' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // -------------------------
    // Relations
    // -------------------------

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    // -------------------------
    // Scopes / helpers
    // -------------------------

    public function scopeActiveOrTrialing($query)
    {
        return $query->whereIn('status', ['active', 'trialing']);
    }

    public function isActive(): bool
    {
        return in_array(strtolower((string) $this->status), ['active', 'trialing'], true);
    }
}
