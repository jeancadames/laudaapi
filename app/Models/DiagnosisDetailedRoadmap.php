<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiagnosisDetailedRoadmap extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_PUBLISHED = 'published';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_PUBLISHED,
    ];

    protected $fillable = [
        'diagnosis_assessment_id',
        'source_expanded_report_id',
        'version',
        'status',
        'generated_by_user_id',
        'reviewed_by_user_id',
        'methodology_version',
        'source_snapshot',
        'roadmap',
        'review_notes',
        'reviewed_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'source_snapshot' => 'array',
            'roadmap' => 'array',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(DiagnosisAssessment::class, 'diagnosis_assessment_id');
    }

    public function sourceExpandedReport(): BelongsTo
    {
        return $this->belongsTo(DiagnosisExpandedReport::class, 'source_expanded_report_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function transformationCapabilityActivations(): HasMany
    {
        return $this->hasMany(
            TransformationCapabilityActivation::class,
            'source_id'
        )->where(
            'source_type',
            TransformationCapabilityActivation::SOURCE_DETAILED_ROADMAP
        );
    }

    public function isEditable(): bool
    {
        return in_array(
            $this->status,
            [self::STATUS_DRAFT, self::STATUS_UNDER_REVIEW],
            true
        );
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && $this->published_at !== null;
    }
}
