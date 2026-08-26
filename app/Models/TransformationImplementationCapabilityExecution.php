<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransformationImplementationCapabilityExecution extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'transformation_implementation_phase_capability_id',
        'status',
        'progress_percentage',
        'assigned_user_id',
        'started_at',
        'blocked_at',
        'completed_at',
        'cancelled_at',
        'blocking_reason',
        'evidence_snapshot',
        'internal_notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'progress_percentage' => 'decimal:2',
            'started_at' => 'datetime',
            'blocked_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'evidence_snapshot' => 'array',
        ];
    }

    public function capability(): BelongsTo
    {
        return $this->belongsTo(
            TransformationImplementationPhaseCapability::class,
            'transformation_implementation_phase_capability_id'
        );
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
