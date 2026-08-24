<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosisExpandedReport extends Model
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
        'version',
        'status',
        'generated_by_user_id',
        'reviewed_by_user_id',
        'currency',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total',
        'methodology_version',
        'source_snapshot',
        'sections',
        'review_notes',
        'reviewed_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:3',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'source_snapshot' => 'array',
            'sections' => 'array',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(
            DiagnosisAssessment::class,
            'diagnosis_assessment_id'
        );
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'generated_by_user_id'
        );
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by_user_id'
        );
    }

    public function isEditable(): bool
    {
        return in_array(
            $this->status,
            [
                self::STATUS_DRAFT,
                self::STATUS_UNDER_REVIEW,
            ],
            true
        );
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && $this->published_at !== null;
    }
}
