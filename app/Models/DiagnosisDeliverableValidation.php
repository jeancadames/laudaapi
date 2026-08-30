<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosisDeliverableValidation extends Model
{
    public const TYPE_EXPANDED_REPORT = 'expanded_report';
    public const TYPE_DETAILED_ROADMAP = 'detailed_roadmap';
    public const TYPE_IMPLEMENTATION_PLAN = 'implementation_plan';

    protected $fillable = [
        'diagnosis_assessment_id',
        'deliverable_type',
        'deliverable_id',
        'deliverable_version',
        'reviewed_by_user_id',
        'reviewed_at',
        'validated_by_user_id',
        'validated_at',
        'adjustment_requested_by_user_id',
        'adjustment_requested_at',
        'adjustment_note',
    ];

    protected function casts(): array
    {
        return [
            'deliverable_id' => 'integer',
            'deliverable_version' => 'integer',
            'reviewed_at' => 'datetime',
            'validated_at' => 'datetime',
            'adjustment_requested_at' => 'datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(
            DiagnosisAssessment::class,
            'diagnosis_assessment_id'
        );
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by_user_id'
        );
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'validated_by_user_id'
        );
    }

    public function adjustmentRequestedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'adjustment_requested_by_user_id'
        );
    }
}
