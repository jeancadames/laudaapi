<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransformationImplementationPlan extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_PRESENTED = 'presented';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const MODALITY_GUIDED = 'guided';

    public const MODALITY_ASSISTED = 'assisted';

    public const MODALITY_MANAGED = 'managed';

    protected $fillable = [
        'diagnosis_assessment_id',
        'diagnosis_detailed_roadmap_id',
        'version',
        'status',
        'recommended_modality',
        'recommended_modality_label',
        'selected_modality',
        'selected_modality_label',
        'source_snapshot',
        'internal_notes',
        'created_by_user_id',
        'updated_by_user_id',
        'presented_at',
        'accepted_at',
        'cancelled_at',
    ];

    protected $casts = [
        'source_snapshot' => 'array',
        'presented_at' => 'datetime',
        'accepted_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(
            DiagnosisAssessment::class,
            'diagnosis_assessment_id'
        );
    }

    public function roadmap(): BelongsTo
    {
        return $this->belongsTo(
            DiagnosisDetailedRoadmap::class,
            'diagnosis_detailed_roadmap_id'
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

    public static function modalities(): array
    {
        return [
            self::MODALITY_GUIDED => 'LAUDA 360 Guiado',
            self::MODALITY_ASSISTED => 'LAUDA 360 Asistido',
            self::MODALITY_MANAGED => 'LAUDA 360 Gestionado',
        ];
    }

    public function phases(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(
            TransformationImplementationPhase::class,
            'transformation_implementation_plan_id'
        )->orderBy('sequence')->orderBy('id');
    }

}
