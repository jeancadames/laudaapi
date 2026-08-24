<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DiagnosisAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'organization_id',
        'organization_name',
        'business_activity_type',
        'business_sector',
        'business_sector_other',
        'customer_market',
        'sales_channels',
        'sales_channel_other',
        'logistics_operation_types',
        'logistics_operation_other',
        'business_activity_description',
        'business_profile_completed_at',
        'methodology_version',
        'status',
        'current_step',
        'answers',
        'notes',
        'maturity_score',
        'capacity_score',
        'urgency_score',
        'dimension_scores',
        'maturity_level',
        'urgency_level',
        'recommended_modality',
        'recommended_modality_label',
        'review_required',
        'reviewed_by_user_id',
        'review_summary',
        'review_priorities',
        'final_modality',
        'final_modality_label',
        'published_at',
        'started_at',
        'submitted_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'notes' => 'array',
            'sales_channels' => 'array',
            'logistics_operation_types' => 'array',
            'business_profile_completed_at' => 'datetime',
            'dimension_scores' => 'array',
            'review_required' => 'boolean',
            'review_priorities' => 'array',
            'published_at' => 'datetime',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function expandedReportOrder(): HasOne
    {
        return $this->hasOne(
            DiagnosisExpandedReportOrder::class,
            'diagnosis_assessment_id'
        );
    }

    public function detailedRoadmapOrder(): HasOne
    {
        return $this->hasOne(
            DiagnosisDetailedRoadmapOrder::class,
            'diagnosis_assessment_id'
        );
    }



    public function detailedRoadmaps(): HasMany
    {
        return $this->hasMany(
            DiagnosisDetailedRoadmap::class,
            'diagnosis_assessment_id'
        );
    }

    public function latestDetailedRoadmap(): HasOne
    {
        return $this->hasOne(
            DiagnosisDetailedRoadmap::class,
            'diagnosis_assessment_id'
        )->latestOfMany('version');
    }

    public function publishedDetailedRoadmap(): HasOne
    {
        return $this->hasOne(
            DiagnosisDetailedRoadmap::class,
            'diagnosis_assessment_id'
        )->ofMany(
            'version',
            'max',
            fn ($query) => $query
                ->where('status', DiagnosisDetailedRoadmap::STATUS_PUBLISHED)
                ->whereNotNull('published_at')
        );
    }





    public function expandedReports(): HasMany
    {
        return $this->hasMany(
            DiagnosisExpandedReport::class,
            'diagnosis_assessment_id'
        );
    }

    public function latestExpandedReport(): HasOne
    {
        return $this->hasOne(
            DiagnosisExpandedReport::class,
            'diagnosis_assessment_id'
        )->latestOfMany('version');
    }

    public function publishedExpandedReport(): HasOne
    {
        return $this->hasOne(
            DiagnosisExpandedReport::class,
            'diagnosis_assessment_id'
        )
            ->ofMany(
                'version',
                'max',
                fn ($query) => $query
                    ->where(
                        'status',
                        DiagnosisExpandedReport::STATUS_PUBLISHED
                    )
                    ->whereNotNull('published_at')
            );
    }



    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'in_progress'], true);
    }
}
