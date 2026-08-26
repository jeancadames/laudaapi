<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransformationImplementationCapabilityGoLive extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_READY = 'ready';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_LIVE = 'live';
    public const STATUS_ROLLED_BACK = 'rolled_back';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'transformation_implementation_phase_capability_id',
        'transformation_implementation_capability_execution_id',
        'attempt',
        'status',
        'readiness_snapshot',
        'evidence_snapshot',
        'ready_at',
        'scheduled_at',
        'went_live_at',
        'rolled_back_at',
        'cancelled_at',
        'rollback_reason',
        'internal_notes',
        'created_by_user_id',
        'updated_by_user_id',
        'went_live_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'attempt' => 'integer',
            'readiness_snapshot' => 'array',
            'evidence_snapshot' => 'array',
            'ready_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'went_live_at' => 'datetime',
            'rolled_back_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function capability(): BelongsTo
    {
        return $this->belongsTo(
            TransformationImplementationPhaseCapability::class,
            'transformation_implementation_phase_capability_id'
        );
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(
            TransformationImplementationCapabilityExecution::class,
            'transformation_implementation_capability_execution_id'
        );
    }

    public function wentLiveBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'went_live_by_user_id');
    }

    public function subscriptionActivation(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(
            TransformationImplementationSubscriptionActivation::class,
            'transformation_implementation_capability_go_live_id'
        );
    }


    public function subscriptionItemActivation(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(
            TransformationImplementationSubscriptionItemActivation::class,
            'transformation_implementation_capability_go_live_id'
        );
    }

}
