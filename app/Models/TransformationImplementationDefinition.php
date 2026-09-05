<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TransformationImplementationDefinition
    extends Model
{
    public const STATUS_DRAFT =
        'draft';

    public const STATUS_UNDER_REVIEW =
        'under_review';

    public const STATUS_READY =
        'ready';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_READY,
    ];

    protected $fillable = [
        'transformation_implementation_plan_id',
        'diagnosis_assessment_id',
        'company_id',
        'transformation_implementation_request_id',
        'transformation_implementation_phase_capability_id',
        'capability_key',
        'version',
        'status',
        'source_snapshot',
        'implementation_scope',
        'deliverables',
        'dependencies',
        'responsibility_model',
        'readiness',
        'internal_notes',
        'created_by_user_id',
        'updated_by_user_id',
        'reviewed_by_user_id',
        'reviewed_at',
        'ready_at',
    ];

    protected function casts(): array
    {
        return [
            'version' =>
                'integer',

            'source_snapshot' =>
                'array',

            'implementation_scope' =>
                'array',

            'deliverables' =>
                'array',

            'dependencies' =>
                'array',

            'responsibility_model' =>
                'array',

            'readiness' =>
                'array',

            'reviewed_at' =>
                'datetime',

            'ready_at' =>
                'datetime',
        ];
    }


    public function implementationRequest(): BelongsTo
    {
        return $this->belongsTo(
            TransformationImplementationRequest::class,
            'transformation_implementation_request_id'
        );
    }

    public function phaseCapability(): BelongsTo
    {
        return $this->belongsTo(
            TransformationImplementationPhaseCapability::class,
            'transformation_implementation_phase_capability_id'
        );
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(
            TransformationImplementationPlan::class,
            'transformation_implementation_plan_id'
        );
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(
            DiagnosisAssessment::class,
            'diagnosis_assessment_id'
        );
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class,
            'company_id'
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

    public function isReady(): bool
    {
        return $this->status
            === self::STATUS_READY
            && $this->ready_at !== null;
    }
}
