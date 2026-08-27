<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePlanPricingTier extends Model
{
    protected $guarded = [];

    protected $casts = [
        'service_plan_id' => 'integer',
        'min_quantity' => 'integer',
        'max_quantity' => 'integer',
        'price' => 'decimal:2',
        'active' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    public function servicePlan(): BelongsTo
    {
        return $this->belongsTo(
            ServicePlan::class,
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
