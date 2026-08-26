<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionBundleDiscountApplication extends Model
{
    protected $fillable = [
        'subscription_id',
        'rule_id',
        'bundle_service_id',
        'bundle_base_amount',
        'discount_amount',
        'currency',
        'matched_service_ids',
        'fingerprint',
        'snapshot',
        'active',
        'applied_at',
        'superseded_at',
    ];

    protected $casts = [
        'subscription_id' => 'integer',
        'rule_id' => 'integer',
        'bundle_service_id' => 'integer',
        'bundle_base_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'matched_service_ids' => 'array',
        'snapshot' => 'array',
        'active' => 'boolean',
        'applied_at' => 'datetime',
        'superseded_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(
            Subscription::class
        );
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(
            ServiceBundleDiscountRule::class,
            'rule_id'
        );
    }

    public function bundleService(): BelongsTo
    {
        return $this->belongsTo(
            Service::class,
            'bundle_service_id'
        );
    }
}
