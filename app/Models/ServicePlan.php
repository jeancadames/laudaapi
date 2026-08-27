<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicePlan extends Model
{
    protected $guarded = [];

    protected $casts = [
        'service_id' => 'integer',

        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'overage_unit_price' => 'decimal:4',

        'block_size' => 'integer',
        'included_units' => 'integer',
        'sort_order' => 'integer',

        'features' => 'array',
        'limits' => 'array',
        'source_snapshot' => 'array',
        'meta' => 'array',

        'synced_at' => 'datetime',
        'active' => 'boolean',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(
            Service::class
        );
    }

    public function pricingTiers(): HasMany
    {
        return $this->hasMany(
            ServicePlanPricingTier::class,
            'service_plan_id'
        );
    }

    public function subscriptionItems(): HasMany
    {
        return $this->hasMany(
            SubscriptionItem::class,
            'service_plan_id'
        );
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(
            InvoiceItem::class,
            'service_plan_id'
        );
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(
            StandaloneServiceSettlement::class,
            'service_plan_id'
        );
    }

    public function scopeActive($query)
    {
        return $query->where(
            'active',
            true
        );
    }
}
