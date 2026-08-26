<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransformationImplementationSubscriptionActivation extends Model
{
    public const TYPE_CREATED = 'created';
    public const TYPE_REUSED = 'reused';

    public const STATUS_ACTIVE = 'active';

    protected $fillable = [
        'transformation_implementation_capability_go_live_id',
        'subscriber_id',
        'company_id',
        'subscription_id',
        'activation_type',
        'status',
        'go_live_at',
        'subscription_started_at',
        'source_snapshot',
        'internal_notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'go_live_at' => 'datetime',
            'subscription_started_at' => 'datetime',
            'source_snapshot' => 'array',
        ];
    }

    public function goLive(): BelongsTo
    {
        return $this->belongsTo(
            TransformationImplementationCapabilityGoLive::class,
            'transformation_implementation_capability_go_live_id'
        );
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
