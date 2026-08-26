<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransformationImplementationSubscriptionItemActivation extends Model
{
    public const TYPE_CREATED = 'created';
    public const TYPE_REUSED = 'reused';

    public const STATUS_ACTIVE = 'active';

    protected $fillable = [
        'transformation_implementation_capability_go_live_id',
        'transformation_implementation_subscription_activation_id',
        'transformation_implementation_capability_service_mapping_id',
        'service_id',
        'subscription_item_id',
        'activation_type',
        'status',
        'price_snapshot',
        'activated_at',
        'internal_notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'price_snapshot' => 'array',
            'activated_at' => 'datetime',
        ];
    }

    public function goLive(): BelongsTo
    {
        return $this->belongsTo(
            TransformationImplementationCapabilityGoLive::class,
            'transformation_implementation_capability_go_live_id'
        );
    }

    public function subscriptionActivation(): BelongsTo
    {
        return $this->belongsTo(
            TransformationImplementationSubscriptionActivation::class,
            'transformation_implementation_subscription_activation_id'
        );
    }

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(
            TransformationImplementationCapabilityServiceMapping::class,
            'transformation_implementation_capability_service_mapping_id'
        );
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function subscriptionItem(): BelongsTo
    {
        return $this->belongsTo(SubscriptionItem::class);
    }
}
