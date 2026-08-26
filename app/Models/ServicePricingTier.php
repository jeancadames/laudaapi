<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePricingTier extends Model
{
    protected $fillable = [
        'service_id',
        'billing_cycle',
        'min_quantity',
        'max_quantity',
        'price',
        'currency',
        'active',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'service_id' => 'integer',
        'min_quantity' => 'integer',
        'max_quantity' => 'integer',
        'price' => 'decimal:2',
        'active' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(
            Service::class
        );
    }
}
