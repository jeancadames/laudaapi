<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransformationCapabilityDecision extends Model
{
    public const RECOMMENDATION_RECOMMENDED = 'recommended';
    public const RECOMMENDATION_NOT_RECOMMENDED = 'not_recommended';

    public const DECISION_PENDING = 'pending';
    public const DECISION_ACCEPTED = 'accepted';
    public const DECISION_DECLINED = 'declined';

    protected $fillable = [
        'company_id',
        'diagnosis_assessment_id',
        'capability_key',
        'recommendation_status',
        'decision',
        'source_type',
        'source_id',
        'source_version',
        'source_snapshot',
        'decided_by_user_id',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'source_version' => 'integer',
            'source_snapshot' => 'array',
            'decided_at' => 'datetime',
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

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'decided_by_user_id'
        );
    }
}
