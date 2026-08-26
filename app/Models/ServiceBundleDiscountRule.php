<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceBundleDiscountRule extends Model
{
    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED_AMOUNT = 'fixed_amount';

    protected $fillable = [
        'bundle_service_id',
        'code',
        'name',
        'discount_type',
        'discount_value',
        'currency',
        'priority',
        'active',
        'metadata',
    ];

    protected $casts = [
        'bundle_service_id' => 'integer',
        'discount_value' => 'decimal:4',
        'priority' => 'integer',
        'active' => 'boolean',
        'metadata' => 'array',
    ];

    public function bundleService(): BelongsTo
    {
        return $this->belongsTo(
            Service::class,
            'bundle_service_id'
        );
    }

    public function applications(): HasMany
    {
        return $this->hasMany(
            SubscriptionBundleDiscountApplication::class,
            'rule_id'
        );
    }
}
