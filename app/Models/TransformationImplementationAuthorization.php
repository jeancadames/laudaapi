<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TransformationImplementationAuthorization extends Model
{
    public const STATUS_AUTHORIZED = 'authorized';

    public const STATUS_REVOKED = 'revoked';

    public const STATUSES = [
        self::STATUS_AUTHORIZED,
        self::STATUS_REVOKED,
    ];

    protected $fillable = [
        'transformation_implementation_commercial_engagement_id',
        'transformation_implementation_request_id',
        'transformation_implementation_definition_id',
        'company_id',
        'transformation_implementation_phase_capability_id',
        'capability_key',
        'status',
        'authorization_snapshot',
        'authorized_by_user_id',
        'revoked_by_user_id',
        'authorized_at',
        'revoked_at',
        'revocation_reason',
    ];

    protected function casts(): array
    {
        return [
            'authorization_snapshot' => 'array',
            'authorized_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function commercialEngagement(): BelongsTo
    {
        return $this->belongsTo(
            TransformationImplementationCommercialEngagement::class,
            'transformation_implementation_commercial_engagement_id'
        );
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

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'authorized_by_user_id'
        );
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'revoked_by_user_id'
        );
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_AUTHORIZED
            && $this->authorized_at !== null
            && $this->revoked_at === null;
    }
}
