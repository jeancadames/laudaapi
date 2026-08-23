<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosisAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'organization_id',
        'organization_name',
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

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'in_progress'], true);
    }
}
