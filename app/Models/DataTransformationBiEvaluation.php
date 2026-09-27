<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DataTransformationBiEvaluation extends Model
{
    public const STATUS_DRAFT =
        'draft';

    public const STATUS_READY_FOR_REVIEW =
        'ready_for_review';

    public const STATUS_PUBLISHED =
        'published';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_READY_FOR_REVIEW,
        self::STATUS_PUBLISHED,
    ];

    protected $fillable = [
        'data_transformation_bi_intake_session_id',
        'company_id',
        'transformation_implementation_request_id',
        'status',
        'submission_manifest_sha256',
        'evidence_version',
        'evidence_sha256',
        'evidence_snapshot',
        'evidence_captured_at',
        'created_by_user_id',
        'ready_for_review_by_user_id',
        'ready_for_review_at',
        'published_by_user_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence_version' =>
                'integer',

            'evidence_snapshot' =>
                'array',

            'evidence_captured_at' =>
                'datetime',

            'ready_for_review_at' =>
                'datetime',

            'published_at' =>
                'datetime',
        ];
    }

    public function intakeSession(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiIntakeSession::class,
            'data_transformation_bi_intake_session_id'
        );
    }

    public function findings(): HasMany
    {
        return $this->hasMany(
            DataTransformationBiEvaluationFinding::class,
            'data_transformation_bi_evaluation_id'
        )->orderBy('sort_order')
            ->orderBy('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }

    public function readyForReviewBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'ready_for_review_by_user_id'
        );
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'published_by_user_id'
        );
    }

    public function isDraft(): bool
    {
        return $this->status
            === self::STATUS_DRAFT;
    }

    public function isReadyForReview(): bool
    {
        return $this->status
            === self::STATUS_READY_FOR_REVIEW;
    }

    public function isPublished(): bool
    {
        return $this->status
            === self::STATUS_PUBLISHED;
    }
}
