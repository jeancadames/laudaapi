<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TransformationCapabilityActivation extends Model
{
    public const SOURCE_DETAILED_ROADMAP = 'detailed_roadmap';
    public const SOURCE_MANUAL = 'manual';

    public const STATUS_ACTIVATED = 'activated';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_READY_FOR_REVIEW = 'ready_for_review';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_ACTIVATED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_READY_FOR_REVIEW,
        self::STATUS_VALIDATED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'company_id',
        'diagnosis_assessment_id',
        'capability_key',
        'source_type',
        'source_id',
        'source_version',
        'source_snapshot',
        'status',
        'activated_by_user_id',
        'activated_at',
        'started_at',
        'ready_for_review_at',
        'validated_at',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'diagnosis_assessment_id' => 'integer',
            'source_id' => 'integer',
            'source_version' => 'integer',
            'source_snapshot' => 'array',
            'activated_at' => 'datetime',
            'started_at' => 'datetime',
            'ready_for_review_at' => 'datetime',
            'validated_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(
            DiagnosisAssessment::class,
            'diagnosis_assessment_id'
        );
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'activated_by_user_id'
        );
    }

    public function needs(): HasMany
    {
        return $this->hasMany(
            TransformationCapabilityNeed::class,
            'transformation_capability_activation_id'
        )->orderBy('sequence')->orderBy('id');
    }


    public function evaluationSummary(): HasOne
    {
        return $this->hasOne(
            TransformationCapabilityEvaluationSummary::class,
            'transformation_capability_activation_id'
        );
    }

    public function isClosed(): bool
    {
        return in_array(
            $this->status,
            [
                self::STATUS_COMPLETED,
                self::STATUS_CANCELLED,
            ],
            true
        );
    }
}
