<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class TransformationImplementationCommercialEngagement extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PRESENTED = 'presented';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SUPERSEDED = 'superseded';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PRESENTED,
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
        self::STATUS_SUPERSEDED,
        self::STATUS_CANCELLED,
    ];

    public const TERMINAL_STATUSES = [
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
        self::STATUS_SUPERSEDED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'transformation_implementation_request_id',
        'transformation_implementation_definition_id',
        'company_id',
        'transformation_implementation_phase_capability_id',
        'capability_key',
        'version',
        'status',
        'currency',
        'price_amount',
        'duration_days',
        'scope_snapshot',
        'deliverables_snapshot',
        'commercial_terms_snapshot',
        'internal_notes',
        'created_by_user_id',
        'updated_by_user_id',
        'presented_by_user_id',
        'accepted_by_user_id',
        'rejected_by_user_id',
        'cancelled_by_user_id',
        'presented_at',
        'accepted_at',
        'rejected_at',
        'superseded_at',
        'cancelled_at',
        'rejection_reason',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'price_amount' => 'decimal:2',
            'duration_days' => 'integer',
            'scope_snapshot' => 'array',
            'deliverables_snapshot' => 'array',
            'commercial_terms_snapshot' => 'array',
            'presented_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'superseded_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(
            TransformationImplementationRequest::class,
            'transformation_implementation_request_id'
        );
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(
            TransformationImplementationDefinition::class,
            'transformation_implementation_definition_id'
        );
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class,
            'company_id'
        );
    }

    public function phaseCapability(): BelongsTo
    {
        return $this->belongsTo(
            TransformationImplementationPhaseCapability::class,
            'transformation_implementation_phase_capability_id'
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by_user_id'
        );
    }

    public function presentedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'presented_by_user_id'
        );
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'accepted_by_user_id'
        );
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'rejected_by_user_id'
        );
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'cancelled_by_user_id'
        );
    }

    public function authorization(): HasOne
    {
        return $this->hasOne(
            TransformationImplementationAuthorization::class,
            'transformation_implementation_commercial_engagement_id'
        );
    }

    public function isTerminal(): bool
    {
        return in_array(
            $this->status,
            self::TERMINAL_STATUSES,
            true
        );
    }
}
