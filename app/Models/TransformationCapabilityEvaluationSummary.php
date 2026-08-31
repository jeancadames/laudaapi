<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransformationCapabilityEvaluationSummary extends Model
{
    public const STATUS_PENDING =
        'pending';

    public const STATUS_DRAFT_GENERATED =
        'draft_generated';

    public const STATUS_REVIEWED =
        'reviewed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_DRAFT_GENERATED,
        self::STATUS_REVIEWED,
    ];

    protected $fillable = [
        'transformation_capability_activation_id',
        'status',
        'generated_payload',
        'generation_context',
        'generation_version',
        'generated_at',
        'reviewed_payload',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'generated_payload' =>
                'array',

            'generation_context' =>
                'array',

            'generation_version' =>
                'integer',

            'generated_at' =>
                'datetime',

            'reviewed_payload' =>
                'array',

            'reviewed_at' =>
                'datetime',
        ];
    }

    public function activation(): BelongsTo
    {
        return $this->belongsTo(
            TransformationCapabilityActivation::class,
            'transformation_capability_activation_id'
        );
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by_user_id'
        );
    }

    public function hasGeneratedDraft(): bool
    {
        return in_array(
            $this->status,
            [
                self::STATUS_DRAFT_GENERATED,
                self::STATUS_REVIEWED,
            ],
            true
        )
            && is_array(
                $this->generated_payload
            )
            && $this->generated_at !== null;
    }

    public function hasReviewedSummary(): bool
    {
        return $this->status === self::STATUS_REVIEWED
            && is_array(
                $this->reviewed_payload
            )
            && $this->reviewed_at !== null
            && $this->reviewed_by_user_id !== null;
    }

}
